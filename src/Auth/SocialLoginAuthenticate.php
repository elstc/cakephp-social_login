<?php

namespace Elastic\SocialLogin\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Elastic\SocialLogin\Model\HybridAuthFactory;
use Elastic\SocialLogin\Model\Table\SocialAccountsTableInterface;
use Exception;
use Hybridauth\Adapter\AdapterInterface;
use Hybridauth\Hybridauth;
use Hybridauth\User\Profile;
use RuntimeException;
use UnexpectedValueException;

class SocialLoginAuthenticate extends BaseAuthenticate
{
    /**
     *
     * @var Hybridauth
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
        $this->setConfig([
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
        ]);
        parent::__construct($registry, $config);
    }

    /**
     * @param string $callbackUrl the callback url
     * @return void
     */
    public function setCallback($callbackUrl)
    {
        Configure::write('HybridAuth.callback', $callbackUrl);
    }

    /**
     * Authenticate a user based on the request information.
     *
     * @param ServerRequest $request Request to get authentication information from.
     * @param Response $response A response object that can have headers added.
     * @return array|bool User array on success, false on failure.
     * @throws \Hybridauth\Exception\InvalidArgumentException
     * @throws \Hybridauth\Exception\UnexpectedValueException
     */
    public function authenticate(ServerRequest $request, Response $response)
    {
        $fields = $this->getConfig('fields');

        // - プロバイダをチェックして認証リクエストを実行する
        $provider = $this->_checkFields($request, $fields);
        if (!$provider) {
            return false;
        }

        // 認証リクエストの実行
        $adapter = $this->authenticateWithHybridAuth($request);

        if ($adapter) {
            return $this->_getUser($provider, $adapter);
        }

        return false;
    }

    /**
     * HybridAuthを利用して認証リクエストを実行する
     *
     * @param ServerRequest $request Request instance.
     * @return AdapterInterface|null
     * @throws \Hybridauth\Exception\InvalidArgumentException
     * @throws \Hybridauth\Exception\UnexpectedValueException
     */
    protected function authenticateWithHybridAuth(ServerRequest $request)
    {
        $fields = $this->getConfig('fields');
        $provider = $this->_checkFields($request, $fields);
        if (!$provider) {
            return null;
        }
        $request->session()->write('hybridauth.provider', $provider);

        $this->_init($request);

        $adapter = $this->hybridAuth->authenticate($provider);

        $request->session()->delete('hybridauth.provider');

        return $adapter;
    }

    /**
     * Check if a provider already connected return user record if available
     *
     * @param ServerRequest $request Request instance.
     * @return array|bool User array on success, false on failure.
     * @throws \Hybridauth\Exception\InvalidArgumentException
     * @throws \Hybridauth\Exception\UnexpectedValueException
     */
    public function getUser(ServerRequest $request)
    {
        $this->_init($request);
        $providers = $this->hybridAuth->getConnectedProviders();
        foreach ($providers as $provider) {
            $adapter = $this->hybridAuth->getAdapter($provider);

            $user = $this->_getUser($provider, $adapter);
            if ($user) {
                return $user;
            }
        }

        return false;
    }

    /**
     * HybridAuthからユーザプロファイルの取得
     *
     * @param ServerRequest $request Request instance.
     * @return array [$provider, Profile]
     * @throws \Hybridauth\Exception\InvalidArgumentException
     * @throws \Hybridauth\Exception\UnexpectedValueException
     */
    public function getHybridUserProfile(ServerRequest $request)
    {
        $hybridAuth = HybridAuthFactory::create($request);
        $providers = $hybridAuth->getConnectedProviders();

        foreach ($providers as $provider) {
            $adapter = $hybridAuth->getAdapter($provider);
            $userProfile = $adapter->getUserProfile();
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
     * @throws \Hybridauth\Exception\InvalidArgumentException
     * @throws \Hybridauth\Exception\UnexpectedValueException
     */
    public function associateWithUser(ServerRequest $request, $user)
    {
        /** @var string $provider */
        /** @var Profile $userProfile */
        list($provider, $userProfile) = $this->getHybridUserProfile($request);

        $usersTable = TableRegistry::get($this->getConfig('userModel'));
        $accountsTable = $this->getAccountsTable();
        $account = $accountsTable->generateAssociatedAccount($usersTable, $user, $provider, $userProfile);

        if (!$accountsTable->save($account)) {
            // エラー処理
            Log::debug($account->getErrors());
            throw new RuntimeException(__('ソーシャルアカウントの紐付けに失敗しました。'));
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
            Log::debug($association->getErrors());
            throw new RuntimeException(__('ソーシャルアカウントの紐付けに失敗しました。'));
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
     * @param array $fields the request field names
     * @return string|bool False if the fields have not been supplied. True if they exist.
     */
    protected function _checkFields(ServerRequest $request, array $fields)
    {
        $provider = $request->getData($fields['provider']);
        if (!$provider) {
            $provider = $request->session()->read('hybridauth.provider');
        }
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
     * @param AdapterInterface $adapter Hybrid auth adapter instance.
     * @return array|null User record
     * @throws Exception
     */
    protected function _getUser($provider, AdapterInterface $adapter)
    {
        try {
            $userProfile = $adapter->getUserProfile();
        } catch (Exception $e) {
            if ($adapter->isConnected()) {
                $adapter->disconnect();
            }
            throw $e;
        }

        $usersTable = $this->getUsersTable();

        // ユーザーIDの取得
        $userId = $this->getAccountsTable()->getUserIdFromUserProfile($usersTable, $provider, $userProfile);
        if (empty($userId)) {
            // ユーザーの紐付けがない場合
            return null;
        }
        $conditions = [
            $usersTable->aliasField($usersTable->getPrimaryKey()) => $userId,
        ];

        return $this->_fetchUserFromDb($conditions);
    }

    /**
     * Fetch user from database matching required conditions
     *
     * @param array $conditions Query conditions.
     * @return array|null User array on success, false on failure.
     */
    protected function _fetchUserFromDb(array $conditions)
    {
        // ユーザーテーブルから取得
        $usersTable = $this->getUsersTable();

        $scope = $this->getConfig('scope');
        if ($scope) {
            $conditions = array_merge($conditions, $scope);
        }

        $query = $usersTable->find();
        $contain = $this->getConfig('contain');

        if ($contain) {
            $query = $query->contain($contain);
        }

        $result = $query
            ->where($conditions)
            ->enableHydration(false)
            ->first();

        if ($result) {
            if (!empty($this->getConfig('fields.password'))) {
                unset($result[$this->getConfig('fields.password')]);
            }

            return $result;
        }

        return null;
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
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpUnused
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
        $this->hybridAuth->disconnectAllAdapters();

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
     * @return SocialAccountsTableInterface|Table
     */
    private function getAccountsTable()
    {
        return TableRegistry::get($this->getConfig('accountTable'));
    }
}
