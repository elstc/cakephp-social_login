<?php

namespace Elastic\SocialLogin\Test\TestCase\Adapter;

use Cake\Network\Session;
use Cake\TestSuite\TestCase;
use Elastic\SocialLogin\Adapter\SessionStorage;
use Hybridauth\Storage\StorageInterface;

/**
 * Test for SessionStorage
 */
class SessionStorageTest extends TestCase
{
    /**
     * @var SessionStorage
     */
    private $storage;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $session = new Session();
        $this->storage = new SessionStorage($session);
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        unset($this->storage);
        parent::tearDown();
    }

    /**
     * @return array
     */
    public function dataSomeRandomSession()
    {
        return [
            ['foo', 'bar'],
            [1234, 'bar'],
            ['foo', 1234],

            ['Bonjour', '안녕하세요'],
            ['ஹலோ', 'Γεια σας'],

            ['array', [1, 2, 3]],
            ['string', json_encode($this)],
            ['object', $this],

            ['provider.token.request_token', '9DYPEJ&qhvhP3eJ!'],
            ['provider.token.oauth_token', '80359084-clg1DEtxQF3wstTcyUdHF3wsdHM'],
            ['provider.token.oauth_token_secret', 'qiHTi1znz6qiH3tTcyUdHnz6qiH3tTcyUdH3xW3wsDvV08e'],
        ];
    }

    /**
     * @return void
     */
    public function testInstanceOf()
    {
        $this->assertInstanceOf(StorageInterface::class, $this->storage);
    }

    /**
     * @dataProvider dataSomeRandomSession
     * @covers       SessionStorage::get
     * @covers       SessionStorage::set
     */
    public function testSetAndGetData($key, $value)
    {
        $this->storage->set($key, $value);

        $this->assertSame($value, $this->storage->get($key));
    }

    /**
     * @dataProvider dataSomeRandomSession
     * @covers       SessionStorage::delete
     */
    public function testDelete($key, $value)
    {
        $this->storage->set($key, $value);

        $this->storage->delete($key);

        $this->assertNull($this->storage->get($key));
    }

    /**
     * @dataProvider dataSomeRandomSession
     * @covers       SessionStorage::clear
     */
    public function testClear($key, $value)
    {
        $this->storage->set($key, $value);

        $this->storage->clear();

        $this->assertNull($this->storage->get($key));
    }

    /**
     * @covers SessionStorage::clear
     */
    public function testClearDataBulk()
    {
        $data = array_values($this->dataSomeRandomSession());

        foreach ($data as $key => $value) {
            $this->storage->set($key, $value);
        }

        $this->storage->clear();

        foreach ($data as $key => $value) {
            $this->assertNull($this->storage->get($key));
        }
    }

    /**
     * @dataProvider dataSomeRandomSession
     * @covers       SessionStorage::deleteMatch
     */
    public function testDeleteMatch($key, $value)
    {
        $this->storage->set($key, $value);

        $this->storage->deleteMatch('provider.token.');

        $this->assertNull($this->storage->get('provider.token.request_token'));
    }
}
