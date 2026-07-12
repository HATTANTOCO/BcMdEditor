<?php
/**
 * setting.php
 *
 * BcMdEditorプラグインの設定ファイル
 *
 * @package    BcMdEditor
 * @subpackage Config
 * @author     HATTA
 * @license    MIT License
 * @link       https://hattantoco.com
 */

return [
    // Markdownエディタをコアシステムのエディタ選択肢に登録
    'BcApp' => [
        'editors' => [
            'BcMdEditor.BcMdEditor' => 'Markdownエディタ'
        ]
    ],

    // EasyMDEのツールバーおよびカスタムボタン設定（info-box拡張）
    'BcMdEditor' => [
        'toolbar' => [
            "bold", "italic", "strikethrough", "heading", "|",
            "quote", "code", "table", "horizontal-rule", "|",
            "unordered-list", "ordered-list", "|",
            "link", "image", "|",
            
            // カスタムアクションボタン：補足情報ボックス（info-box）の挿入
            [
                'name' => 'info-box',
                'className' => 'fa fa-info-circle',
                'title' => '補足情報（info）の枠を挿入',
                'defaultText' => ":::info\nここに補足情報を記入\n:::\n"
            ],
            "|",
            "preview", "side-by-side", "fullscreen", "|",
            "guide"
        ]
    ],

    // コントローラーイベントリスナーをシステムへ自動登録
    'BcEvent' => [
        'Controller' => [
            'BcMdEditorControllerEventListener' => [
                'plugin' => 'BcMdEditor',
                'className' => 'BcMdEditor\Event\BcMdEditorControllerEventListener'
            ]
        ]
    ]
];
