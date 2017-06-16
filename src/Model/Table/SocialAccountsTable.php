<?php

namespace Elastic\SocialLogin\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * SocialAccounts Model
 */
class SocialAccountsTable extends Table
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

        $this->table('social_accounts');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always'
                ]
            ],
        ]);
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
}
