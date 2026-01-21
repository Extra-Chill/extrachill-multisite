# Changelog

## [1.4.7] - 2026-01-21

- Register social links data via extrachill_social_links_data filter for footer icons

## [1.4.5] - 2026-01-19

### Added
- Added theme integration hooks for 404 content, admin menu, DNS prefetch, footer main menu, network dropdown, and site title
- Updated cross-site links and canonical authority system
- Improved admin settings pages (OAuth, payments, security, shipping)
- Updated blog IDs and Turnstile integration
- Removed vendor directory from git tracking (now gitignored)

## [1.4.4] - 2026-01-19

### Added
- Added music-specific taxonomy badge colors (festivals, locations, venues, artists)
- Added artist dropdown filter for song-meanings and music-history categories
- Added EC footer bottom menu links via theme filter

## [1.4.3] - 2026-01-08
### Added
- Object Cache Pro configuration via `objectcache_config` filter to mark `co-authors-plus` as a non-prefetchable group.
- Canonical authority resolution for shared taxonomy archives across the multisite network.

### Changed
- Plugin initialization now loads the Object Cache Pro config module and canonical authority resolver.

## [1.4.2] - 2026-01-05
### Added
- Dynamic lookup for `EC_PLATFORM_ARTIST_ID` from network options with fallback to production ID.
- User-managed artist profile links in cross-site user link resolution via `ec_get_artists_for_user()`.

### Changed
- Refined cross-site user link resolution to include links to published artist profiles managed by the user.

## [1.4.1] - 2026-01-05
### Added
- REST API integration for cross-site taxonomy counts (Main, Events, Shop, Wire)
- Internal REST API calls via `rest_do_request()` for zero-overhead data retrieval
- Support for `events` site in artist taxonomy mapping

### Changed
- Refactored `ec_get_cross_site_artist_links` to use REST API for upcoming events and shop products
- Optimized `ec_get_cross_site_term_links` to use REST APIs for accurate cross-site content counts
- Removed `extrachill_archive_header_actions` hook for artist archive profile links (now handled via REST-backed resolution)
- Updated artist profile link resolution to use CPT matching on artist site instead of taxonomy

## [1.4.0] - 2026-01-05
### Added
- Unified Cross-Site Links system (`inc/cross-site-links/`) for network-wide navigation
- Taxonomy archive cross-linking with content existence verification
- User profile and artist profile cross-site link resolution
- Support for Blog ID 12 (Horoscope) as an active site
- Enhanced documentation for cross-domain authentication and REST nonces

### Changed
- Updated network site count to 11 active sites (IDs 1-5, 7-12)
- Refined Blog ID helper documentation with recommended patterns

## [1.3.1] - 2025-12-23
### Added
- `ec_get_allowed_redirect_hosts()` function to retrieve all network domains as allowed redirect hosts from domain map
- `ec_filter_allowed_redirect_hosts()` function to register network domains as allowed redirect targets for wp_safe_redirect()
- Automatic registration of all network subdomains as allowed redirect hosts via allowed_redirect_hosts filter
- `ec_allowed_redirect_hosts` filter for extensibility of allowed redirect hosts list

## [1.3.0] - 2025-12-22
### Added
- Support for wire.extrachill.com site (Blog ID 11) with EC_BLOG_ID_WIRE constant
- Legacy path redirects module (`inc/core/legacy-path-redirects.php`) for `/festival-wire` URLs to wire.extrachill.com
- Updated site count and blog ID mappings in documentation

### Changed
- Moved EC_BLOG_ID_HOROSCOPE from 11 to 12 to accommodate wire site
- Updated blog ID arrays and helper functions to include wire site
- Updated README.md site count description (9 active sites, IDs 1-5,7-11)
- Updated docs/blog-id-helpers.md with wire site documentation and horoscope ID correction

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
