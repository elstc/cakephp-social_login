<?php

use Cake\Core\ClassLoader;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;

$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);

require_once 'vendor/cakephp/cakephp/src/basics.php';
require_once 'vendor/autoload.php';
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', $root . DS . 'tests' . DS . 'test_app' . DS);
define('APP', ROOT);
define('CONFIG', $root . DS . 'config' . DS);
define('TMP', sys_get_temp_dir() . DS);
define('CAKE_CORE_INCLUDE_PATH', $root . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);

$loader = new ClassLoader();
$loader->register();
$loader->addNamespace('TestApp', APP);
Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'base' => '',
    'paths' => [
        'plugins' => [ROOT . 'Plugin' . DS],
        'templates' => [ROOT . 'Template' . DS],
    ],
]);
Cake\Cache\Cache::setConfig([
    '_cake_core_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
        'path' => '/tmp',
    ],
    '_cake_model_' => [
        'engine' => 'File',
        'prefix' => 'cake_model_',
        'serialize' => true,
        'path' => '/tmp',
    ],
]);
if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}
if (!getenv('DB')) {
    putenv('DB=sqlite');
}
ConnectionManager::setConfig('test', ['url' => getenv('db_dsn')]);

Configure::write('HybridAuth', [
    'providers' => [
        'OpenID' => [
            'enabled' => true,
        ],
    ],
    'debug_mode' => false,
]);

// Disable deprecations for now when using 3.6
if (version_compare(Configure::version(), '3.6.0', '>=')) {
    error_reporting(E_ALL ^ E_USER_DEPRECATED);
}

Plugin::load('Elastic/SocialLogin', ['path' => $root . DS, 'bootstrap' => true, 'route' => true]);
Router::reload();

error_reporting(E_ALL);
