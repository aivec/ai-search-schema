# FAQ / よくある質問

AI Search Schema プラグインに関するよくある質問と回答をまとめています。

---

## Support Policy / サポートポリシー

### Supported (Free Version) / サポート対象

- Bug reports with clear reproduction steps
- 再現手順が明確なバグ報告のみ

### NOT Supported (Free Version) / サポート対象外

- Usage questions ("How do I...?") / 使い方の質問
- SEO/AEO strategy consulting / SEO/AEO戦略のコンサルティング
- Feature requests / 機能リクエスト
- Customization requests / カスタマイズ依頼
- Issues without reproduction steps / 再現手順のない問題

### Response Policy / 対応方針

- Best effort, no guaranteed response time / ベストエフォート、返信保証なし
- Issues without required information will be closed without response / 必要情報のない問題は対応なしでクローズ
- All bug reports must be submitted via [GitHub Issues](https://github.com/aivec/ai-search-schema/issues/new?template=bug_report.yml)

---

## Required Information for Bug Reports / バグ報告に必要な情報

When submitting a bug report, you **MUST** provide:

1. **WordPress Version** (e.g., 6.7)
2. **PHP Version** (e.g., 8.2)
3. **Plugin Version** (e.g., 1.0.0)
4. **Theme Name**
5. **Active Plugins** (especially SEO plugins)
6. **Steps to Reproduce** - Exact steps to trigger the issue
7. **Expected Behavior** - What should happen
8. **Actual Behavior** - What actually happens
9. **Error Logs** (if applicable)

**Issues without reproduction steps will be closed without response.**

---

## How to Validate Structured Data / 構造化データの検証方法

Use these tools to validate your schema output:

1. **Google Rich Results Test** - https://search.google.com/test/rich-results
2. **Schema.org Validator** - https://validator.schema.org/
3. **Google Search Console** - Check "Enhancements" section

---

## General / 一般的な質問

### What is AEO (Answer Engine Optimization)?
### AEO（回答エンジン最適化）とは何ですか？

**English:** AEO is the practice of optimizing your content so that AI-powered search engines (Google SGE, Bing Copilot, ChatGPT) can understand, cite, and feature it in their answers. Unlike traditional SEO which focuses on ranking links, AEO focuses on being the *source* that AI references.

**日本語:** AEOとは、AIを活用した検索エンジン（Google SGE、Bing Copilot、ChatGPT）があなたのコンテンツを理解し、引用し、回答に表示できるように最適化する手法です。リンクの順位付けに焦点を当てる従来のSEOとは異なり、AEOはAIが参照する「情報源」となることに焦点を当てています。

---

### What is JSON-LD and why does it matter?
### JSON-LDとは何ですか？なぜ重要ですか？

**English:** JSON-LD (JavaScript Object Notation for Linked Data) is a structured data format that helps search engines understand your content. It's embedded in your HTML and tells Google exactly what your page represents—a business, article, product, FAQ, etc.

**日本語:** JSON-LD（Linked Data用JavaScript Object Notation）は、検索エンジンがあなたのコンテンツを理解するための構造化データ形式です。HTMLに埋め込まれ、あなたのページが何を表しているか（ビジネス、記事、商品、FAQなど）をGoogleに正確に伝えます。

**Benefits / メリット:**
- Rich search results (stars, prices, FAQs displayed in Google) / リッチな検索結果（星評価、価格、FAQがGoogleに表示される）
- Better AI comprehension for answer engines / 回答エンジンによるAI理解の向上
- Enhanced local search visibility (Google Maps, local pack) / ローカル検索の可視性向上（Googleマップ、ローカルパック）

---

### Do I still need Yoast SEO / Rank Math if I use this plugin?
### このプラグインを使う場合、Yoast SEO / Rank Mathは必要ですか？

**English:** Yes, you can keep using your existing SEO plugin. AI Search Schema focuses *only* on structured data (JSON-LD). It doesn't handle meta tags, sitemaps, or content analysis. Use both together—this plugin will automatically suppress conflicting schema output from other plugins.

**日本語:** はい、既存のSEOプラグインを引き続き使用できます。AI Search Schemaは構造化データ（JSON-LD）*のみ*に焦点を当てています。メタタグ、サイトマップ、コンテンツ分析は扱いません。両方を併用してください—このプラグインは他のプラグインからの競合するスキーマ出力を自動的に抑制します。

---

## Schema Conflicts / スキーマの衝突

### My site already has schema from another plugin. Will there be duplicates?
### サイトに他のプラグインからのスキーマがあります。重複しますか？

**English:** No. AI Search Schema automatically detects and suppresses schema output from:
- Yoast SEO
- Rank Math
- All in One SEO (AIOSEO)

The plugin uses output buffering and filter removal to ensure only one schema source is active.

**日本語:** いいえ。AI Search Schemaは以下からのスキーマ出力を自動的に検出して抑制します：
- Yoast SEO
- Rank Math
- All in One SEO (AIOSEO)

プラグインは出力バッファリングとフィルター除去を使用して、アクティブなスキーマソースが1つだけになることを保証します。

---

### How does the conflict suppression work?
### 衝突抑制はどのように機能しますか？

**English:** When "Use AI Search Schema output" is selected in settings:

1. **Filter removal**: Hooks from competing plugins are removed before `wp_head`
2. **Output buffering**: Any remaining schema in the HTML buffer is stripped
3. **Unified output**: AI Search Schema outputs a single, merged `@graph`

**日本語:** 設定で「AI Search Schema 出力を使用」が選択されている場合：

1. **フィルター除去**: `wp_head`の前に競合プラグインのフックを除去
2. **出力バッファリング**: HTMLバッファ内の残りのスキーマを除去
3. **統合出力**: AI Search Schemaが単一の統合された`@graph`を出力

---

### I want to keep both schemas. Is that possible?
### 両方のスキーマを保持したいです。可能ですか？

**English:** Yes. In **Settings → AI Search Schema → Schema priority**, select "Allow both (may cause conflicts)". Note that Google may show warnings for duplicate Organization/LocalBusiness schemas.

**日本語:** はい。**設定 → AI Search Schema → スキーマ優先度**で「両方許可（競合の可能性あり）」を選択してください。ただし、GoogleがOrganization/LocalBusinessスキーマの重複に対して警告を表示する場合があります。

---

## LocalBusiness Schema / LocalBusinessスキーマ

### What's the difference between Organization and LocalBusiness?
### OrganizationとLocalBusinessの違いは何ですか？

**English:**

| Type | Best for | Key features |
|------|----------|--------------|
| **Organization** | Companies, brands, nonprofits without physical locations | Logo, social profiles, contact info |
| **LocalBusiness** | Stores, restaurants, service providers with physical locations | Address, hours, geo coordinates, price range |

**日本語:**

| タイプ | 最適な用途 | 主な特徴 |
|--------|-----------|----------|
| **Organization** | 物理的な場所を持たない企業、ブランド、非営利団体 | ロゴ、ソーシャルプロフィール、連絡先情報 |
| **LocalBusiness** | 物理的な場所を持つ店舗、レストラン、サービス提供者 | 住所、営業時間、緯度経度、価格帯 |

---

### Which LocalBusiness properties does Google require?
### GoogleはどのLocalBusinessプロパティを必須としていますか？

**English:** Google recommends (but doesn't strictly require) these properties for LocalBusiness:

1. `name` - Business name
2. `address` - Full postal address
3. `telephone` - Contact phone number
4. `geo` - Latitude and longitude
5. `openingHours` - Business hours
6. `image` - Photo of the storefront
7. `url` - Website URL
8. `priceRange` - Price level ($, $$, $$$, $$$$)

The plugin's admin panel includes a real-time guide showing which properties are complete.

**日本語:** GoogleはLocalBusinessに対して以下のプロパティを推奨しています（厳密には必須ではありません）：

1. `name` - 店舗名
2. `address` - 完全な住所
3. `telephone` - 連絡先電話番号
4. `geo` - 緯度と経度
5. `openingHours` - 営業時間
6. `image` - 店舗の写真
7. `url` - ウェブサイトURL
8. `priceRange` - 価格帯（$, $$, $$$, $$$$）

プラグインの管理パネルには、どのプロパティが完了しているかをリアルタイムで表示するガイドが含まれています。

---

### How do I get coordinates (latitude/longitude)?
### 緯度・経度を取得するにはどうすればいいですか？

**English:**
1. Enter your address in the plugin settings
2. Click "Fetch coordinates"
3. The plugin uses Google Maps Geocoding API (if configured) or OpenStreetMap as fallback

**Note:** For production sites, configure a Google Maps Geocoding API key for accuracy.

**日本語:**
1. プラグイン設定で住所を入力
2. 「座標を取得」をクリック
3. プラグインはGoogle Maps Geocoding API（設定されている場合）またはOpenStreetMapをフォールバックとして使用

**注意:** 本番サイトでは、精度のためにGoogle Maps Geocoding APIキーを設定してください。

---

## Common Misconceptions / よくある誤解

### "Structured data guarantees better rankings"
### 「構造化データは順位向上を保証する」

**Reality / 実際:** Structured data doesn't directly affect rankings. It helps search engines *understand* your content and may trigger rich results, but quality content and other SEO factors remain primary.

**実際:** 構造化データは直接的に順位に影響しません。検索エンジンがコンテンツを*理解*するのを助け、リッチリザルトのトリガーになる可能性がありますが、質の高いコンテンツや他のSEO要因が引き続き主要です。

---

### "I need to add schema to every page"
### 「すべてのページにスキーマを追加する必要がある」

**Reality / 実際:** Not necessarily. Focus on pages where structured data adds value:
- Homepage (Organization/LocalBusiness)
- Articles/blog posts (Article)
- Products (Product)
- FAQ pages (FAQPage)
- Service pages (Service)

**実際:** 必ずしもそうではありません。構造化データが価値を追加するページに焦点を当ててください：
- ホームページ（Organization/LocalBusiness）
- 記事/ブログ投稿（Article）
- 商品（Product）
- FAQページ（FAQPage）
- サービスページ（Service）

---

### "More schema types = better results"
### 「スキーマタイプが多い = より良い結果」

**Reality / 実際:** Quality over quantity. Adding irrelevant or inaccurate schema can trigger Google penalties. Only use schema types that accurately describe your content.

**実際:** 量より質です。無関係または不正確なスキーマを追加すると、Googleのペナルティを引き起こす可能性があります。コンテンツを正確に説明するスキーマタイプのみを使用してください。

---

## Troubleshooting / トラブルシューティング

### Google's Rich Results Test shows errors
### GoogleのリッチリザルトテストでエラーがI表示される

**Common causes / 一般的な原因:**

1. **Missing recommended properties** - Fill in all fields shown in the LocalBusiness guide panel
   **推奨プロパティの欠落** - LocalBusinessガイドパネルに表示されているすべてのフィールドを入力してください

2. **Invalid URL format** - Ensure URLs start with `https://`
   **無効なURL形式** - URLが`https://`で始まることを確認してください

3. **Missing image** - Add a logo and/or storefront image
   **画像の欠落** - ロゴや店舗画像を追加してください

4. **Cached old data** - Clear your cache and re-test
   **古いデータのキャッシュ** - キャッシュをクリアして再テストしてください

---

### Schema doesn't appear on my site
### スキーマがサイトに表示されない

**Checklist / チェックリスト:**

1. ✅ Plugin is activated / プラグインが有効化されている
2. ✅ Company name is filled in / 会社名が入力されている
3. ✅ Cache is cleared (if using caching plugin) / キャッシュがクリアされている（キャッシュプラグイン使用時）
4. ✅ Theme doesn't have conflicting `wp_head` output / テーマに競合する`wp_head`出力がない
5. ✅ View page source and search for `application/ld+json` / ページソースを表示して`application/ld+json`を検索

---

### Admin notices about missing properties
### プロパティ欠落に関する管理者通知

**English:** The plugin includes a self-diagnostic validator that checks for Google-recommended properties. When fields are missing, you'll see admin notices. These are warnings, not errors—your schema will still output, but may not qualify for rich results.

**日本語:** プラグインには、Google推奨プロパティをチェックする自己診断バリデーターが含まれています。フィールドが欠落している場合、管理者通知が表示されます。これらは警告であり、エラーではありません—スキーマは引き続き出力されますが、リッチリザルトの対象にならない可能性があります。

---

## External Communication / 外部通信について

### License Validation (Pro Version) / ライセンス検証（Pro版）

When activating a Pro license, the following information may be sent to our license server:

Pro版ライセンス有効化時に、以下の情報がライセンスサーバーに送信される場合があります：

- License key / ライセンスキー
- Site URL (domain) / サイトURL（ドメイン）
- Plugin version / プラグインバージョン

**We do NOT collect or transmit / 収集・送信しない情報:**

- Post/page content / 投稿・ページのコンテンツ
- User personal data / ユーザーの個人情報
- Analytics or tracking data / アナリティクス・トラッキングデータ

**Note:** The free version does not make any external API calls for license validation.

**注意:** 無料版ではライセンス検証のための外部API通信は行いません。

---

## Further Resources / 関連リソース

- [Google Structured Data Documentation](https://developers.google.com/search/docs/appearance/structured-data)
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Full Reference](https://schema.org/)
- [Google LocalBusiness Guidelines](https://developers.google.com/search/docs/appearance/structured-data/local-business)
- [GitHub Repository](https://github.com/aivec/ai-search-schema)
- [Bug Report](https://github.com/aivec/ai-search-schema/issues/new?template=bug_report.yml)
