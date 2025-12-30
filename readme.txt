=== AI Search Schema ===
Contributors: Aivec LLC
Tags: schema, seo, structured-data, ai-search, local-seo, breadcrumbs, faq, llms-txt
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.4-dev
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Overview / 概要 ==
WordPress plugin for generating structured data optimized for AI-powered search engines, including Local SEO, breadcrumbs, and FAQ extraction. Optimizes your site for AI-powered answer engines (sometimes called AEO: Answer Engine Optimization). Configure everything in **Settings → AI Search Schema** and the plugin will emit JSON-LD tailored to your organization or storefront.
AI検索最適化・ローカルSEO対応のWordPressプラグインです。**設定 → AI Search Schema** で会社情報・LocalBusiness 情報・SNS・ジオコーディングなどを入力すると、検索エンジン向けの JSON-LD を一括生成できます。

== Why Structured Data Matters Now / 今、構造化データが重要な理由 ==

**The Age of Answer Engines / 回答エンジンの時代**

Search is evolving from "10 blue links" to AI-powered answer engines (Google SGE, Bing Copilot, ChatGPT with browsing). These systems don't just index pages—they understand them. Structured data (JSON-LD) is the language that helps AI comprehend your content.
検索は「10本の青いリンク」から、AIによる回答エンジン（Google SGE、Bing Copilot、ChatGPTのブラウジング機能）へと進化しています。これらのシステムはページを単にインデックスするだけでなく、「理解」します。構造化データ（JSON-LD）は、AIがあなたのコンテンツを正しく理解するための言語です。

**Why AI Search Optimization Matters / AI検索最適化が重要な理由**

- **AI citations**: When AI assistants answer questions, they cite sources. Proper schema increases the chance of being cited.
  **AI引用**: AIアシスタントが質問に回答する際、出典を引用します。適切なスキーマは引用される可能性を高めます。
- **Rich results**: Google displays enhanced search results (stars, prices, FAQs, breadcrumbs) for sites with valid schema.
  **リッチリザルト**: Googleは有効なスキーマを持つサイトに対し、拡張検索結果（星評価、価格、FAQ、パンくず）を表示します。
- **Local SEO/MEO**: LocalBusiness schema directly powers Google Maps and local pack rankings.
  **ローカルSEO/MEO**: LocalBusinessスキーマはGoogleマップとローカルパックの順位に直接影響します。
- **Voice search**: Structured data helps voice assistants provide accurate answers from your site.
  **音声検索**: 構造化データは音声アシスタントがあなたのサイトから正確な回答を提供することを助けます。

== Features / 機能 ==
- **Organization / LocalBusiness JSON-LD** – address, geo, price range, payment methods, reservations, storefront imagery, `areaServed`, `branchOf` などを 1 つの @graph に統合  
  **Organization / LocalBusiness スキーマ**：住所・緯度経度・価格帯・支払方法・予約可否・店舗画像・商圏情報を1つのJSON-LDに集約
- **Article / FAQ / QAPage / Product** – per-post metabox lets editors switch schema types, auto injects FAQPage `mainEntity`, and WooCommerce products map to Product schema with offers/brand/rating/images  
  **投稿メタボックス切替 / WooCommerce連携**：Article / FAQPage / QAPage / Product を選択可能。WooCommerce商品はブランド・価格・在庫・ギャラリー画像付き Product スキーマに自動変換
- **BreadcrumbList & ItemList** – structured breadcrumbs plus archive ItemList, with optional frontend breadcrumbs template  
  **Structured Breadcrumbs**：パンくず / アーカイブ ItemList を JSON-LD で出力し、テンプレートでも表示可能
- **SearchAction & Site metadata** – `WebSite` includes SearchAction, supported languages, and SearchAction URLs  
  **SearchAction 対応**：サイト内検索を SearchAction で明示し、対応言語タグを JSON-LD に反映
- **Automatic geocoding** – Google Maps Geocoding API + OpenStreetMap fallback, rate limiting and caching  
  **ジオコーディング**：Google Maps Geocoding API＋OSMフォールバック、レート制御・キャッシュ機能付き
- **Self-diagnostics** – Validator checks required Google Rich Results properties (WebSite/Organization/LocalBusiness/Article/Product) and shows admin notices when fields are missing  
  **自己診断**：Google推奨プロパティを自動検証し、不足項目を管理画面で警告
- **Multilingual admin** – 全 UI と翻訳リソースを英語ベースで整備し、日本語翻訳を同梱
  **多言語UI**：英語ベースの翻訳ファイル＆日本語翻訳を標準同梱
- **llms.txt Generation** – Auto-generate llms.txt to help AI systems understand your site structure. Editable in the settings.
  **llms.txt生成**：AI検索エンジンがサイト構造を理解しやすいllms.txtを自動生成。設定画面で編集可能

== Geocoding / ジオコーディング ==
1. Obtain a **Google Maps Geocoding API key** (limit it to “Geocoding API” only) and add referrer/IP restrictions plus daily quotas.  
   **Google Geocoding APIキー** を取得し、API制限（Geocoding のみ）、HTTPリファラー／IP制限、クォータ設定を行ってください。
   - Google Cloud Console で新規プロジェクトを作成し、**Geocoding API** を有効化（他 API は不要）
   - 「認証情報」から API キーを発行し、HTTP リファラーまたは IP 制限を設定
   - 1 日あたりのクォータ上限を設定して不正利用を防止
2. Enter the key in **Settings → AI Search Schema**. The key is saved only in WordPress options and never rendered in HTML/JS.
   キーは WordPress のオプションにのみ保存され、HTML/JS には出力されません。
3. Click **Fetch coordinates** to geocode the current address. Requests are rate-limited (10 seconds) and cached. Re-fetching is required only when address fields change.  
   **住所から緯度・経度を取得** ボタンで geocode が発火します。10 秒のレート制御とキャッシュを実装しているため、住所を変更したタイミングのみ再取得してください。
4. When the key is empty the plugin falls back to OpenStreetMap (Nominatim) for development use. Production sites should keep a Google API key configured.  
   キー未設定時は開発用フォールバックとして OpenStreetMap (Nominatim) を使用します。本番では Google API キーの設定を推奨します。

== Security / セキュリティ ==

This plugin follows WordPress security best practices:
本プラグインはWordPressのセキュリティベストプラクティスに従っています：

- All settings require administrator privileges / 全設定に管理者権限が必要
- User inputs are sanitized and validated / ユーザー入力はサニタイズ・検証済み
- API keys are stored securely (never exposed in HTML) / APIキーは安全に保存（HTMLに露出しない）

== Installation / セットアップ ==
1. Upload the plugin folder to `wp-content/plugins/ai-search-schema`
   プラグイン一式を `wp-content/plugins/ai-search-schema` に配置
2. Activate from WordPress admin
   管理画面で有効化
3. Open **Settings → AI Search Schema** and complete the forms
   **設定 → AI Search Schema** で各項目（ブランド情報／店舗情報／SNS／APIキー 等）を入力して保存

== FAQ / よくある質問 ==

See [See the FAQ on GitHub:](https://github.com/aivec/ai-search-schema/blob/main/docs/FAQ.md) for frequently asked questions about:
よくある質問については [See the FAQ on GitHub:](https://github.com/aivec/ai-search-schema/blob/main/docs/FAQ.md) をご覧ください：

- Schema conflicts with other plugins / 他プラグインとのスキーマ衝突
- LocalBusiness schema setup / LocalBusinessスキーマの設定
- Troubleshooting common issues / よくある問題のトラブルシューティング

== License / ライセンス ==

GPLv2 or later


== Support Policy ==

**Free version support: Bug reports only.**

= What We Support =
* Bug reports with clear reproduction steps
* Issues causing PHP errors or broken schema output

= What We Do NOT Support =
* Usage questions ("How do I...?")
* SEO/AEO strategy consulting
* Feature requests
* Customization requests

= Response Policy =
* Best effort - no guaranteed response time
* Issues without reproduction steps will be closed
* Bug reports: [GitHub Issues](https://github.com/aivec/ai-search-schema/issues) only
* **We do not provide support via WordPress.org forums.**
* WP.orgフォーラムでのサポートは行っておりません。

= Required Information for Bug Reports =
* WordPress version, PHP version, plugin version
* Theme name and active plugins (especially SEO plugins)
* **Steps to reproduce** (mandatory)
* Expected vs actual behavior
* Error logs (if available)

== External Communication ==

= License Validation (Pro Version) =
When activating a Pro license, the following may be sent to our server:
* License key
* Site URL (domain)
* Plugin version

We do NOT collect: post content, user data, or analytics.

The free version makes no external license API calls.

== Frequently Asked Questions ==

= Does it support multiple locations? =
Current version supports a single LocalBusiness location. Use separate sites or custom extensions for multi-store setups.

= How often does geocoding run? =
It runs only when you click "Fetch coordinates from address" or when address fields change. Repeated requests are rate limited (10 seconds).

= What is llms.txt? =
llms.txt is a standard for helping AI systems understand your site structure. See [llmstxt.org](https://llmstxt.org/) for the specification. This plugin auto-generates the file at yoursite.com/llms.txt.

= Can I use this with other SEO plugins? =
Yes! This plugin automatically suppresses conflicting schema output from Yoast SEO, Rank Math, and All in One SEO.

== Screenshots ==
1. Settings → AI Search Schema (card-based UI with brand/site, local business, schema output, geocoding)

== Changelog ==
<!-- Generated by bin/generate-changelog.php -->

= 1.0.0 =
* [Features] **Organization / LocalBusiness JSON-LD** - Complete structured data for businesses with address, geo, price range, payment methods, reservations, storefront imagery, areaServed, and branchOf
* [Features] **Article / FAQ / QAPage / Product schemas** - Per-post metabox for schema type selection with WooCommerce integration
* [Features] **BreadcrumbList & ItemList** - Structured breadcrumbs and archive ItemList with optional frontend template
* [Features] **SearchAction & Site metadata** - WebSite schema with SearchAction and supported languages
* [Features] **Automatic geocoding** - Google Maps Geocoding API with OpenStreetMap fallback
* [Features] **Self-diagnostics** - Validator checks required Google Rich Results properties
* [Features] **Multilingual admin** - English base with Japanese translation included
* [Features] **llms.txt Generation** - Auto-generate llms.txt for AI systems

= 0.10.2 =
* [Dev Build Improvements] **Test Manager Link** (dev mode only)
* [Dev Build Improvements] Floating button on settings page bottom-right
* [Dev Build Improvements] Direct link to `tools/test-manager.php`
* [Dev Build Improvements] DEV badge for visibility
* [Dev Build Improvements] `is_dev_mode()` method to detect dev builds
* [Dev Build Improvements] Updated `test-spec.json` to v0.10.0
* [Dev Build Improvements] Renamed menu from "AEO Schema" to "AI Search Schema"
* [Dev Build Improvements] Added llms.txt feature tests (UI-120~123, FN-090~093)
* [Dev Build Improvements] Added license feature tests (UI-130~134, FN-100~102)
* [Dev Build Improvements] Total tests: 170 (16 added)
* [Dev Build Improvements] Fixed `readme.txt` sync with generated output
* [Dev Build Improvements] Fixed PHPCS warnings (long lines, unused parameter)
* [Dev Build Improvements] Synced `composer.lock` with `composer.json`
* [Dev Build Improvements] `class-ai-search-schema-settings.php`: Added `is_dev_mode()` method
* [Dev Build Improvements] `templates/admin-settings.php`: Added Test Manager link UI
* [Dev Build Improvements] WordPress 6.0+
* [Dev Build Improvements] PHP 8.0+

= 0.10.0 =
* [Phase 4: Pro Version Foundation] **License Management Class** (`includes/class-ai-search-schema-license.php`)
* [Phase 4: Pro Version Foundation] License key storage, validation, and status management
* [Phase 4: Pro Version Foundation] `is_pro()` method to check Pro license status
* [Phase 4: Pro Version Foundation] `validate()` method for license validation (currently stub)
* [Phase 4: Pro Version Foundation] AJAX handlers for license activation/deactivation
* [Phase 4: Pro Version Foundation] **Pro Features Manager** (`includes/class-ai-search-schema-pro-features.php`)
* [Phase 4: Pro Version Foundation] Pro feature registration and management
* [Phase 4: Pro Version Foundation] Pro feature list retrieval
* [Phase 4: Pro Version Foundation] Upgrade notification display
* [Phase 4: Pro Version Foundation] **License Card in Settings**
* [Phase 4: Pro Version Foundation] License status display
* [Phase 4: Pro Version Foundation] License key input field
* [Phase 4: Pro Version Foundation] Activation/deactivation buttons
* [Phase 4: Pro Version Foundation] Pro feature preview (multi-location, custom templates, etc.)
* [Phase 4: Pro Version Foundation] `ai-search-schema.php`: Added Pro features manager initialization
* [Phase 4: Pro Version Foundation] `ai-search-schema.php`: Added license option null filter
* [Phase 4: Pro Version Foundation] `admin-settings.js`: Added AJAX handlers for license activation/deactivation
* [Phase 4: Pro Version Foundation] `admin.scss`: Added license UI styles
* [Phase 4: Pro Version Foundation] Added Japanese translations
* [Phase 4: Pro Version Foundation] WordPress 6.0+
* [Phase 4: Pro Version Foundation] PHP 8.0+
* [Phase 4: Pro Version Foundation] Implement license validation API
* [Phase 4: Pro Version Foundation] Implement Pro features (multi-location, etc.)
* [Phase 4: Pro Version Foundation] WP.org submission preparation (v1.0.0)

= 0.9.0 =
* [New Features] **GitHub Issue Templates**: Added structured bug report and feature request templates
* [New Features] **Security Policy**: Added SECURITY.md with vulnerability reporting guidelines
* [New Features] **Support Policy in readme.txt**: Clear documentation of supported and unsupported use cases
* [New Features] **readme.txt Improvements**:
* [New Features] Added llms.txt FAQ entry
* [New Features] Added SEO plugin compatibility FAQ
* [New Features] Comprehensive Support Policy section with bug report guidelines
* [New Features] Added `llms-txt` tag
* [Previous Features (v0.2.x)] Auto-generate llms.txt to help AI systems understand site structure
* [Previous Features (v0.2.x)] Editable content in Settings → AI Search Schema
* [Previous Features (v0.2.x)] Separate "Save edits" and "Regenerate from site data" buttons
* [Previous Features (v0.2.x)] AJAX-based save/regenerate with status feedback
* [Previous Features (v0.2.x)] Organization / LocalBusiness JSON-LD
* [Previous Features (v0.2.x)] Article / FAQ / QAPage / Product schemas
* [Previous Features (v0.2.x)] BreadcrumbList & ItemList
* [Previous Features (v0.2.x)] WebSite with SearchAction
* [Previous Features (v0.2.x)] WooCommerce Product integration
* [Previous Features (v0.2.x)] Automatic geocoding (Google Maps + OSM fallback)
* [Previous Features (v0.2.x)] Self-diagnostics and validation
* [Previous Features (v0.2.x)] Automatic schema conflict suppression (Yoast, Rank Math, AIOSEO)
* [Files Changed] `docs/readme.txt.tpl` - Added Support Policy section and FAQs
* [Files Changed] `.github/ISSUE_TEMPLATE/bug_report.yml` - New bug report template
* [Files Changed] `.github/ISSUE_TEMPLATE/feature_request.yml` - New feature request template
* [Files Changed] `.github/ISSUE_TEMPLATE/config.yml` - Issue template configuration
* [Files Changed] `SECURITY.md` - Security policy and vulnerability reporting
* [Files Changed] `ai-search-schema.php` - Version bump to 0.9.0
* [Files Changed] `package.json` - Version bump to 0.9.0
* [Next Steps] v1.0.0: WordPress.org submission and public release
* [Next Steps] v0.10.0: Pro version foundation (License and Pro features stubs)

= 0.2.0 =
* [New Features] Added llms.txt generator to help AI systems understand your site structure
* [New Features] Auto-generates content from pages and posts with titles and excerpts
* [New Features] Editable via Settings → AI Search Schema
* [New Features] Served at `/llms.txt` with proper content-type headers
* [New Features] Can be enabled/disabled from settings
* [New Features] Rewrite rules are flushed on plugin activation/deactivation
* [Technical Details] New class: `AI_Search_Schema_Llms_Txt`
* [Technical Details] New settings card in admin UI
* [Technical Details] AJAX handler for regenerating content from site data
* [Technical Details] Filter: `ai_search_schema_llms_txt_default_content` for customizing auto-generated content
* [Technical Details] Options: `ai_search_schema_llms_txt` (content), `ai_search_schema_llms_txt_enabled` (toggle)
* [Compatibility] WordPress 6.0+
* [Compatibility] PHP 8.0+
* [Compatibility] Tested up to WordPress 6.7


== Upgrade Notice ==
