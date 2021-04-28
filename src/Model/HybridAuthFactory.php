<?php

namespace Elastic\SocialLogin\Model;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\Routing\Router;
use Exception;
use Hybridauth\HttpClient\Util;
use Hybridauth\Hybridauth;
use RuntimeException;

/**
 * Hybridauthの生成
 */
class HybridAuthFactory
{

    /**
     * Hybridauthの生成
     *
     * @param ServerRequest $request Request instance
     * @return Hybridauth
     * @throws RuntimeException
     */
    public static function create(ServerRequest $request)
    {
        $request->session()->start();

        $hybridAuth = null;

        $config = Configure::read('HybridAuth');
        if (empty($config['callback'])) {
            $config['callback'] = Util::getCurrentUrl();
        }
        if (!preg_match('!\Ahttps?://!', $config['callback'])) {
            $config['callback'] = Router::url($config['callback'], true);
        }
        try {
            $hybridAuth = new Hybridauth($config);
        } catch (Exception $e) {
            Log::debug($e->getTraceAsString());
            throw new RuntimeException($e->getMessage());
        }

        return $hybridAuth;
    }
}
