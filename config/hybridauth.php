<?php

use Cake\Core\Configure;

/**
 * SocialLogin Plugin example config
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
$config['HybridAuth'] = [
    'providers' => [
        'OpenID' => [
            'enabled' => true
        ],
    ],
    'debug_mode' => (bool) Configure::read('debug'),
    'debug_file' => LOGS . 'social_login.log',
];
