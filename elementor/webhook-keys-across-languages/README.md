# Elementor form webhooks: the same keys in every language

## Problem

A site gets a second language with Polylang. The contact form is translated, and from that day the automation that receives the form webhook (Make, Zapier, n8n) breaks for submissions from the new language. The submissions screen in WordPress looks fine, so nobody notices until leads go missing.

The cause: with **Advanced Data** off, which is Elementor's default, the webhook body is keyed by **field labels** and by **translated meta titles**. In Polish, for example, "Date" becomes "Data" and "Page URL" becomes "Adres URL do strony". Translate the labels, and the keys change.

```text
default language:  { "Name": "Ann", "E-mail": "...", "Date": "October 24, 2026", ... }
translation:       { "Imię": "Ann", "E-mail": "...", "Data": "24 października 2026", ... }
```

## Solution

Hook into `elementor_pro/forms/webhooks/request_args`. For a form on a non-default-language page, rebuild the body as the default-language original of the same form would produce it.

## Why it works

- Polylang links the translated page to its original (`pll_get_post()`), and a duplicated Elementor form keeps its element ID and its field IDs (`custom_id`). That is enough to find the original form widget and read its labels.
- Meta titles and the date format are produced by `Form_Record::get_form_meta()` in the current locale. Calling it inside `switch_to_locale()` gives the default-language titles and values.
- `page_url` keeps its real value, so the language of a submission is still visible.
- The submissions database stores fields by ID and is not affected.

## Tested

Dry run on a test site with Elementor Pro 4.3.2 (default language Polish), with the filter applied to a `Form_Record` built for the translated page. Nothing was sent.

```text
without the snippet:  Name, E-mail address, Date, Time, Page URL
with the snippet:     Imię, Adres e-mail, Data, Czas, Adres URL do strony, form_id, form_name
                      (Page URL value: the translated page; Date value: "24 października, 2026")
```

## How to use

1. Add [`webhook-keys-across-languages.php`](webhook-keys-across-languages.php) to your child theme or a small plugin.
2. Keep the field IDs identical in every translation. Duplicating the page with Polylang does this.
3. Test without sending a real lead: submit the translated form to a request bin, or temporarily point the webhook at one, and compare its keys with a submission from the original language.

The simpler alternative is to enable **Advanced Data** in the webhook settings (keys become field IDs) and remap the scenario once. Use this snippet when the receiving side cannot be changed.

## Notes

- Also leave the date-format string untranslated in Polylang's string translations, or the "Date" value will still differ.
- Requires Elementor Pro and Polylang. The dry run above used stand-ins for Polylang's lookup functions.
