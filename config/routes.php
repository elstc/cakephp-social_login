<?php

use Cake\Routing\Router;

Router::scope('/social_login', function ($routes) {
    $routes->connect('/:action', ['plugin' => 'Elastic/SocialLogin', 'controller' => 'SocialLogin']);
});

Router::plugin('Elastic/SocialLogin', function ($routes) {
    $routes->fallbacks('InflectedRoute');
});
