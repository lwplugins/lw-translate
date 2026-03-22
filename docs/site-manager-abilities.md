# LW Translate - LW Site Manager Abilities

LW Translate registers four abilities with the [LW Site Manager](https://github.com/lwplugins/lw-site-manager) Abilities API, enabling AI agents and REST API clients to manage WordPress translations programmatically.

## Category

`translate` - Translation management abilities

## Abilities

### `lw-translate/list-translations` (readonly)

Returns a list of installed translations and their current update status, comparing local files against the remote community repository.

**Input**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | No | Filter by type: `plugin`, `theme`, or `all` (default) |
| `status` | string | No | Filter by status: `up_to_date`, `update`, `not_installed`, or `all` (default) |

**Output**

```json
{
  "success": true,
  "translations": [
    {
      "slug": "woocommerce",
      "name": "WooCommerce",
      "type": "plugin",
      "status": "update",
      "file_count": 3,
      "local_date": "2024-01-15 10:30+0000"
    }
  ],
  "total": 1
}
```

**Status values**

| Value | Meaning |
|-------|---------|
| `up_to_date` | Local translation matches the remote version |
| `update` | A newer version is available in the remote repository |
| `not_installed` | Translation is available remotely but not yet installed locally |

---

### `lw-translate/get-options` (readonly)

Returns the current LW Translate plugin settings.

**Input:** none required

**Output**

```json
{
  "success": true,
  "options": {
    "locale": "hu_HU",
    "tone": "formal",
    "cache_ttl": 43200
  }
}
```

---

### `lw-translate/install-translation` (write)

Downloads and installs translation files for a specific plugin or theme from the remote community repository. Also serves as an update operation when a newer version is available.

**Input**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `slug` | string | Yes | Plugin or theme slug (e.g. `woocommerce`, `storefront`) |
| `type` | string | Yes | `plugin` or `theme` |

**Output**

```json
{
  "success": true,
  "message": "Translation installed for plugin woocommerce."
}
```

---

### `lw-translate/update-translations` (write)

Scans all installed plugins and themes, then updates every translation that has a newer version available in the remote repository.

**Input:** none required

**Output**

```json
{
  "success": true,
  "updated": ["plugin:woocommerce", "theme:storefront"],
  "failed": [],
  "total": 2
}
```

**Failed item shape**

```json
{
  "slug": "some-plugin",
  "type": "plugin",
  "message": "No translation files found for this item."
}
```

## Permission Requirements

All abilities require the `manage_options` capability (WordPress administrator).

## Notes

- Abilities are only registered when LW Site Manager is active. The integration is a no-op otherwise.
- The translation source is the [hellowpio/wordpress-translations](https://github.com/hellowpio/wordpress-translations) community repository on GitHub.
- Locale and tone are read from the active LW Translate settings at the time of each ability execution.
- After a successful install or update, the local comparison cache is cleared so subsequent `list-translations` calls reflect the new state.
