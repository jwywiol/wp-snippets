# Yoast SEO: author credentials in the schema graph

## Problem

You want articles to carry author expertise in their structured data: job title, areas of expertise, the organisation the author works for. You hook into `wpseo_schema_graph`, look for `$data['@graph']`, and nothing appears in the output. There is no error.

## Solution

Treat the filter argument as a flat list of graph pieces. Find the author's `Person` node by the ID Yoast itself generates, and merge the extra properties into it. The values come from two fields on the user profile, so editors maintain them in wp-admin and none of the copy lives in code.

## Why it works

- In Yoast's schema generator, `wpseo_schema_graph` runs on the list of pieces (`[ {WebPage}, {Person}, {Organization}, ... ]`). The `{"@context": "https://schema.org", "@graph": [...]}` wrapper is added after the filter. Code that expects the wrapper silently does nothing.
- `YoastSEO()->helpers->schema->id->get_user_schema_id( $user_id, $context )` returns the exact `@id` Yoast uses for an author, so the right `Person` is enriched even when a page contains several.
- Merging into Yoast's node, instead of printing a second JSON-LD script, keeps one consistent graph. `worksFor` points at Yoast's own `#organization` node by `@id` rather than duplicating it.

Output on a test post (Yoast SEO 28.7):

```json
{
  "@type": "Person",
  "@id": "https://example.com/#/schema/person/ad84...",
  "name": "Jane Doe",
  "jobTitle": "Lead Developer",
  "knowsAbout": ["WordPress", "Web performance", "Accessibility"],
  "worksFor": { "@id": "https://example.com/#organization" }
}
```

## How to use

1. Add [`schema-graph-author.php`](schema-graph-author.php) to your child theme or a small plugin.
2. Fill **Job title** and **Areas of expertise** under *Users > Profile > Author details*.
3. Check a post with the [Schema Markup Validator](https://validator.schema.org/).

To add more properties, filter the field list:

```php
add_filter( 'wpsnip_author_schema_fields', function ( $fields ) {
	$fields['wpsnip_alumni_of'] = array( 'label' => 'Alumni of', 'property' => 'alumniOf' );
	return $fields;
} );
```

## Notes

- `worksFor` is added only when Yoast outputs an Organization node. Yoast does that when *Site representation* is set to an organisation with a name and a logo.
- The same pattern (iterate, match by `@type` or `@id`, modify, return the list) works for any other node, for example adding `reviewedBy` to the WebPage node.
