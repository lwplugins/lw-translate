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
- PHP 8.1+
- WordPress 6.0+

---

## Admin Pages

The plugin adds two pages under the **LW Plugins** menu:

### 1. Translations (`LW Plugins > Translate`)

The main page with a WP_List_Table showing all available translations.

**Toolbar:**
- Displays the current **tone** (Formal/Informal) and **locale** (e.g. hu_HU)
- **Refresh Cache** button to force-reload repository data from GitHub

**Table columns:**

| Column | Description |
|--------|-------------|
| Name | Plugin/theme display name and slug |
| Type | "Plugin" or "Theme" badge |
| Status | Green checkmark (up to date), orange arrow (update available), or dash (not installed) |
| Files | Number of translation files available remotely |
| Local Date | `PO-Revision-Date` from the locally installed .po file |
| Actions | Install / Update / Delete buttons |

**View filters (above the table):**
- **All** - Every translation available for your installed plugins/themes
- **Plugins** - Plugin translations only
- **Themes** - Theme translations only
- **Updates Available** - Translations where the remote version differs from local
- **Not Installed** - Translations available but not yet installed locally

**Search:** Use the search box to filter by plugin/theme name or slug.

**Sorting:** Click column headers (Name, Type, Status, Local Date) to sort.

**Bulk actions:**
1. Select items with checkboxes
2. Choose "Install/Update Selected" or "Delete Selected" from the dropdown
3. Click "Apply"

### 2. Translate Settings (`LW Plugins > Translate Settings`)

Settings page with the following options:

| Setting | Default | Description |
|---------|---------|-------------|
| **Tone** | Formal | Choose between formal and informal translation variants. Some repositories provide both (e.g. formal = polite form, informal = familiar form). |
| **Locale** | hu_HU | Target locale code. Determines which language folder to look for in the repository. |
| **Cache TTL** | 43200 (12h) | How long to cache the GitHub repository tree (in seconds). Minimum: 3600 (1h), maximum: 604800 (7 days). |

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
- **Local SHA**: Calculated from the local `.mo` file content using the same algorithm: `sha1("blob " + filesize + "\0" + content)`
- If SHAs match, the translation is **up to date**
- If they differ, an **update** is available
- If no local file exists, it shows as **not installed**

This approach avoids unnecessary downloads - only genuinely changed files trigger update notifications.

### File installation
When you click Install or Update:
1. The plugin downloads all translation files for that slug (`.mo`, `.po`, `.l10n.php`, `.json`)
2. Files are saved via `WP_Filesystem` to the standard WordPress language directory:
   - Plugins: `WP_LANG_DIR/plugins/{slug}-{locale}.mo`
   - Themes: `WP_LANG_DIR/themes/{slug}-{locale}.mo`
3. The comparison cache is cleared so the table reflects the new state

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
- You click the "Refresh Cache" button
- The tree cache TTL expires naturally

**Cache is also cleared when you change tone or locale in settings** (via the comparison transient key which includes both values).

---

## Troubleshooting

### No translations appear
- Check that you have plugins/themes installed that exist in the repository
- Click "Refresh Cache" to force a fresh API call
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
