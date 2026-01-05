# ExtraChill Multisite

Network-activated WordPress plugin providing the network administration foundation for the ExtraChill Platform.

## Overview

This WordPress network plugin serves as the **network administration foundation** for the ExtraChill multisite network, providing essential network-wide functionality that supports all 11 active sites (Blog IDs 1–5, 7–12).

## Features

- **Network Admin Menu Consolidation** - Centralized top-level network admin menu for all ExtraChill Platform settings and configuration
- **Cloudflare Turnstile Integration** - Network-wide captcha management and configuration accessible from all network sites
- **OAuth Provider Settings** - Network-wide Google OAuth configuration with helper functions for extrachill-users integration
- **Payment Provider Settings** - Network-wide Stripe configuration for extrachill-shop integration

## Purpose

This plugin maintains focused responsibility for network administration infrastructure. Historical features like user management, search, and newsletter integration have been successfully migrated to specialized plugins (extrachill-users, extrachill-search, extrachill-newsletter) following the platform's single responsibility principle.


## Installation

Installation steps are intentionally omitted from this documentation set.

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
