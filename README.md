# ExtraChill Multisite

Network activated multisite functionality for the ExtraChill Platform.

## Overview

This WordPress network plugin provides centralized multisite functionality across all sites in the ExtraChill network (extrachill.com, community.extrachill.com, shop.extrachill.com). It handles cross-site features like search, activity feeds, license validation, and admin access control.

## Features

- **Network-wide Admin Access Control** - Restricts wp-admin access to administrators only across all sites
- **Cross-Site Search** - Unified search functionality across the multisite network
- **Activity Feeds** - Cross-site activity integration using direct database queries
- **License Validation** - Ad-free license validation across sites
- **Comment Integration** - Cross-site comment author linking and display

## Installation

1. Upload the plugin to `/wp-content/plugins/`
2. Network activate the plugin in WordPress multisite admin
3. The plugin automatically provides functionality across all network sites

## Architecture

- **Network Activated** - Single plugin serving all sites in the multisite network
- **Direct Database Queries** - Uses `switch_to_blog()` for cross-site data access
- **Performance Optimized** - Domain-based site resolution via `get_blog_id_from_url()` with WordPress blog-id-cache
- **Security First** - Comprehensive admin access control and capability checks

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