<?php

namespace Elastic\SocialLogin\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Hybrid_Auth;
use RuntimeException;

class SocialLoginAuthenticate extends BaseAuthenticate
{

    /**
     *
     * @var Hybrid_Auth
     */
    protected $hybridAuth = null;

    /**
     * Constructor
     *
     * @param ComponentRegistry $registry The Component registry used on this request.
     * @param array $config Array of config to use.
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        $this->config([
            'accountTable' => 'Elastic/SocialLogin.SocialAccounts',
            'fields' => [
                'provider' => 'provider',
                'provider_uid' => 'provider_uid',
                'openid_identifier' => 'openid_identifier'
            ],
            // ソーシャルアカウント連携完了のリダイレクト先
            // null => Auth->redirectUrl()
            'associatedRedirect' => null,
            // ソーシャルで認証完了後のアプリケーション側ログイン処理のリダイレクト先
            'hauthLoginAction' => [
                'plugin' => 'Elastic/SocialLogin',
                'controller' => 'SocialLogin',
                'action' => 'authenticated'
            ],
        ]);
        parent::__construct($registry, $config);
    }

    /**
     * Authenticate a user based on the request information.
     *
     * @param Request $request Request to get authentication information from.
     * @param Response $response A response object that can have headers added.
     * @return array|bool User array on success, false on failure.
     */
    public function authenticate(ServerRequest $request, Response $response)
    {
        $fields = $this->_config['fields'];

        // プロバイダ未指定の場合は、認証戻りのためユーザーデータを取得する
        if (!$request->data($fields['provider'])) {
            return $this->getUser($request);
        }

        // - プロバイダをチェックして認証リクエストを実行する
        $provider = $this->_checkFields($request, $fields);
        if (!$provider) {
            return false;
        }

        // 認証後の戻りURLを生成
        $returnTo = Router::url($this->_config['hauthLoginAction'], true);

        // 認証リクエストの実行
        $adapter = $this->authenticateWithHybridAuth($request, $returnTo);

        if ($adapter) {
            return $this->_getUser($provider, $adapter);
        }

        return false;
    }

    /**
     * HybridAuthを利用して認証リクエストを実行する
     *
     * @param ServerRequest $request Request instance.
     * @param string $returnTo リダイレクト先
     * @return \Hybrid_Provider_Adapter
     */
    public function authenticateWithHybridAuth(ServerRequest $request, $returnTo)
    {
        $fields = $this->_config['fields'];
        $provider = $this->_checkFields($request, $fields);
        if (!$provider) {
            return false;
        }

        $params = ['hauth_return_to' => $returnTo];
        if ($provider === 'OpenID') {
            $params['openid_identifier'] = $request->data[$fields['openid_identifier']];
        }

        $this->_init($request);
        $adapter = $this->hybridAuth->authenticate($provider, $params);

        return $adapter;
    }

    /**
     * Check if a provider already connected return user record if available
     *
     * @param ServerRequest $request Request instance.
     * @return array|bool User array on success, false on failure.
     */
    public function getUser(ServerRequest $request)
    {
        $this->_init($request);
        $idps = $this->hybridAuth->getConnectedProviders();
        foreach ($idps as $provider) {
            $adapter = $this->hybridAuth->getAdapter($provider);

            return $this->_getUser($provider, $adapter);
        }

        return false;
    }

    /**
     * ユーザーと外部アカウントの紐付け
     *
     * @param ServerRequest $request Request instance.
     * @param array $user ログインユーザーデータ
     * @return bool
     */
    public function associateWithUser(ServerRequest $request, $user)
    {
        $this->_init($request);
        $idps = $this->hybridAuth->getConnectedProviders();
        foreach ($idps as $provider) {
            $adapter = $this->hybridAuth->getAdapter($provider);
            $userProfile = $adapter->getUserProfile();
            /* @var $userProfile \Hybrid_User_Profile */
            if (!empty($userProfile->identifier)) {
                break;
            }
        }

        //
        if (empty($userProfile)) {
            return false;
        }

        $userModel = TableRegistry::get($this->_config['userModel']);
        $table = TableRegistry::get($this->_config['accountTable']);

        $conditions = [
            'table' => $userModel->registryAlias(),
            'foreign_id' => $user[$userModel->primaryKey()],
            'provider' => $provider,
        ];

        $data = [
            'provider_uid' => $userProfile->identifier,
            'provider_username' => $userProfile->displayName,
        ];

        $association = $table->find()->where($conditions)->first();

        if (empty($association)) {
            // 新規作成
            $data = array_merge($conditions, $data);
            $association = $table->newEntity($data);
        } else {
            // 更新
            $association = $table->patchEntity($association, $data);
        }
        $association->set('user_profile', $userProfile);

        if ($table->save($association)) {
            return true;
        }
        // TODO: エラー処理
        Log::debug($association->errors());

        return false;
    }

    /**
     * ユーザーと外部アカウントの紐付け解除
     *
     * @param string $provider ログインプロバイダー名
     * @param array $user ログインユーザーデータ
     * @return bool
     */
    public function unlinkWithUser($provider, $user)
    {
        $userModel = TableRegistry::get($this->_config['userModel']);
        $table = TableRegistry::get($this->_config['accountTable']);

        $conditions = [
            'table' => $this->_config['userModel'],
            'foreign_id' => $user[$userModel->primaryKey()],
            'provider' => $provider,
        ];

        $association = $table->find()->where($conditions)->first();

        if (empty($association)) {
            return false;
        }

        if ($table->delete($association)) {
            return true;
        }

        // TODO: エラー処理
        Log::debug($association->errors());

        return false;
    }

    /**
     * Initialize hybrid auth
     *
     * @param ServerRequest $request Request instance.
     * @return void
     * @throws RuntimeException Incase case of unknown error.
     */
    protected function _init(ServerRequest $request)
    {
        $request->session()->start();
        $config = Configure::read('HybridAuth');
        if (empty($config['base_url'])) {
            $baseUrl = [
                'plugin' => 'Elastic/SocialLogin',
                'controller' => 'SocialLogin',
                'action' => 'endpoint'
            ];
            $config['base_url'] = Router::url($baseUrl, true);
        }
        try {
            $this->hybridAuth = new \Hybrid_Auth($config);
        } catch (\Exception $e) {
            if ($e->getCode() < 5) {
                throw new RuntimeException($e->getMessage());
            } else {
                $this->_registry->Auth->flash($e->getMessage());
                $this->hybridAuth = new \Hybrid_Auth($config);
            }
        }
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param ServerRequest $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(ServerRequest $request)
    {
        $fields = $this->_config['fields'];
        $provider = $request->data($fields['provider']);
        if (empty($provider) ||
            ($provider === 'OpenID' && !$request->data($fields['openid_identifier']))
        ) {
            return false;
        }

        return $provider;
    }

    /**
     * Get user record for hybrid auth adapter and try to get associated user record
     * from your application database. If app user record is not found and
     * `registrationCallback` is set the specified callback function of User model
     * is called.
     *
     * @param string $provider Provider name.
     * @param object $adapter Hybrid auth adapter instance.
     * @return array User record
     */
    protected function _getUser($provider, \Hybrid_Provider_Adapter $adapter)
    {
        try {
            $providerProfile = $adapter->getUserProfile();
        } catch (\Exception $e) {
            $adapter->logout();
            throw $e;
        }

        $user = false;
        $userModel = TableRegistry::get($this->_config['userModel']);

        try {
            // ユーザーIDの取得
            $userId = $this->_getUserIdFromSocialAccounts($userModel, $provider, $providerProfile);
            $conditions = [
                $userModel->aliasField($userModel->primaryKey()) => $userId,
            ];
            $user = $this->_fetchUserFromDb($conditions);
        } catch (RecordNotFoundException $e) {
            // ユーザーの紐付けがない場合
        }

        return $user;
    }

    /**
     * アカウントテーブルからユーザーidを取得
     *
     * @param Table $userModel アカウントテーブル
     * @param string $provider ログインプロバイダ名
     * @param \Hybrid_User_Profile $providerProfile プロバイダから取得したユーザープロファイル
     * @return mixed User.id
     * @throws RecordNotFoundException
     */
    protected function _getUserIdFromSocialAccounts($userModel, $provider, $providerProfile)
    {
        $accountTable = TableRegistry::get($this->_config['accountTable']);

        $fields = $this->_config['fields'];
        $conditions = [
            $accountTable->aliasField('table') => $userModel->registryAlias(),
            $accountTable->aliasField($fields['provider']) => $provider,
            $accountTable->aliasField($fields['provider_uid']) => $providerProfile->identifier
        ];

        $account = $accountTable->find()
            ->where($conditions)
            ->hydrate(false)
            ->firstOrFail();

        return $account['foreign_id'];
    }

    /**
     * Fetch user from database matching required conditions
     *
     * @param array $conditions Query conditions.
     * @return array|bool User array on success, false on failure.
     */
    protected function _fetchUserFromDb(array $conditions)
    {
        $userModel = $this->_config['userModel'];
        list(, $userAlias) = pluginSplit($userModel);

        // ユーザーテーブルから取得
        $table = TableRegistry::get($userModel);

        $scope = $this->_config['scope'];
        if ($scope) {
            $conditions = array_merge($conditions, $scope);
        }

        $query = $table->find('all');
        $contain = $this->_config['contain'];

        if ($contain) {
            $query = $query->contain($contain);
        }

        $result = $query
            ->where($conditions)
            ->hydrate(false)
            ->first();

        if ($result) {
            if (isset($this->_config['fields']['password'])) {
                unset($result[$this->_config['fields']['password']]);
            }

            return $result;
        }

        return false;
    }

    /**
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Auth.logout' => 'onLogout'
        ];
    }

    /**
     * event on 'Auth.logout'
     *
     * @param Event $event A Event
     * @param array $user logged in user data
     * @return bool
     */
    public function onLogout(Event $event, array $user)
    {
        return $this->logoutHybridAuth();
    }

    /**
     * Logged out from all HybridAuth providers.
     *
     * @return bool
     */
    public function logoutHybridAuth()
    {
        $this->_init($this->_registry->getController()->request);
        $this->hybridAuth->logoutAllProviders();

        return true;
    }
}
