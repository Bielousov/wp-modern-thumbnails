# Modern Thumbnails

**Modern Thumbnails** is a WordPress plugin that automatically generates optimized WebP thumbnails for all your media uploads, reducing file sizes by 25-35% without any loss of visual quality.

## Overview

Unlike other thumbnail plugins that simply generate additional image files, Modern Thumbnails works smarter. It automatically replaces generated JPG/PNG thumbnails with optimized WebP format versions, resulting in faster page loads and reduced server storage.

## Key Features

✅ **Automatic WebP Generation** — Every image upload automatically creates WebP versions  
✅ **Theme-Aware** — Works with all theme-defined image sizes  
✅ **One-Click Regeneration** — Regenerate WebP for existing media  
✅ **Quality Control** — Fine-tune compression levels  
✅ **Server Configuration** — Built-in nginx/Apache setup guides  
✅ **System Status** — Comprehensive health check dashboard  
✅ **EXIF Management** — Control metadata preservation  

## Quick Start

1. **Install & Activate** — Upload the plugin and activate it
2. **Check System Status** — Verify Imagick support
3. **Configure Server** (Optional) — Add configuration to nginx/Apache for automatic WebP serving
4. **Adjust Settings** — Set quality levels and options
5. **Regenerate** — Regenerate existing thumbnails

## Requirements

- **WordPress:** 5.0+
- **PHP:** 7.4+
- **Imagick:** PHP Imagick extension
- **Web Server:** nginx or Apache (optional, for automatic format serving)

## Plugin Features

### WebP Thumbnail Generation
- Automatically generates WebP versions for all registered image sizes
- File size reduction: typically 25-35% smaller than JPEG/PNG
- Respects theme crop settings and aspect ratios
- Supports all image formats: JPEG, PNG, GIF, WebP

### Media Library Integration
- **Regenerate Button** — Generate WebP for individual images
- **Bulk Actions** — Regenerate multiple images at once
- **Format Status** — See available formats for each image
- **Real-time Feedback** — Progress indicators during regeneration

### Server Configuration Support

#### Nginx
- Automatic nginx detection
- Content negotiation configuration
- Copy-ready configuration snippets
- Serves WebP based on browser Accept header

#### Apache
- Automatic Apache/mod_rewrite detection
- .htaccess configuration generation
- Copy-ready configuration snippets
- Automatic browser detection for format selection

### Settings & Control
- **Quality Settings**
  - WebP quality (default: 80)
  - Original format quality (default: 85)
  - AVIF quality for future use (default: 75)

- **EXIF Management**
  - Keep EXIF in source images (default: yes)
  - Strip EXIF from thumbnails (default: yes, saves space)
  - Optional EXIF in post thumbnails

- **Format Options**
  - Keep original JPEG/PNG alongside WebP (default: no)
  - Future: AVIF format generation
  - Future: GIF to video conversion

### System Status Dashboard
Comprehensive monitoring including:
- Server type detection (nginx/Apache)
- Imagick availability
- WebP/AVIF format support
- Configuration status
- Thumbnail generation statistics

## How It Works

### On Upload
1. WordPress generates standard thumbnails
2. Modern Thumbnails automatically creates WebP versions
3. Original thumbnails are deleted (by default)
4. Only WebP versions are stored

### Performance Impact
- **File Size:** 25-35% reduction typical
- **Page Load:** 5-15% faster (varies by content)
- **Storage:** 25-35% less disk usage for thumbnails
- **Bandwidth:** Proportional to file size reduction

## Server Configuration

### Nginx
Enable automatic WebP serving by adding a location block to your nginx config:

```nginx
include /path/to/plugin/nginx.conf;
```

Or copy the configuration manually from the System Status tab.

### Apache
Enable automatic WebP serving by adding this to your `.htaccess`:

```apache
# Copy from System Status → View Configuration
```

**Note:** Configuration is optional. Images will serve as WebP without it, but the server won't automatically serve original formats to older browsers.

## Extensibility

The plugin provides hooks and filters for developers:

- `mmt_quality_settings` — Filter quality settings
- `mmt_image_sizes` — Filter detected image sizes
- `wp_generate_attachment_metadata` — Integrated with standard WordPress image generation

## Compatibility

- ✅ All modern WordPress themes
- ✅ nginx and Apache servers
- ✅ Shared hosting, VPS, and dedicated servers
- ✅ Docker and cloud platforms
- ✅ PHP 7.4+

## Limitations

### Current Version (1.0.0)
- AVIF generation (marked "Coming Soon")
- GIF to video conversion (marked "Coming Soon")
- Manual regeneration trigger (bulk actions and individual buttons)

## Security

- Original source images are **never modified**
- Only auto-generated thumbnails are optimized
- Settings restricted to administrators
- AJAX operations include nonce verification
- No custom database tables

## Version History

### 1.0.0 (February 2026)
- Initial release
- WebP thumbnail generation
- Settings and quality control
- Server detection and configuration
- Media library integration
- System status monitoring
- Comprehensive documentation

## Documentation

- **System Status Tab** — Built-in health checks and configuration guidance
- **Settings Page** — Inline help for each setting
- **Release Notes** — Detailed feature and upgrade information (see RELEASE-NOTES.md)
- **Structure Documentation** — Developer reference (see STRUCTURE.md)

## License

See LICENSE file for details.

## Changelog

See RELEASE-NOTES.md for detailed version history and feature information.

---

**Modern Thumbnails** — Making web images faster, one thumbnail at a time.

For more information, see the [Release Notes](RELEASE-NOTES.md).
