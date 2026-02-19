# Modern Media Thumbnails - Refactored Structure

## Overview

The plugin has been refactored from a monolithic single-file structure into a well-organized, object-oriented class-based architecture with proper separation of concerns.

## Directory Structure

```
modern-media-thumbnails/
├── index.php                  # Bootstrap file - plugin entry point only
├── css/
│   └── admin.css             # All admin styles (moved from inline)
├── js/
│   └── admin.js              # Admin JavaScript with AJAX handlers
├── includes/
│   ├── Plugin.php            # Main plugin class, initializes all components
│   ├── SystemCheck.php       # System requirement checking (Imagick, WebP, AVIF)
│   ├── ImageSizeManager.php  # Handles detection and retrieval of image sizes
│   ├── FormatManager.php     # Manages format selection settings (Original/WebP/AVIF)
│   ├── ThumbnailGenerator.php # Imagick-based thumbnail generation
│   ├── WordPress/
│   │   ├── UploadHooks.php   # Automatically generate formats on upload
│   │   └── RegenerationManager.php # Regenerate thumbnails for existing images
│   └── Admin/
│       ├── SettingsPage.php  # Admin settings page rendering
│       ├── Ajax.php          # AJAX handlers for regeneration and settings
│       ├── Assets.php        # Script and style enqueuing
│       └── AdminNotices.php  # Admin notice display
└── languages/                # Translation files

```

## Class Organization

### Core Classes

#### **Plugin** (`includes/Plugin.php`)
- **Purpose**: Bootstrap the entire plugin
- **Responsibilities**:
  - Initialize all components and hooks
  - Load text domain for translations
  - Handle plugin activation

#### **SystemCheck** (`includes/SystemCheck.php`)
- **Purpose**: Verify system requirements
- **Methods**:
  - `isImagickAvailable()` - Check if PHP Imagick extension is installed
  - `isWebPSupported()` - Check WebP format support
  - `isAVIFSupported()` - Check AVIF format support

#### **ImageSizeManager** (`includes/ImageSizeManager.php`)
- **Purpose**: Manage image size detection and naming
- **Methods**:
  - `getAllImageSizes()` - Get all registered image sizes from theme and plugins
  - `getImageSizeNames()` - Get readable names for all sizes (with WordPress filter)
  - `getImageSizeName($slug)` - Get readable name for a specific size

#### **FormatManager** (`includes/FormatManager.php`)
- **Purpose**: Handle format selection settings
- **Methods**:
  - `getFormatSettings()` - Get all format settings from database
  - `getSizeFormats($size)` - Get formats for a specific size
  - `updateSizeFormats($size, $formats)` - Update format preferences
  - `isValidSelection($formats)` - Validate at least Original or WebP is selected

#### **ThumbnailGenerator** (`includes/ThumbnailGenerator.php`)
- **Purpose**: Generate thumbnails in various formats using Imagick
- **Methods**:
  - `generateThumbnail($src, $dst, $width, $height, $crop, $format)` - Generic generator
  - `generateWebP(...)` - Generate WebP thumbnail
  - `generateAVIF(...)` - Generate AVIF thumbnail

### WordPress Integration Classes

#### **UploadHooks** (`includes/WordPress/UploadHooks.php`)
- **Purpose**: Hook into WordPress upload process
- **Hooks**: `wp_generate_attachment_metadata`
- **Responsibility**: Automatically generate WebP/AVIF formats when images are uploaded

#### **RegenerationManager** (`includes/WordPress/RegenerationManager.php`)
- **Purpose**: Regenerate thumbnails for existing images
- **Methods**:
  - `regenerateSize($size_name = null)` - Regenerate specific or all sizes
  - `regenerateSizeForAttachment()` - Private helper for per-image regeneration

### Admin Classes

#### **SettingsPage** (`includes/Admin/SettingsPage.php`)
- **Purpose**: Render the admin settings interface
- **Methods**:
  - `render()` - Display settings page with format tiles and size options
  - `registerMenu()` - Register the Settings submenu

#### **Ajax** (`includes/Admin/Ajax.php`)
- **Purpose**: Handle AJAX requests from the admin interface
- **Methods**:
  - `regenerateAll()` - AJAX handler for bulk regeneration
  - `regenerateSize()` - AJAX handler for single-size regeneration
  - `saveFormatSettings()` - AJAX handler for saving format selections
  - `register()` - Register all AJAX hooks

#### **Assets** (`includes/Admin/Assets.php`)
- **Purpose**: Enqueue admin scripts and styles
- **Methods**:
  - `enqueue($hook)` - Enqueue scripts/styles for settings page
  - `register()` - Register the enqueue hook
- **Enqueued Files**:
  - `js/admin.js` - Admin JavaScript
  - `css/admin.css` - Admin styles

#### **AdminNotices** (`includes/Admin/AdminNotices.php`)
- **Purpose**: Display admin notices for system issues
- **Methods**:
  - `display()` - Show warnings if Imagick or WebP not available
  - `register()` - Register the admin notices hook

## Key Design Patterns

### 1. Namespace Organization
- All classes use `ModernMediaThumbnails` namespace
- Subnamespaces: `WordPress` and `Admin` for logical grouping
- Autoloader in `index.php` handles file discovery

### 2. Static Methods
- Most classes use static methods (no instantiation needed)
- Promotes simplicity and direct access to utilities
- Suitable for WordPress plugin context

### 3. Separation of Concerns
- **Image Detection**: `ImageSizeManager`
- **Format Preferences**: `FormatManager`
- **Image Processing**: `ThumbnailGenerator`
- **WordPress Integration**: `UploadHooks`, `RegenerationManager`
- **Admin UI**: `SettingsPage`, `Ajax`, `Assets`, `AdminNotices`

### 4. Error Handling
- Database updates are wrapped in option management
- File operations check existence before processing
- Try-catch blocks in Imagick operations log errors

## File Organization

### CSS (`css/admin.css`)
All admin styles organized by component:
- Resolution box (soft/hard crop visualization)
- Format controls grid layout
- Format tile styling with hover states
- AVIF help tooltip
- Format badges and error messages
- Information section styling

### JavaScript (`js/admin.js`)
Admin interface interactions:
- Tile click handlers with checkbox toggling
- Real-time validation (at least Original or WebP required)
- AJAX communication for regeneration and settings save
- UI state management and error handling

### Plugin Bootstrap (`index.php`)
Minimal entry point featuring:
- Plugin header for WordPress recognition
- PSR-4 autoloader for class discovery
- Plugin initialization call
- Activation hook registration

## Usage Examples

### Check System Requirements
```php
if (SystemCheck::isImagickAvailable() && SystemCheck::isWebPSupported()) {
    // Safe to process images
}
```

### Get Theme Image Sizes
```php
$sizes = ImageSizeManager::getAllImageSizes();
foreach ($sizes as $name => $info) {
    $readable = ImageSizeManager::getImageSizeName($name);
    echo $readable;
}
```

### Get/Update Format Settings
```php
$formats = FormatManager::getSizeFormats('thumbnail');
// $formats = ['original' => true, 'webp' => true, 'avif' => false];

FormatManager::updateSizeFormats('thumbnail', [
    'original' => true,
    'webp' => true,
    'avif' => true
]);
```

### Generate Thumbnails
```php
ThumbnailGenerator::generateWebP(
    '/path/to/original.jpg',
    '/path/to/thumbnail.webp',
    300, 200, false
);
```

### Regenerate Thumbnails
```php
// Regenerate all formats for all images
$count = RegenerationManager::regenerateSize();

// Regenerate only 'thumbnail' size
$count = RegenerationManager::regenerateSize('thumbnail');
```

## Benefits of This Refactoring

1. **Maintainability**: Code is organized into focused classes
2. **Reusability**: Each class can be used independently
3. **Testability**: Smaller units are easier to unit test
4. **Scalability**: New features can be added without cluttering the main file
5. **Professionalism**: Follows WordPress and PHP best practices
6. **Asset Management**: Cleaner separation of CSS, JS, and PHP code
7. **Documentation**: Each class has clear docstrings
8. **Extensibility**: Easy to extend functionality via class inheritance

## Migration Notes

The refactoring maintains 100% backward compatibility:
- All WordPress hooks are registered the same way
- Database option format unchanged (`mmt_format_settings`)
- AJAX endpoints use identical URLs
- Admin page renders identically
- Generated file formats and naming unchanged

## Future Enhancements

Possible improvements enabled by this structure:
- Unit tests for individual classes
- API endpoints for programmatic access
- CLI commands for bulk operations
- Caching layer for performance
- Batch processing for large media libraries
- Custom format configuration
- Scheduled regeneration tasks
