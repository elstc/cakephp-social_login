<?php

namespace Elastic\SocialLogin\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Elastic\SocialLogin\Model\Entity\SocialAccount;
use Hybridauth\User\Profile;

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
     * @param Table $usersTable システムユーザーテーブル
     * @param array $user 認証情報: AuthComponent->user()
     * @param string $provider ログインプロバイダ名
     * @param Profile $userProfile プロバイダから取得したユーザープロファイル
     * @return SocialAccount
     */
    public function generateAssociatedAccount(Table $usersTable, $user, $provider, Profile $userProfile);

    /**
     * システムユーザー情報とプロバイダからのアカウント取得
     *
     * @param Table $usersTable システムユーザーテーブル
     * @param array $user 認証情報: AuthComponent->user()
     * @param string $provider ログインプロバイダ名
     * @return SocialAccount
     */
    public function getAccountByUserAndProvider(Table $usersTable, $user, $provider);

    /**
     * アカウントテーブルからユーザーidを取得
     *
     * @param Table $usersTable システムユーザーテーブル
     * @param string $provider ログインプロバイダ名
     * @param Profile $userProfile プロバイダから取得したユーザープロファイル
     * @return string|null Users.id
     */
    public function getUserIdFromUserProfile(Table $usersTable, $provider, Profile $userProfile);
}
