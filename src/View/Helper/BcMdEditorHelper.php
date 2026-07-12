<?php
/**
 * BcMdEditorHelper.php
 *
 * BcMdEditorプラグインのヘルパークラス
 *
 * @package    BcMdEditor
 * @subpackage View\Helper
 * @author     HATTA
 * @license    MIT License
 * @link       https://hattantoco.com
 */
declare(strict_types=1);

namespace BcMdEditor\View\Helper;

use Cake\View\Helper;
use Cake\Core\Configure;

class BcMdEditorHelper extends Helper 
{
    public function __get(string $name): ?Helper
    {
        if ($name === 'BcBaser' || $name === 'BcForm') {
            return $this->_View->{$name};
        }
        return parent::__get($name);
    }

    /**
     * 管理画面エディタ（EasyMDE）初期化エリアの生成と必要なアセットのロード
     */
    public function editor(string $fieldId, array $options = []): string 
    {
        if (isset($options['editorStyles'])) { unset($options['editorStyles']); }
        if (isset($options['type'])) { unset($options['type']); }
        
        $this->BcBaser->css('BcMdEditor.easymde.min', false);
        $this->BcBaser->css('BcMdEditor.mde-preview', false);
        $this->BcBaser->js('BcMdEditor.easymde.min', false);

        // フィールドから現在のデータを取得
        $value = $this->BcForm->context()->val($fieldId);

        if (empty($value)) {
            $request = $this->_View->getRequest();
            $value = $request->getData($fieldId) 
                  ?? ($request->getData('Page.' . $fieldId) 
                  ?? ($request->getData('BlogPost.' . $fieldId) ?? ''));
        }

        if (empty($value)) {
            foreach (['page', 'blogPost', 'entity', 'post'] as $varName) {
                $entity = $this->_View->get($varName);
                if ($entity && is_object($entity) && method_exists($entity, 'get')) {
                    if ($entity->has($fieldId)) {
                        $value = $entity->get($fieldId);
                        break;
                    }
                }
            }
        }

        // 取得した値をオプション配列にバインド
        if (!empty($value)) {
            $options['value'] = $value;
        }

        $html = $this->BcForm->textarea($fieldId, $options);
        $script = $this->_buildMdeScript($fieldId);
        
        $wrappedScript = "
        <script type=\"text/javascript\">
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof EasyMDE !== 'undefined') {
                {$script}
            }
        });
        </script>
        ";
        
        return $html . $wrappedScript;
    }

    public function ckeditor(string $fieldId, array $options = []): string 
    { 
        return $this->editor($fieldId, $options); 
    }

    /**
     * フロント公開画面用：マークダウンのパース処理とアセットのロード
     */
    public function parse(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // テキストデータの初期化と独自マーカーの除去
        $decodedText = $text;
        $cleanMarkdown = $decodedText;
        $cleanMarkdown = str_replace(['<!--MDE_BODY_START-->', '<!--MDE_BODY_END-->'], '', $cleanMarkdown);
        $cleanMarkdown = trim($cleanMarkdown);

        $baseDir = rtrim(dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
        $customParsedownFile = $baseDir . DIRECTORY_SEPARATOR . 'Vendor' . DIRECTORY_SEPARATOR . 'CustomParsedown.php';
        
        $parsedHtml = '';

        // 独自クラスを使用したマークダウンのパース実行
        if (file_exists($customParsedownFile)) {
            require_once $customParsedownFile;
            $parsedown = new \BcMdEditor\Vendor\CustomParsedown();
            
            if (is_object($parsedown)) {
                $parsedown->setMarkupEscaped(false);
                $parsedown->setBreaksEnabled(true);
                $parsedHtml = $parsedown->text($cleanMarkdown);
            }
        }

        $this->BcBaser->css('BcMdEditor.atom-one-light.min', false);
        $this->BcBaser->css('BcMdEditor.mde-add', false);
        $this->BcBaser->js('BcMdEditor.highlight.min', false, ['defer' => 'defer']);
        $this->BcBaser->js('BcMdEditor.mde-core', false, ['defer' => 'defer']);

        // 出力用HTMLのサニライズと整形
        if ($parsedHtml !== '') {
            $safeHtml = $this->_sanitizeHtml($parsedHtml);
            return '<div class="mde-parsed-body">' . $safeHtml . '</div>';
        }

        return '<div class="mde-parsed-body">' . nl2br(h($cleanMarkdown)) . '</div>';
    }

    /**
     * 出力用HTMLサニタイザー
     */
    protected function _sanitizeHtml(string $html): string
    {
        if ($html === '') return '';

        // 許可する安全なHTMLタグのホワイトリスト
        $allowedTags = '<div><span><p><br><hr><h1><h2><h3><h4><h5><h6><a><img><strong><em><b><i><ul><ol><li><pre><code><blockquote><table><thead><tbody><tr><th><td><iframe>';
        
        // ホワイトリストに含まれない危険なタグを除去
        $cleaned = strip_tags($html, $allowedTags);

        // DOMDocumentを使用した危険な属性の除去処理
        $dom = new \DOMDocument();
        $htmlWithMeta = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $cleaned . '</body></html>';
        @$dom->loadHTML($htmlWithMeta, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*');

        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            if (!$node->hasAttributes()) {
                continue;
            }

            $attributes = [];
            foreach ($node->attributes as $attr) {
                $attributes[] = $attr->name;
            }

            foreach ($attributes as $attrName) {
                if (strpos(strtolower($attrName), 'on') === 0) {
                    $node->removeAttribute($attrName);
                    continue;
                }

                if (in_array(strtolower($attrName), ['href', 'src'])) {
                    $attrValue = trim($node->getAttribute($attrName));
                    if (preg_match('/^(javascript|data|vbscript):/i', $attrValue)) {
                        $node->removeAttribute($attrName);
                    }
                }
            }
        }

        // body要素内のクレンジング済みHTMLを抽出
        $bodyNode = $dom->getElementsByTagName('body')->item(0);
        $result = '';
        if ($bodyNode) {
            foreach ($bodyNode->childNodes as $child) {
                $result .= $dom->saveHTML($child);
            }
        }

        return $result;
    }
    
    /**
     * EasyMDEの拡張スクリプト生成（config/setting.phpの設定と同期）
     */
    protected function _buildMdeScript(string $fieldId): string 
    {
        $configToolbar = Configure::read('BcMdEditor.toolbar');
        if (empty($configToolbar) || !is_array($configToolbar)) {
            $configToolbar = ['bold', 'italic', 'strikethrough', 'heading', '|', 'quote', 'unordered-list', 'ordered-list', '|', 'link', 'image', '|', 'preview', 'side-by-side', 'fullscreen', '|', 'guide'];
        }

        $jsToolbarItems = [];
        foreach ($configToolbar as $item) {
            if (is_array($item)) {
                $name = $item['name'] ?? 'custom';
                $className = $item['className'] ?? 'fa fa-star';
                $title = $item['title'] ?? '';
                $defaultText = $item['defaultText'] ?? '';
                $safeText = str_replace(["\r\n", "\r", "\n"], "\\n", $defaultText);

                $jsToolbarItems[] = "{
                    name: " . json_encode($name) . ",
                    className: " . json_encode($className) . ",
                    title: " . json_encode($title) . ",
                    action: function(editor) {
                        var cm = editor.codemirror;
                        var doc = cm.getDoc();
                        var cursor = doc.getCursor();
                        doc.replaceRange(" . json_encode($safeText) . ", cursor);
                        cm.focus();
                    }
                }";
            } else {
                $jsToolbarItems[] = json_encode($item);
            }
        }
        $jsonToolbar = '[' . implode(',', $jsToolbarItems) . ']';

        return "
            var targetElement = document.querySelector('textarea[name=\"' + '{$fieldId}' + '\"]')
                || document.querySelector('textarea[name*=\"' + '{$fieldId}' + '\"]')
                || document.getElementById('{$fieldId}');

            if (targetElement) {
                var easyMDE = new EasyMDE({
                    element: targetElement,
                    spellChecker: false,
                    status: ['autosave', 'lines', 'words', 'cursor', 'upload-image'],
                    tabSize: 4,
                    renderingConfig: {
                        singleLineBreaks: false,
                        codeSyntaxHighlighting: true
                    },
                    toolbar: {$jsonToolbar},
                    
                    uploadImage: true,
                    imageUploadFunction: function(file, onSuccess, onError) {
                        var url = '" . \Cake\Routing\Router::url([
                            'plugin' => 'BcMdEditor',
                            'controller' => 'BcMdEditorUploads',
                            'action' => 'upload',
                            'prefix' => 'Admin'
                        ]) . "';
                        
                        var formData = new FormData();
                        formData.append('image', file);
                        
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', url, true);
                        
                        var csrfToken = '';
                        var tokenElement = document.querySelector('input[name=\'_csrfToken\']') 
                                        || document.querySelector('input[name=\'data[_Token][key]\']');
                        
                        if (tokenElement) {
                            csrfToken = tokenElement.value;
                        } else {
                            var metaToken = document.querySelector('meta[name=\'csrf-token\']');
                            if (metaToken) csrfToken = metaToken.getAttribute('content');
                        }
                        
                        if (csrfToken) {
                            xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                        } else {
                            console.error('[MdEditor] CSRF token element not found.');
                        }

                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.data && response.data.link) {
                                        onSuccess(response.data.link);
                                    } else {
                                        onError('Invalid response format');
                                    }
                                } catch (e) {
                                    onError('JSON parse error');
                                }
                            } else {
                                onError('Upload failed with status: ' + xhr.status);
                            }
                        };
                        
                        xhr.onerror = function() {
                            onError('Network error');
                        };
                        
                        xhr.send(formData);
                    }
                });
                
                easyMDE.codemirror.on('change', function() { 
                    targetElement.value = easyMDE.value(); 
                    targetElement.dispatchEvent(new Event('input', { bubbles: true }));
                    targetElement.dispatchEvent(new Event('change', { bubbles: true }));
                });

                var form = targetElement.closest('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        targetElement.value = easyMDE.value();
                    });
                }
            }
        ";
    }
}

