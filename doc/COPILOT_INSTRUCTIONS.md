# Modern Thumbnails - Copilot Instructions

This document defines coding standards and best practices for the Modern Thumbnails WordPress plugin. All code contributions must follow these guidelines.

## Core Requirements

### 1. WordPress Coding Standards
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) strictly
- Use 1 **tab** for indentation (never spaces)
- Add space before opening parenthesis in control structures: `if ( $condition ) {`
- All output must be escaped: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Run PHPCS locally before submitting: `phpcs --standard=WordPress`

### 2. PHP Standards

#### File Structure
- Direct file access prevention on ALL PHP files:
  ```php
  if ( ! defined( 'ABSPATH' ) ) {
      exit;
  }
  ```
- Namespace declaration must be **immediately after** `<?php` tag (before any checks)
- Use PSR-4 namespaces: `namespace ModernMediaThumbnails\...;`

#### PHPDoc Comments
- Every class requires PHPDoc header with `@package` and `@since` tags
- Every public method requires description, `@param`, and `@return` tags
- Example:
  ```php
  /**
   * Brief description of the function.
   *
   * @since 0.0.1
   * @param string $parameter_name Description of parameter.
   * @return bool True if successful, false otherwise.
   */
  public function example( $parameter_name ) { }
  ```

#### Naming Conventions
- **Constants**: `UPPERCASE_WITH_UNDERSCORES` (plugin prefix: `MMT_`)
- **Functions**: `lowercase_with_underscores` (prefix with plugin slug: `mmt_`)
- **Classes**: `PascalCase` with namespace
- **Methods**: `camelCase` with underscore separator in names where logical
- **Variables**: `snake_case`
- **Hooks**: Prefix all custom hooks with `mmt_` and use lowercase with underscores

#### String Syntax
- Single quotes for strings without interpolation: `'simple string'`
- Double quotes for strings with variables: `"String with $variable"`
- Use `.` for concatenation in multi-line strings
- **Never use heredoc or nowdoc syntax** (`<<<`) — use standard string concatenation instead

### 3. Security Requirements

#### Input Sanitization (CRITICAL)
- **ALWAYS** use `wp_unslash()` BEFORE sanitization:
  ```php
  $value = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
  ```
- Common sanitization functions:
  - `sanitize_text_field()` — General text input
  - `sanitize_key()` — Array keys
  - `intval()` — Integer values
  - `sanitize_email()` — Email addresses
  - `wp_kses_post()` — Post content with allowed HTML

#### Output Escaping
- Always escape before echo: `echo esc_html( $var );`
- **Attribute escaping**: `esc_attr()` for HTML attributes
- **URL escaping**: `esc_url()` for URLs and hrefs
- **HTML content**: `wp_kses_post()` for user-generated content with HTML

#### Database Queries
- **NEVER** concatenate SQL queries directly
- Always use `$wpdb->prepare()` for parameterized queries
- Cache queries using `wp_cache_get()` and `wp_cache_set()` with 1-hour default TTL
- Query cache keys should be md5 hashes of unique identifiers

#### Nonce Verification
- Use `wp_verify_nonce()` for forms and AJAX requests
- Use `check_ajax_referer()` for additional AJAX security
- Include `phpcs:ignore WordPress.Security.NonceVerification` comments for intentional exceptions (internal navigation)

#### User Capabilities
- Always check `current_user_can()` before sensitive operations
- Example: `if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }`

### 4. CSS Standards

#### CSS Files & Organization
- Keep all styles in `/css/` directory with `.css` extension
- Use CSS class names for all styling — **NEVER use inline CSS**
- Exception: Only use inline styles in dynamic scenarios where classes cannot be applied (rare cases)

#### Class Naming
- Use BEM (Block Element Modifier) naming convention for clarity
- Prefix all classes with `mmt-` (Modern Thumbnails):
  - Block: `.mmt-settings-page`
  - Element: `.mmt-settings-page__header`
  - Modifier: `.mmt-settings-page--active`
- Keep class names semantic and descriptive

#### CSS Property Ordering
All CSS properties must be organized in the following order within each rule:

1. **Box Model** (alphabetically within group)
   - border, border-radius, border-bottom, border-left, border-right, border-top
   - height, margin, margin-bottom, margin-left, margin-right, margin-top
   - max-height, max-width, min-height, min-width, padding, padding-bottom, padding-left, padding-right, padding-top
   - width

2. **Background** (alphabetically within group)
   - background, background-attachment, background-color, background-image, background-position, background-repeat, background-size

3. **Layout** (alphabetically within group)
   - align-content, align-items, bottom, display, flex-direction, flex-wrap, grid-gap, grid-template-columns, grid-template-rows
   - justify-content, justify-items, left, position, right, top, z-index

4. **Typography** (alphabetically within group)
   - color, font, font-family, font-size, font-style, font-weight, letter-spacing, line-height, text-align, text-decoration, text-overflow, text-transform, white-space, word-wrap

5. **Everything Else** (alphabetically)
   - All other properties in strict alphabetical order (box-shadow, cursor, opacity, pointer-events, transform, transition, visibility, etc.)

#### CSS Examples
```css
/* GOOD: Properly ordered properties with class-based styling */
.mmt-settings-page {
    /* Box Model */
    max-width: 1024px;
    padding: 20px;
    width: 100%;
    
    /* Background */
    background-color: #ffffff;
    
    /* Layout */
    display: flex;
    flex-direction: column;
    
    /* Typography */
    color: #333333;
    font-size: 16px;
    
    /* Everything Else */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.mmt-settings-page__header {
    /* Box Model */
    margin-bottom: 30px;
    margin-top: 0;
    padding: 15px;
    
    /* Typography */
    font-size: 24px;
    font-weight: bold;
}

.mmt-settings-page--loading {
    /* Everything Else */
    opacity: 0.6;
    pointer-events: none;
}

/* BAD: Inline styles (avoid) */
<div style="padding: 20px; max-width: 1024px;"> <!-- Don't do this -->

/* BAD: Random property order (avoid) */
.bad-example {
    /* Box Model */
    margin: 10px;
    padding: 20px;
    
    /* Background */
    background: white;
    
    /* Layout */
    display: flex;
    
    /* Typography */
    font-size: 14px;
}
```

### 5. JavaScript Standards
- Use consistent indentation (1 tab, matching PHP)
- Add JSDoc comments for functions
- Use `const` and `let` (never `var`)
- Namespace custom JS objects with plugin prefix: `ModernThumbnails.`
- Always enqueue scripts with `wp_enqueue_script()` and include dependencies
- Add `wp_localize_script()` for passing PHP data to JavaScript

### 6. Internationalization (i18n)
- Text domain: `'modern-thumbnails'` (ALWAYS)
- Translation functions:
  - `__( 'text', 'modern-thumbnails' )` — Simple text
  - `esc_html__( 'text', 'modern-thumbnails' )` — Text with HTML escape
  - `_n( 'singular', 'plural', $count, 'modern-thumbnails' )` — Pluralization
  - `_x( 'text', 'context', 'modern-thumbnails' )` — Context-aware
- Domain path: `/languages/` (must exist)
- Generate translation template file (.pot) during releases

### 7. Version Management
- Current version: **0.0.5**
- Update in all three locations:
  1. `index.php` — Plugin header `Version:` field
  2. `index.php` — `MMT_PLUGIN_VERSION` constant
  3. `includes/Helpers.php` — `mmt_get_version()` default fallback
  4. `README.md` — `Stable Tag:` field and changelog
- Semantic versioning: `major.minor.patch`

### 8. File Organization

```
modern-thumbnails/
├── index.php                          # Main plugin file
├── README.md                          # WordPress.org readme
├── LICENSE                            # GPL v2 license
├── nginx.conf                         # Nginx configuration template
├── languages/
│   └── modern-thumbnails.pot          # Translation template
├── doc/                               # Documentation (hidden from users)
│   └── *.md                           # Internal documentation
├── includes/                          # Plugin classes
│   ├── Plugin.php
│   ├── Helpers.php
│   ├── ApacheConfigCheck.php
│   ├── NginxConfigCheck.php
│   ├── Admin/
│   │   └── *.php                      # Admin-related classes
│   └── WordPress/
│       └── *.php                      # WordPress hooks & integration
├── css/                               # Stylesheets
│   └── *.css                          # Class-based styling only
├── js/                                # JavaScript
│   └── *.js                           # Enqueued via wp_enqueue_script()
└── assets/                            # Images and other assets
```

### 9. Commits & Documentation
- Write clear commit messages describing what changed
- Update `README.md` changelog for each release
- Keep `/doc/` folder for internal technical documentation
- Document complex functions with detailed PHPDoc comments

### 10. Testing & Validation

Before committing:
- Run PHPCS: `phpcs --standard=WordPress` (strict compliance)
- Test in WordPress admin: No fatal errors or warnings
- Verify nonces on AJAX endpoints
- Check database query preparation
- Validate all superglobal handling with `wp_unslash()` + sanitization

### 11. Common Pitfalls (AVOID)

❌ **Don't do this:**
```php
// Inline CSS
echo '<div style="color: red;">Error</div>';

// Unslashed POST/GET
$value = $_POST['field'];

// Unescaped output
echo $variable;

// Hard-coded strings without i18n
echo 'Some label';

// Heredoc syntax
return <<<TEXT
Some text
TEXT;

// Direct database queries
$wpdb->query( "SELECT * FROM $table WHERE id = $id" );

// Missing ABSPATH check
if ( ! defined( 'ABSPATH' ) ) { exit; }
// ... code ...
namespace MyNamespace;  // WRONG ORDER

// Inline JavaScript
<button onclick="myFunction()">Click</button>
```

✅ **Do this instead:**
```php
// CSS class
echo '<div class="mmt-error">' . esc_html( 'Error' ) . '</div>';

// Properly sanitized
$value = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';

// Escaped output
echo esc_html( $variable );

// i18n text
echo esc_html__( 'Some label', 'modern-thumbnails' );

// String concatenation
return 'Some text with ' . $variable . ' embedded.';

// Prepared query
$wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $id ) );

// Correct namespace order
namespace ModernMediaThumbnails;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use SomeClass;

// Event handlers via wp_enqueue_script + JS
// Handle in JavaScript or via data attributes
```

### 12. Plugin Metadata

- **Plugin Name**: Modern Thumbnails
- **License**: GPL v2 or later
- **License URI**: https://www.gnu.org/licenses/gpl-2.0.html
- **Text Domain**: modern-thumbnails
- **Domain Path**: /languages
- **Requires WordPress**: 6.0+
- **Requires PHP**: 7.4+
- **Required Extension**: ImageMagick (PHP Imagick extension)

---

## Summary Checklist

Before submitting code:
- [ ] PHPCS passes with WordPress standard
- [ ] No inline CSS (use class names in `css/` files)
- [ ] All output escaped properly
- [ ] All input sanitized with `wp_unslash()` first
- [ ] Database queries use `$wpdb->prepare()`
- [ ] Nonces verified for sensitive operations
- [ ] PHPDoc comments on all classes/public methods
- [ ] i18n text domain used consistently
- [ ] File access check present (ABSPATH)
- [ ] Namespace declared immediately after `<?php`
- [ ] Version updated in all 4 locations
- [ ] No heredoc/nowdoc syntax
- [ ] ABSPATH check before any code logic
- [ ] Capability checks for admin operations
