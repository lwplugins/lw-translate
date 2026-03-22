# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
