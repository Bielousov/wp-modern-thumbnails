<?php
/**
 * Apache Configuration Checker
 * 
 * Detects and validates Apache mod_rewrite configuration for image format negotiation
 */

namespace ModernMediaThumbnails;

class ApacheConfigCheck {
    
    /**
     * Check if Apache mod_rewrite image format negotiation is configured
     * 
     * @return bool True if configuration is detected, false otherwise
     */
    public static function isApacheConfigured() {
        // Check if running on Apache
        if (!self::isRunningOnApache()) {
            return false;
        }
        
        // Check if mod_rewrite is enabled
        if (!self::isModRewriteEnabled()) {
            return false;
        }
        
        // Check if .htaccess is readable
        $htaccess_path = self::getHtaccessPath();
        if (!$htaccess_path || !file_exists($htaccess_path) || !is_readable($htaccess_path)) {
            return false;
        }
        
        $htaccess_content = @file_get_contents($htaccess_path);
        if (!$htaccess_content) {
            return false;
        }
        
        // Look for key markers that indicate the config is present
        $required_markers = [
            'mod_rewrite',              // mod_rewrite module check
            'RewriteEngine',            // RewriteEngine directive
            'image/avif',               // AVIF accept header check
            'image/webp',               // WebP accept header check
            'wp-content',               // WordPress content directory rewrite
        ];
        
        $markers_found = 0;
        foreach ($required_markers as $marker) {
            if (stripos($htaccess_content, $marker) !== false) {
                $markers_found++;
            }
        }
        
        // Require at least 4 of 5 markers to be present
        return $markers_found >= 4;
    }
    
    /**
     * Check if running on Apache server
     * 
     * @return bool True if server is Apache, false otherwise
     */
    public static function isRunningOnApache() {
        // Check SERVER_SOFTWARE variable
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $server_software = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
            if (stripos($server_software, 'Apache') !== false) {
                return true;
            }
        }
        
        // Check if function_exists apache_get_version
        if (function_exists('apache_get_version')) {
            return true;
        }
        
        // Check for Apache environment variables
        if (isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['DOCUMENT_ROOT'])) {
            // More likely to be Apache if both are set
            // But also check it's not nginx (which also sets these)
            if (!isset($_SERVER['SERVER_SOFTWARE'])) {
                return true;
            }
            $server_software = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
            if (stripos($server_software, 'nginx') === false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if Apache mod_rewrite is enabled
     * 
     * @return bool True if mod_rewrite is available, false otherwise
     */
    public static function isModRewriteEnabled() {
        // Check via function
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            return in_array('mod_rewrite', $modules);
        }
        
        // As fallback, check if .htaccess can be read (indicates Apache with some .htaccess support)
        // This is less reliable but works when apache_get_modules is not available
        return @file_exists(ABSPATH . '.htaccess');
    }
    
    /**
     * Get the path to the active .htaccess file
     * 
     * @return string|false Path to .htaccess or false if not found
     */
    public static function getHtaccessPath() {
        // Check root .htaccess
        $root_htaccess = ABSPATH . '.htaccess';
        if (file_exists($root_htaccess) && is_readable($root_htaccess)) {
            return $root_htaccess;
        }
        
        // Check wp-content .htaccess
        $wp_content_htaccess = WP_CONTENT_DIR . '/.htaccess';
        if (file_exists($wp_content_htaccess) && is_readable($wp_content_htaccess)) {
            return $wp_content_htaccess;
        }
        
        return false;
    }
    
    /**
     * Get the Apache configuration snippet for copy/paste
     * 
     * @return string The configuration snippet
     */
    public static function getConfigurationSnippet() {
        return "# BEGIN Modern Thumbnails\n" .
               "# This section enables automatic serving of optimized AVIF and WebP image formats\n" .
               "# based on browser support. Much smaller file sizes = faster pages.\n" .
               "\n" .
               "<IfModule mod_rewrite.c>\n" .
               "    RewriteEngine On\n" .
               "\n" .
               "    # Serve AVIF format if the browser supports it AND the .avif version exists\n" .
               "    # AVIF provides superior compression (25-35% smaller than JPEG)\n" .
               "    RewriteCond %{HTTP_ACCEPT} image/avif\n" .
               "    RewriteCond %{REQUEST_FILENAME}\\.avif -f\n" .
               "    RewriteRule ^wp-content/(.+)\\.(jpg|jpeg|png|gif|webp)$ wp-content/$1.$2.avif [QSA,L,T=image/avif]\n" .
               "\n" .
               "    # Fallback to WebP if AVIF unavailable but browser supports it\n" .
               "    # WebP is widely supported and 25-35% smaller than JPEG/PNG\n" .
               "    RewriteCond %{HTTP_ACCEPT} image/webp\n" .
               "    RewriteCond %{REQUEST_FILENAME}\\.webp -f\n" .
               "    RewriteRule ^wp-content/(.+)\\.(jpg|jpeg|png|gif)$ wp-content/$1.$2.webp [QSA,L,T=image/webp]\n" .
               "</IfModule>\n" .
               "\n" .
               "# Cache headers: Allow browsers to cache optimized images for 1 year\n" .
               "<FilesMatch \"\\.(?:avif|webp)$\">\n" .
               "    <IfModule mod_expires.c>\n" .
               "        ExpiresActive On\n" .
               "        ExpiresDefault \"access plus 1 year\"\n" .
               "    </IfModule>\n" .
               "    <IfModule mod_headers.c>\n" .
               "        Header set Cache-Control \"public, max-age=31536000, immutable\"\n" .
               "        Header set Vary \"Accept\"\n" .
               "    </IfModule>\n" .
               "</FilesMatch>\n" .
               "\n" .
               "# END Modern Thumbnails";
    }
}
