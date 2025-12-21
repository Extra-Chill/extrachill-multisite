# Changelog

## [1.2.1] - 2025-12-20
### Added
- Network Shipping Settings admin page for Shippo API key configuration and shipping label integration
- `EC_PLATFORM_ARTIST_ID` constant (value: 12114) for internal artist profile identification
- Automatic Turnstile verification bypass for local development environments (WP_ENVIRONMENT_TYPE = 'local')

### Changed
- Enhanced `ec_verify_turnstile_response()` with environment-aware bypass logic for streamlined local testing

## [1.2.0] - 2025-12-20
### Added
- iOS OAuth Client ID field in network OAuth settings page for native iOS app authentication
- Android OAuth Client ID field in network OAuth settings page for native Android app authentication
- `ec_get_google_ios_client_id()` helper function to retrieve iOS OAuth client identifier
- `ec_get_google_android_client_id()` helper function to retrieve Android OAuth client identifier
- Setup instructions for iOS and Android OAuth client creation in network settings

### Changed
- Updated README.md to document OAuth and Payment Provider Settings features
- Enhanced network OAuth settings page with iOS and Android configuration fields
- Improved OAuth settings form layout with aligned variable declarations

## [1.1.1] - 2025-12-19
### Changed
- Refactored OAuth helper functions to dedicated `inc/core/oauth-helpers.php` module for better code organization
- Moved `ec_is_google_oauth_configured()` and `ec_is_apple_oauth_configured()` from `admin/network-oauth-settings.php` to core module

## [1.1.0] - 2025-12-19
### Added
- Network OAuth settings page for Google Sign-In and Apple Sign-In configuration
- Network Payments settings page for Stripe Connect configuration
- Documentation for blog ID helper functions and usage patterns
- Documentation for cross-domain authentication patterns
- Documentation for Cloudflare Turnstile integration in registration forms

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