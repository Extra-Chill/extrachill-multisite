# Changelog

## [1.0.3] - 2025-12-11
### Added
- `ec_site_url_override` filter hook in `ec_get_site_url()` for development environment URL overrides

## [1.0.2] - 2025-12-08
### Added
- `ec_get_site_url()` helper function for logical site URL resolution

### Changed
- Corrected extrachill.link domain mapping to artist.extrachill.com
- Fixed Turnstile verification failures affecting registration and contact forms
- Added avatar menu for cross-platform reusability

### Technical
- Updated composer vendor package references

## [1.0.1] - 2025-12-08
### Added
- Blog ID mapping system (`inc/core/blog-ids.php`) with comprehensive site routing
- Domain mapping support for extrachill.link → artist.extrachill.com
- Helper functions for cross-site blog ID resolution

### Changed
- Updated plugin description to reflect network administration focus
- Improved README with current architecture and site count
- Fixed plugin reference in security settings (community → users)

### Technical
- Added blog-ids.php include to plugin initialization
- Updated composer dependencies