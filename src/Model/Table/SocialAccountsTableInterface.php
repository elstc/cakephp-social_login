<?php

namespace Elastic\SocialLogin\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Elastic\SocialLogin\Model\Entity\SocialAccount;
use Hybrid_User_Profile;

/**
 * アカウントテーブルのインターフェース
 */
interface SocialAccountsTableInterface
{

    /**
     * @param EntityInterface $entity a entity.
     * @param array $options save options
     * @return EntityInterface|false
     */
    public function save(EntityInterface $entity, $options = []);

    /**
     * @param EntityInterface $entity delete target entity.
     * @param array $options delete options
     * @return EntityInterface|false
     */
    public function delete(EntityInterface $entity, $options = []);

    /**
     * システムユーザーと紐付けられたソーシャルアカウントの取得
     *
     * @param Table $usersTable
     * @param array $user
     * @param string $provider
     * @param Hybrid_User_Profile $userProfile
     * @return SocialAccount
     */
    public function generateAssociatedAccount(Table $usersTable, $user, $provider, Hybrid_User_Profile $userProfile);

    /**
     * システムユーザー情報とプロバイダからのアカウント取得
     *
     * @param Table $usersTable
     * @param array $user AuthComponent->user()
     * @param string $provider
     * @return SocialAccount
     */
    public function getAccountByUserAndProvider(Table $usersTable, $user, $provider);

    /**
     * アカウントテーブルからユーザーidを取得
     *
     * @param Table $usersTable システムユーザーテーブル
     * @param string $provider ログインプロバイダ名
     * @param Hybrid_User_Profile $userProfile プロバイダから取得したユーザープロファイル
     * @return mixed Users.id
     * @throws RecordNotFoundException
     */
    public function getUserIdFromUserProfile(Table $usersTable, $provider, Hybrid_User_Profile $userProfile);
}
