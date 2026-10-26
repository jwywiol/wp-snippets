# Load a plugin's CSS and JS only on the posts that use it

## Problem

A plugin you need on a handful of posts (copy-to-clipboard buttons, a table library, a gallery) enqueues its stylesheets and scripts on every page of the site. Its settings have no "load only here" option, and a hard-coded list of post IDs in `functions.php` goes stale the day someone adds a new post.

## Solution

Add a private, checkbox-style taxonomy, **Page assets**. Editors tick a feature on the posts that need it. Everywhere else, the feature's handles are dequeued just before WordPress prints them.

## Why it works

- A taxonomy is the editor-friendly flag: it shows up as a panel in the block editor and the classic editor, and as a column in the post list. With `hierarchical => true` it renders as checkboxes, so there is nothing to mistype. With `public => false` and `rewrite => false` it has no archive, no URL and no sitemap entry.
- Dequeuing at the last moment before printing catches handles that plugins and page-builder widgets enqueue late, after `wp_enqueue_scripts`, including the ones printed in the footer: `wp_print_styles` and `wp_print_scripts` at the highest priority, `wp_print_footer_scripts` at 9, just before core prints the footer at 10.
- Matching by handle prefix (`ctc-`) covers a plugin's whole family of handles without listing each one.
- Page-builder previews (Elementor, Beaver Builder, Oxygen) are skipped, so widgets keep working while you edit.

Tested on WordPress 7.1 with test handles: untagged pages lose them, including one enqueued late on `wp_print_styles`; tagged pages keep them.

## How to use

1. Add [`plugin-assets-per-page.php`](plugin-assets-per-page.php) to your child theme or a small plugin.
2. Find the plugin's handles: in the page source they appear as `id="handle-css"` and `id="handle-js"`, or use Query Monitor's *Scripts* and *Styles* panels.
3. Declare the feature:

```php
add_filter( 'wpsnip_page_asset_features', function ( $features ) {
	return array(
		'copy-to-clipboard' => array( 'label' => 'Copy to clipboard', 'prefixes' => array( 'ctc-' ) ),
		'data-tables'       => array( 'label' => 'Data tables', 'prefixes' => array( 'tablepress-', 'datatables' ) ),
	);
} );
```

4. Open wp-admin once: the terms are created automatically. Tick them on the posts that need them.
5. For custom post types, filter `wpsnip_page_assets_post_types`.

## Notes

- Archives and the home page never get the assets, because only single posts can be tagged. If a feature is needed on an archive, keep it out of the list.
- Check every tagged post after enabling: some plugins split one feature across handles with different prefixes.
