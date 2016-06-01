<?php

namespace Elastic\SocialLogin\Controller;

use Zonde\Portal\Controller\AppController;
use Cake\Event\Event;
use Cake\Routing\Router;

/**
 * SocialLogin Controller
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 *
 * @property \Cake\Controller\Component\FlashComponent $Flash
 */
class SocialLoginController extends AppController
{

    /**
     * Allow methods 'endpoint' and 'authenticated'.
     *
     * @param \Cake\Event\Event $event Before filter event.
     * @return void
     */
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->Auth->allow(['endpoint', 'authenticated']);
    }

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
        } catch (\Exception $e) {
            \Cake\Log\Log::error($e->getMessage());
        }
    }

    /**
     * This action exists just to ensure AuthComponent fetches user info from
     * hybridauth after successful login
     *
     * Hyridauth's `hauth_return_to` is set to this action.
     *
     * @return \Cake\Network\Response
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

    /**
     * ユーザーと外部アカウントの紐付けリクエスト
     *
     * @return \Cake\Network\Response
     *
     * @example inView.
     *
     * ```
     * $this->Form->postLink('Associate with Twitter', [
     *      'plugin' => 'Elastic/SocialLogin',
     *      'controller' => 'SocialLogin',
     *      'action' => 'associate',
     * ], [
     *      'data' => ['provider' => 'Twitter'],
     * ]);
     * ```
     */
    public function associate()
    {
        $auth = $this->Auth->getAuthenticate('Elastic/SocialLogin.SocialLogin');
        /* @var $auth \Elastic\SocialLogin\Auth\SocialLoginAuthenticate */

        // HybridAuthのログイン状態をリセット
        $auth->logoutHybridAuth();

        $returnTo = Router::url(
                [
                'plugin' => 'Elastic/SocialLogin',
                'controller' => 'SocialLogin',
                'action' => 'association'
                ], true
        );

        $auth->authenticateWithHybridAuth($this->request, $returnTo);
    }

    /**
     * ユーザーと外部アカウントの紐付け
     *
     * @return \Cake\Network\Response
     */
    public function association()
    {
        $auth = $this->Auth->getAuthenticate('Elastic/SocialLogin.SocialLogin');
        /* @var $auth \Elastic\SocialLogin\Auth\SocialLoginAuthenticate */

        if ($auth->associateWithUser($this->request, $this->Auth->user())) {
            // アカウント連携に成功
            $this->Flash->set(__('アカウントを連携しました。'));
        } else {
            // アカウント連携に失敗
            $this->Flash->set(__('アカウント連携に失敗しました。'));
        }

        $redirectTo = $auth->config('associatedRedirect') ?: $this->Auth->redirectUrl();
        return $this->redirect($redirectTo);
    }

    /**
     * ユーザーと外部アカウントの紐付け解除
     *
     * @return \Cake\Network\Response
     */
    public function unlink()
    {
        $auth = $this->Auth->getAuthenticate('Elastic/SocialLogin.SocialLogin');
        /* @var $auth \Elastic\SocialLogin\Auth\SocialLoginAuthenticate */

        if ($auth->unlinkWithUser($this->request->data('provider'), $this->Auth->user())) {
            // アカウント連携に成功
            $this->Flash->set(__('アカウント連携を解除しました。'));
        } else {
            // アカウント連携に失敗
            $this->Flash->set(__('アカウント連携の解除に失敗しました。'));
        }

        $redirectTo = $auth->config('associatedRedirect') ?: $this->Auth->redirectUrl();
        return $this->redirect($redirectTo);
    }

}
