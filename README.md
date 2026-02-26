=== Modern Thumbnails ===
Contributors: Anton Bielousov
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tested up to: 6.9
Requires PHP: 7.4
Stable Tag: 0.0.4

Generate modern image formats (WebP, AVIF) for WordPress thumbnails with ImageMagick.

== Description ==

Modern Thumbnails automatically generates modern image formats (WebP, AVIF) for your WordPress media library using the ImageMagick PHP extension. This plugin optimizes your website's image delivery, improving page load times and overall performance.

When you upload or regenerate images, Modern Thumbnails automatically creates WebP and AVIF versions alongside your original JPEG/PNG files. The plugin handles all the complexity of format conversion and quality optimization, allowing you to focus on your content.

= Features =

* **Automatic WebP Generation** - Instantly converts JPEG and PNG uploads to WebP format
* **AVIF Format Support** - Generate next-generation AVIF images for maximum compatibility
* **Bulk Regeneration** - Regenerate thumbnails for existing media with a single click
* **Quality Control** - Customize compression quality for each image format
* **ImageMagick Powered** - Uses ImageMagick for fast, reliable image processing
* **Transparent Integration** - Works seamlessly with existing WordPress media workflows
* **Status Dashboard** - Monitor conversion status and system requirements

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* **ImageMagick PHP extension (REQUIRED)** - Must be installed on your server
* Imagick PHP module (for ImageMagick integration)

= Installation =

1. **Upload the Plugin**
   - Upload the `modern-thumbnails` folder to `/wp-content/plugins/` via FTP or the WordPress plugin upload page

2. **Activate the Plugin**
   - Go to the Plugins section in your WordPress admin and activate Modern Thumbnails

3. **Verify System Requirements**
   - Visit Tools → Modern Thumbnails Status to confirm ImageMagick is installed

4. **Configure Settings**
   - Go to Settings → Modern Thumbnails to adjust quality settings and format options

5. **Regenerate Thumbnails** (Optional)
   - Use Media → Bulk Regenerate to process your existing image library

= Configuration =

After activation, visit **Settings → Modern Thumbnails** to:

* Enable/disable WebP generation
* Enable/disable AVIF generation
* Set image quality levels (1-100)
* Choose which image sizes to process
* Enable debug logging for troubleshooting

Note for Copilot users: to have VS Code Chat / GitHub Copilot reference the project's instructions, create a local symlink at the repository root that points to `doc/COPILOT_INSTRUCTIONS.md`, for example:

```
ln -s doc/COPILOT_INSTRUCTIONS.md .copilot-instructions.md
```

Important: WordPress.org plugin rules disallow hidden files in plugin packages. Do NOT commit or include `.copilot-instructions.md` (or any hidden files) in your plugin release. Keep the symlink local (add it to `.git/info/exclude` or remove it before creating distribution archives).

== Frequently Asked Questions ==

= Why do I need ImageMagick? =

ImageMagick is a powerful, open-source image manipulation library that provides superior image conversion quality and performance compared to GD. It's required for reliable WebP and AVIF generation.

= Will this slow down my site? =

No. Image conversion happens during upload or during bulk regeneration—not during site visits. Once converted, images are served directly without additional processing.

= What about existing images? =

Use bulk regenerate to convert your existing image library. Start with a small batch to test, then process the entire library in the background.

= Can I choose which formats to generate? =

Yes. Settings allow you to enable or disable WebP and AVIF generation independently. You can also choose which image sizes to process.

= Is this compatible with my theme? =

Modern Thumbnails works with any WordPress theme. Most modern browsers automatically use WebP/AVIF if available; older browsers fall back to original formats.

= How do I check if ImageMagick is installed? =

Visit **Tools → Modern Thumbnails Status**. The status page displays your ImageMagick version and shows if the plugin can function properly.

= What about image quality? =

Quality settings range from 1-100. The plugin defaults to quality settings optimized for web delivery. You can adjust per format in settings if needed.

= Can I rollback if something goes wrong? =

Yes. The plugin preserves your original images. If you disable or uninstall the plugin, original files remain intact.

= Does this support responsive images? =

Yes. Modern Thumbnails respects WordPress srcset and converts all registered image sizes.

= Do I need special hosting? =

ImageMagick must be available on your server. Most managed WordPress hosts include it; ask your provider if unsure.

== Changelog ==

= 0.0.4 =
* Full WordPress coding standards compliance
* Enhanced security hardening and sanitization
* Added database query caching
* Improved error handling and validation

= 0.0.3 =
* Enhanced ImageMagick compatibility detection
* Better debug logging for troubleshooting

= 0.0.2 =
* Added AVIF format support
* Improved bulk regeneration performance

= 0.0.1 =
* Initial release
* WebP generation support

== License ==

This plugin is licensed under the GNU General Public License v2 or later.

By using this plugin, you agree to the terms of the GPL v2 license. This plugin comes with ABSOLUTELY NO WARRANTY. See the GNU General Public License for complete details.

[View GPL v2 License](https://www.gnu.org/licenses/gpl-2.0.html)

== Support ==

For support, questions, or feature requests, please contact the plugin author.
