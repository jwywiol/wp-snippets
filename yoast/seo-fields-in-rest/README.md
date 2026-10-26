# Yoast SEO: write the SEO title and meta description through the REST API

## Problem

An SEO audit lists 80 pages with missing or duplicate meta descriptions. You have the new texts in a spreadsheet and want to apply them with a script. `GET /wp/v2/pages/{id}?context=edit` does not show Yoast's fields at all, and writing them through `meta` is silently ignored.

The tempting workaround is a `wpseo_metadesc` filter with the texts hard-coded in PHP. It works until an editor changes a description in the Yoast box and nothing happens on the site, because the filter overrides it.

## Solution

Register Yoast's existing meta keys for REST, with an `auth_callback` that allows users who can edit the post. Then write the values as ordinary post meta, the same fields an editor fills in by hand.

## Why it works

- Yoast stores the values in post meta: `_yoast_wpseo_title` and `_yoast_wpseo_metadesc`. It does not call `register_post_meta()` with `show_in_rest` for them, so the REST API leaves them out.
- Keys starting with `_` are protected meta. `register_post_meta()` with an `auth_callback` is the supported way to expose a protected key, limited to users with `edit_post` on that post.
- Nothing about Yoast's behaviour changes. It reads the same fields, and editors see the new values in the Yoast box, where they can keep changing them.

Tested with Yoast SEO 28.7: after a REST write, the post rendered `<title>REST title - Northwind Studio</title>` and the new `<meta name="description">`. An unauthenticated request does not see the fields.

## How to use

1. Add [`seo-fields-in-rest.php`](seo-fields-in-rest.php) to your child theme, a small plugin, or a code-snippets plugin.
2. Write with an application password:

```bash
curl -u "editor:xxxx xxxx xxxx xxxx xxxx xxxx" -X POST \
  -H "Content-Type: application/json" \
  -d '{"meta":{"_yoast_wpseo_title":"Pricing %%sep%% %%sitename%%","_yoast_wpseo_metadesc":"Plans from 9 EUR a month. No setup fee."}}' \
  https://example.com/wp-json/wp/v2/pages/42
```

Yoast variables such as `%%sep%%` and `%%sitename%%` work as in the Yoast box. When the bulk update is done, you can remove the snippet; the values stay.

## Notes

- **Social previews.** If a page already has a reviewed text in Yoast's *Social* tab (`_yoast_wpseo_opengraph-description`), consider reusing it as the meta description and clearing the Social fields. Yoast falls back to the SEO title and description when they are empty, so you end up with one text to maintain instead of two diverging ones.
- Registered for every public post type that is in the REST API and supports `custom-fields` (posts and pages do). The REST API leaves `meta` out for post types without that support; add it with `add_post_type_support( 'your_cpt', 'custom-fields' )` if needed.
