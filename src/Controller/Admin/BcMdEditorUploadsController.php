<?php
/**
 * BcMdEditorUploadsController.php
 *
 * BcMdEditorプラグインの画像アップロード制御コントローラークラス
 *
 * @package    BcMdEditor
 * @subpackage Controller\Admin
 * @author     HATTA
 * @license    MIT License
 * @link       https://hattantoco.com
 */
declare(strict_types=1);

namespace BcMdEditor\Controller\Admin;

use BaserCore\Controller\Admin\BcAdminAppController;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use BaserCore\Utility\BcUtil;

class BcMdEditorUploadsController extends BcAdminAppController 
{
    /**
     * コントローラー初期化フェーズ
     */
    public function initialize(): void
    {
        parent::initialize();

        // FormProtectionコンポーネントをアンロード
        if ($this->components()->has('FormProtection')) {
            $this->components()->unload('FormProtection');
        }
    }

    /**
     * 画像非同期アップロード実行エンドポイント
     *
     * @return \Cake\Http\Response
     */
    public function upload(): Response {
        $request = $this->getRequest();

        if (empty($request->getParam('prefix')) || $request->getParam('prefix') !== 'Admin') {
            return $this->response->withStatus(403)->withType('application/json')->withStringBody(
                json_encode(array('message' => 'Access denied: Invalid routing'))
            );
        }

        $loginUser = BcUtil::loginUser('Admin');
        
        if (empty($loginUser)) {
            return $this->response->withStatus(403)->withType('application/json')->withStringBody(
                json_encode(array('message' => 'Access denied: Authentication required'))
            );
        }

        // ログインユーザーがシステム管理者グループ（ID: 1）に所属しているか判定
        $userGroupId = null;
        $isSystemAdmin = false;

        // ユーザー情報を配列形式に変換
        $userData = is_object($loginUser) && method_exists($loginUser, 'toArray') ? $loginUser->toArray() : (array)$loginUser;

        // user_groups配下のグループIDを走査
        if (isset($userData['user_groups']) && is_array($userData['user_groups'])) {
            foreach ($userData['user_groups'] as $group) {
                if (isset($group['id']) && (int)$group['id'] === 1) {
                    $userGroupId = 1;
                    $isSystemAdmin = true;
                    break;
                }
            }
        }
        $adminId = 1;
        $allowedGroups = array($adminId);

        // 判定フラグ、または従来の変数をそのまま活かしてガード
        if (!$isSystemAdmin && ($userGroupId === null || !in_array((int)$userGroupId, $allowedGroups))) {
            return $this->response->withStatus(403)->withType('application/json')->withStringBody(
                json_encode(array('message' => 'アクセス拒否: お使いのユーザーグループにはファイルのアップロード権限がありません。'))
            );
        }

        // POSTデータ処理の実行
        if ($request->is('post')) {
            $uploadedFiles = $request->getUploadedFiles();
            
            /** @var \Psr\Http\Message\UploadedFileInterface|null $file */
            $file = $uploadedFiles['image'] ?? null;

            if ($file && $file->getError() === UPLOAD_ERR_OK) {
                $clientMediaType = $file->getClientMediaType();
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($clientMediaType, $allowedTypes)) {
                    return $this->response->withStatus(400)->withType('application/json')->withStringBody(
                        json_encode(array('message' => 'Invalid file type'))
                    );
                }

                // 公開領域（webroot）内の保存先ディレクトリの生成
                $uploadDir = WWW_ROOT . 'files' . DS . 'BcMdEditor' . DS;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // 一意のファイル名（タイムスタンプ + ユニークID）の生成
                $clientFilename = $file->getClientFilename();
                $ext = pathinfo($clientFilename, PATHINFO_EXTENSION);
                $newFileName = date('YmdHis') . '_' . uniqid() . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;

                try {
                    // アップロードされたファイルを移動
                    $file->moveTo($targetPath);
                    
                    // 公開用の画像URLを生成
                    $filePath = $request->getAttribute('webroot') . 'files/BcMdEditor/' . $newFileName;
                    
                    // EasyMDEの仕様に準拠したJSONフォーマットでレスポンスデータを生成
                    $responseData = array(
                        'data' => array(
                            'link' => $filePath
                        )
                    );
                    
                    return $this->response->withStatus(200)->withType('application/json')->withStringBody(
                        json_encode($responseData, JSON_UNESCAPED_SLASHES)
                    );
                } catch (\Exception $e) {
                    return $this->response->withStatus(500)->withType('application/json')->withStringBody(
                        json_encode(array('message' => 'File move failed: ' . $e->getMessage()))
                    );
                }
            }
        }

        return $this->response->withStatus(400)->withType('application/json')->withStringBody(
            json_encode(array('message' => 'Upload failed: No file data'))
        );
    }
}
