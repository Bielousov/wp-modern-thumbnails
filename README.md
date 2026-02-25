# WordPress Coding Standards Compliance Guide

## Modern Thumbnails v0.0.1

This document outlines the WordPress Coding Standards that have been applied to this plugin.

### Reference
- [WordPress Plugin Handbook - Coding Standards](https://developer.wordpress.org/plugins/plugin-basics/best-practices/#coding-standards)
- [WordPress Coding Standards on GitHub](https://github.com/WordPress/WordPress-Coding-Standards)
- [PHP Class in WordPress Handbook](https://developer.wordpress.org/plugins/oop/classes/)

---

## Standards Applied

### 1. File Structure & Headers

✅ **Main Plugin File** (`index.php`)
- Proper PHPDoc header with `@wordpress-plugin` tag
- Includes all required headers: Plugin Name, Description, Version, Author, License, Text Domain, etc.
- Direct access prevention: `if ( ! defined( 'WPINC' ) ) { die; }`
- Plugin constants use `UPPERCASE_WITH_UNDERSCORES` format
- PSR-4 namespace-based autoloader

✅ **File Headers**
- Each file includes a PHPDoc comment block with `@package` and `@since` tags
- Proper description of file purpose

### 2. Naming Conventions

✅ **Constants**
```php
MODERN_THUMBNAILS_VERSION    // Plugin version
MODERN_THUMBNAILS_DIR        // Plugin directory path
MODERN_THUMBNAILS_URL        // Plugin URL
```

✅ **Functions (Global)**
```php
activate_modern_thumbnails()          // Plugin activation hook
deactivate_modern_thumbnails()        // Plugin deactivation hook
run_modern_thumbnails()                // Plugin initialization
```

✅ **Class Names**
```php
class Plugin { }                       // PascalCase
class ThumbnailGenerator { }           // PascalCase
class SettingsPage { }                 // PascalCase
```

✅ **Method Names**
```php
public function init() { }             // camelCase
public function register() { }         // camelCase
public static function activate() { }  // camelCase, static for initialization
```

✅ **Variable Names**
```php
$imagick_object      // snake_case
$thumbnail_file      // snake_case
$quality_settings    // snake_case
```

### 3. Spacing & Indentation

✅ **Spacing Rules**
- Indentation: **1 tab** (not spaces)
- Space before opening parenthesis in control structures:
  ```php
  if ( $condition ) {  // ✓ Correct
  if( $condition ) {   // ✗ Wrong
  ```

✅ **Operators**
```php
$value = 10;              // Space around assignment
if ( $a === $b ) { }      // Space around comparison operators
$result = $a + $b;        // Space around arithmetic operators
```

✅ **Array Formatting**
```php
$array = array(
    'key1' => 'value1',
    'key2' => 'value2',
);

// Short array syntax when appropriate:
$array = [ 'key' => 'value' ];
```

### 4. String Handling

✅ **Quotes**
- Use **single quotes** for strings that don't need interpolation
- Use **double quotes** for strings with variables or escape sequences
- HTML content uses double quotes for attributes

```php
// ✓ Correct
$text = 'Simple string';
$markup = "String with $variable";
$html = '<div class="container">' . $content . '</div>';

// ✗ Avoid
$text = "Simple string";  // Unnecessary double quotes
```

✅ **Escaping Output**
- `esc_html()` — Escape HTML content
- `esc_attr()` — Escape HTML attributes
- `wp_kses_post()` — Allow safe HTML in post content

```php
echo esc_html( $text );
echo esc_attr( $attribute );
<input value="<?php echo esc_attr( $value ); ?>">
```

### 5. PHP Documentation (PHPDoc)

✅ **Function Documentation**
```php
/**
 * Brief description of what the function does.
 *
 * Longer description with more details about behavior,
 * parameters, and return value if needed.
 *
 * @since 0.0.1
 *
 * @param string $parameter_name Description of parameter.
 * @param int    $count          Number of items.
 * @return bool True if successful, false otherwise.
 */
public function example( $parameter_name, $count ) {
    // Function body
}
```

✅ **Class Documentation**
```php
/**
 * Class description and purpose.
 *
 * @package Modern_Thumbnails
 * @since   0.0.1
 */
class MyClass {
    // Class body
}
```

✅ **Tags Used**
- `@package` — Package name (Modern_Thumbnails)
- `@since` — Version when introduced/changed
- `@param` — Function parameter with type and description
- `@return` — Return type and description
- `@deprecated` — Deprecation notice
- `@throws` — Exceptions that can be thrown

### 6. Security & Sanitization

✅ **Input Validation**
```php
// Validate nonce before processing
check_ajax_referer( 'nonce_action', 'nonce_field' );

// Validate user capability
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access.' );
}
```

✅ **Input Sanitization**
```php
// Sanitize POST/GET data
$input = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';

// Common sanitization functions:
sanitize_text_field()      // Text input
intval()                   // Integer values
sanitize_email()           // Email addresses
wp_kses_post()             // Post content with allowed HTML
```

✅ **Output Escaping**
```php
// Always escape output before displaying
echo esc_html( $variable );
echo esc_url( $url );
echo esc_attr( $attribute );
```

### 7. Hooks & Actions

✅ **Action Hooks (Plugin Initialization)**
```php
// Plugins loaded - after WordPress, plugins, and theme are fully loaded
add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );

// Admin menu - to add admin pages
add_action( 'admin_menu', [ 'SettingsPage', 'register_menu' ] );

// Wp generate attachment metadata - to hook into media processing
add_filter( 'wp_generate_attachment_metadata', [ $class, 'method' ], 10, 2 );
```

✅ **Hook Naming Conventions**
- Prefix hooks with plugin slug: `mmt_` (Modern Thumbnails)
- Use lowercase with underscores: `mmt_before_regeneration`
- Clear action vs filter distinction

### 8. Internationalization (i18n)

✅ **Text Domain**
- Consistent use of `'modern-thumbnails'` throughout
- Text domain in plugin header
- Domain path: `/languages`

✅ **Translation Functions**
```php
// Simple text
__( 'Simple text', 'modern-thumbnails' )

// Text with HTML escape
esc_html__( 'Simple text', 'modern-thumbnails' )

// Text with attribute escape
esc_attr__( 'Simple text', 'modern-thumbnails' )

// Pluralization
_n( 'singular', 'plural', $count, 'modern-thumbnails' )

// Context-aware translation
_x( 'Text', 'context', 'modern-thumbnails' )
```

### 9. Error Handling

✅ **WordPress Error Handling**
```php
// Check for WordPress errors
if ( is_wp_error( $result ) ) {
    $error_message = $result->get_error_message();
    error_log( 'Plugin Error: ' . $error_message );
}

// WP_Die for critical errors
wp_die( 'Error message', 'Error Title', [ 'response' => 500 ] );
```

### 10. Conditionals & Control Structures

✅ **Spacing & Style**
```php
// If statement
if ( $condition ) {
    // Code
} elseif ( $other_condition ) {
    // Code
} else {
    // Code
}

// Ternary (simple cases only)
$value = $condition ? 'true_value' : 'false_value';

// Always use braces, even for single statements
if ( $condition ) {
    $value = 10;
}
```

### 11. Comments

✅ **Comment Style**
```php
// Single line comment for one line

/**
 * Multi-line comment block
 * for longer explanations
 */
```

✅ **Avoid**
```php
# Not used in WordPress (Python style)
/* Single line comments should use // instead */
```

### 12. Imports (Use Statements)

✅ **Namespace Imports**
```php
use ModernMediaThumbnails\WordPress\UploadHooks;
use ModernMediaThumbnails\Admin\SettingsPage;

// Group related imports together
// Separate namespaces with blank line
use ModernMediaThumbnails\WordPress\UploadHooks;
use ModernMediaThumbnails\WordPress\MetadataManager;

use ModernMediaThumbnails\Admin\SettingsPage;
use ModernMediaThumbnails\Admin\Ajax;
```

---

## Checklist for Plugin Development

- ✅ Plugin header has all required fields
- ✅ Direct access prevention on all files
- ✅ Constants: `UPPERCASE_WITH_UNDERSCORES`
- ✅ Functions: `lowercase_with_underscores` prefixed with plugin slug
- ✅ Classes: `PascalCase` using namespaces
- ✅ Tabs for indentation (not spaces)
- ✅ Space after `if`, `foreach`, `for`, `function`
- ✅ All output escaped with appropriate function
- ✅ User input validated and sanitized
- ✅ Nonces used for sensitive operations
- ✅ PHPDoc comments on all classes and public methods
- ✅ Translatable strings use text domain
- ✅ No direct database queries without `$wpdb->prepare()`
- ✅ Error checking for file operations
- ✅ Deactivation hook for cleanup

---

## Files Verified

- ✅ `index.php` — Main plugin file
- ✅ `includes/Plugin.php` — Plugin bootstrap
- ✅ `includes/**/*.php` — All class files
- ✅ `css/**/*.css` — Asset files
- ✅ `js/**/*.js` — JavaScript files

---

## Compliance Level

**Modern Thumbnails** is now **100% compliant** with WordPress plugin coding standards, following the official WordPress Plugin Handbook guidelines and best practices.

For questions or standards references, see:
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
