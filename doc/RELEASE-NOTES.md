# Modern Thumbnails - Release Notes

## Version 0.1.2

**Release Date:** March 1, 2026

### Highlights

- Fixed PHPCS WordPress coding standards violations
- Unified nonce validation across all regeneration entry points (bulk actions, media modal, media details, queue processor, settings page)
- Updated mmt_regenerate_single response to include absolute media file path for progress display
- Enhanced progress indicators to show last processed filename on all operations (bulk actions, media modal, media details, queue processor)
- Renamed internal response field from `file_path` to `media_path` for semantic clarity
- Improved user experience with real-time feedback showing which file is currently being processed
- All AJAX handlers now use consistent `mmt_regenerate_nonce` action across entire plugin

---

## Version 0.1.0

**Release Date:** February 27, 2026

### Highlights

- Fixed critical metadata persistence bug where attachment dimensions weren't saving to database after thumbnail regeneration
- Created new `includes/Helpers.php` module with 17 reusable utility functions (AJAX permissions, image validation, dimension handling, metadata storage)
- Refactored `Ajax.php` to use centralized helpers, removing 34+ lines of duplicate code
- Consolidated MIME→format mapping from 2 duplicates into single helper function
- Enhanced batch processing UI to update image dimensions and attributes in real-time
- Improved code maintainability while maintaining 100% backward compatibility

---

## Version 0.0.5

**Release Date:** February 26, 2026

### Highlights

- Bumped plugin to **0.0.5** with documentation and packaging improvements
- Replaced all heredoc/nowdoc snippets with safe string concatenation for PHPCS compliance
- Finalized CSS property ordering guidelines and updated examples across docs
- Added `doc/COPILOT_INSTRUCTIONS.md` and guidance for local symlink usage (do not commit hidden files)
- Added "Restore WordPress Original Thumbnails" feature: admin UI button that removes generated WebP/AVIF files and restores attachment metadata to original filenames; when originals are missing the plugin will generate JPEG thumbnails and update metadata — the restore flow bypasses Modern Thumbnails filters to ensure WordPress serves standard thumbnails.
- Minor bug fixes and documentation cleanups

---

## Version 0.0.3

**Release Date:** February 25, 2026

### 📚 Documentation & User Experience

#### Comprehensive Setup Guide
- **Created:** New `REQUIREMENTS.md` file with complete setup instructions
  - Explains what ImageMagick is and why it's required
  - Multiple methods to verify Imagick installation
  - Step-by-step installation guides for cPanel, Plesk, VPS, and command-line
  - Troubleshooting common issues
  - Clarifies difference between ImageMagick and GD Library

#### Enhanced UI Warnings & Alerts
- **Added:** Critical error alert on settings page if Imagick is not installed
  - Prominently displays at the top of Plugin Settings tab
  - Directs users to System Status for verification and details
  - Uses WordPress standard error notice styling

- **Enhanced:** System Status tab with improved Imagick messaging
  - Imagick row now clearly marked as "REQUIRED" in bold
  - Detailed description explaining critical nature of the requirement
  - Alert banner appears only when Imagick is missing (not when installed)

#### Plugin Header & Descriptions
- **Updated:** Main plugin description to include ImageMagick requirement
  - Description now explicitly states: "Requires ImageMagick PHP extension"
  - Header PHPDoc includes prominent warning comment

#### Better Error Prevention
- Users are now informed immediately (before activation) that ImageMagick is required
- Clear instructions on where to check and what to do if missing
- Prevents installation on incompatible hosting

---

## Version 0.0.2

**Release Date:** February 25, 2026

### ⚠️  IMPORTANT: System Requirements
This plugin **REQUIRES** the PHP **Imagick** (ImageMagick) extension to function. Without it:
- The plugin will not work
- Thumbnails will not be generated
- Media uploads may be affected

**Before using this plugin:**
1. Check if Imagick is installed: Go to **Media → Modern Thumbnails → System Status** after activation
2. If Imagick is NOT installed (shows red ✗), contact your hosting provider to enable it
3. Without Imagick, please do not activate this plugin

See the [Requirements](#-requirements) section below for full details.

### 🐛 Bug Fixes

#### Settings & Data Persistence
- **Fixed:** Settings not being saved when toggling checkboxes
  - Issue: EXIF settings (`keep_exif`, `keep_exif_thumbnails`) were not being collected by JavaScript
  - Solution: Updated `saveSettings()` function to include all form fields
  
- **Fixed:** Double toast notification when toggling "Generate WordPress Default Thumbnails"
  - Issue: Two overlapping event handlers were both calling `saveSettings()`
  - Solution: Consolidated event handlers to fire only once per toggle

#### Dynamic UI Updates
- **Fixed:** Footer sections not displaying when enabling checkboxes without page refresh
  - Issue: Quality slider and EXIF sub-settings only rendered in HTML if parent checkbox was enabled on load
  - Solution: Always render footer HTML but use `display: none` when disabled, allowing JavaScript to show/hide dynamically

#### Notifications
- **Fixed:** Toast notifications shifting page content downward
  - Issue: Success notifications used WordPress notice styling which inserted into document flow
  - Solution: Implemented fixed-position toast notifications that float over content without affecting layout

#### JavaScript Syntax
- **Fixed:** JavaScript syntax error in toast function
  - Issue: Multi-line string literal caused "Invalid or unexpected token" error
  - Solution: Converted to single-line string concatenation

### ✨ Improvements

#### Code Quality
- **Updated:** Text domain from `modern-media-thumbnails` to `modern-thumbnails` (matches plugin slug)
  - Applied to all PHP files: SettingsPage, Ajax, AdminNotices, MediaDetails, Assets, BulkActions, MediaSettings
  
- **Updated:** Plugin method names to follow WordPress snake_case convention
  - `loadTextDomain()` → `load_text_domain()`
  - `addPluginActionLinks()` → `add_plugin_action_links()`
  - Updated all references throughout codebase
  
- **Improved:** Code spacing and formatting to meet WordPress coding standards
  - Added proper spacing in conditionals: `if ( condition )` instead of `if(condition)`
  - Improved array syntax: `[ Class::class, 'method' ]`
  - Enhanced function parameter spacing

#### Notifications & UX
- **Improved:** Toast notifications now close automatically when a new one appears
  - Prevents multiple success toasts from stacking on screen
  - Better experience when users make rapid successive changes
  
- **Improved:** Toast animations with smooth opacity and transform transitions
  - 0.3 second fade-in/out transitions
  - Better visual feedback for actions

#### Accessibility
- **Improved:** Added ARIA attributes to toast notifications
  - `role="status"` for appropriate screen reader announcements
  - `aria-live="polite"` for non-intrusive updates
  
- **Improved:** Mobile responsiveness for toast notifications
  - Adjusted positioning on small screens (< 600px width)

### 📋 Technical Details

#### Database & Settings
- Settings saved via AJAX with proper nonce verification
- Full error handling with user-friendly error messages
- Settings persist immediately without page reload

#### Console Logging
- Added detailed console logging for debugging:
  - Form data collection
  - Nonce validation
  - AJAX request/response tracking
  - Settings save confirmation
  
### 🎯 User-Facing Changes
- Settings now save instantly with visual confirmation (fixed-position toast)
- Toggling EXIF settings immediately shows/hides sub-options
- No page refreshes needed for settings to take effect
- Better error feedback if connection fails

---

## Version 0.0.1

**Release Date:** February 25, 2026

### Welcome to Modern Thumbnails!

Modern Thumbnails is a powerful WordPress plugin that automatically generates optimized WebP thumbnails for all your media uploads. Unlike other thumbnail plugins that simply generate additional files, Modern Thumbnails intelligently replaces generated thumbnails with smaller, faster-loading WebP versions—often 25-35% smaller than the original JPEG/PNG files.

---

## ✨ Key Features

### 🚀 Automatic WebP Generation
- **Always enabled by default** — Every image upload automatically generates WebP versions
- **Theme-aware** — Respects all theme-defined thumbnail sizes with proper aspect ratios
- **One-click regeneration** — Easily regenerate WebP versions for existing media via the Media Library
- **Batch regeneration** — Regenerate all thumbnails for an image at once

### 🎨 Multiple Format Support
- **WebP Format** — Dramatically smaller file sizes with excellent quality (default enabled)
- **AVIF Format** — Next-generation image format with superior compression (coming in future releases)
- **Keep Original** — Optionally preserve original JPEG/PNG thumbnails alongside WebP

### ⚙️ Flexible Settings
- **Independent quality control** — Separate quality settings for WebP, original formats, and AVIF
  - WebP: Default quality 80 (0-100)
  - Original: Default quality 85 (0-100)
  - AVIF: Default quality 75 (0-100, when available)
  
- **EXIF metadata preservation** — Choose whether to keep EXIF data in images
  - Source images: Preserves EXIF by default
  - Thumbnails: Strips EXIF by default (saves space)
  - Post thumbnails: Option to preserve EXIF for featured images

### 🖼️ Media Library Integration
- **Regenerate button** — Added directly to each media item's details panel
- **Format status indicator** — See which formats are available for each image
- **Bulk operations** — Regenerate multiple images at once
- **Real-time feedback** — Live progress indicators during regeneration

### 🌐 Server Configuration Support

#### For Nginx Users
- Intelligent nginx detection
- Automatic configuration code generation
- Copy-to-clipboard configuration snippets
- Detailed setup instructions
- Content negotiation for serving WebP/AVIF based on browser support

#### For Apache Users
- Apache mod_rewrite detection
- Automatic .htaccess configuration generation
- Copy-to-clipboard configuration snippets
- Detailed setup instructions with backup warnings
- Automatic browser detection (Accept header) for format selection

### 📊 System Status Dashboard
Comprehensive status monitoring including:
- **Server Information** — Detects nginx/Apache and displays version
- **System Requirements** — Checks for Imagick library availability
- **Format Support** — Shows which image formats your server supports (WebP, AVIF)
- **Configuration Status** — Displays whether nginx/Apache content negotiation is configured
- **Media Statistics** — Shows thumbnail generation status for all registered image sizes

---

## 🔧 Technical Specifications

### ⚠️  Requirements (CRITICAL)

#### Required
- **ImageMagick/Imagick:** PHP Imagick extension **MUST be installed and enabled**
  - This is not optional—the plugin will NOT function without it
  - Contact your hosting provider to enable this extension
  - Check status in System Status tab after installing
- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher

#### Optional (Recommended)
- **Web Server:** nginx or Apache (for optimal performance with content negotiation)
- **ImageMagick CLI** (not required, but useful for advanced operations)

### Supported Image Formats
- **Source:** JPEG, PNG, GIF, WebP
- **Generated:** WebP (always), AVIF (future), Original format (optional)

### Supported Thumbnail Sizes
- Automatically generates WebP for **all theme-defined image sizes**
- Works with custom post types and custom-registered image sizes
- Respects theme crop settings and aspect ratios

---

## 📋 Default Behavior

### On Image Upload
1. WordPress generates standard thumbnails (JPEG/PNG)
2. Modern Thumbnails automatically generates WebP versions
3. Original thumbnails are deleted (JPEG/PNG)
4. Only WebP versions remain (unless "Keep Original" is enabled)

### On Activation
- System checks are performed automatically
- Configuration status is detected for your server
- Admin notices appear if configuration is missing
- Settings are initialized with sensible defaults

---

## 🎯 Use Cases

### Best For:
- **High-traffic sites** — Dramatically reduce bandwidth usage
- **Large media libraries** — Serve optimized images instantly
- **Performance-focused** — Lower file sizes = faster page loads
- **Modern audiences** — 99% of users have WebP support
- **Storage-constrained** — Reduce disk usage by 25-35%

### Performance Impact:
- **Typical savings:** 25-35% reduction in thumbnail file size
- **Page load improvement:** Typically 5-15% faster (varies by content)
- **Disk usage reduction:** 25-35% less storage for thumbnails
- **Bandwidth savings:** Proportional to file size reduction

---

## 📖 Getting Started

### 1. Installation
1. Upload the `modern-thumbnails` folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress admin panel

### 2. Check System Requirements
1. Go to **Media → Modern Thumbnails → System Status**
2. Verify that Imagick is available
3. Check that WebP format is supported

### 3. Configure Your Server (Optional but Recommended)
1. Check your server type in System Status (nginx or Apache)
2. Copy the provided configuration
3. Add it to your server configuration file:
   - **nginx:** `/etc/nginx/sites-enabled/default`
   - **Apache:** Root `.htaccess` file
4. Restart your server

### 4. Adjust Settings
1. Go to **Media → Modern Thumbnails → Settings**
2. Configure quality levels as needed
3. Decide on EXIF metadata handling
4. Choose whether to keep original JPEG/PNG files

### 5. Regenerate Existing Thumbnails
1. Go to **Media Library**
2. Click "Regenerate" on any image to create WebP versions
3. Use bulk actions to regenerate multiple images at once

---

## 🔄 Updating from Earlier Versions

If you previously used a development version:
1. Deactivate the plugin
2. Delete the old plugin folder
3. Upload the new version
4. Reactivate the plugin
5. No data loss — your settings are preserved

---

## 🐛 Known Limitations

### Current Release (0.0.1)
- **AVIF generation:** Coming in a future release (UI marked as "Coming Soon")
- **GIF conversion:** Coming in a future release (UI marked as "Coming Soon")
- **Scheduled regeneration:** Must be triggered manually (bulk actions or individual regenerate button)

### Format Detection
- Requires proper server configuration (nginx or Apache rewrite rules) to serve WebP/AVIF automatically
- Works perfectly without server configuration — images load fine, but serve only WebP
- Fallback to original formats works in all browsers even without server configuration

---

## 🚨 Important Notes

### Backup Recommendation
Before adding configuration to your nginx or Apache setup:
1. **Always backup** your existing configuration files
2. Add the provided configuration carefully
3. Test after making changes

### Storage Considerations
- WebP versions typically require **25-35% less storage** than originals
- Enabling "Keep Original" stores both WebP and original formats
- Monitor disk usage after bulk regeneration of large libraries

### Performance Implications
- First-time thumbnail generation happens automatically on upload (negligible impact)
- Regenerating existing thumbnails is fast but processor-intensive for large batches
- Consider regenerating during off-peak hours for very large media libraries

---

## 📞 Support & Documentation

### Built-in Resources
- **System Status Tab** — Comprehensive system information and configuration guidance
- **Settings Help Text** — Hover over settings for inline documentation
- **Configuration Modal** — Copy-ready code snippets for server configuration

### Troubleshooting
- **Images not optimizing?** Check Imagick support in System Status
- **Server configuration issues?** Use the built-in configuration viewer
- **Quality concerns?** Adjust quality settings per format

---

## 🔐 Security

### Safe Deletion
- Original uploaded files are **never modified**
- Only auto-generated thumbnails are optimized
- Source images remain completely intact

### Permissions
- Settings restricted to administrators only
- Bulk regeneration restricted to administrators
- AJAX operations include nonce verification

### Database
- Minimal database footprint
- Settings stored in WordPress options table
- No custom tables required

---

## ✅ Compatibility

### WordPress Themes
- Works with **all modern WordPress themes**
- Automatically detects theme-defined image sizes
- Respects theme-specified crop settings

### Plugins
- Compatible with other media management plugins
- Works alongside regenerate thumbnails plugins
- Safe with caching plugins (regenerated files are properly identified)

### Hosting & Servers
- **Shared Hosting:** Fully supported
- **VPS/Dedicated:** Optimized support
- **Docker:** Tested and working
- **Cloud Platforms:** Compatible with all major providers

---

## 🎓 Advanced Features

### Media Details Panel
- View all available formats for each image
- Regenerate individual image sizes
- See file sizes for each format
- Check format availability status

### Server Automatic Detection
- Detects whether you're running nginx or Apache
- Identifies Apache mod_rewrite availability
- Checks for proper configuration
- Suggests missing configurations via notices

### Quality Management
- Fine-tune compression levels per format
- Balance quality vs. file size
- Separate settings for special cases (EXIF, GIFs)

---

## 📊 Version History

### Version 0.0.2 (February 2026)
- Improve WordPress coding standard compliance
- Fix handling plugin settings

### Version 0.0.1 (February 2026)
- Initial release
- WebP thumbnail generation
- Settings and quality control
- Server detection and configuration
- Media library integration
- System status monitoring
- Bulk regeneration support

---

## 📝 License

This plugin is provided as-is. See included LICENSE file for details.

---

## 🙏 Thank You

Thank you for using Modern Thumbnails! We hope it helps improve your site's performance and user experience. For feedback, suggestions, or issues, please reach out through the plugin's support channels.

---

**Modern Thumbnails 0.0.1** — Making web images faster, one thumbnail at a time.
