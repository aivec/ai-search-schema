# Developer Hooks Reference / 開発者向けフックリファレンス

This document lists all available WordPress hooks (filters and actions) provided by AVC AEO Schema.
このドキュメントでは、AVC AEO Schema が提供するすべての WordPress フック（フィルターとアクション）を一覧にしています。

---

## Schema Control Filters / スキーマ制御フィルター

### `avc_aeo_schema_enabled`

Controls whether schema output is enabled for the current page.
現在のページでスキーマ出力を有効にするかどうかを制御します。

**Parameters:**
- `bool $enabled` - Whether schema is enabled (default: `true`)

**Example:**
```php
// Disable schema on specific pages
add_filter( 'avc_aeo_schema_enabled', function( $enabled ) {
    if ( is_page( 'no-schema-page' ) ) {
        return false;
    }
    return $enabled;
});
```

---

### `avc_aeo_competing_schema_patterns`

Add custom regex patterns to detect and suppress competing schema output from other plugins.
他のプラグインからの競合スキーマ出力を検出・抑制するためのカスタム正規表現パターンを追加します。

**Parameters:**
- `array $patterns` - Array of regex patterns (default: `[]`)

**Example:**
```php
add_filter( 'avc_aeo_competing_schema_patterns', function( $patterns ) {
    // Add pattern to suppress custom schema plugin
    $patterns[] = '/<script[^>]*class="my-other-schema"[^>]*>/';
    return $patterns;
});
```

---

## Breadcrumb Filters / パンくずフィルター

### `avc_aeo_show_breadcrumbs`

Controls whether breadcrumb HTML/JSON-LD is displayed.
パンくず HTML/JSON-LD を表示するかどうかを制御します。

**Parameters:**
- `bool $show` - Whether to show breadcrumbs (default: `true`)

**Example:**
```php
// Hide breadcrumbs on archive pages
add_filter( 'avc_aeo_show_breadcrumbs', function( $show ) {
    if ( is_archive() ) {
        return false;
    }
    return $show;
});
```

---

### `avc_aeo_breadcrumb_items`

Modify the breadcrumb items array before rendering.
レンダリング前にパンくずアイテムの配列を変更します。

**Parameters:**
- `array $items` - Array of breadcrumb items, each with `name` and `url` keys
- `array $context` - Current page context

**Example:**
```php
add_filter( 'avc_aeo_breadcrumb_items', function( $items, $context ) {
    // Add a custom item after home
    $custom_item = [
        'name' => 'Products',
        'url'  => home_url( '/products/' ),
    ];
    array_splice( $items, 1, 0, [ $custom_item ] );
    return $items;
}, 10, 2 );
```

---

### `avc_aeo_breadcrumb_home_label`

Customize the "Home" breadcrumb label.
「ホーム」パンくずのラベルをカスタマイズします。

**Parameters:**
- `string $label` - The home label (default: `'Home'` / localized)

**Example:**
```php
add_filter( 'avc_aeo_breadcrumb_home_label', function( $label ) {
    return 'トップページ';
});
```

---

## Metabox Filters / メタボックスフィルター

### `avc_aeo_allowed_post_types`

Control which post types show the AEO Schema metabox.
AEO Schema メタボックスを表示する投稿タイプを制御します。

**Parameters:**
- `array $post_types` - Array of allowed post type slugs (default: `['post', 'page']`)

**Example:**
```php
add_filter( 'avc_aeo_allowed_post_types', function( $post_types ) {
    // Add custom post type
    $post_types[] = 'product';
    $post_types[] = 'portfolio';
    return $post_types;
});
```

---

## API Filters / API フィルター

### `avc_aeo_google_maps_api_key`

Filter the Google Maps API key before use.
使用前に Google Maps API キーをフィルタリングします。

**Parameters:**
- `string $api_key` - The sanitized API key

**Example:**
```php
add_filter( 'avc_aeo_google_maps_api_key', function( $api_key ) {
    // Use different key for specific environments
    if ( defined( 'WP_ENV' ) && WP_ENV === 'production' ) {
        return 'production-api-key';
    }
    return $api_key;
});
```

---

### `avc_aeo_bypass_geocode_nonce`

**SECURITY WARNING:** This filter should only be used in test environments.
**セキュリティ警告:** このフィルターはテスト環境でのみ使用してください。

Bypass nonce verification for geocode AJAX requests. Only works when:
- `WP_TESTS_DIR` or `PHPUNIT_COMPOSER_INSTALL` is defined
- `WP_DEBUG` is `true`

ジオコード AJAX リクエストの nonce 検証をバイパスします。以下の条件でのみ動作:
- `WP_TESTS_DIR` または `PHPUNIT_COMPOSER_INSTALL` が定義されている
- `WP_DEBUG` が `true`

**Parameters:**
- `bool $bypass` - Whether to bypass nonce check (default: `false`)

---

## Action Hooks / アクションフック

### `avc_aeo_json_responder`

Fired when sending JSON response from geocode request. Useful for testing.
ジオコードリクエストから JSON レスポンスを送信する際に発火します。テストに有用です。

**Parameters:**
- `bool $success` - Whether the request was successful
- `mixed $data` - Response data
- `int $status` - HTTP status code

---

## Wizard Hooks / ウィザードフック

### `avc_wizard_steps`

Modify the setup wizard steps.
セットアップウィザードのステップを変更します。

**Parameters:**
- `array $steps` - Array of wizard step configurations

**Example:**
```php
add_filter( 'avc_wizard_steps', function( $steps ) {
    // Add custom step
    $steps['my_step'] = [
        'title'       => 'My Custom Step',
        'description' => 'Configure custom settings',
    ];
    return $steps;
});
```

---

### `avc_wizard_completed`

Fired when the setup wizard is completed.
セットアップウィザードが完了した際に発火します。

**Parameters:**
- `array $progress` - Wizard progress data

**Example:**
```php
add_action( 'avc_wizard_completed', function( $progress ) {
    // Log completion or trigger other actions
    error_log( 'AVC AEO Schema wizard completed' );
});
```

---

### `avc_wizard_step_completed`

Fired when a single wizard step is completed.
ウィザードの単一ステップが完了した際に発火します。

**Parameters:**
- `string $step` - Step identifier
- `array $data` - Sanitized step data

---

### `avc_wizard_import_completed`

Fired when settings import from another plugin is completed.
他のプラグインからの設定インポートが完了した際に発火します。

**Parameters:**
- `string $source` - Source plugin identifier (e.g., `'yoast'`, `'rankmath'`)
- `array $imported_data` - The imported settings data

---

### `avc_wizard_import_sources`

Modify available import sources in the wizard.
ウィザードで利用可能なインポートソースを変更します。

**Parameters:**
- `array $sources` - Array of import source configurations

**Example:**
```php
add_filter( 'avc_wizard_import_sources', function( $sources ) {
    // Add custom import source
    $sources['my_plugin'] = [
        'name'     => 'My Plugin',
        'callback' => 'my_import_function',
    ];
    return $sources;
});
```

---

## Usage Tips / 使用上のヒント

### Priority Recommendations / 優先度の推奨

- Use priority `10` (default) for most modifications
- Use priority `5` for early modifications that other filters might depend on
- Use priority `20` or higher for final overrides

### Testing Hooks / フックのテスト

When testing custom hook implementations:

```php
// In your test file
add_filter( 'avc_aeo_schema_enabled', '__return_false' );

// Verify schema is disabled
$schema = new AVC_AEO_Schema();
// Assert schema output is empty
```

---

## See Also / 関連情報

- [Quick Start Guide](./quick-start.md)
- [FAQ](./FAQ.md)
- [WordPress Plugin API](https://developer.wordpress.org/plugins/hooks/)
