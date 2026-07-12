<?php
/**
 * BcMdEditorControllerEventListener.php
 *
 * BcMdEditorプラグインの管理画面専用コントローラーイベントリスナークラス
 *
 * @package    BcMdEditor
 * @subpackage Event
 * @author     HATTA
 * @license    MIT License
 * @link       https://hattantoco.com
 */
declare(strict_types=1);

namespace BcMdEditor\Event;

use BaserCore\Event\BcControllerEventListener;
use Cake\Event\Event;

class BcMdEditorControllerEventListener extends BcControllerEventListener
{
    public function implementedEvents(): array
    {
        return [
            'Controller.initialize' => 'initialize',
            'Controller.startup' => 'startup'
        ];
    }

    /**
     * コントローラー初期化フェーズでの割り込み（管理画面へのヘルパー追加）
     */
    public function initialize(Event $event): void
    {
        /** @var \Cake\Controller\Controller $controller */
        $controller = $event->getSubject();

        // 管理画面（prefix === Admin）の時のみ、エディタ表示用にヘルパーを追加
        if ($controller->getRequest()->getParam('prefix') === 'Admin') {
            if (in_array($controller->getName(), ['Pages', 'PagesAdmin', 'BlogPosts'])) {
                $controller->viewBuilder()->addHelper('BcMdEditor.BcMdEditor');
            }
        }
    }

    /**
     * コントローラー起動フェーズでのデータエスケープ（画像UP保護 ＆ 現行フロー踏襲）
     */
    public function startup(Event $event): void
    {
        /** @var \Cake\Controller\Controller $controller */
        $controller = $event->getSubject();
        $request = $controller->getRequest();

        // 対象コントローラーに対するForm保護解除
        $targetControllers = ['Pages', 'PagesAdmin', 'BlogPosts'];
        if (in_array($controller->getName(), $targetControllers)) {
            if ($request->is(['post', 'put'])) {
                if ($controller->components()->has('FormProtection')) {
                    /** @var \Cake\Controller\Component\FormProtectionComponent $formProtection */
                    $formProtection = $controller->components()->get('FormProtection');
                    $unlockedFields = $formProtection->getConfig('unlockedFields') ?? [];
                    
                    // 既存のフィールドロック解除処理
                    foreach (['contents', 'detail', 'contents_tmp', 'Page.contents', 'BlogPost.detail'] as $field) {
                        if (!in_array($field, $unlockedFields)) { $unlockedFields[] = $field; }
                    }
                    $formProtection->setConfig('unlockedFields', $unlockedFields);

                    // 画像アップロード用のアクションをFormProtectionの監視対象から除外
                    $unlockedActions = $formProtection->getConfig('unlockedActions') ?? [];
                    if (!in_array('upload', $unlockedActions)) {
                        $unlockedActions[] = 'upload';
                    }
                    $formProtection->setConfig('unlockedActions', $unlockedActions);
                }
            }
        }

        // リクエストデータを読み込み、値をセットしてコントローラーに書き戻す処理
        if ($request->is(array('post', 'put'))) {
            $parsedBody = $request->getParsedBody();
            $isChanged = false;

            // 固定ページデータの処理
            if (isset($parsedBody['Page']['contents']) || isset($parsedBody['contents'])) {
                $rawText = isset($parsedBody['Page']['contents']) ? (string)$parsedBody['Page']['contents'] : (string)$parsedBody['contents'];
                if ($rawText !== '') {
                    if (isset($parsedBody['Page'])) {
                        $parsedBody['Page']['contents'] = $rawText;
                        $parsedBody['Page']['contents_tmp'] = $rawText;
                    }
                    $parsedBody['contents'] = $rawText;
                    $parsedBody['contents_tmp'] = $rawText;
                    $isChanged = true;
                }
            }

            // ブログ記事データの処理
            if (isset($parsedBody['BlogPost']['detail']) || isset($parsedBody['detail'])) {
                $rawText = isset($parsedBody['BlogPost']['detail']) ? (string)$parsedBody['BlogPost']['detail'] : (string)$parsedBody['detail'];
                if ($rawText !== '') {
                    if (isset($parsedBody['BlogPost'])) { $parsedBody['BlogPost']['detail'] = $rawText; }
                    $parsedBody['detail'] = $rawText;
                    $isChanged = true;
                }
            }

            if ($isChanged) {
                $controller->setRequest($request->withParsedBody($parsedBody));
            }
        }
    }
}
