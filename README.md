# WordPress snippets

Short, tested fixes for WordPress problems that are easy to run into and hard to diagnose. Each one comes with the explanation I wish I had found when I first hit the problem.

Every folder has the same layout: **problem → solution → why it works → how to use**, plus the code.

## Index

| Snippet | Problem it solves | Needs |
| --- | --- | --- |
| **Elementor** | | |
| [REST editing and cache flush](elementor/rest-editing-cache-flush/) | A page edited through the REST API keeps showing the old content | Elementor, Python 3 |
| [Webhook keys across languages](elementor/webhook-keys-across-languages/) | Form webhooks send different keys from translated pages, so Make or Zapier breaks | Elementor Pro, Polylang |
| [Referrer honeypot](elementor/form-referrer-honeypot/) | Bots post straight to `admin-ajax.php` and bypass the honeypot | Elementor Pro |
| **Yoast SEO** | | |
| [Author credentials in the schema graph](yoast/schema-graph-author/) | `wpseo_schema_graph` edits silently do nothing | Yoast SEO |
| [SEO fields in the REST API](yoast/seo-fields-in-rest/) | Meta descriptions cannot be written by a script | Yoast SEO |
| **Performance** | | |
| [Hero image priority](performance/hero-image-priority/) | The mobile hero (or the logo) loses the `fetchpriority` race | none |
| [Plugin assets per page](performance/plugin-assets-per-page/) | A plugin loads its CSS and JS on every page | none |
| **Security** | | |
| [REST user enumeration](security/rest-user-enumeration/) | `/wp/v2/users` leaks usernames; the usual `.htaccess` fix breaks application passwords | none |
| **Server** | | |
| [WP-Cron with a secret key](server/wp-cron-secret-key/) | URL-based host cron gets a 403, or `wp-cron.php` is open to anyone | Apache |
| **JavaScript** | | |
| [Vimeo events without the SDK](javascript/vimeo-events-without-sdk/) | `timeupdate` and `ended` never arrive over `postMessage` | none |

## How these were checked

- Every snippet was tested before publishing: on a clean local install (WordPress 7.1.3, PHP 8.3, Elementor 4.3.5, Yoast SEO 28.7, Apache 2.4), the Elementor Pro snippets on a test site (Elementor Pro 4.3.2), and the Vimeo one against the live player. Each README says how.
- PHP follows the WordPress Coding Standards (`phpcs.xml.dist`). CI runs PHP lint, PHPCS, and syntax checks for the Python and JavaScript files.

## Using a snippet

- PHP: put the file in a child theme and `require_once` it from `functions.php`, wrap it in a small plugin, or paste it into a code-snippets plugin. All functions use the `wpsnip_` prefix, so they will not collide with your code.
- `.htaccess`: paste above the `# BEGIN WordPress` block, which WordPress may rewrite.
- Read the *Notes* section first; it lists the limits.

## License

[MIT](LICENSE). The snippets only use public hooks of WordPress and of the plugins named above and contain none of their code, so you can paste them into GPL or proprietary projects alike.

---

## Po polsku

Zbiór krótkich, przetestowanych rozwiązań problemów z WordPressem, Elementorem i Yoast SEO, na które łatwo trafić, a trudno je zdiagnozować. README każdego folderu ma układ: problem → rozwiązanie → dlaczego działa → jak użyć, a sekcja testów opisuje, jak dane rozwiązanie sprawdziłem. Licencja MIT.
