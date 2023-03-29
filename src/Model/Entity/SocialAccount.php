<?php

namespace Elastic\SocialLogin\Model\Entity;

use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Hybridauth\User\Profile;
use stdClass;

/**
 * SocialAccount Entity.
 *
 * @property string $table
 * @property string $foreign_id
 * @property string $provider
 * @property string $provider_uid
 * @property string $provider_username
 * @property stdClass $user_profile
 * @property FrozenTime $created_at
 * @property FrozenTime $updated_at
 * @property EntityInterface $user
 * @property-read Profile $user_profile_obj
 */
class SocialAccount extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     * Note that '*' is set to true, which allows all unspecified fields to be
     * mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    /**
     *
     * @return Profile
     */
    protected function _getUserProfileObj()
    {
        $userProfile = $this->user_profile;

        $obj = new Profile();
        foreach (get_object_vars($obj) as $property => $value) {
            $obj->{$property} = isset($userProfile[$property]) ? $userProfile[$property] : null;
        }

        return $obj;
    }

    /**
     * ユーザーの取得
     *
     * @return EntityInterface
     */
    protected function _getUser()
    {
        return TableRegistry::get($this->_properties['table'])
            ->get($this->_properties['foreign_id']);
    }
}
