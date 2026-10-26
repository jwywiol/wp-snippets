# Make the hero image, not the logo, win the LCP race

## Problem

PageSpeed Insights flags the mobile hero image as the Largest Contentful Paint element and suggests "fetchpriority=high". The page was built with separate desktop and mobile hero images: one is hidden on small screens, the other on large ones. WordPress or Elementor gives `fetchpriority="high"` to the **desktop** copy, because it comes first in the HTML. On phones the image that is actually visible loads late, sometimes even lazily.

Fixing a second problem makes it worse: you give the SVG logo width and height to stop layout shift, and from then on the logo gets `fetchpriority="high"` instead of the hero.

## Solution

1. Preload the mobile hero with a `media` query, so only small screens fetch it, at high priority. Keep it eager.
2. Give the logo its **display** size, not its intrinsic size, so it stays below the 50,000 px² threshold.

## Why it works

- WordPress core picks one image per page for `fetchpriority="high"`: the first one whose `width × height` is at least 50,000 px² (filter `wp_min_priority_img_pixels`). Elementor's image loading optimisation turns the core logic off and re-applies the same rule to the final HTML through an output buffer, using the `width`/`height` attributes it finds there. Both choose by DOM order, not by what is visible.
- `<link rel="preload" as="image" media="(max-width: 767px)" fetchpriority="high">` with the same `imagesrcset`/`imagesizes` as the `<img>` makes phones start the download in the `<head>`, and the `<img>` reuses it. Desktops ignore it.
- An SVG logo exported at 2000×500 has an area of 1,000,000 px², so with its intrinsic size it "wins". Displayed at about 200 px wide, a size of 400×100 = 40,000 px² describes it well enough. That is the same aspect ratio, so there is no layout shift, and it stays under the threshold.

## How to use

Add [`hero-image-priority.php`](hero-image-priority.php) to your child theme and configure it:

```php
add_filter( 'wpsnip_hero_priority_config', function ( $config ) {
	$config['condition']     = 'is_front_page';          // where the hero is
	$config['mobile_image']  = 123;                      // attachment ID of the mobile-only hero
	$config['mobile_media']  = '(max-width: 767px)';     // the breakpoint that shows it
	$config['eager_images']  = array( 456 );             // other above-the-fold images
	$config['display_sizes'] = array( 78 => array( 400, 100 ) ); // SVG logo: display size, same ratio
	return $config;
} );
```

Verify in Chrome DevTools: *Network* (filter `Img`, *Priority* column) on a phone-sized viewport, and the LCP element in a Lighthouse mobile run.

## Notes

- `display_sizes` is applied in `wp_get_attachment_image_src`, before WordPress core picks the priority image, and again on the final attributes, for SVGs without file dimensions and for Elementor's optimiser, which reads the finished HTML.
- If you can, a single responsive `<img>` with `srcset`/`sizes`, or `<picture>` with art direction, avoids the two-image problem altogether. This snippet is for layouts you cannot rebuild.
- Tested on WordPress 7.1: preload, eager loading and the display size on the core image path. The 50,000 px² rule was checked in the source of WordPress core and Elementor 4.3.5.
