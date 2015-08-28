<?php

use Phinx\Migration\AbstractMigration;

class CreateSocialAccounts extends AbstractMigration
{

    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('social_accounts');
        $table->addColumn('table', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('foreign_id', 'string', [
            'default' => null,
            'limit' => 36,
            'null' => false,
        ]);
        $table->addColumn('provider', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('provider_uid', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addTimestamps();
        // Don't to overlap the account relation at the same table
        $table->addIndex(['table', 'provider', 'provider_uid'], ['unique' => true, 'name' => 'U_social_login_identifier']);
        // Don't to overlap the provider type at a user object
        $table->addIndex(['table', 'foreign_id', 'provider'], ['unique' => true, 'name' => 'U_social_login_user_identifier']);
        $table->create();
    }

}
