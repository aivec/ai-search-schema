=== Aivec AI Search Schema ===
Contributors: Aivec LLC
Tags: schema, seo, structured-data, ai-search, local-seo, breadcrumbs, faq, llms-txt
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

{{BODY}}

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
{{CHANGELOG}}

== Upgrade Notice ==
{{UPGRADE_NOTICE}}
