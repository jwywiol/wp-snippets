#!/usr/bin/env python3
"""Read and write Elementor page data through the WordPress REST API.

    export WP_URL=https://example.com WP_USER=admin WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
    python elementor_rest.py get 42 > page-42.json        # widget tree as JSON
    python elementor_rest.py put 42 page-42.json          # write, clear Elementor's cache, verify

Needs only the Python standard library and an administrator's application
password (Users > Profile > Application Passwords): Elementor's cache endpoint
requires the manage_options capability. Use type "posts" or a custom post
type's REST base with --type.
"""
import argparse
import base64
import json
import os
import sys
import urllib.error
import urllib.request


def request(method, route, body=None):
    url = os.environ["WP_URL"].rstrip("/") + "/wp-json" + route
    token = base64.b64encode(f'{os.environ["WP_USER"]}:{os.environ["WP_APP_PASSWORD"]}'.encode()).decode()
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method, headers={
        "Authorization": f"Basic {token}",
        "Content-Type": "application/json",
        # Some firewalls block the default Python user agent.
        "User-Agent": "Mozilla/5.0 (elementor_rest.py)",
    })
    try:
        with urllib.request.urlopen(req, timeout=60) as res:
            raw = res.read().decode()
            return json.loads(raw) if raw else None
    except urllib.error.HTTPError as err:
        sys.exit(f"{method} {route} -> HTTP {err.code}: {err.read().decode()[:300]}")


def get_data(post_id, post_type="pages"):
    """Returns the decoded _elementor_data tree of a post."""
    post = request("GET", f"/wp/v2/{post_type}/{post_id}?context=edit&_fields=meta")
    raw = (post.get("meta") or {}).get("_elementor_data")
    if not raw:
        sys.exit(f"No _elementor_data on {post_type}/{post_id} (not an Elementor page, or the meta is not in REST).")
    return json.loads(raw)


def put_data(post_id, tree, post_type="pages"):
    """Writes the tree back, clears Elementor's cache and re-reads it to verify."""
    # Clear once before writing: it fails fast (403) when the user cannot clear
    # the cache, before the page has been changed.
    request("POST", "/elementor/v1/cache?_method=DELETE")

    # Same compact form Elementor stores itself.
    encoded = json.dumps(tree, ensure_ascii=False, separators=(",", ":"))
    request("POST", f"/wp/v2/{post_type}/{post_id}", {"meta": {"_elementor_data": encoded}})

    # Without this the live page keeps the old markup (element cache, 24 h).
    # POST + _method=DELETE because some hosts silently drop real DELETE requests.
    request("POST", "/elementor/v1/cache?_method=DELETE")

    # The write response echoes what was sent; trust a fresh read instead.
    if get_data(post_id, post_type) != tree:
        sys.exit("Verification failed: stored data differs from what was written.")


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("command", choices=["get", "put"])
    parser.add_argument("post_id", type=int)
    parser.add_argument("file", nargs="?", help="JSON file for put")
    parser.add_argument("--type", default="pages", help="REST base: pages, posts, elementor_library, ...")
    args = parser.parse_args()

    if args.command == "get":
        json.dump(get_data(args.post_id, args.type), sys.stdout, ensure_ascii=False, indent=2)
        print()
    else:
        if not args.file:
            parser.error("put needs a JSON file")
        with open(args.file, encoding="utf-8") as fh:
            put_data(args.post_id, json.load(fh), args.type)
        print(f"Saved {args.type}/{args.post_id}, cache cleared, verified.")


if __name__ == "__main__":
    main()
