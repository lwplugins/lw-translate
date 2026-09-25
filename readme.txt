=== LW Translate ===
Contributors: lwplugins
Tags: translation, locale, language, i18n, community
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WordPress translations from community repositories.

== Description ==

LW Translate provides an easy way to install and manage community translations for your WordPress plugins and themes from GitHub-hosted translation repositories like [hellowpio/wordpress-translations](https://github.com/hellowpio/wordpress-translations).

**Features:**

* Browse available translations for installed plugins and themes
* One-click install and update translations
* Formal (magázó) and informal (tegező) tone support
* SHA-based update detection (no unnecessary downloads)
* WP_List_Table interface with search, sort, and filter
* Bulk install/update/delete actions
* Smart caching for GitHub API calls
* WP_Filesystem for safe file operations

**How it works:**

1. The plugin checks the community translation repository on GitHub
2. It compares available translations with your installed plugins/themes
3. Shows which translations are available, installed, or need updating
4. You can install or update translations with a single click

== Installation ==

1. Upload the `lw-translate` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to LW Plugins → Translations to manage translations
4. Configure tone and locale in LW Plugins → Translate Settings

**Via Composer:**

    composer require lwplugins/lw-translate

== Frequently Asked Questions ==

= Which translation repository does this use? =

The plugin uses the [hellowpio/wordpress-translations](https://github.com/hellowpio/wordpress-translations) GitHub repository which contains 393+ plugin and 9 theme translations.

= Is it safe to point this at any repository? =

Only use a repository you trust. The plugin installs only the plain translation files WordPress expects for each plugin or theme (`{slug}-{locale}.mo`, `.po` and script `.json` files), checks every download against the repository's checksum, and never writes outside wp-content/languages. Translation files that run as PHP (.l10n.php) are never downloaded: on WordPress 6.5 and newer, WordPress's own converter generates them from the installed .mo file. Only administrators who may install languages can install, update or delete translations.

= What is the difference between formal and informal tone? =

Some translation repositories provide both formal and informal variants. Formal uses polite forms while informal uses familiar forms (e.g. in Hungarian: magázó vs. tegező).

= How does update detection work? =

The plugin calculates git blob SHA hashes of your local .mo files and compares them with the remote repository. Only genuinely changed files trigger an update notification.

== Changelog ==

= 1.1.4 =
* Fix: Translation files that execute as PHP (.l10n.php) are no longer downloaded; WordPress's own converter generates them from the .mo file.
* Fix: Only the translation files that belong to the plugin or theme being installed are written (`{slug}-{locale}.mo`, `.po` and script `.json` files). Anything else in the repository folder is ignored and listed in the result.
* Fix: Every downloaded file is checked against the repository's checksum before anything is written. If one file doesn't match, nothing is installed for that item.
* Fix: Installing, updating and deleting translations now requires the "install languages" permission. On multisite only network admins can do it, and nothing can be changed when file changes are disabled on the site (DISALLOW_FILE_MODS).
* Fix: The cache duration is kept between 1 hour and 1 week, and the tone setting only accepts formal or informal, also when set from WP-CLI. A cache duration of 0 could store the whole translation list permanently in the database.

= 1.1.3 =
* Fix: notices from themes and other plugins (for example a theme's purchase-code or recommended-plugins notice) could show on the LW Translate screen. They are now kept off every LW Plugins screen, whatever their markup.

= 1.1.2 =
* Fix: the release package and Composer dist no longer ship tests, docs or development configuration

= 1.1.1 =
* Update: Tested up to WordPress 7.1.

= 1.1.0 =
* Fix: Only real translation files (.po, .mo, .l10n.php, .json) are installed — previously any filename under the matching folder was downloaded into wp-content/languages
* Fix: A malformed repository response no longer causes a fatal error
* Change: Minimum PHP requirement lowered from 8.1 to 8.0
* New: Test suite, PHPStan level 5 static analysis, and a CI workflow

= 1.0.10 =
* New: LW Site Manager integration - translation abilities for AI agents
* New: lw-translate/list-translations - list installed translations
* New: lw-translate/get-options - get translation settings
* New: lw-translate/install-translation - install a translation
* New: lw-translate/update-translations - update all translations
* Fix: list-translations input schema accepts empty requests

= 1.0.9 =
* Fix: Smarter autoloader fallback - supports root Composer dependency installs

= 1.0.8 =
* Fix: Graceful error when autoloader is missing (admin notice instead of fatal error)

= 1.0.7 =
* Minor fix

= 1.0.6 =
* Hash-based tab navigation on settings page
* Active tab preserved after save via redirect hash
* Updated ParentPage with SVG icon support from registry

= 1.0.5 =
* Fix admin notice isolation for notices relocated by WordPress core JS

= 1.0.4 =
* Isolate third-party admin notices on LW plugin pages

= 1.0.3 =
* Add WP-CLI support (list, install, delete, refresh, settings)

= 1.0.2 =
* Add fresh POT file and Hungarian (hu_HU) translation

= 1.0.1 =
* Remove redundant "Up to date" label from Actions column

= 1.0.0 =
* Initial release
* Translation browser with WP_List_Table
* Install/update/delete translations
* Formal and informal tone support
* GitHub Trees API integration
* Smart SHA-based comparison
* Bulk actions support

== Upgrade Notice ==

= 1.1.4 =
Security release. Translation files that run as PHP are no longer downloaded, downloads are verified, and only users who may install languages can change translations. Updating is recommended.
