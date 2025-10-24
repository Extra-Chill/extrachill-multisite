# ExtraChill Multisite

Network activated multisite functionality for the ExtraChill Platform.

## Overview

This WordPress network plugin provides centralized multisite functionality across all sites in the ExtraChill network (extrachill.com, community.extrachill.com, shop.extrachill.com, chat.extrachill.com, artist.extrachill.com, events.extrachill.com, app.extrachill.com). It handles cross-site features like network administration, security, newsletter integration, and commerce integration.

## Features

- **Network Admin Menu** - Consolidated top-level network admin menu for ExtraChill Platform settings
- **Network-wide Admin Access Control** - Restricts wp-admin access to administrators only across all sites
- **Cloudflare Turnstile Integration** - Centralized captcha configuration accessible from all network sites


## Installation

1. Upload the plugin to `/wp-content/plugins/`
2. Network activate the plugin in WordPress multisite admin
3. The plugin automatically provides functionality across all network sites

## Architecture

- **Network Activated** - Single plugin serving all sites in the multisite network
- **Direct Database Queries** - Uses `switch_to_blog()` for cross-site data access
- **Performance Optimized** - Dynamic site discovery with automatic WordPress blog-id-cache
- **Centralized Configuration** - Network-wide settings stored via `get_site_option()` accessible from all sites
- **Modular Organization** - Core functionality in `inc/core/`, site-specific features in dedicated directories, admin interface in `admin/`
- **Security First** - Comprehensive admin access control, Cloudflare Turnstile integration, and capability checks

## Requirements

- WordPress 5.0+
- WordPress Multisite installation
- PHP 7.4+

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Create production build
./build.sh
```

## License

GPL v2 or later