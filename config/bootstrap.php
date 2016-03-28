<?php

/**
 * HybridAuth Plugin bootstrap
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
use Cake\Core\Configure;
use Cake\Core\Plugin;

if (file_exists(CONFIG . 'hybridauth.php')) {
    Configure::load('hybridauth');
} else {
    $config = [
        'providers' => [
            'OpenID' => [
                'enabled' => true
            ]
        ],
        'debug_mode' => (bool) Configure::read('debug'),
        'debug_file' => LOGS . 'hybridauth.log',
    ];
    Configure::write('HybridAuth', $config);
}
if (file_exists(CONFIG . 'hybridauth_local.php')) {
    Configure::load('hybridauth_local');
}

if (Plugin::routes('Elastic/SocialLogin') === false) {
    require __DIR__ . DS . 'routes.php';
}
