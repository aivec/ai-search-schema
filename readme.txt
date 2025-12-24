=== AI Search Schema ===
Contributors: Aivec LLC
Tags: schema, seo, structured-data, local-seo, breadcrumbs, faq, aeo, llms-txt
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Overview / 概要 ==
WordPress plugin for Answer Engine Optimization (AEO), Local SEO, breadcrumbs, and FAQ extraction. Configure everything in **Settings → AI Search Schema** and the plugin will emit JSON-LD tailored to your organization or storefront.
WordPress 用 AEO / ローカルSEO プラグインです。**設定 → AI Search Schema** で会社情報・LocalBusiness 情報・SNS・ジオコーディングなどを入力すると、検索エンジン向けの JSON-LD を一括生成できます。

== Why Structured Data Matters Now / 今、構造化データが重要な理由 ==

**The Age of Answer Engines / 回答エンジンの時代**

Search is evolving from "10 blue links" to AI-powered answer engines (Google SGE, Bing Copilot, ChatGPT with browsing). These systems don't just index pages—they understand them. Structured data (JSON-LD) is the language that helps AI comprehend your content.
検索は「10本の青いリンク」から、AIによる回答エンジン（Google SGE、Bing Copilot、ChatGPTのブラウジング機能）へと進化しています。これらのシステムはページを単にインデックスするだけでなく、「理解」します。構造化データ（JSON-LD）は、AIがあなたのコンテンツを正しく理解するための言語です。

**Why AEO Matters for Your Business / AEOがビジネスに重要な理由**

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

== Comparison with Other SEO Plugins / 他SEOプラグインとの比較 ==

| Feature | AI Search Schema | Yoast SEO | Rank Math | All in One SEO |
|---------|---------------|-----------|-----------|----------------|
| **AEO-focused JSON-LD** | ✅ Specialized | ⚠️ Basic | ⚠️ Basic | ⚠️ Basic |
| **Single @graph output** | ✅ Unified | ❌ Scattered | ❌ Scattered | ❌ Scattered |
| **LocalBusiness full support** | ✅ All properties | ⚠️ Limited | ⚠️ Limited | ⚠️ Limited |
| **Auto schema conflict suppression** | ✅ Built-in | ❌ | ❌ | ❌ |
| **Japanese address format** | ✅ Native | ❌ | ❌ | ❌ |
| **Geocoding (Google + OSM)** | ✅ Built-in | ❌ | ❌ | ❌ |
| **Self-diagnostics** | ✅ Google validation | ⚠️ Limited | ⚠️ Limited | ⚠️ Limited |
| **Lightweight / No bloat** | ✅ Schema-only | ❌ Full SEO suite | ❌ Full SEO suite | ❌ Full SEO suite |

**Key Differentiators / 主な差別化ポイント:**

1. **AEO-first design**: Built specifically for the AI search era, not retrofitted from traditional SEO tools.
   **AEOファースト設計**: 従来のSEOツールからの拡張ではなく、AI検索時代のために専用設計。

2. **Unified @graph**: All schema types are merged into a single, coherent JSON-LD graph—exactly what Google recommends.
   **統合@graph**: すべてのスキーマタイプを単一の一貫したJSON-LDグラフに統合—Googleが推奨する形式。

3. **No conflicts**: Automatically suppresses schema output from Yoast, Rank Math, and AIOSEO to prevent duplicate/conflicting markup.
   **衝突なし**: Yoast、Rank Math、AIOSEOからのスキーマ出力を自動抑制し、重複・競合するマークアップを防止。

4. **Japan-ready**: Native support for Japanese address hierarchy (prefecture/city/line), postal codes, and business customs.
   **日本対応**: 日本の住所体系（都道府県/市区町村/番地）、郵便番号、商習慣にネイティブ対応。

5. **Schema-only focus**: Does one thing well. Use alongside your existing SEO plugin without feature overlap.
   **スキーマ専用**: 一つのことを確実に実行。既存のSEOプラグインと機能重複なく併用可能。

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

See [docs/FAQ.md](docs/FAQ.md) for frequently asked questions about:
よくある質問については [docs/FAQ.md](docs/FAQ.md) をご覧ください：

- Schema conflicts with other plugins / 他プラグインとのスキーマ衝突
- LocalBusiness schema setup / LocalBusinessスキーマの設定
- Troubleshooting common issues / よくある問題のトラブルシューティング

== License / ライセンス ==

GPLv2 or later


== Frequently Asked Questions ==

= Does it support multiple locations? =
Current version supports a single LocalBusiness location. Use separate sites or custom extensions for multi-store setups.

= How often does geocoding run? =
It runs only when you click "Fetch coordinates from address" or when address fields change. Repeated requests are rate limited (10 seconds).

= What is llms.txt? =
llms.txt is a standard for helping AI systems understand your site structure. See [llmstxt.org](https://llmstxt.org/) for details. This plugin auto-generates the file at yoursite.com/llms.txt.

= Can I use this with other SEO plugins? =
Yes! This plugin automatically suppresses conflicting schema output from Yoast SEO, Rank Math, and All in One SEO.

== Support Policy ==

= Free Version =
* Bug reports only (with reproduction steps required)
* Please use GitHub Issues for bug reports

= Not Supported =
* Usage questions and setup assistance
* SEO/AEO strategy consulting
* Theme or other plugin compatibility issues
* Customization requests

= Bug Reports =
Please report bugs on [GitHub](https://github.com/aivec/ai-search-schema/issues) with:
* WordPress version
* PHP version
* Theme name
* Conflicting plugins (if any)
* Steps to reproduce
* Expected vs actual behavior
* Error logs (if available)

== Screenshots ==
1. Settings → AI Search Schema (card-based UI with brand/site, local business, schema output, geocoding)

== Changelog ==
<!-- Generated by bin/generate-changelog.php -->

= 0.10.0 =
* [Phase 4: Pro版基盤準備] **ライセンス管理クラス追加** (`includes/class-ai-search-schema-license.php`)
* [Phase 4: Pro版基盤準備] ライセンスキーの保存・検証・ステータス管理
* [Phase 4: Pro版基盤準備] `is_pro()` メソッドでPro版の有効/無効を判定
* [Phase 4: Pro版基盤準備] `validate()` メソッドでライセンス検証（現在はスタブ）
* [Phase 4: Pro版基盤準備] AJAX経由でのライセンス有効化/無効化
* [Phase 4: Pro版基盤準備] **Pro機能管理クラス追加** (`includes/class-ai-search-schema-pro-features.php`)
* [Phase 4: Pro版基盤準備] Pro機能の登録・管理
* [Phase 4: Pro版基盤準備] Pro機能リストの取得
* [Phase 4: Pro版基盤準備] アップグレード通知の表示
* [Phase 4: Pro版基盤準備] **設定画面にライセンスカード追加**
* [Phase 4: Pro版基盤準備] ライセンスステータス表示
* [Phase 4: Pro版基盤準備] ライセンスキー入力フィールド
* [Phase 4: Pro版基盤準備] 有効化/無効化ボタン
* [Phase 4: Pro版基盤準備] Pro機能プレビュー（マルチロケーション、カスタムテンプレート等）
* [Phase 4: Pro版基盤準備] `ai-search-schema.php`: Pro機能マネージャーの初期化を追加
* [Phase 4: Pro版基盤準備] `ai-search-schema.php`: ライセンスオプションのnullフィルターを追加
* [Phase 4: Pro版基盤準備] `admin-settings.js`: ライセンス有効化/無効化のAJAXハンドラーを追加
* [Phase 4: Pro版基盤準備] `admin.scss`: ライセンスUI用のスタイルを追加
* [Phase 4: Pro版基盤準備] 日本語翻訳を追加
* [Phase 4: Pro版基盤準備] WordPress 6.0以上
* [Phase 4: Pro版基盤準備] PHP 8.0以上
* [Phase 4: Pro版基盤準備] ライセンス検証APIの実装
* [Phase 4: Pro版基盤準備] Pro機能の実装（マルチロケーション等）
* [Phase 4: Pro版基盤準備] WP.org申請準備（v1.0.0）

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
