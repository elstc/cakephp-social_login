<?php

namespace Elastic\SocialLogin\Controller;

use Cake\Controller\Component\AuthComponent;
use Cake\Controller\Component\FlashComponent;
use Cake\Http\Response;
use Elastic\SocialLogin\Auth\SocialLoginAuthenticate;

/**
 * システムユーザーとソーシャルアカウントの紐付け処理用Trait
 *
 * @property AuthComponent $Auth
 * @property FlashComponent $Flash
 * @noinspection PhpUnused
 */
trait AssociateAccountsTrait
{
    /**
     * ユーザーと外部アカウントの紐付けリクエスト
     *
     * @return void
     * @throws \Hybridauth\Exception\InvalidArgumentException
     * @throws \Hybridauth\Exception\UnexpectedValueException
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
        /** @var SocialLoginAuthenticate $auth */
        $auth = $this->Auth->getAuthenticate('Elastic/SocialLogin.SocialLogin');
        // アソシエーション紐付けのエンドポイントにコールバック先を変更する
        $auth->setCallbackToAssociationReturnTo();

        // HybridAuthのログイン状態をリセット
        $auth->logoutHybridAuth();

        $auth->authenticateWithHybridAuth($this->request);
    }

    /**
     * ユーザーと外部アカウントの紐付け
     *
     * @return Response
     * @throws \Hybridauth\Exception\InvalidArgumentException
     * @throws \Hybridauth\Exception\UnexpectedValueException
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
