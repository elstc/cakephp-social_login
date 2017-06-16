<?php
/*
 *
 * Copyright 2017 ELASTIC Consultants Inc.
 *
 */

namespace Elastic\SocialLogin\Test\TestCase\Model;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Elastic\SocialLogin\Model\HybridAuthFactory;

/**
 * HybridAuthFactoryTest
 */
class HybridAuthFactoryTest extends TestCase
{

    public function testCreate()
    {
        Configure::write('HybridAuth.debug_mode', false); // stop log
        $request = new ServerRequest();
        $hauth = HybridAuthFactory::create($request);

        $this->assertInstanceOf('\Hybrid_Auth', $hauth);
        $this->assertTrue($request->session()->started());
    }
}
