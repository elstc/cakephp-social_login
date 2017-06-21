<?php

namespace Elastic\SocialLogin\Model;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\Routing\Router;
use Exception;
use Hybrid_Auth;
use RuntimeException;

/**
 * Hybird_Authの生成
 */
class HybridAuthFactory
{

    /**
     * Hybird_Authの生成
     *
     * @param ServerRequest $request Request instance
     * @return Hybrid_Auth
     * @throws RuntimeException
     */
    public static function create(ServerRequest $request)
    {
        $request->session()->start();

        $hybridAuth = null;

        $config = Configure::read('HybridAuth');
        if (empty($config['base_url'])) {
            $baseUrl = [
                'plugin' => 'Elastic/SocialLogin',
                'controller' => 'SocialLogin',
                'action' => 'endpoint'
            ];
            $config['base_url'] = Router::url($baseUrl, true);
        } elseif (!preg_match('!\Ahttps?://!', $config['base_url'])) {
            $config['base_url'] = Router::url($config['base_url'], true);
        }
        try {
            $hybridAuth = new Hybrid_Auth($config);
        } catch (Exception $e) {
            Log::debug($e->getTraceAsString());
            throw new RuntimeException($e->getMessage());
        }

        return $hybridAuth;
    }
}
