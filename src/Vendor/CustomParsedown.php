<?php
/**
 * CustomParsedown.php
 *
 * Parsedownを拡張した独自Markdownパースエンジンクラス
 *
 * @package    BcMdEditor
 * @subpackage Vendor
 * @author     HATTA
 * @license    MIT License
 * @link       https://hattantoco.com
 */
declare(strict_types=1);

namespace BcMdEditor\Vendor;

// システム側にすでにクラスが存在しない場合のみParsedownをロード
if (!class_exists('\Parsedown', false)) {
    $parsedownPath = __DIR__ . DIRECTORY_SEPARATOR . 'Parsedown.php';
    if (file_exists($parsedownPath)) { 
        require_once $parsedownPath; 
    }
}

class CustomParsedown extends \Parsedown 
{
    /**
     * コンストラクタ / カスタムブロック構文の登録
     */
    public function __construct() {
        if (method_exists(parent::class, '__construct')) {
            parent::__construct();
        }
        $this->BlockTypes[':'][] = 'InfoBox';
    }

    /**
     * コードブロック（Fenced Code Blocks）の解析拡張
     */
    protected function blockFencedCode($Line) {
        $filename = '';
        $openerChar = substr($Line['text'], 0, 1);
        if ($openerChar === '`' || $openerChar === '~') {
            if (preg_match('/^([' . $openerChar . ']{3,})[ ]*([^\s]+)?[ ]*$/', $Line['text'], $matches)) {
                if (isset($matches[2]) && strpos($matches[2], ':') !== false) {
                    $parts = explode(':', $matches[2], 2);
                    $lang  = isset($parts[0]) ? $parts[0] : '';
                    $filename = isset($parts[1]) ? $parts[1] : '';
                    $Line['text'] = preg_replace('/:.*$/', '', $Line['text']);
                }
            }
        }

        $Block = parent::blockFencedCode($Line);

        if (isset($Block['element']['element']['attributes']['class'])) {
            $classAttr = $Block['element']['element']['attributes']['class'];
            $pureLang  = str_replace('language-', '', $classAttr);

            $Block['element']['attributes']['class'] = 'mde-pre language-' . $pureLang;

            if ($filename !== '') {
                $Block['element']['attributes']['data-filename'] = $filename;
            }
        }

        return $Block;
    }

    /**
     * 補足情報ボックス（:::info）の開始条件判定
     */
    protected function blockInfoBox($Line, $Block = null) {
        if (preg_match('/^:::\s*is-?info|^:::\s*info/i', $Line['text'])) {
            $Block = array(
                'char' => $Line['text'],
                'element' => array(
                    'name'    => 'div',
                    'handler' => 'lines',
                    'attributes' => array('class' => 'mde-info-box'),
                    'text'    => array(),
                ),
            );
            return $Block;
        }
    }

    protected function blockInfoBoxContinue($Line, $Block) {
        if (isset($Block['complete'])) { 
            return $Block; 
        }
        if (preg_match('/^:::\s*$/', $Line['text'])) {
            $Block['complete'] = true;
            return $Block;
        }
        $Block['element']['text'][] = $Line['text'];
        return $Block;
    }

    protected function blockInfoBoxComplete($Block) { 
        return $Block; 
    }
}
