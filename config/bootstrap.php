<?php

/**
 * HybridAuth Plugin bootstrap
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
use Cake\Core\Configure;

if (file_exists(CONFIG . 'hybridauth.php')) {
    Configure::load('hybridauth');
}

if (!Configure::check('HybridAuth')) {
    throw new InvalidArgumentException(__("Can't found HybridAuth configuration."));
}

if (file_exists(CONFIG . 'hybridauth_local.php')) {
    Configure::load('hybridauth_local');
}
