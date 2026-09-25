# Changelog

## [1.2.1] - 2026-09-25

### Fixed
- `Requires at least` raised to WordPress 6.6: the React settings screen needs the `react-jsx-runtime` script that core registers from 6.6, so on older versions the page stayed blank without an error (verified on 6.5 and 6.6)

## [1.2.0] - 2026-09-25

### Added
- New Translate screen built with WordPress components, like the other LW plugins: side navigation, save bar with a Cmd/Ctrl+S shortcut, loading skeletons and a mobile layout.
- The translations table has search, view filters with counts (All, Plugins, Themes, Updates available, Not installed), sortable columns and a result list showing what happened to each item.
- Bulk "Install/Update selected" and "Delete selected" work; items are sent in batches and deleting asks first.
- GitHub errors and incomplete repository listings are shown above the table instead of "No items found"; an exhausted rate limit says when to try again.
- The toolbar shows the source repository, how long ago the listing was fetched and a Refresh button.
- Admin REST API under `lw-translate/v1/admin/` for settings and translations. Reading needs manage_options; installing, deleting and refreshing need install_languages.

### Changed
- A cache lifetime outside 1 hour–7 days is rejected with a message on the admin screen instead of being adjusted silently.
- The old settings form, list table, admin.js/admin.css, the AJAX endpoints and the unused TranslationsPage and FileMatcher classes were removed.

### Fixed
- The list no longer stays stale after an install or delete when a persistent object cache (Redis, Memcached) is active, or after a WP-CLI install/delete.
- A theme with the same folder name as a plugin now shows up in the list.
- Updates are detected for every translation file (.mo, .po, .json), not only the .mo.
- Delete reports failures honestly and removes only the files the repository lists for the item plus the generated .l10n.php; language-pack files it never installed are left alone. If GitHub can't be reached, nothing is deleted and the reason is shown. WP-CLI `delete --all` counts failures.
- Plugins and themes whose repository folder holds no installable file no longer appear with an Install button that always fails.
- The locale is validated on every save, including `wp lw-translate settings set`.
- The settings screen no longer needs the mbstring PHP extension.

## [1.1.4] - 2026-09-25

### Security
- Translation files that execute as PHP (.l10n.php) are no longer downloaded; WordPress's own converter generates them from the .mo file.
- On update, translation PHP files (.l10n.php) downloaded by earlier versions are regenerated from their .mo files, or removed if that isn't possible.
- Only the translation files that belong to the plugin or theme being installed are written (`{slug}-{locale}.mo`, `.po` and script `.json` files). Anything else in the repository folder is ignored and listed in the result.
- Every downloaded file is checked against the repository's checksum before anything is written. If one file doesn't match, nothing is installed for that item.
- Installing, updating and deleting translations now requires the "install languages" permission. On multisite only network admins can do it, and nothing can be changed when file changes are disabled on the site (DISALLOW_FILE_MODS).

### Fixed
- The cache duration is kept between 1 hour and 1 week, and the tone setting only accepts formal or informal, also when set from WP-CLI. A cache duration of 0 could store the whole translation list permanently in the database.

## [1.1.3] - 2026-09-25

### Fixed
- Notices from themes and other plugins (for example a theme's purchase-code or recommended-plugins notice) could show on the LW Translate screen. They are now kept off every LW Plugins screen, whatever their markup.

## [1.1.2] - 2026-09-06

### Fixed
- The release package and the Composer/Packagist dist no longer ship tests, docs or development configuration (`.gitattributes` export-ignore plus unified release excludes). A hosting malware scanner had flagged a unit-test fixture on a customer site

## [1.1.1] - 2026-08-20

### Changed
- Tested up to WordPress 7.1.

## [1.1.0] - 2026-07-19

### Fixed
- The installer only matched remote files by their folder path, so a repository entry with any filename — including one that is not a translation file — was downloaded and written into `wp-content/languages/`. Only the known translation extensions (`.po`, `.mo`, `.l10n.php`, `.json`) are accepted now, from a single shared list.
- A malformed repository response whose entry path was not a string raised a fatal TypeError instead of being skipped.

### Changed
- Minimum PHP requirement lowered from 8.1 to 8.0.

### Added
- Test suite (PHPUnit + Brain Monkey), PHPStan level 5 with an empty baseline, and a CI workflow running code style, static analysis and tests on PHP 8.1–8.5.

## [1.0.10] - 2026-03-22

### Added
- LW Site Manager integration - translation abilities for AI agents
- `lw-translate/list-translations` ability - list installed translations
- `lw-translate/get-options` ability - get translation settings
- `lw-translate/install-translation` ability - install a translation
- `lw-translate/update-translations` ability - update all translations

### Fixed
- `list-translations` input schema now accepts empty requests

## [1.0.9]

### Fixed
- Smarter autoloader fallback - supports root Composer dependency installs

## [1.0.8]

### Fixed
- Graceful error when autoloader is missing (admin notice instead of fatal error)

## [1.0.7]

### Fixed
- Minor fix

## [1.0.6]

### Added
- Hash-based tab navigation on settings page
- Active tab preserved after save via redirect hash
- Updated ParentPage with SVG icon support from registry

## [1.0.5]

### Fixed
- Admin notice isolation for notices relocated by WordPress core JS

## [1.0.4]

### Changed
- Isolate third-party admin notices on LW plugin pages

## [1.0.3]

### Added
- WP-CLI support (`list`, `install`, `delete`, `refresh`, `settings`)

## [1.0.2]

### Added
- Fresh POT file and Hungarian (hu_HU) translation

## [1.0.1]

### Changed
- Removed redundant "Up to date" label from Actions column

## [1.0.0]

### Added
- Initial release
- Translation browser with `WP_List_Table`
- Install/update/delete translations
- Formal and informal tone support
- GitHub Trees API integration
- Smart SHA-based comparison
- Bulk actions support
