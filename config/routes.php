<?php

use Cake\Routing\Router;

Router::plugin('Elastic/SocialLogin', function ($routes) {
    $routes->fallbacks('InflectedRoute');
});
