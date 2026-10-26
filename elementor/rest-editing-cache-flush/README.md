# Editing Elementor pages through the REST API, and why the change does not show up

## Problem

You update an Elementor page from a script, through the standard REST API. The response is `200`, and reading the post back with `context=edit` shows the new content. The live page still shows the old version, with no page-cache plugin and no CDN in play.

## Solution

After writing `_elementor_data`, clear Elementor's own cache with `DELETE /wp-json/elementor/v1/cache`, sent as `POST ...?_method=DELETE`. Then re-read the post to confirm the stored value. [`elementor_rest.py`](elementor_rest.py) does the three steps in one command.

## Why it works

- An Elementor page lives in the `_elementor_data` post meta as a JSON string. Elementor exposes it in the REST API, so `GET /wp/v2/pages/{id}?context=edit` returns it and `POST /wp/v2/pages/{id}` with `{"meta": {"_elementor_data": "..."}}` writes it. No plugin, SSH or WP-CLI is needed, just an application password.
- Elementor caches rendered elements (`_elementor_element_cache`, 24-hour lifetime) and clears that cache when you save in the editor. A plain meta update through REST bypasses the editor, so the cache keeps serving the old markup until it expires.
- `DELETE /elementor/v1/cache` is the same "Clear Files & Data" action as in Elementor > Tools, and like it requires the `manage_options` capability. The script calls it once before writing, so a user without that right gets a 403 before anything changes.
- Some hosts silently drop HTTP `DELETE` requests and answer `200` with an empty body, so the cache is never cleared. WordPress honours `?_method=DELETE` on a `POST`, which goes through.
- The `POST` response echoes the payload you sent. A fresh `GET` is the only proof that the value was actually stored.

Reproduced on a clean install with Elementor 4.3.5: after a REST write, the stored heading was `SECOND EDIT` while the live page still printed the previous one. After the cache call, the live page showed the new heading.

## How to use

```bash
export WP_URL=https://example.com
export WP_USER=admin   # an administrator: the cache endpoint requires manage_options
export WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"

python elementor_rest.py get 42 > page-42.json   # edit the JSON (widget settings, text, IDs stay)
python elementor_rest.py put 42 page-42.json     # write, clear cache, verify
```

Use `--type posts` for posts, or `--type elementor_library` for templates. Keep a copy of the original JSON as a backup before `put`.

The same calls with curl:

```bash
curl -u "$WP_USER:$WP_APP_PASSWORD" "$WP_URL/wp-json/wp/v2/pages/42?context=edit&_fields=meta"
curl -u "$WP_USER:$WP_APP_PASSWORD" -X POST "$WP_URL/wp-json/elementor/v1/cache?_method=DELETE"
```

## Notes

- Write the JSON in Elementor's compact form (`separators=(",", ":")`, no ASCII escaping), as the script does.
- A page cache (LiteSpeed, WP Rocket, Cloudflare APO) and a persistent object cache are separate layers. If the page is still stale after this, purge those too.
- Needs Python 3.8+, standard library only.
