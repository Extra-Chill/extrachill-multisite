# ExtraChill Multisite

Network-activated WordPress plugin providing centralized multisite functionality for the ExtraChill Platform. Handles cross-site data access, admin security, and cross-domain authentication across the WordPress multisite network.

**Note**: User management features (team members, user creation, profile URLs, avatar menu) have been extracted to the extrachill-users plugin. Newsletter subscription functionality has been consolidated into the extrachill-newsletter plugin.

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
- **Composer PSR-4 Configuration**: PSR-4 autoload configuration exists in `composer.json` (line 14-16: `"ExtraChill\\Multisite\\": "inc/"`) but is unused for plugin code (reserved for future use). Composer autoload is ONLY actively used for development dependencies (PHPUnit, PHPCS).
- **Network Plugin Structure**: Network-activated plugin providing functionality across all multisite installations
- **Modular Loading**: Site-specific functionality loaded conditionally via direct includes in main plugin file

### Core Features

#### Network Administration
**Network Admin Menu** (`admin/network-menu.php`):
- Top-level network admin menu consolidation for ExtraChill Platform settings
- Centralized menu slug constant (`EXTRACHILL_MULTISITE_MENU_SLUG`) for plugin integration
- Menu position priority 3 with multisite dashicon

**Network Security Settings** (`admin/network-security-settings.php`):
- Cloudflare Turnstile configuration interface in network admin
- Network-wide security settings management

#### Security and Access Control
**Admin Access Control** (`inc/core/admin-access-control.php`):
- **Network-Wide Security**: Restricts wp-admin access to administrators only
- **WordPress Native Authentication**: Leverages WordPress multisite authentication system
- **AJAX Exception**: Proper handling of AJAX requests without blocking functionality
- **Redirect Logic**: Safe redirect to home URL for unauthorized access attempts

**Cloudflare Turnstile Integration** (`inc/core/extrachill-turnstile.php`):
- **Network-Wide Storage**: Centralized Turnstile configuration via `get_site_option()`
- **Accessible from All Sites**: Functions available across entire multisite network
- **Key Management**: `ec_get_turnstile_site_key()`, `ec_get_turnstile_secret_key()`, `ec_update_turnstile_site_key()`
- **Security-First**: Sanitization and validation for all configuration updates

**Cross-Domain Authentication for extrachill.link** (`inc/core/cross-domain-auth.php`):
- **Dual Domain Cookie Management**: Sets authentication cookies on both .extrachill.com and extrachill.link domains
- **WordPress Native Integration**: Hooks into WordPress `set_auth_cookie` and `set_logged_in_cookie` actions
- **Seamless Authentication**: Users authenticated on .extrachill.com are automatically authenticated on extrachill.link
- **Cookie Types**: Manages AUTH_COOKIE, SECURE_AUTH_COOKIE, and LOGGED_IN_COOKIE for extrachill.link domain
- **Logout Handling**: Clears extrachill.link cookies via `clear_auth_cookie` action hook
- **Security Implementation**: Respects SSL settings and httponly flags for secure cookie transmission

## Technical Implementation

### WordPress Multisite Patterns
**Blog Switching Architecture**:
```php
// Hardcoded blog ID for performance (shop site)
$shop_blog_id = 3; // shop.extrachill.com

switch_to_blog($shop_blog_id);
try {
    // Cross-site database operations
    $results = get_posts($args);
} finally {
    restore_current_blog();
}
```

**Network Site Structure**:
- **Main Site**: extrachill.com (blog ID 1)
- **Community Site**: community.extrachill.com (blog ID 2)
- **Shop Site**: shop.extrachill.com (blog ID 3)
- **App Site**: app.extrachill.com (planning stage)
- **Chat Site**: chat.extrachill.com (blog ID 5)
- **Artist Site**: artist.extrachill.com (blog ID 4)
- **Events Site**: events.extrachill.com (blog ID 7)
- **Link Pages Domain**: extrachill.link (domain mapped to artist.extrachill.com blog ID 4 via .github/sunrise.php)
  - Domain mapping preserves extrachill.link URLs in frontend while operating on artist.extrachill.com backend
  - Cross-domain authentication via inc/core/cross-domain-auth.php sets cookies for both .extrachill.com and extrachill.link domains
  - Link page routing and templates handled by extrachill-artist-platform plugin
  - Join flow redirect (extrachill.link/join → artist.extrachill.com/login/?from_join=true) configured in sunrise.php
- **Performance**: Hardcoded blog IDs throughout for optimal performance

### Plugin Loading Strategy
**Network Activation Requirements**:
- Plugin automatically checks for WordPress multisite installation
- Deactivates with error message if multisite not detected
- Loads core functionality needed by all sites
- Conditionally loads site-specific functionality

**File Organization**:
- **Core**: `inc/core/` - Network-wide functionality (admin access control, Turnstile, cross-domain auth)
- **Admin**: `admin/` - Network admin interface (network menu, security settings)

## Development Standards

### Code Organization
- **Procedural Pattern**: Direct `require_once` includes throughout plugin architecture
- **Composer Autoloader**: PSR-4 autoload configuration exists but is unused for plugin code (reserved for future use). Composer autoload only actively used for development dependencies (PHPUnit, PHPCS, WordPress standards).
- **WordPress Standards**: Full compliance with network plugin development guidelines
- **Security Implementation**: Network-wide admin access control and secure cross-site data access
- **Performance Focus**: Direct database queries with hardcoded blog IDs for optimal performance

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

### Network Administration
**Consolidated Admin Menu**:
- Top-level network admin menu for all ExtraChill Platform settings
- Extensible menu slug constant for other plugins
- Security settings interface for Cloudflare Turnstile configuration

### Admin Security
**Network-Wide Access Control**:
- Restricts wp-admin access to administrators across all sites
- Maintains consistent security policy across multisite network
- Preserves AJAX functionality while blocking unauthorized admin access

**Cloudflare Turnstile**:
- Centralized captcha configuration stored at network level
- Accessible from all sites via helper functions
- Integrates with users plugin and other security features

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
1. **Follow Blog Switching Pattern**: Use hardcoded blog IDs with `switch_to_blog()` for optimal performance
2. **Error Handling**: Always use try/finally pattern with `restore_current_blog()`
3. **Network-Wide Loading**: Add new functionality to main plugin initialization
4. **Security Checks**: Implement proper capability checks for network-wide features

### Site-Specific Integration
- **Core Features**: Add to `inc/core/` for network-wide functionality (admin access control, Turnstile, cross-domain auth)
- **Admin Features**: Add to `admin/` directory for network admin interface components

## WordPress Multisite Integration

### Native Functions Used
- **`switch_to_blog()`**: Cross-site database access with hardcoded blog IDs
- **`restore_current_blog()`**: Restore original site context
- **`is_multisite()`**: Multisite installation detection
- **Network activation hooks**: Proper network plugin initialization

### Performance Optimizations
- **Direct Database Queries**: Optimized cross-site data access
- **Hardcoded Blog IDs**: Maximum performance for known site relationships
- **Minimal Context Switching**: Efficient blog switching patterns
- **Error Handling**: Comprehensive error logging and fallback mechanisms

## Security Implementation

### Network-Wide Security
- **Admin Access Restriction**: Consistent admin access control across all sites
- **WordPress Native Authentication**: Leverages multisite user authentication system
- **Cross-Site Data Security**: Proper sanitization and escaping for cross-site operations
- **Capability Checks**: Administrator-level verification for sensitive operations

## Workflow Preferences

- We like to collaborate about concepts and side effects prior to implementation. It is critical to have a full grasp on the systems before making code changes
- Our workflows involve ideas that sometimes need refinement. We are always willing to refine our ideas.
- When coding, we are careful to complete tasks end-to-end, with all edge cases accounted for
- We always read files directly after completion to ensure correctness

## Architectural Principles

- We adhere to KISS (KEEP IT SIMPLE, STUPID) and always favor the most direct, centralized solution
- The single responsibility principle is paramount. Each file must be responsible for a single responsibility with no exceptions.
- Human-readable code is highly preferable, with each function making clear sense to the human reader, reducing the need for inline code comments
- All data must have a single source of truth, located in PHP files on the server side
- Centralized WordPress filters are used for centralizing transformations, and data sources, and complex operations
- Centralized WordPress action hooks are used for one-way functionality
- Code is as short and sweet as possible to achieve the desired result according to these architectural principles

## FORBIDDEN FALLBACK TYPES

- Placeholder fallbacks for undefined data that should be required
- Legacy fallbacks for removed functionality
- Fallbacks that prevent code failure or hide broken functionality
- Fallbacks that provide dual support for multiple methods

## Planning Standards (Plan Mode)

- Create an specific and refined plan outlining all explicit changes exactly as they will be implemented
- Plans must explicitly identify which files and functions to modify, create, or delete
- When writing todos, always include excessive detail, intermediary steps, and files to modify/create
- Plans MUST align with existing codebase patterns
- All code review should be completed before you present the plan

## Special Rules

- All AI models in the codebase are correct. Do not change them.
- Verify all API documentation using the context7 mcp

## Documentation Standards

- Use concise inline docblocks at the top of files and/or critical functions to explain technicalities for developers
- Inline comments are reserved strictly for nuanced behavior that is not obvious from the readable code
- Actively remove outdated documentation, including references to deleted functionality

## Build Process

### WordPress Plugin/Theme Build Requirements

- Create a `build.sh` shell command that creates an optimized package for production use
- Production build structure:
  - `/build/[root-directory-name]/` - Clean production directory with only essential files
  - `/build/[root-directory-name].zip` - Production ZIP file for WordPress deployment
- File exclusions: Exclude development files (vendor/, node_modules/, .git/, docs/, build files, composer.lock, package-lock.json, .DS_Store, .claude/, CLAUDE.md, README.MD, .buildignore, build.sh)
- Use `composer install --no-dev` for production dependencies only
- Composer scripts must use `vendor/bin/` prefix for tool paths
- Also create tasks.json file in VSCode for theme development
- Build validation: Verify all essential plugin/theme files are present before creating ZIP

### Build Script Template Structure

```bash
# Clean previous builds -> Install production deps -> Copy files with exclusions -> Validate -> Create ZIP in /build -> Restore dev deps
```

## User Info

- Name: Chris Huber
- Dev website: https://chubes.net
- GitHub: https://github.com/chubes4
- Founder & Editor: https://extrachill.com
- Creator: https://saraichinwag.com