=== AI Search Schema ===
Contributors: Aivec LLC
Tags: schema, seo, structured-data, local-seo, breadcrumbs, faq, aeo, llms-txt
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

{{BODY}}

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
{{CHANGELOG}}

== Upgrade Notice ==
{{UPGRADE_NOTICE}}
