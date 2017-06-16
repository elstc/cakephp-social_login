<?php

namespace Elastic\SocialLogin\Controller;

use Cake\Controller\Component\FlashComponent;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Log\Log;
use Exception;

/**
 * SocialLogin Controller
 */
class SocialLoginController extends Controller
{

    /**
     * Endpoint method
     *
     * @return void
     */
    public function endpoint()
    {
        $this->request->session()->start();
        try {
            \Hybrid_Endpoint::process();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * This action exists just to ensure AuthComponent fetches user info from
     * hybridauth after successful login
     *
     * Hyridauth's `hauth_return_to` is set to this action.
     *
     * @return \Cake\Http\Response
     */
    public function authenticated()
    {
        $user = $this->Auth->identify();
        if ($user) {
            $this->Auth->setUser($user);

            return $this->redirect($this->Auth->redirectUrl());
        }

        return $this->redirect($this->Auth->config('loginAction'));
    }
}
