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
use Elastic\SocialLogin\Model\HybridAuthFactory;
use Hybrid_Auth;
use Hybrid_Provider_Adapter;
use Hybrid_User_Profile;
use RuntimeException;
use UnexpectedValueException;

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
                'table' => 'table',
                'foreign_id' => 'foreign_id',
                'provider' => 'provider',
                'provider_uid' => 'provider_uid',
                'openid_identifier' => 'openid_identifier'
            ],
            // ソーシャルアカウント連携完了のリダイレクト先
            // null => Auth->redirectUrl()
            'associatedRedirect' => null,
            // システムアカウントとソーシャルアカウントの紐付け時リダイレクト先
            // @see AssociateAccountsTrait
            'associationReturnTo' => null,
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
        $fields = $this->getConfig('fields');

        // プロバイダ未指定の場合は、認証戻りのためユーザーデータを取得する
        if (!$request->getData($fields['provider'])) {
            return $this->getUser($request);
        }

        // - プロバイダをチェックして認証リクエストを実行する
        $provider = $this->_checkFields($request, $fields);
        if (!$provider) {
            return false;
        }

        // 認証後の戻りURLを生成
        $returnTo = Router::url($this->getConfig('hauthLoginAction'), true);

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
     * @return Hybrid_Provider_Adapter
     */
    public function authenticateWithHybridAuth(ServerRequest $request, $returnTo)
    {
        $fields = $this->getConfig('fields');
        $provider = $this->_checkFields($request, $fields);
        if (!$provider) {
            return false;
        }

        $params = ['hauth_return_to' => $returnTo];
        if ($provider === 'OpenID') {
            $params['openid_identifier'] = $request->getData($fields['openid_identifier']);
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
        $providers = $this->hybridAuth->getConnectedProviders();
        foreach ($providers as $provider) {
            $adapter = $this->hybridAuth->getAdapter($provider);

            return $this->_getUser($provider, $adapter);
        }

        return false;
    }

    /**
     * HybridAuthからユーザプロファイルの取得
     *
     * @param Hybrid_Auth $hybridAuth a HybridAuth instance.
     * @return array [$provider, \Hybrid_User_Profile]
     * @throws UnexpectedValueException
     */
    public function getHybridUserProfile(Hybrid_Auth $hybridAuth)
    {
        $providers = $hybridAuth->getConnectedProviders();

        foreach ($providers as $provider) {
            $adapter = $hybridAuth->getAdapter($provider);
            $userProfile = $adapter->getUserProfile();
            /* @var $userProfile \Hybrid_User_Profile */
            if (!empty($userProfile->identifier)) {
                return [$provider, $userProfile];
            }
        }

        throw new UnexpectedValueException(__('ソーシャルアカウントが取得できません。'));
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
        list($provider, $userProfile) = $this->getHybridUserProfile($this->hybridAuth);
        /* @var $userProfile \Hybrid_User_Profile */

        $usersTable = TableRegistry::get($this->getConfig('userModel'));
        $accountsTable = $this->getAccountsTable();
        $account = $accountsTable->generateAssociatedAccount($usersTable, $user, $provider, $userProfile);

        if (!$accountsTable->save($account)) {
            // エラー処理
            Log::debug($account->errors());
            throw new \RuntimeException(__('ソーシャルアカウントの紐付けに失敗しました。'));
        }

        return true;
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
        $usersTable = $this->getUsersTable();
        $accountsTable = $this->getAccountsTable();

        $association = $accountsTable->getAccountByUserAndProvider($usersTable, $user, $provider);
        if (empty($association)) {
            throw new UnexpectedValueException(__('紐付け解除対象のソーシャルアカウントが取得できません。'));
        }

        if (!$accountsTable->delete($association)) {
            // エラー処理
            Log::debug($association->errors());
            throw new \RuntimeException(__('ソーシャルアカウントの紐付けに失敗しました。'));
        }

        return true;
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
        $this->hybridAuth = HybridAuthFactory::create($request);
    }

    /**
     * Checks the fields to ensure they are supplied.
     *
     * @param ServerRequest $request The request that contains login information.
     * @return bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(ServerRequest $request)
    {
        $fields = $this->getConfig('fields');
        $provider = $request->getData($fields['provider']);
        if (empty($provider) ||
            ($provider === 'OpenID' && !$request->getData($fields['openid_identifier']))
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
    protected function _getUser($provider, Hybrid_Provider_Adapter $adapter)
    {
        try {
            $providerProfile = $adapter->getUserProfile();
        } catch (\Exception $e) {
            $adapter->logout();
            throw $e;
        }

        $user = false;
        $usersTable = $this->getUsersTable();

        try {
            // ユーザーIDの取得
            $userId = $this->getAccountsTable()->getUserIdFromUserProfile($usersTable, $provider, $userProfile);
            $conditions = [
                $usersTable->aliasField($usersTable->primaryKey()) => $userId,
            ];
            $user = $this->_fetchUserFromDb($conditions);
        } catch (RecordNotFoundException $e) {
            // ユーザーの紐付けがない場合
            $user = false;
        }

        return $user;
    }

    /**
     * Fetch user from database matching required conditions
     *
     * @param array $conditions Query conditions.
     * @return array|bool User array on success, false on failure.
     */
    protected function _fetchUserFromDb(array $conditions)
    {
        // ユーザーテーブルから取得
        $usersTable = $this->getUsersTable();

        $scope = $this->getConfig('scope');
        if ($scope) {
            $conditions = array_merge($conditions, $scope);
        }

        $query = $usersTable->find('all');
        $contain = $this->getConfig('contain');

        if ($contain) {
            $query = $query->contain($contain);
        }

        $result = $query
            ->where($conditions)
            ->hydrate(false)
            ->first();

        if ($result) {
            if (!empty($this->getConfig('fields.password'))) {
                unset($result[$this->getConfig('fields.password')]);
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

    /**
     * @return Table
     */
    private function getUsersTable()
    {
        return TableRegistry::get($this->getConfig('userModel'));
    }

    /**
     * @return \Elastic\SocialLogin\Model\Table\SocialAccountsTableInterface
     */
    private function getAccountsTable()
    {
        return TableRegistry::get($this->getConfig('accountTable'));
    }
}
