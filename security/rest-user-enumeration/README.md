# Block REST user enumeration without breaking application passwords

## Problem

A security scan reports that `GET /wp-json/wp/v2/users` lists your users to anyone, with their slugs (usually the login). The common fix found online is an `.htaccess` rule that forbids `^/wp-json/wp/v2/users`. Weeks later:

- creating an application password in *Users > Profile* fails with an empty red error, because it calls `/wp-json/wp/v2/users/me/application-passwords`;
- the author picker in the block editor breaks, because it calls `/wp-json/wp/v2/users?who=authors`;
- the scan still passes `?rest_route=/wp/v2/users`, which the rule never matched.

## Solution

Block the users routes **for anonymous requests only**, inside WordPress, with `rest_pre_dispatch`. Optionally, add a narrower `.htaccess` rule as a first layer, one that lets authenticated requests through.

## Why it works

- `rest_pre_dispatch` runs inside the REST server after authentication (cookie and nonce, or application password). `is_user_logged_in()` is reliable there, so logged-in editors and API clients keep full access, and anonymous visitors get `401`.
- The REST server resolves `/wp-json/wp/v2/users` and `?rest_route=/wp/v2/users` (also URL-encoded) to the same route, so one PHP check covers every spelling. A path-based rule has to list them all.
- `.htaccess` cannot know who is logged in. The optional rule therefore only blocks requests that carry neither an `Authorization` header nor a WordPress login cookie, and it matches exactly `/users` and `/users/{id}`, so `/users/me/...` stays reachable. A fake cookie gets past it, which is why the PHP check stays the real gate.

Tested on WordPress 7.1 with the optional `.htaccess` layer active:

| Request | Result |
| --- | --- |
| anonymous `/wp-json/wp/v2/users`, `/users/1/` | 403 (server) |
| anonymous `?rest_route=/wp/v2/users`, `?rest_route=%2Fwp%2Fv2%2Fusers%2F1` | 403 (server) |
| anonymous with a fake login cookie | 401 (PHP) |
| application password: `/users?who=authors`, list and create application passwords | 200 / 201 |

Without the snippet, the same anonymous request returns the user list with a `200`.

## How to use

1. Add [`rest-user-enumeration.php`](rest-user-enumeration.php) to your child theme or a small plugin.
2. Optional: copy [`users-endpoint.htaccess`](users-endpoint.htaccess) above the `# BEGIN WordPress` block. If you already have a broad `^/wp-json/wp/v2/users` rule, replace it with this one.

## Notes

- Other places that reveal usernames: author archives (`/?author=1` redirects to `/author/<slug>/`) and the users sitemap (`/wp-sitemap-users-1.xml`, or Yoast's `author-sitemap.xml`). Handle them separately if they matter to you.
- Hiding usernames is defence in depth. Strong passwords, 2FA and login rate limiting do the real work.
