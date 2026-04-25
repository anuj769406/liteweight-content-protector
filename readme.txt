=== Liteweight Content Protector ===
Contributors: anujdhungana
Tags: content protection, copy protection, right click, disable copy, security
Requires at least: 6.3
Tested up to: 6.9
Stable tag: 1.0.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, selective content protection for posts and pages with performance-first loading.

== Description ==

Liteweight Content Protector helps deter casual content copying attempts while keeping frontend overhead low.

Features include:

- Disable copy actions (Ctrl/Cmd + C and copy event)
- Disable right-click, with optional allowance for standard navigation links
- Disable text selection and image drag behavior
- Disable common shortcuts (Ctrl/Cmd + U, S, P, and F12)
- Apply protection globally or only on selected pages
- Override behavior per post/page via meta box
- Optional bypass for logged-in users or selected roles

This plugin is intended to deter casual copying attempts. It cannot prevent all forms of content extraction.

== Installation ==

1. Upload the `liteweight-content-protector` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to Settings > Content Protection to configure restrictions.
4. Optional: open any post/page and use the Content Protection meta box for per-item override.

== Frequently Asked Questions ==

= Does this fully prevent content theft? =

No. It is a deterrent layer for casual copying and shortcut abuse, not a complete anti-scraping solution.

= Will this affect site performance? =

The plugin loads protection assets conditionally only where needed to minimize overhead.

== Changelog ==

= 1.0.5 =
- Improved admin and frontend standards compliance.
- Added stricter defaults and migration handling for right-click behavior and role bypass settings.
- Stability and security hardening updates.
