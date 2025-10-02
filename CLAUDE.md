# ExtraChill Multisite

Network-activated WordPress plugin providing centralized multisite functionality for the ExtraChill Platform. Handles cross-site data access, admin security, search integration, and commerce features across the WordPress multisite network.

## Plugin Information

- **Name**: ExtraChill Multisite
- **Version**: 1.0.0
- **Text Domain**: `extrachill-multisite`
- **Author**: Chris Huber
- **Author URI**: https://chubes.net
- **License**: GPL v2 or later
- **Network**: true (network activated across all sites)
- **Requires at least**: 5.0
- **Tested up to**: 6.4

## Architecture

### Plugin Loading Pattern
- **Procedural WordPress Pattern**: Uses direct `require_once` includes for all plugin functionality
- **Composer PSR-4 Configuration**: Exists in `composer.json` but ONLY used for development dependencies (PHPUnit, PHPCS)
- **Network Plugin Structure**: Network-activated plugin providing functionality across all multisite installations
- **Modular Loading**: Site-specific functionality loaded conditionally via direct includes in main plugin file

### Core Features

#### Multisite Data Access
**Cross-Site Search Integration** (`inc/extrachill-main/multisite-search.php`):
- **Real-Time Forum Search**: `ec_fetch_forum_results_multisite()` searches community forum from main site
- **Domain-Based Site Resolution**: Uses WordPress native `get_blog_id_from_url()` with automatic blog-id-cache
- **bbPress Integration**: Searches topic and reply post types with metadata filtering
- **Error Handling**: Comprehensive logging and fallback mechanisms

**Recent Activity Feed** (`inc/extrachill-main/recent-activity-feed.php`):
- Cross-site activity aggregation using native WordPress multisite functions
- Performance-optimized data retrieval with direct database queries

#### Security and Access Control
**Admin Access Control** (`inc/core/admin-access-control.php`):
- **Network-Wide Security**: Restricts wp-admin access to administrators only
- **WordPress Native Authentication**: Leverages WordPress multisite authentication system
- **AJAX Exception**: Proper handling of AJAX requests without blocking functionality
- **Redirect Logic**: Safe redirect to home URL for unauthorized access attempts

#### Commerce Integration
**Ad-Free License System** (`inc/shop/ad-free-license.php`):
- **Cross-Domain License Validation**: Domain-based site resolution to check shop site licenses
- **WordPress Multisite Integration**: Follows established cross-site data access patterns
- **User License Checking**: Validates ad-free access via multisite database lookup
- **Performance Optimized**: Direct database queries with WordPress native caching

#### Comment and Author Integration
**Comment Author Links** (`inc/extrachill-main/comment-author-links.php`):
- Cross-site comment author information integration
- Unified user experience across multisite network

**Main Site Comments** (`inc/community/main-site-comments.php`):
- Community site integration with main site commenting system
- Seamless cross-domain comment functionality

## Technical Implementation

### WordPress Multisite Patterns
**Blog Switching Architecture**:
```php
// Standard pattern used throughout plugin
switch_to_blog( get_blog_id_from_url( 'community.extrachill.com', '/' ) );
try {
    // Cross-site database operations
    $results = get_posts($args);
} finally {
    restore_current_blog();
}
```

**Domain-Based Site Resolution**:
- **Main Site**: extrachill.com
- **Community Site**: community.extrachill.com
- **Shop Site**: shop.extrachill.com
- **App Site**: app.extrachill.com (planning stage)
- **Chat Site**: chat.extrachill.com
- **Artist Site**: artist.extrachill.com
- **Events Site**: events.extrachill.com
- **Performance**: WordPress native `get_blog_id_from_url()` with automatic blog-id-cache

### Plugin Loading Strategy
**Network Activation Requirements**:
- Plugin automatically checks for WordPress multisite installation
- Deactivates with error message if multisite not detected
- Loads core functionality needed by all sites
- Conditionally loads site-specific functionality

**File Organization**:
- **Core**: `inc/core/` - Network-wide functionality (admin access control)
- **Main Site**: `inc/extrachill-main/` - Main site specific features (search, activity, comments)
- **Community**: `inc/community/` - Community site integration features
- **Shop**: `inc/shop/` - E-commerce and license functionality

## Development Standards

### Code Organization
- **Procedural Pattern**: Direct `require_once` includes throughout plugin architecture
- **Composer Autoloader**: Only for development dependencies (PHPUnit, PHPCS, WordPress standards)
- **WordPress Standards**: Full compliance with network plugin development guidelines
- **Security Implementation**: Network-wide admin access control and secure cross-site data access
- **Performance Focus**: Direct database queries with domain-based site resolution via `get_blog_id_from_url()` and automatic WordPress blog-id-cache

### Build System
- **Universal Build Script**: Symlinked to shared build script at `../../.github/build.sh`
- **Auto-Detection**: Script auto-detects network plugin from `Network: true` header
- **Production Build**: Creates `/build/extrachill-multisite/` directory and `/build/extrachill-multisite.zip` file (non-versioned)
- **Composer Integration**: Production builds use `composer install --no-dev`, restores dev dependencies after
- **File Exclusion**: `.buildignore` rsync patterns exclude development files and vendor directory
- **Structure Validation**: Ensures network plugin integrity before packaging

## Dependencies

### PHP Requirements
- **PHP**: 7.4+
- **WordPress**: 5.0+ multisite network
- **Multisite**: Requires WordPress multisite installation (enforced on activation)

### Development Dependencies
- **PHP CodeSniffer**: WordPress coding standards compliance
- **PHPUnit**: Unit testing framework
- **WPCS**: WordPress Coding Standards ruleset

### WordPress Integration
- **Network Activation**: Must be network activated to function properly
- **Multisite Functions**: Leverages native `switch_to_blog()` and `restore_current_blog()`
- **Cross-Site Data**: Uses WordPress multisite database structure for cross-site access

## Key Functionality

### Cross-Site Search
**Forum Search Integration**:
- Searches community forum from main site using domain-based resolution
- Real-time search with no caching for fresh results
- bbPress post type integration (topics and replies)
- Metadata filtering for published content only

### Admin Security
**Network-Wide Access Control**:
- Restricts wp-admin access to administrators across all sites
- Maintains consistent security policy across multisite network
- Preserves AJAX functionality while blocking unauthorized admin access

### Commerce Features
**License Validation System**:
- Cross-site ad-free license checking from shop database
- Follows WordPress multisite patterns for performance
- Integrates with existing user authentication system

## Common Development Commands

### Building and Testing
```bash
# Install dependencies
composer install

# Create production build (network plugin)
./build.sh

# Run PHP linting
composer run lint:php

# Fix PHP coding standards
composer run lint:fix

# Run tests
composer run test
```

### Build Output
- **Production Package**: `/build/extrachill-multisite/` directory and `/build/extrachill-multisite.zip` file
- **Network Plugin**: Must be installed in network plugins directory
- **File Exclusions**: Development files, vendor/, .git/, build tools excluded

## Integration Guidelines

### Adding New Cross-Site Features
1. **Follow Blog Switching Pattern**: Use `switch_to_blog( get_blog_id_from_url( 'domain.extrachill.com', '/' ) )` with proper error handling
2. **Domain-Based Resolution**: Use domain strings for maintainable, readable code
3. **Network-Wide Loading**: Add new functionality to main plugin initialization
4. **Security Checks**: Implement proper capability checks for network-wide features

### Site-Specific Integration
- **Main Site Features**: Add to `inc/extrachill-main/` directory
- **Community Features**: Add to `inc/community/` directory
- **Shop Features**: Add to `inc/shop/` directory
- **Core Features**: Add to `inc/core/` for network-wide functionality

## WordPress Multisite Integration

### Native Functions Used
- **`switch_to_blog()`**: Cross-site database access
- **`restore_current_blog()`**: Restore original site context
- **`get_blog_id_from_url()`**: Domain-based blog ID resolution with automatic caching
- **`is_multisite()`**: Multisite installation detection
- **Network activation hooks**: Proper network plugin initialization

### Performance Optimizations
- **Direct Database Queries**: Optimized cross-site data access
- **WordPress Native Caching**: `get_blog_id_from_url()` uses blog-id-cache automatically
- **Minimal Context Switching**: Efficient blog switching patterns
- **Error Handling**: Comprehensive error logging and fallback mechanisms

## Security Implementation

### Network-Wide Security
- **Admin Access Restriction**: Consistent admin access control across all sites
- **WordPress Native Authentication**: Leverages multisite user authentication system
- **Cross-Site Data Security**: Proper sanitization and escaping for cross-site operations
- **Capability Checks**: Administrator-level verification for sensitive operations

## User Info

- Name: Chris Huber
- Dev website: https://chubes.net
- GitHub: https://github.com/chubes4
- Founder & Editor: https://extrachill.com
- Creator: https://saraichinwag.com