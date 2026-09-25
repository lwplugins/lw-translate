# LW Translate - User Guide

## Overview

LW Translate lets you install and manage community WordPress translations directly from your admin dashboard. It connects to GitHub-hosted translation repositories (e.g. [hellowpio/wordpress-translations](https://github.com/hellowpio/wordpress-translations)) and compares available translations with your installed plugins and themes.

---

## Installation

### Manual
1. Download the latest release ZIP from GitHub
2. Upload to `wp-content/plugins/lw-translate/`
3. Activate in **Plugins** menu

### Composer
```bash
composer require lwplugins/lw-translate
```

### Requirements
- PHP 8.0+
- WordPress 6.0+

---

## Admin Page

The plugin adds one page under the **LW Plugins** menu: `LW Plugins > Translate`. It has two sections in the side navigation (links work with `#translations` / `#general` or `?tab=general`).

### 1. Translations

A table of every installed plugin and theme that has a folder in the repository for the selected tone and locale.

**Toolbar:**
- The current **tone** (Formal/Informal) and **locale** (e.g. hu_HU), with a link to the repository
- How long ago the repository listing was fetched
- **Refresh** to drop the cached listing and comparison and fetch fresh data from GitHub

**Warnings:** a GitHub error (for example an exhausted rate limit, with the wait time) or an incomplete ("truncated") repository listing is shown above the table instead of an empty list.

**Table columns:**

| Column | Description |
|--------|-------------|
| Name | Plugin/theme display name, slug and a link to its repository folder |
| Type | "Plugin" or "Theme" badge |
| Status | Up to date, Update available or Not installed |
| Files | Number of installable translation files in the repository |
| Local Date | `PO-Revision-Date` from the locally installed .po file |
| Actions | Install / Update / Delete buttons |

**View chips:** All, Plugins, Themes, Updates available, Not installed — each with its count.

**Search:** filters by plugin/theme name or slug. **Sorting:** click a column header.

**Bulk actions:** tick rows, then **Install/Update selected** or **Delete selected** (deleting asks first). Items are sent in batches of 10. After every action a result list shows what happened to each item.

Installing, updating, deleting and refreshing need the `install_languages` capability. WordPress denies it when `DISALLOW_FILE_MODS` is set and, on multisite, to everyone but super admins; such users see the list read-only.

### 2. General

| Setting | Default | Description |
|---------|---------|-------------|
| **Tone** | Formal | Choose between formal and informal translation variants. |
| **Locale** | hu_HU | Target locale. Only the locales the repository offers can be chosen. |
| **Cache lifetime** | 43200 (12 hours) | How long the repository listing is cached, in seconds: 3600 (1 hour) to 604800 (7 days). |

Save with the top bar button or Cmd/Ctrl+S. A save is all or nothing: if any field is invalid, nothing is stored and the field shows why.

---

## How It Works

### Translation discovery
1. The plugin fetches the full file tree from the GitHub repository using the [Trees API](https://docs.github.com/en/rest/git/trees)
2. It parses the tree for files matching the pattern: `{tone}/{plugins|themes}/{locale}/{slug}/`
3. It cross-references this with your installed plugins (`get_plugins()`) and themes (`wp_get_themes()`)
4. Only translations for **installed** items are shown

### Update detection (SHA comparison)
The plugin uses git blob SHA hashes to detect changes:
- **Remote SHA**: Provided by the GitHub Trees API for each file
- **Local SHA**: Calculated from each local file (`.mo`, `.po` and script translation `.json`) using the same algorithm: `sha1("blob " + filesize + "\0" + content)`
- If every file matches, the translation is **up to date**
- If any file differs or is missing, an **update** is available
- If none of the files exists locally, it shows as **not installed**

This approach avoids unnecessary downloads - only genuinely changed files trigger update notifications.

### File installation
When you click Install or Update:
1. The plugin downloads the item's `{slug}-{locale}.mo`, `.po` and `{slug}-{locale}-{md5}.json` files and checks each against its blob SHA; the fast `.l10n.php` file is generated locally from the `.mo` (never downloaded)
2. Files are saved via `WP_Filesystem` to the standard WordPress language directory:
   - Plugins: `WP_LANG_DIR/plugins/{slug}-{locale}.mo`
   - Themes: `WP_LANG_DIR/themes/{slug}-{locale}.mo`
3. The comparison cache is cleared so the table reflects the new state (also after WP-CLI install/delete)

### Repository structure
The plugin expects this directory structure in the GitHub repository:

```
formal/
  plugins/
    hu_HU/
      akismet/
        akismet-hu_HU.mo
        akismet-hu_HU.po
      woocommerce/
        woocommerce-hu_HU.mo
        ...
  themes/
    hu_HU/
      flavor/
        flavor-hu_HU.mo
        ...
informal/
  plugins/
    hu_HU/
      ...
```

---

## Caching

| Transient | Default TTL | Content |
|-----------|-------------|---------|
| `lw_translate_tree_cache` | 12 hours (configurable) | Full GitHub tree data |
| `lw_translate_compare_{locale}_{tone}` | 1 hour | Comparison results (TranslationItem array) |

**Cache is automatically cleared when:**
- A translation is installed, updated, or deleted
- You click the "Refresh" button (or run `wp lw-translate refresh`)
- The tree cache TTL expires naturally

**Cache is also cleared when you change tone or locale in settings** (via the comparison transient key which includes both values).

---

## Troubleshooting

### No translations appear
- Check that you have plugins/themes installed that exist in the repository
- Click "Refresh" to force a fresh API call, and read the warning above the table if there is one
- Verify your locale setting matches the repository structure (e.g. `hu_HU`)

### GitHub API rate limit
The GitHub API allows 60 requests/hour for unauthenticated requests. The plugin caches aggressively to stay well within this limit. If you hit the limit, wait an hour or increase the Cache TTL.

### Translations not loading in WordPress
- Verify the `.mo` files are in the correct directory (`WP_LANG_DIR/plugins/` or `WP_LANG_DIR/themes/`)
- Check that your WordPress site locale matches the translation locale (Settings > General > Site Language)
- Some plugins load translations from their own directory - these may not pick up files from `WP_LANG_DIR`

### Permission errors during install
The plugin uses `WP_Filesystem` for file operations. If your server requires FTP credentials, WordPress will prompt for them. Ensure `WP_LANG_DIR` is writable by the web server.

---

## Uninstall

When you delete the plugin through WordPress:
- The `lw_translate_options` option is removed
- All `lw_translate_tree_cache` and `lw_translate_compare_*` transients are cleaned up
- **Installed translation files are NOT removed** (they remain in `WP_LANG_DIR`)
