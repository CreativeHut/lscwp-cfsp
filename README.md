# LiteSpeed Cache CloudFlare Single Purge

Contributors: creativehut, caiobleggi
Tags: litespeed cache, cloudflare, purge, litespeed, cdn
Requires at least: 5.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Adds Cloudflare purge functionality to LiteSpeed Cache (LSCWP) when a post or page is updated or when using the "Clear this page - LS Cache".

## Description

**What this plugin does**

- When LSCWP purges **a post** (e.g., after updating or publishing), this plugin detects the event and identifies the affected URLs (the post permalink, the post type archive, related taxonomies and author archives), then sends a **selective purge** to Cloudflare.
- When you click **“Purge this page – LSCache”** in the admin bar, it mirrors that same purge in Cloudflare, targeting only that URL and any relevant related pages.
- It performs **no purge** if LSCWP is inactive or if Cloudflare credentials are not configured in *LiteSpeed Cache → CDN → Cloudflare*.  
- It **never runs a purge-all** on Cloudflare, because LSCWP already have this feature nativelly.

> **Native integration with LSCWP Cloudflare settings**  
> The plugin reads the Cloudflare credentials directly from the options saved by LSCWP on *LiteSpeed Cache → CDN → Cloudflare* page.  
> Supports both **Global API Key + Email** and **API Token (Bearer)** authentication methods.

**Why use this plugin**

- Prevents unnecessary and manually *purge all* operations on the CDN, reducing cache misses and maintaining a warm edge cache.
- Ensures proper purge order: the server cache (LiteSpeed) is cleared first, then Cloudflare is notified to invalidate only what changed.

**Requirements**

- The **LiteSpeed Cache for WordPress (LSCWP)** plugin must be active and with **Cache feature enabled**.
- Cloudflare credentials configured inside LSCWP (*LiteSpeed Cache → CDN → Cloudflare*).

## How it works

1. LSCWP triggers a purge event on a post or URL.  
2. **LSCWP Cloudflare Single Purge** listens for:
   - `litespeed_purged_post` (post purge)
   - `litespeed_purged_front` (single-page purge via “Purge this page – LSCache”)
3. The plugin collects relevant URLs:
   - The post permalink,
   - Post type archive (if applicable),
   - Associated taxonomy archive URLs,
   - Author archive.
4. It then calls the Cloudflare API `/zones/{ZONE}/purge_cache` with `files: [ ...urls ]`, batching up to 30 URLs per request, using:
   - **Bearer Token** (for API Token),
   - **X-Auth-Email + X-Auth-Key** (for Global API Key).

## Installation

1. Install and activate **LiteSpeed Cache**.
2. In **LiteSpeed Cache → CDN → Cloudflare API**, fill in your **Zone ID** and **API Token** *or* **Global API Key + Email**.
3. Upload and activate this plugin like any standard WordPress plugin.
4. Update or publish a post, or use “**Purge this page – LSCache**” to test Cloudflare’s selective purge.

## Frequently Asked Questions

### Do I need to configure anything in this plugin?
No, but in the LSCWP panel you do. It automatically reads your Cloudflare settings on *LiteSpeed Cache → CDN → Cloudflare*. So it must be configurated and both switches *Cloudflare API* and *Clear Cloudflare cache* must be enabled.

### Will it work if LSCWP is inactive?
No. It depends on LSCWP events and configuration. If LSCWP is disabled or Cloudflare credentials are missing, this plugin won’t run.

### Does it ever trigger a purge-all on Cloudflare?
No. It only performs selective purges of specific URLs according your actions.

### What is the purge order?
First, LSCWP clears the cache on the server (tags like `Po.{id}`, `URL.{path}`, etc.).  
**Then**, this plugin calls Cloudflare’s API to invalidate the same URLs at the edge.

### Does it support both API Token and Global API Key authentication?
Yes. The plugin auto-detects:
- **API Token (Bearer)** – a long alphanumeric string;
- **Global API Key + Email** – sent via `X-Auth-Email` and `X-Auth-Key` headers.

## Compatibility / Requirements

- **Hooks used**
  - `litespeed_purged_front`: added in **LSCWP v5.6** (Aug 15, 2023). 
  - `litespeed_purged_post`: available in the **5.x+ series**; confirmed in current builds. Use **LSCWP ≥ 5.6** to ensure full hook compatibility. 
  - `litespeed_purge_url`:  available on LSCWP API for programatically purge specific URL.
- **Cloudflare options in LSCWP**
  - LSCWP stores Cloudflare credentials as `litespeed.conf.cdn-cloudflare_*` options (e.g. `_key`, `_email`, `_zone`), a format introduced in 5.x and still present in **7.6.2**. 
- **Recommended minimum:** LSCWP **≥ 5.6**  
- **Tested with:** LSCWP **7.6.2** (current stable release) 

## Screenshots

1. No interface is required — the plugin is “set-and-forget.”  
   You can verify its actions in the **LSCWP debug log** or by checking Cloudflare’s cache purge logs.

## Changelog

= 1.1 = 
* Added litespeed_purge_url hook to improve plugin compatibility with purges made programatically.

= 1.0 =
* Initial public release.
* Added selective Cloudflare purge on:
  * `litespeed_purged_post` (post/page updates),
  * `litespeed_purged_front` (single-page purge).
* Auto-detection of authentication method (API Token or Global API Key).
* Batching of 30 URLs per Cloudflare API call.
* Fully dependent on LSCWP’s Cloudflare configuration options.

## Upgrade Notice ==

= 1.0 =
First stable release. Requires LSCWP ≥ 5.6 to support the `litespeed_purged_front` hook and ensure compatibility with Cloudflare option keys (`litespeed.conf.cdn-cloudflare_*`).

## Privacy ==
This plugin makes requests to the Cloudflare API to **invalidate cached URLs**.  
It does **not** collect or transmit any personal data.