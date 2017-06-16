<?php

use Cake\Routing\Router;
use Cake\Routing\Route\InflectedRoute;

Router::plugin('Elastic/SocialLogin', ['path' => '/social_login'], function ($routes) {
    $routes->connect('/:action', ['controller' => 'SocialLogin']);
    $routes->fallbacks(InflectedRoute::class);
});
