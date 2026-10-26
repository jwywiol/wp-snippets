# Elementor forms: drop submissions that skipped the page's JavaScript

## Problem

A contact form protected by a honeypot plugin still gets spam. The bot downloads the page, reads the honeypot's field name and value straight from the public script, and POSTs to `admin-ajax.php` without ever running the page. The honeypot passes, the lead lands in the CRM, and the notification e-mail goes out.

## Solution

Reject Elementor Pro form submissions that arrive without the `referrer` field, and answer them with the normal success message so the bot gets no signal that it was caught.

## Why it works

- Elementor's front-end form script adds `referrer` (the URL of the page with the form) to every submission. A real visitor always sends it, because a real visitor submits through that script.
- A bot posting directly to `admin-ajax.php` builds the request by hand. Bots replaying a generic form template leave the field out.
- `elementor_pro/forms/validation` runs before any "Actions After Submit" (collect submissions, e-mail, webhook, redirect). The request handler starts in the success state, so calling `$ajax_handler->send()` there returns Elementor's own success JSON and ends the request. Nothing is stored or sent.
- The value is only checked for presence, not matched against the site domain. It is the address of the page the form is on, which can differ legitimately (`www` vs. bare domain, a page opened through a translation proxy).

Tested on a site with Elementor Pro 4.3.2, using a form whose only action was *Collect Submissions*:

| Request | Response | Stored |
| --- | --- | --- |
| no `referrer`, valid fields (bot) | success message | nothing |
| no `referrer`, missing required field | the normal validation error | nothing |
| with `referrer`, missing required field (browser) | the normal validation error | nothing |

A submission that is invalid anyway gets the same validation error a person would see. The snippet only changes the outcome for otherwise valid submissions without the field.

## How to use

Add [`form-referrer-honeypot.php`](form-referrer-honeypot.php) to your child theme (`require_once` from `functions.php`) or to a small plugin. No settings.

To log blocked attempts:

```php
add_action( 'wpsnip_elementor_submission_blocked', function ( $settings ) {
	error_log( 'Blocked Elementor form submission: ' . ( $settings['form_name'] ?? '?' ) );
} );
```

## Notes

- This is a cheap filter, not proof that JavaScript ran: anyone writing a bot for your form can add a `referrer` field. It removes the low-effort spam that does not bother. Against targeted bots, people filling the form by hand, or headless browsers, add a server-verified challenge such as Cloudflare Turnstile.
- Because a dropped submission still gets a success message, it does not tip off the bot author that the field matters, which keeps the filter useful for longer.
- Test once after installing: submit the form normally and check that the submission arrives.
- Requires Elementor Pro with the Forms module. Tested with Elementor Pro 4.3.2.
