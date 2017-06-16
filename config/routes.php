<?php

use Cake\Routing\Route\InflectedRoute;
use Cake\Routing\Router;

Router::plugin('Elastic/SocialLogin', ['path' => '/social_login'], function ($routes) {
    $routes->connect('/:action', ['controller' => 'SocialLogin']);
    $routes->fallbacks(InflectedRoute::class);
});
