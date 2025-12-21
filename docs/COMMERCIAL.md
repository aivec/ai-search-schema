# 商用配布計画 / Commercial Distribution Plan

AVC AEO Schema の商用配布に関する技術仕様と実装計画です。

---

## 目次

1. [販売形態](#販売形態)
2. [Phase 1: 即時リリース版](#phase-1-即時リリース版)
3. [Phase 2: 自動更新対応](#phase-2-自動更新対応)
4. [Phase 3: ライセンスキー検証](#phase-3-ライセンスキー検証)
5. [サーバー側実装](#サーバー側実装)
6. [API仕様](#api仕様)

---

## 販売形態

| 項目 | 設定値 |
|------|--------|
| 販売形態 | 年間サブスクリプション |
| サイト制限 | 1ドメインにつき1ライセンス |
| 更新方法 | WordPress 管理画面から自動更新 |
| 配布形式 | ZIP ファイル |

---

## Phase 1: 即時リリース版

### 実装済み機能

- **更新通知**: 新バージョンが利用可能な場合、管理画面に通知を表示
- **WordPress 更新システム統合**: プラグイン一覧に更新が表示される
- **キャッシュ**: 12時間のキャッシュで API 負荷を軽減
- **cptm-client 統合**: `aivec/cptm-client` を拡張し、Welcart 非依存で動作

### 技術的アプローチ

`aivec/cptm-client` パッケージを使用し、以下のように実装：

1. **cptm-client を継承**: `Avc\Aeo\Schema\License\Client` が `CptmClient` を継承
2. **Welcart 依存の除去**: `initUpdateChecker()` をオーバーライドし、`USCES_VERSION` への依存を削除
3. **既存インフラ活用**: AIVEC の既存更新サーバーと互換性を維持

### 関連ファイル

```
src/License/
└── Client.php            # 更新チェッカー（cptm-client 拡張版）

composer.json             # aivec/cptm-client 依存を追加
```

### サーバー側に必要なもの

1. **update.json** を API エンドポイントに配置
   - URL: `https://api.aivec.co.jp/plugins/avc-aeo-schema/update.json`
   - サンプル: [docs/update-api-sample.json](./update-api-sample.json)

2. **ZIP ファイル** をダウンロード可能な場所に配置
   - URL: `https://api.aivec.co.jp/plugins/avc-aeo-schema/download/avc-aeo-schema-v0.20.1.zip`

### update.json の形式

```json
{
    "name": "AVC AEO Schema",
    "slug": "avc-aeo-schema",
    "version": "0.20.1",
    "download_url": "https://api.aivec.co.jp/plugins/avc-aeo-schema/download/avc-aeo-schema-v0.20.1.zip",
    "requires": "6.0",
    "tested": "6.7",
    "requires_php": "8.0",
    "changelog": "<h4>0.20.1</h4><ul><li>商用配布機能 Phase 1</li></ul>"
}
```

---

## Phase 2: 自動更新対応

### 追加実装（1週間）

1. **ダウンロード認証**: ライセンスキー付きでダウンロード
2. **サイト登録**: 購入時にドメインを登録
3. **更新トークン**: 有効期限付きのダウンロードURL生成

### 更新チェッカーの変更

```php
// Update_Checker.php に追加
private function get_download_url(): string {
    $license_key = get_option('avc_aeo_license_key');
    $site_url = home_url();

    return add_query_arg([
        'license_key' => $license_key,
        'site_url' => urlencode($site_url),
    ], self::DOWNLOAD_API_URL);
}
```

---

## Phase 3: ライセンスキー検証

### 追加ファイル

```
src/License/
├── Client.php              # 更新チェッカー（Phase 1で実装済み、cptm-client 拡張）
├── License_Manager.php     # ライセンス管理（Phase 3で実装）
└── Admin_License_Page.php  # 管理画面（Phase 3で実装）
```

### License_Manager.php の概要

```php
<?php
namespace Avc\Aeo\Schema\License;

class License_Manager {
    private const API_URL = 'https://api.aivec.co.jp/v1';
    private const OPTION_KEY = 'avc_aeo_license';

    /**
     * ライセンスを有効化
     */
    public function activate(string $license_key): array {
        $response = wp_remote_post(self::API_URL . '/licenses/activate', [
            'body' => [
                'license_key' => $license_key,
                'site_url'    => home_url(),
                'product'     => 'avc-aeo-schema',
            ],
        ]);

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['success'])) {
            update_option(self::OPTION_KEY, [
                'key'          => $license_key,
                'activated_at' => current_time('mysql'),
                'expires_at'   => $data['expires_at'] ?? null,
            ]);
        }

        return $data;
    }

    /**
     * ライセンスを無効化
     */
    public function deactivate(): array {
        $license = get_option(self::OPTION_KEY);

        if (empty($license['key'])) {
            return ['success' => false, 'message' => 'No license found'];
        }

        $response = wp_remote_post(self::API_URL . '/licenses/deactivate', [
            'body' => [
                'license_key' => $license['key'],
                'site_url'    => home_url(),
            ],
        ]);

        delete_option(self::OPTION_KEY);
        delete_transient('avc_aeo_license_valid');

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * ライセンスが有効かチェック
     */
    public function is_valid(): bool {
        $license = get_option(self::OPTION_KEY);

        if (empty($license['key'])) {
            return false;
        }

        // キャッシュ確認（24時間）
        $cached = get_transient('avc_aeo_license_valid');
        if ($cached !== false) {
            return (bool) $cached;
        }

        // サーバーに確認
        $valid = $this->verify_with_server($license['key']);
        set_transient('avc_aeo_license_valid', $valid ? 1 : 0, DAY_IN_SECONDS);

        return $valid;
    }

    /**
     * サーバーでライセンスを検証
     */
    private function verify_with_server(string $license_key): bool {
        $response = wp_remote_post(self::API_URL . '/licenses/verify', [
            'body' => [
                'license_key' => $license_key,
                'site_url'    => home_url(),
            ],
        ]);

        if (is_wp_error($response)) {
            return true; // ネットワークエラー時は許可
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return !empty($data['valid']);
    }
}
```

### 管理画面での表示

設定ページに「ライセンス」タブを追加：

```php
// templates/admin-license.php
<div class="avc-aeo-license-panel">
    <h2><?php esc_html_e('License', 'avc-aeo-schema'); ?></h2>

    <?php if ($license_manager->is_valid()): ?>
        <div class="notice notice-success">
            <p><?php esc_html_e('License is active', 'avc-aeo-schema'); ?></p>
        </div>
        <button class="button" id="avc-deactivate-license">
            <?php esc_html_e('Deactivate', 'avc-aeo-schema'); ?>
        </button>
    <?php else: ?>
        <label for="avc-license-key">
            <?php esc_html_e('License Key', 'avc-aeo-schema'); ?>
        </label>
        <input type="text" id="avc-license-key" class="regular-text" />
        <button class="button button-primary" id="avc-activate-license">
            <?php esc_html_e('Activate', 'avc-aeo-schema'); ?>
        </button>
    <?php endif; ?>
</div>
```

---

## サーバー側実装

### データベース設計

```sql
-- licenses テーブル
CREATE TABLE licenses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    product_id VARCHAR(32) NOT NULL DEFAULT 'avc-aeo-schema',
    customer_email VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255),
    max_activations INT UNSIGNED DEFAULT 1,
    status ENUM('active', 'expired', 'revoked', 'pending') DEFAULT 'pending',
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_customer_email (customer_email),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- activations テーブル
CREATE TABLE activations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    license_id BIGINT UNSIGNED NOT NULL,
    site_url VARCHAR(255) NOT NULL,
    site_name VARCHAR(255),
    ip_address VARCHAR(45),
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_checked_at TIMESTAMP NULL,

    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_license_site (license_id, site_url)
);

-- downloads テーブル（ダウンロード追跡用）
CREATE TABLE downloads (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    license_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    INDEX idx_license_version (license_id, version)
);
```

### ライセンスキー生成

```php
function generate_license_key(): string {
    $segments = [];
    for ($i = 0; $i < 4; $i++) {
        $segments[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return implode('-', $segments);
}

// 出力例: A1B2C3D4-E5F6G7H8-I9J0K1L2-M3N4O5P6
```

---

## API仕様

### エンドポイント一覧

| エンドポイント | メソッド | 説明 |
|---------------|---------|------|
| `/v1/licenses/activate` | POST | ライセンス有効化 |
| `/v1/licenses/deactivate` | POST | ライセンス無効化 |
| `/v1/licenses/verify` | POST | ライセンス検証 |
| `/v1/update-check` | POST | 更新確認 |
| `/v1/download` | GET | ZIP ダウンロード |

### 共通レスポンス形式

```json
{
    "success": true,
    "data": { ... },
    "message": "Operation completed successfully"
}
```

### POST /v1/licenses/activate

**リクエスト:**
```json
{
    "license_key": "A1B2C3D4-E5F6G7H8-I9J0K1L2-M3N4O5P6",
    "site_url": "https://example.com",
    "product": "avc-aeo-schema"
}
```

**成功レスポンス:**
```json
{
    "success": true,
    "data": {
        "license_key": "A1B2C3D4-...",
        "expires_at": "2026-12-15T00:00:00Z",
        "activations_remaining": 0
    }
}
```

**エラーレスポンス:**
```json
{
    "success": false,
    "error": "activation_limit_reached",
    "message": "This license has reached the maximum number of activations"
}
```

### POST /v1/licenses/verify

**リクエスト:**
```json
{
    "license_key": "A1B2C3D4-E5F6G7H8-I9J0K1L2-M3N4O5P6",
    "site_url": "https://example.com"
}
```

**レスポンス:**
```json
{
    "success": true,
    "data": {
        "valid": true,
        "expires_at": "2026-12-15T00:00:00Z",
        "status": "active"
    }
}
```

### GET /v1/download

**パラメータ:**
- `license_key`: ライセンスキー
- `site_url`: 登録サイトURL
- `version`: (optional) 指定バージョン

**成功時:** ZIP ファイルをダウンロード

**エラー時:**
```json
{
    "success": false,
    "error": "invalid_license",
    "message": "License key is invalid or expired"
}
```

---

## 実装スケジュール

| Phase | 期間 | 内容 | 状態 |
|-------|------|------|------|
| Phase 1 | 即時 | 更新通知、手動ダウンロード | ✅ 実装済み |
| Phase 2 | 1週間 | 自動更新API | 📋 計画中 |
| Phase 3 | 2週間 | ライセンスキー検証 | 📋 計画中 |

---

## サーバー側の準備（Phase 1）

1. **update.json を配置**
   ```
   https://api.aivec.co.jp/plugins/avc-aeo-schema/update.json
   ```

2. **ZIP ファイルを配置**
   ```
   https://api.aivec.co.jp/plugins/avc-aeo-schema/download/avc-aeo-schema-v0.20.1.zip
   ```

3. **CORS 設定**（必要に応じて）
   ```
   Access-Control-Allow-Origin: *
   Access-Control-Allow-Methods: GET, POST
   ```

---

## セキュリティ考慮事項

1. **ライセンスキーの保護**
   - DB には平文で保存しない（ハッシュ化を検討）
   - WordPress オプションには暗号化して保存

2. **レート制限**
   - API エンドポイントにレート制限を設定
   - IP ベースで 100リクエスト/分

3. **ダウンロードの保護**
   - 一時的なダウンロードトークンを発行
   - トークンの有効期限は1時間

4. **不正利用対策**
   - 同一ライセンスキーの過剰なアクティベーション検知
   - 異常なダウンロードパターンの監視
