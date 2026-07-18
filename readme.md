# BcMdEditor for baserCMS 5
baserCMS 5系（CakePHP 5系ベース）の管理画面（固定ページおよびブログ記事の本文欄）に、軽量でリッチなMarkdownエディタ「EasyMDE」を組み込み、フロント画面で高速かつ安全にパース表示するための拡張プラグインです。
baserCMS 4系対応「MdEditor」プラグインにおけるカスタムツールバー拡張（info-box）などの資産を継承し、5系の新しい名前空間（PSR-4）、Entityオブジェクト、多対多のセッション構造、厳格なセキュリティ基盤（FormProtection）に適合させたマイグレーション版です。

## 主要な機能
* **MarkDownエディタ「EasyMDE」の採用：** <br>固定ページおよびブログプラグインの本文欄に軽量で使いやすい「EasyMDE」を確実にバインドします。
* **安全なドラッグ＆ドロップ画像アップロード：** <br>EasyMDE上への画像のドラッグ＆ドロップ（およびペースト）による非同期アップロードに対応。正規のCSRFトークンとAjax証明ヘッダーを自動付与し、5系の防壁を安全に通過して `webroot/files/` へ物理保存。
* **ツールバーのカスタム拡張：** <br>設定ファイル（setting.php）と同期し、独自の info-box（補足情報枠：:::info）などを簡単に挿入可能。
* **無傷のデータ保存フロー：** <br>5系への最適化により、過去のバージョンでデータ破損の原因となっていた不要な正規表現置換などを廃止。Markdownデータを無傷の状態で安全にデータベースへ保存します。
* **HTMLサニライズとXSS防御：** <br>フロント画面でのMarkdownパースに Parsedown.php を採用し、CustomParsedown.php で拡張。出力直前にDOM解析（DOMDocument）を用いた独自サニタイザーを通すことで、改行や安全なマークアップ（ホワイトリストのHTMLタグ）の保持と、悪意あるスクリプト（XSS）の防御を両立しています。
* **シンタックスハイライト：** <br>「highlight.js」を同梱しており、記事内のソースコードブロック（言語名やファイル名表示、行番号含む）に対して高速で美しい色付け表示を実行します。

## プラグイン構造（主要ファイル）
```text
plugins/BcMdEditor/
├── config/
│   └── setting.php                      # プラグインのシステム登録・ツールバー拡張・イベント自動登録設定
├── src/
│   ├── Controller/
│   │   └── Admin/
│   │       └── BcMdEditorUploadsController.php # 非同期画像アップロード・5系グループ認可制御
│   ├── Event/
│   │   └── BcMdEditorControllerEventListener.php # 記事保存時のForm保護解除処理・データ完全性維持フロー
│   ├── View/
│   │   └── Helper/
│   │       └── BcMdEditorHelper.php     # EasyMDE初期化JS生成・トークン自動抽出・フロント画面用パーサー
│   └── Vendor/
│       └── CustomParsedown.php          # Markdown拡張パースエンジン（ファイル名抽出・InfoBox対応）
└── BcMdEditorPlugin.php                 # プラグイン基盤クラス（baserCMSへのプラグイン認識用・最小構成）
```

## インストール要件
* CMS本体: baserCMS 5.x以上（admin-third）
* 動作環境: PHP 8.1以上

## インストール方法・設定
1. 本リポジトリをダウンロードし、フォルダ名を BcMdEditor に変更します。
2. 本プラグインを plugins/BcMdEditor/ ディレクトリへ配置します。
3. baserCMS 管理画面の「プラグイン管理」を開きます。
4. 一覧から「BcMdEditor」を選択し、「インストール（有効化）」を実行します。
5. 「システム基本設定」→「エディタ設定」より、「Markdownエディタ」を選択します。

## フロント画面（テーマファイル）への実装方法
固定ページやブログ記事の内容を一般公開画面側でマークダウンとして高速パース・安全に表示（シンタックスハイライト・PHPタグ安全再展開）させるため、利用テーマのテンプレートファイル内に以下の通り呼び出しコードを記述します。
5系（CakePHP 5）の規約に則り、システムに自動ロードされた BcMdEditor ヘルパーをビュー内から直接呼び出します。

### 固定ページ（例: templates/Pages/default.php などの本文出力エリア）
以下のように既存の`<?php echo $page->contents ?>`をコメントアウトし、呼び出しコードを記述します。
```php:利用テーマ/templates/Pages/default.php
<?php // echo $page->contents ?>
<?= $this->loadHelper('BcMdEditor.BcMdEditor')->parse($page->contents) ?>
```
テーマ内に固定ページ用ペンプレートファイルが無い場合は、`/vendor/baserproject/bc-front/templates/Pages/default.php`をコピーし`templates/Pages/default.php`に配置します。

### ブログ詳細画面（例: templates/Blog/default/single.php などの本文出力エリア）
以下のように既存の`<?php $this->BcBaser->blogPostContent($post) ?>`をコメントアウトし、呼び出しコードを記述します。
```php:利用テーマ/templates/Blog/default/single.php
<?php // $this->BcBaser->blogPostContent($post) ?>
<?= $this->loadHelper('BcMdEditor.BcMdEditor')->parse($post->detail) ?>
```

## 呼び出しコード（$this->BcMdEditor->parse()）を採用した理由
baserCMS 4系までの「システムによる自動パース」から、テーマ側で `$this->BcMdEditor->parse()` を明示的に呼び出す設計へと刷新した理由は、baserCMS 5系（CakePHP 5ベース）のアーキテクチャへの適合と安全性（UX）を担保することにあります。

1. **プラグイン（固定ページとブログ）の完全独立化への適合**：
   5系では固定ページ（Pages）とブログ（BcBlog）が完全に独立したプラグインに分離され、データの出力経路が異なります。4系のように一括でフックして自動パースするアプローチではなく、テーマ側の出力箇所でヘルパーを個別に呼び出すことで、5系のクリーンなモジュール構造に適合させています。
2. **Entityオブジェクトによる「無傷のデータ保存」の実現**：
   5系の厳格なORM環境において、保存・出力時に正規表現で文字列を無理やりシールド（置換）するロジックは、Entityデータの破損やバグの原因となります。本プラグインでは「DBには生のMarkdownを無傷で保存し、表示する瞬間だけHTMLに変換する」という表示層でのオンデマンドパースを採用しています。

## 使い方
4系 MdEditorプラグインを参照してください。

## 各ファイルの実装仕様
### 1. config/setting.php
システム設定内に「Markdownエディタ」を登録し、EasyMDEのツールバーに独自の info-box ボタンを拡張定義します。また、5系コアのイベントシステムにフックさせるため、`BcEvent` 配列を介してコントローラーリスナーのクラスパスをシステムへ通知します。

### 2. src/Event/BcMdEditorControllerEventListener.php
固定ページおよびブログ記事の保存（POST/PUT）をフックします。5系の厳格な FormProtection と安全に共存（unlockedFields の動的解放、および画像アップロード用アクションの除外）しながら、Markdownデータ（Rawデータ）の状態で安全に保存層へ渡します。固定ページ特有の一時カラム（contents_tmp）のデータ同期もケアしています。

### 3. src/Controller/Admin/BcMdEditorUploadsController.php
EasyMDEからの画像アップロードを受け付ける専用のセキュアAPIエンドポイントです。5系で刷新された多対多（BelongsToMany）のログインユーザーセッション構造（`user_groups` リスト）を安全にループ走査し、システム管理者（ID: 1）の認可権限を正確に判定してアクセスを制御。権限のないアクセスに対しては安全に403エラーを返却します。

### 4. src/View/Helper/BcMdEditorHelper.php
* **editor() / _buildMdeScript()**：5系のフォーム描画タイミングに追従し、テキストエリアへ EasyMDE を確実にバインド。データベースから取得したMarkdownデータをそのままエディタへバインドします。さらに、画面のDOMから正規の `_csrfToken` を抽出してヘッダーに載せ、Ajax証明ヘッダー（`X-Requested-With: XMLHttpRequest`）を付与してPOST投函する非同期画像アップロード通信用のフロントエンドスクリプトを動的生成します。
* **parse()**：フロント公開画面での出力用メソッド。Markdownパースエンジン（CustomParsedown）でパースを執行。その直後、出力直前の段階でDOM解析（DOMDocument）を用いた独自サニタイザーを通すことで、改行コードや安全なホワイトリストHTMLタグを保持しながら、悪意あるスクリプト（XSS）だけをピンポイントで安全に除去して描画します。

## ライセンス条項
本プラグインおよび同梱されているすべてのコンポーネントは **MIT License** のもとで公開・配布されています。

MITライセンスの再配布規約を遵守するため、内包されている外部ライブラリのコード内ヘッダー（著作権表記および許諾表示コメント）は一切変更・上書きせず、そのままの状態で同梱しています。各コンポーネントの原著作者および帰属情報は以下の通りです。

### 1. BcMDEditor (本プラグイン本体)
* **License**: MIT License
* **Copyright**: (c) 2026 HATTA, HATTANTOCO

### 2. EasyMDE (Easy Markdown Editor)
* **Source**: [Ionaru/easy-markdown-editor](https://github.com/ionaru/easy-markdown-editor)
* **License**: MIT License
* **Copyright**: (c) 2017 Jeroen Ionaru, (c) 2015 NextStepWebs

### 3. Parsedown
* **Source**: [erusev/parsedown](https://github.com/erusev/parsedown)
* **License**: MIT License
* **Copyright**: (c) 2013-2018 Emanuil Rusev

### 4. highlight.js
* **Source**: [highlightjs/highlight.js](https://github.com/highlightjs/highlight.js)
* **License**: BSD 3-Clause License
* **Copyright**: (c) Ivan Sagalaev, highlight.js team
