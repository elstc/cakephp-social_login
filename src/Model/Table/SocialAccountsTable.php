<?php

namespace Elastic\SocialLogin\Model\Table;

use Cake\Database\Schema\TableSchema;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Elastic\SocialLogin\Model\Entity\SocialAccount;
use Hybridauth\User\Profile;

/**
 * SocialAccounts Model
 */
class SocialAccountsTable extends Table implements SocialAccountsTableInterface
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('social_accounts');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);
    }

    /**
     * Setup Schema
     *
     * @param TableSchema $schema The table definition fetched from database.
     * @return TableSchema
     */
    protected function _initializeSchema(TableSchema $schema)
    {
        $schema->setColumnType('user_profile', 'json');

        return $schema;
    }

    /**
     * Default validation rules.
     *
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->add('id', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('table', 'create')
            ->notEmpty('table');

        $validator
            ->requirePresence('provider', 'create')
            ->notEmpty('provider');

        $validator
            ->requirePresence('provider_uid', 'create')
            ->notEmpty('provider_uid');

        $validator
            ->allowEmpty('provider_username');

        $validator
            ->allowEmpty('user_profile');

        $validator
            ->allowEmpty('created_at');

        $validator
            ->allowEmpty('updated_at');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param RulesChecker $rules The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        return $rules;
    }

    /**
     * システムユーザーと紐付けられたソーシャルアカウントの取得
     *
     * @param Table $usersTable システムユーザーテーブル
     * @param array $user 認証情報: AuthComponent->user()
     * @param string $provider ログインプロバイダ名
     * @param Profile $userProfile プロバイダから取得したユーザープロファイル
     * @return SocialAccount
     */
    public function generateAssociatedAccount(Table $usersTable, $user, $provider, Profile $userProfile)
    {
        $conditions = $this->getFindConditionsByUserAndProvider($usersTable, $user, $provider);

        $data = [
            'provider_uid' => $userProfile->identifier,
            'provider_username' => $userProfile->displayName,
        ];

        $account = $this->find()->where($conditions)->first();

        if (empty($account)) {
            // 新規作成
            $data = array_merge($conditions, $data);
            $account = $this->newEntity($data);
        } else {
            // 更新
            $account = $this->patchEntity($account, $data);
        }
        $account->set('user_profile', $userProfile);

        return $account;
    }

    /**
     * システムユーザー情報とプロバイダからのアカウント取得
     *
     * @param Table $usersTable システムユーザーテーブル
     * @param array $user 認証情報: AuthComponent->user()
     * @param string $provider ログインプロバイダ名
     * @return SocialAccount
     */
    public function getAccountByUserAndProvider(Table $usersTable, $user, $provider)
    {
        $conditions = $this->getFindConditionsByUserAndProvider($usersTable, $user, $provider);

        return $this->find()->where($conditions)->first();
    }

    /**
     * システムユーザー情報とプロバイダからの検索条件
     *
     * @param Table $usersTable システムユーザーテーブル
     * @param array $user 認証情報: AuthComponent->user()
     * @param string $provider ログインプロバイダ名
     * @return array
     */
    private function getFindConditionsByUserAndProvider(Table $usersTable, $user, $provider)
    {
        $conditions = [
            'table' => $usersTable->getRegistryAlias(),
            'foreign_id' => $user[$usersTable->getPrimaryKey()],
            'provider' => $provider,
        ];

        return $conditions;
    }

    /**
     * アカウントテーブルからユーザーidを取得
     *
     * @param Table $usersTable システムユーザーテーブル
     * @param string $provider ログインプロバイダ名
     * @param Profile $userProfile プロバイダから取得したユーザープロファイル
     * @return mixed Users.id
     * @throws RecordNotFoundException
     */
    public function getUserIdFromUserProfile(Table $usersTable, $provider, Profile $userProfile)
    {
        $conditions = [
            $this->aliasField('table') => $usersTable->getRegistryAlias(),
            $this->aliasField('provider') => $provider,
            $this->aliasField('provider_uid') => $userProfile->identifier,
        ];

        $account = $this->find()
            ->where($conditions)
            ->enableHydration(false)
            ->first();

        return isset($account['foreign_id']) ? $account['foreign_id'] : null;
    }
}
