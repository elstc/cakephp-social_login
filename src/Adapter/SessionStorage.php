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
     * @inheritDoc
     */
    public function get($key)
    {
        return $this->session->read($this->getSessionKey($key));
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value)
    {
        $this->session->write($this->getSessionKey($key), $value);
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        $this->session->delete($this->getSessionKey($key));
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function clear()
    {
        $this->session->delete($this->storeNamespace);
    }
}
