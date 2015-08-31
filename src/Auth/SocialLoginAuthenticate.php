<?php

namespace Elastic\SocialLogin\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Core\Configure;
use Cake\Controller\ComponentRegistry;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Routing\Router;
use Cake\ORM\TableRegistry;
use Cake\Datasource\Exception\RecordNotFoundException;
use Hybrid_Auth;

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
    public function __construct(ComponentRegistry $registry, array $config = array())
    {
        $this->config([
            'accountTable' => 'Elastic/SocialLogin.SocialAccounts',
            'fields' => [
                'provider' => 'provider',
                'provider_uid' => 'provider_uid',
                'openid_identifier' => 'openid_identifier'
            ],
            'hauth_return_to' => null,
            'hauth_associate_user' => Router::url(
                [
                'plugin' => 'Elastic/SocialLogin',
                'controller' => 'SocialLogin',
                'action' => 'associate',
                ], true
            ),
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
    public function authenticate(\Cake\Network\Request $request, \Cake\Network\Response $response)
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
        if ($this->_config['hauth_return_to']) {
            $returnTo = Router::url($this->_config['hauth_return_to'], true);
        } else {
            $returnTo = Router::url(
                    [
                    'plugin' => 'Elastic/SocialLogin',
                    'controller' => 'SocialLogin',
                    'action' => 'authenticated'
                    ], true
            );
        }

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
     * @param Request $request
     * @param string $returnTo
     * @return \Hybrid_Provider_Adapter
     */
    public function authenticateWithHybridAuth(Request $request, $returnTo)
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
     * @param Request $request Request instance.
     * @return array|bool User array on success, false on failure.
     */
    public function getUser(Request $request)
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
     * @param Request $request
     * @param array $user
     * @return boolean
     */
    public function associateWithUser(Request $request, $user)
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
            'table' => $this->_config['userModel'],
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
        \Cake\Log\Log::debug($association->errors());
        return false;
    }

    /**
     * ユーザーと外部アカウントの紐付け解除
     *
     * @param string $provider
     * @param array $user
     * @return boolean
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
        \Cake\Log\Log::debug($association->errors());

        return false;
    }

    /**
     * Initialize hybrid auth
     *
     * @param \Cake\Network\Request $request Request instance.
     * @return void
     * @throws \RuntimeException Incase case of unknown error.
     */
    protected function _init(Request $request)
    {
        $request->session()->start();
        $config = Configure::read('HybridAuth');
        if (empty($config['base_url'])) {
            $config['base_url'] = Router::url(
                    [
                    'plugin' => 'Elastic/SocialLogin',
                    'controller' => 'SocialLogin',
                    'action' => 'endpoint'
                    ], true
            );
        }
        try {
            $this->hybridAuth = new \Hybrid_Auth($config);
        } catch (\Exception $e) {
            if ($e->getCode() < 5) {
                throw new \RuntimeException($e->getMessage());
            } else {
                $this->_registry->Auth->flash($e->getMessage());
                $this->hybridAuth = new \Hybrid_Auth($config);
            }
        }
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param \Cake\Network\Request $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(Request $request)
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
        $userModel = $this->_config['userModel'];
        list(, $userAlias) = pluginSplit($userModel);

        try {
            // ユーザーIDの取得
            $userId = $this->_getUserIdFromSocialAccounts($userModel, $provider, $providerProfile);
            $conditions = [
                $userAlias . '.' . TableRegistry::get($userModel)->primaryKey() => $userId,
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
     * @param string $userModel
     * @param string $provider
     * @param \Hybrid_User_Profile $providerProfile
     * @return mixed User.id
     * @throws RecordNotFoundException
     */
    protected function _getUserIdFromSocialAccounts($userModel, $provider, $providerProfile)
    {
        $accountTable = $this->_config['accountTable'];
        list(, $accountAlias) = pluginSplit($accountTable);

        $fields = $this->_config['fields'];
        $conditions = [
            $accountAlias . '.' . 'table' => $userModel,
            $accountAlias . '.' . $fields['provider'] => $provider,
            $accountAlias . '.' . $fields['provider_uid'] => $providerProfile->identifier
        ];

        $account = TableRegistry::get($accountTable)
            ->find()
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

    public function implementedEvents()
    {
        return [
            'Auth.logout' => 'onLogout'
        ];
    }

    /**
     * event on 'Auth.logout'
     *
     * @param Event $event
     * @param array $user
     * @return boolean
     */
    public function onLogout(Event $event, array $user)
    {
        return $this->logoutHybridAuth();
    }

    public function logoutHybridAuth()
    {
        $this->_init($this->_registry->getController()->request);
        $this->hybridAuth->logoutAllProviders();
        return true;
    }

}
