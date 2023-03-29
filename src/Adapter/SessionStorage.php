<?php

namespace Elastic\SocialLogin\Adapter;

use Cake\Utility\Hash;
use Hybridauth\Storage\StorageInterface;

class SessionStorage implements StorageInterface
{
    /**
     * @var string
     */
    protected $storeNamespace = 'HYBRIDAUTH::STORAGE';
    /**
     * @var \Cake\Network\Session|\Cake\Http\Session
     */
    protected $session;

    /**
     * @param \Cake\Network\Session|\Cake\Http\Session $session an CakePHP's sesssion object
     */
    public function __construct($session)
    {
        $this->session = $session;
    }

    /**
     * @param string $key the session key
     * @return string
     */
    protected function getSessionKey($key)
    {
        return implode('.', [$this->storeNamespace, $key]);
    }

    /**
     * Retrieve a item from storage
     *
     * @param string $key the key of stored value
     * @return mixed
     */
    public function get($key)
    {
        return $this->session->read($this->getSessionKey($key));
    }

    /**
     * Add or Update an item to storage
     *
     * @param string $key the key of store value
     * @param string $value the store value
     * @return void
     */
    public function set($key, $value)
    {
        $this->session->write($this->getSessionKey($key), $value);
    }

    /**
     * Delete an item from storage
     *
     * @param string $key the key of stored value
     * @return void
     */
    public function delete($key)
    {
        $this->session->delete($this->getSessionKey($key));
    }

    /**
     * Delete a item from storage
     *
     * @param string $key the key prefix of stored value
     * @return void
     */
    public function deleteMatch($key)
    {
        $tmp = $this->session->read($this->storeNamespace);
        if (is_array($tmp)) {
            $flat = Hash::flatten($tmp);
            foreach ($flat as $k => $v) {
                if (strstr($k, $key)) {
                    $this->session->delete($this->getSessionKey($k));
                }
            }
        }
    }

    /**
     * Clear all items in storage
     *
     * @return void
     */
    public function clear()
    {
        $this->session->delete($this->storeNamespace);
    }
}
