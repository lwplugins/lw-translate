=== LW Translate ===
Contributors: lwplugins
Tags: translation, locale, language, i18n, community
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.4
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

= What is the difference between formal and informal tone? =

Some translation repositories provide both formal and informal variants. Formal uses polite forms while informal uses familiar forms (e.g. in Hungarian: magázó vs. tegező).

= How does update detection work? =

The plugin calculates git blob SHA hashes of your local .mo files and compares them with the remote repository. Only genuinely changed files trigger an update notification.

== Changelog ==

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
