=== Aivec AI Search Schema ===
Contributors: Aivec LLC
Tags: schema, structured-data, ai-search, local-seo, llms-txt
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-focused structured data (JSON-LD) for LocalBusiness, breadcrumbs, FAQ extraction, and llms.txt.

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

== External Services ==

This plugin can connect to external services only when you use specific features.

= Google Maps Geocoding API (optional) =
Used to convert the address you enter into latitude/longitude when you click "Fetch coordinates".
Data sent: the address fields you entered, your site locale, and your site URL in the User-Agent header.
Service provided by Google. Terms: https://cloud.google.com/maps-platform/terms | Privacy: https://policies.google.com/privacy

= OpenStreetMap Nominatim (optional fallback) =
Used only when no Google Maps API key is configured and you click "Fetch coordinates".
Data sent: the address fields you entered, the admin email (if available), and your site URL in the User-Agent header.
Service provided by OpenStreetMap (Nominatim). Usage policy: https://operations.osmfoundation.org/policies/nominatim/ | Privacy: https://osmfoundation.org/wiki/Privacy_Policy

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
