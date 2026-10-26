# Run WP-Cron from a URL-based host cron, with a secret key

## Problem

On a busy site you turn off WordPress' visitor-triggered cron (`DISABLE_WP_CRON`) and want a real scheduler instead. The hosting panel only offers "call this URL every N minutes", using wget. But `wp-cron.php` is publicly reachable (anyone can trigger heavy jobs repeatedly), and on many hardened sites it is already blocked in `.htaccess`, together with the wget user agent. The panel cron then gets a 403 and scheduled tasks silently stop: scheduled posts, WooCommerce cleanups, log rotation.

## Solution

Let `wp-cron.php` through only when the URL carries a secret key, and point the panel cron at that URL.

## Why it works

- `mod_rewrite` checks the query string before PHP starts. Requests without the exact `cron_key=...` get a 403 from Apache and cost nothing. The path pattern also covers `/wp-cron.php/anything`, which Apache may hand to the same script as PATH_INFO.
- WordPress ignores unknown query parameters, so `wp-cron.php?doing_wp_cron&cron_key=...` runs normally.
- With `DISABLE_WP_CRON`, WordPress never calls `wp-cron.php` itself, so nothing internal depends on the URL being open.
- If the same `.htaccess` blocks bot user agents, `wp-cron.php` has to be exempted from that rule, because the host's cron is wget.

Tested on Apache 2.4: no key, 403; wrong key, 403; `/wp-cron.php/x` without key, 403; correct key, 200.

## How to use

1. In `wp-config.php`:

   ```php
   define( 'DISABLE_WP_CRON', true );
   ```

2. Generate a key, for example `openssl rand -hex 16`, and paste [`wp-cron-key.htaccess`](wp-cron-key.htaccess) above `# BEGIN WordPress`, with `CHANGE_ME` replaced by the key.
3. In the hosting panel, schedule every 5 minutes:

   ```text
   https://example.com/wp-cron.php?doing_wp_cron&cron_key=YOUR_KEY
   ```

4. Check it runs: *Tools > Site Health* stops reporting late scheduled events, and WooCommerce's *Status > Scheduled Actions* list empties regularly. The access log shows the wget hits with status 200.

## Notes

- If the panel can run commands, a CLI cron (`php /path/to/wp-cron.php` or `wp cron event run --due-now`) is better: no HTTP, no key. Use this snippet when the panel only accepts URLs.
- Treat the key like a password. Keep it out of the repository; the file in this folder holds only the placeholder.
