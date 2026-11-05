=== Aivec AI Search Schema ===
Contributors: aivectai
Tags: schema, structured-data, ai-search, local-seo, llms-txt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-focused structured data (JSON-LD) for LocalBusiness, breadcrumbs, FAQ extraction, and llms.txt.

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
1. Upload the plugin folder to `wp-content/plugins/aivec-ai-search-schema`
   プラグイン一式を `wp-content/plugins/aivec-ai-search-schema` に配置
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

== External Services ==

This plugin can connect to external services only when you use specific features.

= Google Maps Geocoding API (optional) =
Used to convert the address you enter into latitude/longitude when you click "Fetch coordinates".
API endpoint: https://maps.googleapis.com/maps/api/geocode/json
Data sent: the address fields you entered, your site locale, and your site URL in the User-Agent header.
Service provided by Google. Terms: https://cloud.google.com/maps-platform/terms | Privacy: https://policies.google.com/privacy

= OpenStreetMap Nominatim (optional fallback) =
Used only when no Google Maps API key is configured and you click "Fetch coordinates".
API endpoint: https://nominatim.openstreetmap.org/search
Data sent: the address fields you entered, the admin email (if available), and your site URL in the User-Agent header.
Service provided by OpenStreetMap (Nominatim). Usage policy: https://operations.osmfoundation.org/policies/nominatim/ | Privacy: https://osmfoundation.org/wiki/Privacy_Policy

= ZipCloud (optional, Japan only) =
Used to auto-fill address fields from a Japanese postal code when you enter a 7-digit zip code in the admin settings.
API endpoint: https://zipcloud.ibsnet.co.jp/api/search
Data sent: the postal code you entered (e.g., "1000001").
Timing: only when you type a valid 7-digit Japanese postal code in the settings page.
Service provided by ZipCloud. Terms: https://zipcloud.ibsnet.co.jp/rule/api | Privacy: https://ibsnet.co.jp/privacy-policy/

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
1. Schema console — manage brand, site identity, and JSON-LD output from a single settings screen.
2. Page-type schema controls — override schema type, breadcrumbs, FAQ extraction, and priority per post type and taxonomy.
3. Local details & hours — Google's recommended LocalBusiness properties with a real-time completeness check.
4. AEO/GEO diagnostics — validates Organization, LocalBusiness, WebSite, and WebPage schema before it ships.
5. Setup wizard — guided configuration that gets structured data live in about five minutes.

== Changelog ==
<!-- Generated by bin/generate-changelog.php -->

= 1.3.3 =
* [Security] Entries of posts that are not public are now checked every time `/llms.txt` is served. Entries of posts changed while the plugin was deactivated, and entries restored by saving an old settings screen, are no longer served
* [Changed] Entries of posts that are not public are no longer deleted from the saved llms.txt content. They are hidden when `/llms.txt` is served, so publishing a post again brings its entry back, including any hand-written text. Hidden entries are listed on the settings screen. An entry is deleted only when its post is permanently deleted
* [Changed] When a post's slug changes, the URL in its saved llms.txt entry is updated, including the entries of its child pages
* [Changed] An llms.txt entry whose URL matches no post is hidden if a post with the same title is not public, so that its excerpt is not served after a URL change. Links to another host or port, or to a path outside this site's directory, are hidden only if the title and the last part of the URL, either the slug or the post ID, both match a post that is not public. Links to another site in a subdirectory multisite network are not hidden unless their path matches a post that is not public
* [Changed] Known limits of llms.txt hiding: an entry with no matching post is hidden by mistake if it shares its title with a post that is not public and links inside this site's directory, or if it also shares the slug or post ID and links anywhere. A link to another port is hidden if its path matches a post that is not public. A link without `http://` or `https://`, such as `example.com/docs/`, counts as a link inside this site. Changing a post's category does not update its entry when the permalink contains `%category%`. After moving a site to another domain, port, or directory, an entry with the old URL is not hidden if its title was edited, and it stays after its post is permanently deleted, so correct it or use the Regenerate button
* [Changed] Broken llms.txt URLs from earlier versions are repaired the first time an administrator opens the dashboard after updating
* [Changed] The LocalBusiness subtype in the settings is now chosen from a list of 152 schema.org types instead of being typed in. A saved value that is not on the list is kept and a notice appears in the settings, and the JSON-LD outputs `LocalBusiness` until another type is chosen. This includes types that are not LocalBusiness subtypes, such as `Museum` and `VeterinaryCare`. Developers can add types with the `avc_ais_local_business_types` filter
* [Changed] Removed unused internal code, including the duplicate class in `src/ContentResolver.php`
* [Fixed] Fixed the front page JSON-LD using `HomePage`, which does not exist in schema.org. It now uses `WebPage`
* [Fixed] Fixed four business types in the setup wizard that do not exist in schema.org. `Cafe` is now `CafeOrCoffeeShop`, `FitnessCenter` is now `ExerciseGym`, `HealthAndBeautySpa` is now `DaySpa`, and `HomeAndConstruction` is now `HomeAndConstructionBusiness`. Saved values are converted automatically
* [Fixed] Fixed public holidays in opening hours being output as `PublicHoliday`. The JSON-LD now uses `PublicHolidays`
* [Fixed] Fixed the setup wizard not showing the saved business type and resetting it to `LocalBusiness` when submitted again
* [Fixed] Fixed backslashes being removed from settings fields when saving
* [Fixed] Fixed some HTML tags being left in JSON-LD text
* [Fixed] Fixed network-wide activation and deactivation on multisite only updating `/llms.txt` on the current site
* [Fixed] Fixed the admin menu having no icon. The menu now shows the AIVEC symbol in the colors of the admin color scheme
* [Fixed] Fixed the admin menu label wrapping onto two lines. The Japanese label is now `AI Search Schema`, the same as the English one

= 1.3.2 =
* [Security] Excluded password-protected posts and pages from the generated llms.txt, and removed the entries of private and password-protected posts from previously saved llms.txt content
* [Security] Saved llms.txt entries are now removed when a published post is later made private, password-protected, unpublished, or deleted. While the plugin is deactivated or uninstalled these changes cannot be tracked. Only private and password-protected posts are checked again afterwards, so review llms.txt if other posts were unpublished or deleted in the meantime
* [Security] Known limits of this removal: an entry is not found if its URL changed after it was saved, for example after a slug or permalink change. A removed entry does not return when the post is published again, so any hand-written text in it has to be entered again. An entry can come back if llms.txt is edited and saved on a screen opened before the post was drafted, trashed, or deleted. After network deactivation on multisite, only the current site is checked again until the plugin is uninstalled
* [Security] Sites that saved llms.txt before this release should review it or use the Regenerate button, because entries whose title or URL has changed since saving, or whose posts were unpublished before this release, cannot be detected automatically
* [Security] Stopped outputting content-specific schema such as QAPage and Product, and the featured image, for password-protected posts until the password is entered
* [Security] Hardened JSON-LD output escaping
* [Security] Stopped printing the Google Maps API key in the setup wizard and storing it in wizard progress data. Keys stored by earlier versions are removed automatically
* [Security] Prevented browsers from auto-filling the Google Maps API key field with a saved password
* [Fixed] Fixed ampersands in JSON-LD URLs being rewritten to `&amp;`
* [Fixed] Fixed the auto-generated llms.txt being frozen when the settings screen was saved without editing it, including when posts changed while the screen was open. Saving without editing no longer writes llms.txt content
* [Fixed] Fixed the setup wizard, and any other save outside the settings screen, turning llms.txt off and deleting custom llms.txt content. Sites affected by this stay off until llms.txt is turned on again in the settings, because this cannot be told apart from turning it off on purpose. Deleted content cannot be restored
* [Fixed] Fixed `/llms.txt` staying 404 after llms.txt was turned off and on again. Affected sites recover the next time an administrator opens the dashboard
* [Fixed] Fixed saving llms.txt removing percent-encoded characters, which broke URLs with non-ASCII slugs. Broken URLs in saved content are repaired automatically when the title and URL match exactly one published post. Other broken URLs must be corrected by hand or with the Regenerate button
* [Fixed] Fixed `/llms.txt` showing the front page after the plugin was deactivated or uninstalled. On multisite, network deactivation only updates the current site. The other sites are updated when the plugin is uninstalled
* [Changed] Temporary data such as wizard progress is now deleted on uninstall. Plugin settings, llms.txt content, the Google Maps API key, and per-post settings are kept, so they do not have to be entered again after reinstalling. To remove the key, use the Delete stored key button in the settings before uninstalling
* [Changed] Confirmed compatibility with WordPress 7.1

= 1.3.1 =
* [Fixed] Fixed the setup notice staying visible on sites configured from the settings screen instead of the wizard
* [Fixed] Fixed Japanese labels for business hours, logo selection, store photo selection, and prefecture selection
* [Fixed] Fixed the Japanese validation summary dropping the issue count from "%d error" and "%d warning"
* [Fixed] Fixed several Japanese diagnostic messages reporting the opposite of the actual result
* [Changed] Included LICENSE.txt in the distributed package
* [Changed] Fixed the build task order so the packaged files carry the correct version number

= 1.3.0 =
* [Fixed] Fixed a fatal error on Product schema pages when WooCommerce is not active
* [Fixed] Fixed llms.txt being HTML-escaped, which broke Markdown quotes and apostrophes
* [Fixed] Fixed llms.txt redirecting to a trailing-slash URL instead of serving directly
* [Security] Blocked PHP object injection when restoring serialized product meta
* [Changed] Raised the minimum supported WordPress version to 6.5
* [Changed] Confirmed compatibility with WordPress 7.0
* [Changed] Added X-Content-Type-Options: nosniff to the llms.txt response
* [Changed] Consolidated translation templates into a single aivec-ai-search-schema.pot

= 1.2.3 =
* Escape output in templates for WP.org compliance

= 1.2.2 =
* Added ZipCloud API endpoint, Terms, and Privacy to External Services
* Compressed changelog to recent versions only
* Removed PHPCS ignore comments with proper escaping

= 1.2.0 =
* WP.org compliance: Removed bundled dependencies from distribution
* WP.org compliance: Prefixed script handles for conflict avoidance
* WP.org compliance: Updated build configuration for cleaner distribution

= 1.0.0 =
* Initial release with Organization/LocalBusiness JSON-LD
* Article, FAQ, QAPage, Product schema support
* BreadcrumbList and ItemList structured data
* Google Maps geocoding with OpenStreetMap fallback
* llms.txt generation for AI systems
* Self-diagnostics and schema conflict suppression


== Upgrade Notice ==

= 1.3.3 =
Security improvement for llms.txt: entries of posts that are not public are now hidden when served, and return when the post is published again. If you used the setup wizard before 1.3.2, check that llms.txt is still on. If the settings show a business type notice, choose a type from the list.
