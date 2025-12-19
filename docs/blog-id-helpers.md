# Blog ID Resolution and Hardcoded Site IDs

## Overview

The Extra Chill Platform uses a hardcoded blog ID system for performance optimization. All 9 active WordPress multisite sites have predetermined blog IDs that never change. This document explains why blog IDs are hardcoded, how to use the blog ID helper functions, and when to use hardcoded values vs. dynamic discovery.

## Why Hardcode Blog IDs?

### Performance Optimization

**Zero Database Queries**: Hardcoded constants eliminate database lookups for known site IDs

**Direct Multisite Operations**: Faster `switch_to_blog()` calls without site discovery

**Network Architecture Decision**: Blog IDs are fixed infrastructure, not dynamic configuration

**Example Impact**: Retrieving newsletter site (Blog ID 9) takes 0 queries vs. 1-2 queries with dynamic lookup

## Blog ID Constants

All blog ID constants defined in `inc/core/blog-ids.php`:

```php
define( 'EC_BLOG_ID_MAIN', 1 );           // extrachill.com
define( 'EC_BLOG_ID_COMMUNITY', 2 );      // community.extrachill.com
define( 'EC_BLOG_ID_SHOP', 3 );           // shop.extrachill.com
define( 'EC_BLOG_ID_ARTIST', 4 );         // artist.extrachill.com (+ extrachill.link)
define( 'EC_BLOG_ID_CHAT', 5 );           // chat.extrachill.com
define( 'EC_BLOG_ID_EVENTS', 7 );         // events.extrachill.com
define( 'EC_BLOG_ID_STREAM', 8 );         // stream.extrachill.com
define( 'EC_BLOG_ID_NEWSLETTER', 9 );     // newsletter.extrachill.com
define( 'EC_BLOG_ID_DOCS', 10 );          // docs.extrachill.com
define( 'EC_BLOG_ID_HOROSCOPE', 11 );     // horoscope.extrachill.com (planned)
```

**Note**: Blog ID 6 is unused (historical artifact from site deletion)

## Using Blog ID Functions

### ec_get_blog_id() - Recommended Approach

**Purpose**: Get blog ID by logical slug, abstraction layer

**Parameters**: `$key` (string) - Logical site key

**Returns**: `int|null` - Blog ID or null if unknown

**Usage Pattern**:
```php
// Instead of hardcoding blog ID numbers
$blog_id = ec_get_blog_id( 'newsletter' );

// Safely handle unknown blog IDs
if ( ! $blog_id = ec_get_blog_id( 'newsletter' ) ) {
    wp_die( 'Newsletter site not configured' );
}

// Use in multisite operations
try {
    switch_to_blog( $blog_id );
    $newsletters = get_posts( array( 'post_type' => 'newsletter' ) );
} finally {
    restore_current_blog();
}
```

**Advantages**:
- Readable, self-documenting code
- Centralized single source of truth
- Easy to update if blog ID ever changes
- Works with functions that might not exist

**Valid Keys**:
- `'main'` - Main site (Blog ID 1)
- `'community'` - Community hub (Blog ID 2)
- `'shop'` - E-commerce site (Blog ID 3)
- `'artist'` - Artist platform (Blog ID 4)
- `'chat'` - AI chatbot (Blog ID 5)
- `'events'` - Event calendar (Blog ID 7)
- `'stream'` - Live streaming (Blog ID 8)
- `'newsletter'` - Newsletter site (Blog ID 9)
- `'docs'` - Documentation (Blog ID 10)
- `'horoscope'` - Horoscopes (Blog ID 11)

### ec_get_blog_ids() - Associative Map

**Purpose**: Get complete map of all blog IDs

**Returns**: Associative array with slugs as keys, blog IDs as values

**Usage Pattern**:
```php
// Get all sites for iteration
$blogs = ec_get_blog_ids();
foreach ( $blogs as $slug => $blog_id ) {
    // Process all network sites
}

// Check if site exists
if ( array_key_exists( 'newsletter', ec_get_blog_ids() ) ) {
    // Newsletter site exists
}
```

### ec_get_blog_slug_by_id() - Reverse Lookup

**Purpose**: Get logical slug from numeric blog ID

**Parameters**: `$blog_id` (int) - Numeric blog ID

**Returns**: `string|null` - Slug (e.g., 'artist') or null

**Usage Pattern**:
```php
// Get site name from blog ID (useful in hooks)
$slug = ec_get_blog_slug_by_id( get_current_blog_id() );

if ( $slug === 'newsletter' ) {
    // Special handling for newsletter site
}
```

### ec_get_site_url() - Production URLs

**Purpose**: Get production site URL by logical slug

**Parameters**: `$key` (string) - Logical site slug

**Returns**: `string|null` - Full HTTPS URL or null

**Usage Pattern**:
```php
// Get link to another site
$newsletter_url = ec_get_site_url( 'newsletter' );
echo sprintf( '<a href="%s">Newsletter</a>', esc_url( $newsletter_url ) );

// Safe with null check
if ( $url = ec_get_site_url( 'newsletter' ) ) {
    // Use URL
}
```

**Overridable**: Fires `ec_site_url_override` filter for dev environment customization

## When to Use What

### Use ec_get_blog_id() for:

✅ Multisite operations in plugins  
✅ When you need blog ID from plugin code  
✅ When other plugins might check function existence  
✅ Safe default approach (works even if function not defined)

**Example**:
```php
if ( function_exists( 'ec_get_blog_id' ) ) {
    $blog_id = ec_get_blog_id( 'newsletter' );
} else {
    // Fallback for multisite without extrachill-multisite
    $blog_id = 9;
}
```

### Use Constants (EC_BLOG_ID_*) for:

✅ When extrachill-multisite is guaranteed (network-activated)  
✅ When performance is critical (zero function call overhead)  
✅ Direct numeric comparisons

**Example**:
```php
// Direct comparison with constant
if ( get_current_blog_id() === EC_BLOG_ID_NEWSLETTER ) {
    // Load newsletter-specific functionality
}
```

### Use Numeric Values (Hardcoded) for:

❌ Almost never - use functions instead  
✅ Only in `.github/sunrise.php` (executes before WordPress loads)  
✅ In comments to document which site is which

**Don't Do This**:
```php
// Bad - hardcoded in plugin code
$blog_id = 9; // newsletter site
switch_to_blog( $blog_id );
```

**Do This Instead**:
```php
// Good - use helper function
if ( $blog_id = ec_get_blog_id( 'newsletter' ) ) {
    switch_to_blog( $blog_id );
}
```

## Cross-Site Data Access Pattern

### Safe Blog Switching

**Required Pattern**: Always use try/finally to restore blog context

```php
// CORRECT - Always restore even if error occurs
try {
    switch_to_blog( $blog_id );
    // Perform operations in target blog context
    $posts = get_posts( array( 'post_type' => 'post' ) );
} finally {
    restore_current_blog();
}
```

**Why try/finally?**
- Error in blog context doesn't leave blog switched
- Prevents data corruption from incorrect blog context
- Essential for production stability

### Nested Blog Switching

**Safe Pattern**: Track blog stack manually

```php
$current_blog = get_current_blog_id();
try {
    switch_to_blog( $other_blog_id );
    // First operation
    
    // If you need another blog:
    $blog_a = get_current_blog_id();
    switch_to_blog( $blog_b );
    // Second operation
    restore_current_blog(); // Back to $blog_a
    
    // Back to first blog context
} finally {
    if ( get_current_blog_id() !== $current_blog ) {
        restore_current_blog();
    }
}
```

### Query Multiple Sites

```php
// Get posts from multiple sites
$blogs = array(
    ec_get_blog_id( 'main' ),
    ec_get_blog_id( 'community' ),
    ec_get_blog_id( 'artist' ),
);

$all_posts = array();
foreach ( $blogs as $blog_id ) {
    try {
        switch_to_blog( $blog_id );
        $all_posts = array_merge(
            $all_posts,
            get_posts( array( 'posts_per_page' => -1 ) )
        );
    } finally {
        restore_current_blog();
    }
}
```

## Domain to Blog ID Mapping

### ec_get_domain_map()

**Purpose**: Maps all domains to blog IDs for routing

**Returns**: Associative array of domain → blog ID pairs

**Includes**:
- All .extrachill.com subdomains
- extrachill.link domain mapping (→ Blog ID 4)
- www.extrachill.link domain variant

**Usage Pattern**:
```php
// Determine blog from domain (used in routing)
$domain_map = ec_get_domain_map();
$blog_id = $domain_map[ $_SERVER['HTTP_HOST'] ] ?? 1;
```

**Note**: Sunrise.php handles domain routing before WordPress loads, not plugin code

## Blog ID Knowledge Base

### Complete Network Map

| Site | Domain | Blog ID | Key | Status |
|------|--------|---------|-----|--------|
| Main | extrachill.com | 1 | `main` | Active |
| Community | community.extrachill.com | 2 | `community` | Active |
| Shop | shop.extrachill.com | 3 | `shop` | Active |
| Artist | artist.extrachill.com | 4 | `artist` | Active |
| Chat | chat.extrachill.com | 5 | `chat` | Active |
| *Unused* | *N/A* | 6 | *N/A* | Unused |
| Events | events.extrachill.com | 7 | `events` | Active |
| Stream | stream.extrachill.com | 8 | `stream` | Active |
| Newsletter | newsletter.extrachill.com | 9 | `newsletter` | Active |
| Docs | docs.extrachill.com | 10 | `docs` | Active |
| Horoscope | horoscope.extrachill.com | 11 | `horoscope` | Planned |

### Domain Mapping

- `extrachill.link` → Blog ID 4 (artist.extrachill.com)
- `www.extrachill.link` → Blog ID 4 (artist.extrachill.com)

## Adding a New Site

### If Network Expands

**Steps to add site at Blog ID 12**:

1. **Create WordPress site** at subdomain or domain
2. **Assign Blog ID 12** during creation
3. **Update blog-ids.php**:
   ```php
   if ( ! defined( 'EC_BLOG_ID_NEWSITE' ) ) {
       define( 'EC_BLOG_ID_NEWSITE', 12 );
   }
   ```
4. **Update ec_get_blog_ids()** function:
   ```php
   return array(
       // ... existing entries ...
       'newsite'   => EC_BLOG_ID_NEWSITE,
   );
   ```
5. **Update ec_get_domain_map()** if new domain:
   ```php
   'newsite.extrachill.com' => EC_BLOG_ID_NEWSITE,
   ```
6. **No code changes needed** in other plugins (use function to retrieve)

## Forbidden Patterns

**Do NOT**:
- Hardcode `9` or any blog ID in plugin code (use `ec_get_blog_id()`)
- Hardcode blog ID mapping in plugins (only in blog-ids.php)
- Assume blog ID without verification
- Create per-site copies of blog ID constants
- Skip the try/finally pattern in blog switching

## Performance Considerations

### Constant vs. Function Call

**Constant** (`EC_BLOG_ID_NEWSLETTER`):
- 0 function calls
- Fastest possible access
- Best: use in tight loops if needed

**Function** (`ec_get_blog_id( 'newsletter' )`):
- 1 function call
- Array lookup (~20 bytes memory)
- Acceptable overhead for clarity

**Recommendation**: Use function in production code for maintainability, speed difference is negligible

### Network Options vs. Blog IDs

**Never store blog IDs in options**:
- Blog IDs are infrastructure, not configuration
- Options require database query
- Blog IDs are fixed and known

**Good Use of Network Options**:
- Sendy API keys (extrachill-newsletter)
- Turnstile configuration (extrachill-multisite)
- Feature flags or runtime configuration

## Related Documentation

- [extrachill-multisite AGENTS.md - Blog ID Management](../AGENTS.md#blog-id-management)
- [Root AGENTS.md - Hardcoded Blog IDs](../../AGENTS.md#5-performance-optimization)
- [NETWORK-ARCHITECTURE.md](../../../.github/NETWORK-ARCHITECTURE.md)
- [WordPress Multisite Handbook](https://developer.wordpress.org/plugins/multisite/)
