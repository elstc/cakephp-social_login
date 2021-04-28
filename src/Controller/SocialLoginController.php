<?php

namespace Elastic\SocialLogin\Controller;

use Cake\Controller\Controller;

/**
 * SocialLogin Controller
 *
 * @
 */
class SocialLoginController extends Controller
{
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

        return $this->redirect($this->Auth->getConfig('loginAction'));
    }
}
