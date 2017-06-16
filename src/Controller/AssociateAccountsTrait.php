<?php

namespace Elastic\SocialLogin\Controller;

use Cake\Controller\Component\AuthComponent;
use Cake\Controller\Component\FlashComponent;
use Cake\Http\Response;
use Elastic\SocialLogin\Auth\SocialLoginAuthenticate;
use InvalidArgumentException;

/**
 * システムユーザーとソーシャルアカウントの紐付け処理用Trait
 *
 * @property AuthComponent $Auth
 * @property FlashComponent $Flash
 */
trait AssociateAccountsTrait
{

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
        /* @var $auth SocialLoginAuthenticate */

        $returnTo = $auth->getConfig('associationReturnTo');
        if (empty($returnTo)) {
            throw new InvalidArgumentException();
        }

        // HybridAuthのログイン状態をリセット
        $auth->logoutHybridAuth();

        $auth->authenticateWithHybridAuth($this->request, $returnTo);
    }

    /**
     * ユーザーと外部アカウントの紐付け
     *
     * @return Response
     */
    public function association()
    {
        $auth = $this->Auth->getAuthenticate('Elastic/SocialLogin.SocialLogin');
        /* @var $auth SocialLoginAuthenticate */

        if ($auth->associateWithUser($this->request, $this->Auth->user())) {
            // アカウント連携に成功
            $this->Flash->set(__('アカウントを連携しました。'));
        } else {
            // アカウント連携に失敗
            $this->Flash->set(__('アカウント連携に失敗しました。'));
        }

        $redirectTo = $auth->getConfig('associatedRedirect') ?: $this->Auth->redirectUrl();

        return $this->redirect($redirectTo);
    }

    /**
     * ユーザーと外部アカウントの紐付け解除
     *
     * @return Response
     */
    public function unlink()
    {
        $auth = $this->Auth->getAuthenticate('Elastic/SocialLogin.SocialLogin');
        /* @var $auth SocialLoginAuthenticate */

        if ($auth->unlinkWithUser($this->request->data('provider'), $this->Auth->user())) {
            // アカウント連携に成功
            $this->Flash->set(__('アカウント連携を解除しました。'));
        } else {
            // アカウント連携に失敗
            $this->Flash->set(__('アカウント連携の解除に失敗しました。'));
        }

        $redirectTo = $auth->getConfig('associatedRedirect') ?: $this->Auth->redirectUrl();

        return $this->redirect($redirectTo);
    }
}
