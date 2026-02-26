# Modern Thumbnails - System Requirements

## ⚠️ CRITICAL REQUIREMENT: ImageMagick (Imagick PHP Extension)

### This plugin **WILL NOT WORK** without ImageMagick

**Modern Thumbnails requires the PHP Imagick extension to function.** This is not optional. Without it:
- The plugin cannot generate thumbnails
- Thumbnail generation will fail silently
- Your media uploads may be affected

---

## What is Imagick?

**Imagick** is a PHP extension that provides an interface to ImageMagick, a powerful image manipulation library. Modern Thumbnails uses Imagick to:
- Read image files from your media library
- Convert them to WebP and other optimized formats
- Generate thumbnails
- Process batch operations

---

## System Requirements

### Requirements (All are mandatory)

| Component | Requirement | Purpose |
|-----------|-------------|---------|
| **PHP Imagick Extension** | **MUST be installed and enabled** | Image processing and format conversion |
| **PHP Version** | 7.4 or higher | Modern PHP features required by the plugin |
| **WordPress** | 5.0 or higher | Plugin API compatibility |
| **ImageMagick Library** | Underlying Imagick extension | Image manipulation operations |

### Requirements (Recommended but optional)

| Component | Recommendation | Purpose |
|-----------|-----------------|---------|
| **Web Server** | nginx or Apache | Use content negotiation for automatic format selection |
| **mod_rewrite** (Apache) | Enabled | Required for automatic format serving on Apache |

---

## How to Check If Imagick is Installed

### Method 1: Using WordPress Admin (Recommended)

1. Install and activate **Modern Thumbnails** plugin
2. Go to **Media → Modern Thumbnails**
3. Click the **System Status** tab
4. Look for the **Imagick** row in the "Server Components" table:
   - ✓ **Green checkmark** = Imagick is installed ✅
   - ✗ **Red X** = Imagick is NOT installed ❌

### Method 2: Using PHP Info

1. Create a file named `phpinfo.php` in your WordPress root directory:
   ```php
   <?php phpinfo(); ?>
   ```
2. Visit `yourdomain.com/phpinfo.php` in your browser
3. Search for "imagick" on the page
4. Delete the `phpinfo.php` file when done (security risk)

### Method 3: Using WordPress Plugin

1. Install the **Server IP & Memory Usage Display** plugin
   - Or use **DebugBar** or similar debugging plugins
2. Look for Imagick in the system information

### Method 4: Using SSH/Command Line (Hosting Admin Only)

```bash
php -m | grep imagick
# If installed, this will output: imagick
```

---

## Installing ImageMagick

### If Imagick is NOT installed:

**Contact your hosting provider and request:**
- "Please enable the PHP Imagick extension on my account"
- Or: "Please install PHP ImageMagick support"

### For Managed Hosting (cPanel, Plesk, etc.)

Many managed hosting providers have a control panel:

**cPanel:**
1. Go to cPanel → WHM (Web Host Manager)
2. Navigate to: **EasyApache 4** or **Module Installers**
3. Search for "php-imagick" or "Imagick"
4. Enable/install it
5. Recompile PHP

**Plesk:**
1. Go to Plesk Control Panel
2. Navigate to: **Tools & Settings → PHP Settings**
3. Find and enable the Imagick extension
4. Restart PHP

**Other panels:** Check your hosting provider's documentation

### For VPS/Dedicated Servers

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install imagemagick php-imagick
sudo systemctl restart apache2  # or nginx

# CentOS/RHEL
sudo yum install ImageMagick php-imagick
sudo systemctl restart httpd  # or nginx
```

---

## What to Do If Imagick Cannot Be Installed

If your hosting provider cannot install Imagick:

1. **This plugin will not work on your hosting**
2. Consider the following alternatives:
   - Switch to a hosting provider that supports Imagick
   - Use a managed WordPress hosting (most support Imagick by default)
   - Use alternative plugins that don't require Imagick

---

## ImageMagick vs. GD Library

**Don't confuse ImageMagick with GD Library:**

| Feature | ImageMagick | GD |
|---------|-------------|-----|
| **Required for Modern Thumbnails** | ✅ **YES** | ❌ **NO** |
| **WebP Support** | ✅ Excellent | ⚠️ Limited |
| **AVIF Support** | ✅ Yes | ❌ No |
| **Image Quality** | ✅ Superior | ⚠️ Good |
| **Performance** | ✅ Fast | ✅ Very Fast |

If you see "GD Library: Installed" in System Status, you still need **Imagick** for this plugin.

---

## After Installation

### Verify Imagick Was Installed

1. Deactivate and re-activate Modern Thumbnails
2. Go to **Media → Modern Thumbnails → System Status**
3. Check the **Imagick** row—should now show: ✓ **Installed**

### Next Steps

Once Imagick is confirmed installed:
1. Go to **Plugin Settings** tab to configure thumbnail generation options
2. Existing media will NOT be automatically converted
3. New uploads will automatically generate WebP thumbnails
4. Optionally bulk regenerate thumbnails from **Media Library** or using bulk actions

---

## Troubleshooting

### "Imagick not installed" but my hosting said they installed it

**Solution:**
- WordPress requires PHP to be restarted to recognize the extension
- Ask your hosting provider to restart PHP
- Or wait 15-30 minutes for it to take effect
- Then refresh the System Status page

### "Imagick installed" but thumbnails aren't generating

**Check:**
1. Is the plugin activated? (Check **Plugins** admin page)
2. Are you uploading new files or trying existing files?
   - Only **new uploads** are processed after activation
   - Use bulk regenerate for existing media
3. Check PHP error logs for detailed error messages
4. Ensure PHP has sufficient memory (256MB recommended)

### Plugin says Imagick is installed but still not working

**Possible causes:**
- Imagick was disabled after plugin activation
- PHP version changed and lost Imagick
- Hosting provider removed the extension

**Solution:**
- Refresh System Status page
- Contact hosting provider to verify Imagick is still enabled
- Check `php.ini` file for `extension=imagick.so` (or `.dll` on Windows)

---

## Support & Contact

If you need help:

1. **Check System Status tab** - Most issues are visible there
2. **Contact your hosting provider** - They manage ImageMagick installation
3. **Contact plugin author** - For plugin-specific issues
4. **WordPress.org Support** - For broader WordPress questions

---

## Summary

| Action | Status | Next Step |
|--------|--------|-----------|
| **Is Imagick installed?** | ❌ NO | Contact hosting provider to install |
| **Is Imagick installed?** | ✅ YES | Go to Plugin Settings and configure |
| **Plugin not working?** | ❓ | Check System Status tab for details |

---

**Remember: Modern Thumbnails cannot work without ImageMagick/Imagick. This is not a limitation—it's a requirement for thumbnail generation to work properly.**
