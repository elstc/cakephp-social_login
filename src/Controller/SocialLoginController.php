<?php

namespace Elastic\SocialLogin\Controller;

use Cake\Controller\Component\FlashComponent;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\Routing\Router;
use Exception;

/**
 * SocialLogin Controller
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 *
 * @property FlashComponent $Flash
 */
class SocialLoginController extends Controller
{

    /**
     * Allow methods 'endpoint' and 'authenticated'.
     *
     * @param Event $event Before filter event.
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

    /**
     * ユーザーと外部アカウントの紐付けリクエスト
     *
     * @return void
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
            ],
            true
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
