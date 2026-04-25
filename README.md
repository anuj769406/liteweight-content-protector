# Liteweight Content Protector

A performance-first WordPress plugin that adds selective protection against casual content copying and common browser shortcuts.

## Plugin Details

- Author: Anuj Dhungana
- Required WordPress version: 6.3+
- Required PHP version: 7.4+

## Features

- Disable copy actions (Ctrl/Cmd + C and copy event)
- Disable right-click by default (strict mode), with optional exception for standard navigation links
- Disable text selection via CSS
- Disable image drag/save behavior
- Disable common shortcuts (Ctrl/Cmd + U, S, P, F12 and inspect/devtools combos best effort)
- Apply mode: Global (all pages) or Specific (selected pages only)
- Per-post and per-page control via meta box
- Global exclusions for specific pages
- Debug/bypass mode for logged-in users or selected roles (role bypass is opt-in)
- Optional small alert popup for blocked actions

## Installation

1. Copy the folder `liteweight-content-protector` into `wp-content/plugins/`.
2. Activate **Liteweight Content Protector** from **Plugins** in WordPress admin.
3. Open **Settings > Content Protection** to configure restrictions.
4. (Optional) Open any post/page editor and use the **Content Protection** meta box to override global behavior.

## Notes

- This plugin is intended to deter casual copying attempts only.
- Protection is loaded conditionally to minimize performance impact.
- Avoid enabling aggressive restrictions on pages that require full browser interaction.

## Translation Support

- Text domain: `liteweight-content-protector`
- Translation template: `languages/liteweight-content-protector.pot`
- Place compiled translation files (`.mo`/`.po`) in the `languages` folder.

## Uninstall Cleanup

- On uninstall, the plugin removes:
  - Global plugin settings option: `lwcp_settings`
  - Per-post/page meta overrides: `_lwcp_protection_mode`
