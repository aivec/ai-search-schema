# Quick Start Guide / クイックスタートガイド

Get AI Search Schema up and running in 5 minutes.
5分でAI Search Schemaを設定する方法を説明します。

---

## Step 1: Install the Plugin / プラグインのインストール

### Option A: Upload ZIP file / ZIPファイルをアップロード

1. Download the latest `avc-aeo-schema.zip` from the releases page.
2. Go to **Plugins → Add New → Upload Plugin** in WordPress admin.
3. Upload the ZIP file and click **Install Now**.
4. Activate the plugin.

リリースページから最新の `avc-aeo-schema.zip` をダウンロードし、WordPress管理画面の **プラグイン → 新規追加 → プラグインのアップロード** からインストールしてください。

### Option B: Manual installation / 手動インストール

1. Extract the ZIP to `/wp-content/plugins/avc-aeo-schema/`
2. Activate via **Plugins → Installed Plugins**

ZIPを `/wp-content/plugins/avc-aeo-schema/` に展開し、プラグイン一覧から有効化してください。

---

## Step 2: Basic Configuration / 基本設定

Navigate to **Settings → AI Search Schema** in your WordPress admin.
WordPress管理画面で **設定 → AI Search Schema** に移動します。

### Required Fields / 必須項目

Fill in these fields for valid schema output:
スキーマを正しく出力するために、以下のフィールドを入力してください：

| Field | Description |
|-------|-------------|
| **Company or individual name** | Your organization or business name / 組織名または事業者名 |
| **Site URL** | Your website URL (e.g., `https://example.com`) / サイトURL |

### For Local Businesses / ローカルビジネスの場合

If you operate a physical store or service location, also fill in:
店舗やサービス拠点がある場合は、以下も入力してください：

| Field | Description |
|-------|-------------|
| **Phone number** | Contact phone in international format (e.g., `+81-3-1234-5678`) / 電話番号 |
| **Address** | Full postal address (postal code, prefecture, city, street) / 住所 |
| **Coordinates** | Latitude/Longitude (use "Fetch coordinates" button) / 緯度経度 |
| **Entity Type** | Select "LocalBusiness" / エンティティタイプで「LocalBusiness」を選択 |

---

## Step 3: Choose Your Entity Type / エンティティタイプを選択

In the **Publisher & Structured identity** section:
**Publisher & Structured identity** セクションで：

- **Organization**: For companies, nonprofits, or brands without a physical storefront / 物理的な店舗を持たない企業・団体・ブランド向け
- **LocalBusiness**: For stores, restaurants, clinics, or any business with a physical location / 店舗・レストラン・クリニックなど、物理的な拠点を持つビジネス向け

---

## Step 4: Save and Verify / 保存して確認

1. Click **Save Changes** at the bottom of the page.
2. Click **Open Google Rich Results Test** button to verify your schema.
3. Check for any validation errors in the **Schema Validation Summary** panel at the top.

ページ下部の **変更を保存** をクリックし、**Google Rich Results Test を開く** ボタンでスキーマを確認してください。ページ上部の **スキーマ検証サマリー** パネルでエラーがないか確認してください。

---

## Optional: Geocoding Setup / オプション：ジオコーディング設定

To automatically get coordinates from your address:
住所から自動的に座標を取得するには：

### Using Google Maps API (Recommended for Production)

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/)
2. Enable **Geocoding API**
3. Create an API key with appropriate restrictions (HTTP referrer or IP)
4. Paste the key in **Settings → AI Search Schema → Google Maps API key**
5. Click **Fetch coordinates** to get lat/lng from your address

### Using OpenStreetMap (Development Only)

If no API key is set, the plugin falls back to OpenStreetMap/Nominatim for development purposes. This is rate-limited and not recommended for production.

APIキー未設定時は、開発用にOpenStreetMap/Nominatimを使用します。レート制限があるため、本番環境ではGoogle Maps APIキーの設定を推奨します。

---

## What Gets Generated / 生成されるスキーマ

Once configured, the plugin automatically generates:
設定が完了すると、以下のスキーマが自動生成されます：

- **WebSite** - Site metadata with SearchAction / サイト情報と検索アクション
- **Organization** or **LocalBusiness** - Entity information / 組織・事業者情報
- **WebPage** - Page-specific metadata / ページ固有の情報
- **BreadcrumbList** - Breadcrumb navigation / パンくずナビゲーション
- **Article** / **FAQPage** / **Product** - Content-specific schemas / コンテンツ別スキーマ

All schemas are merged into a single `@graph` JSON-LD block in `<head>`.
すべてのスキーマは `<head>` 内の単一の `@graph` JSON-LDブロックに統合されます。

---

## Troubleshooting / トラブルシューティング

### Schema Validation Errors

Check the **Schema Validation Summary** panel at the top of the settings page. It shows:
- **Errors**: Required fields that are missing / 必須項目の不足
- **Warnings**: Recommended fields that could improve your schema / 推奨項目の不足

### Conflicting Schemas from Other Plugins

If you use Yoast SEO, Rank Math, or All in One SEO:
1. Go to **Publisher & Structured identity** section
2. Set **Schema priority** to "AVC (suppress external)" to automatically suppress conflicting schema output

他のSEOプラグインを使用している場合は、**スキーマ優先度** を「AVC (外部を抑制)」に設定すると、競合するスキーマ出力を自動的に抑制します。

---

## Next Steps / 次のステップ

- Configure per-post schema types via the **AI Search Schema** metabox on posts/pages
- Set up social profiles in the **Social profiles** section
- Add business hours in the **Local details & hours** section
- Review the full [FAQ](./FAQ.md) for common questions

投稿・固定ページの **AI Search Schema** メタボックスで記事ごとのスキーマタイプを設定できます。また、ソーシャルプロフィールや営業時間の設定もご確認ください。

---

## Need Help? / サポート

- [FAQ Documentation](./FAQ.md)
- [Report Issues](https://github.com/anthropics/avc-aeo-schema/issues)
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Documentation](https://schema.org/)
