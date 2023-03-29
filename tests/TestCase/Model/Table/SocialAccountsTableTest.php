<?php
namespace Elastic\SocialLogin\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Elastic\SocialLogin\Model\Table\SocialAccountsTable;

/**
 * Elastic\SocialLogin\Model\Table\SocialAccountsTable Test Case
 */
class SocialAccountsTableTest extends TestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.Elastic/SocialLogin.SocialAccounts',
    ];

    /**
     * @var SocialAccountsTable
     */
    private $SocialAccounts;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->SocialAccounts = TableRegistry::get('Elastic/SocialLogin.SocialAccounts');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SocialAccounts);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->assertInstanceOf(SocialAccountsTable::class, $this->SocialAccounts);
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
