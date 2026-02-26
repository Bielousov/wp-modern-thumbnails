<?php
/**
 * Nginx Configuration Checker
 * 
 * Detects and validates nginx image format negotiation configuration
 */

namespace ModernMediaThumbnails;

class NginxConfigCheck {
    
    /**
     * Check if nginx image format negotiation is configured
     * 
     * @return bool True if configuration is detected, false otherwise
     */
    public static function isNginxConfigured() {
        // Check if running on nginx
        if (!self::isRunningOnNginx()) {
            return false;
        }
        
        // Check if config file exists and contains the required rules
        $config_path = self::getNginxConfigPath();
        
        if (!$config_path || !file_exists($config_path)) {
            return false;
        }
        
        $config_content = @file_get_contents($config_path);
        if (!$config_content) {
            return false;
        }
        
        // Look for key markers that indicate the config is present
        $required_markers = [
            'ext_avif',              // AVIF detection variable
            'ext_webp',              // WebP detection variable
            'image/avif',            // AVIF accept header check
            'image/webp',            // WebP accept header check
            'wp-content.*jpe?g',     // Image location regex (can be slightly different)
        ];
        
        $markers_found = 0;
        foreach ($required_markers as $marker) {
            if (stripos($config_content, $marker) !== false) {
                $markers_found++;
            }
        }
        
        // Require at least 4 of 5 markers to be present
        return $markers_found >= 4;
    }
    
    /**
     * Check if running on nginx server
     * 
     * @return bool True if server is nginx, false otherwise
     */
    public static function isRunningOnNginx() {
        // Check SERVER_SOFTWARE variable
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';
        if ($server_software && stripos($server_software, 'nginx') !== false) {
            return true;
        }
        
        // Check if fastcgi params indicate nginx
        if (isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['REQUEST_URI'])) {
            // This is more typical of nginx with PHP-FPM
            if (!isset($_SERVER['DOCUMENT_ROOT'])) {
                return false; // Apache usually has this
            }
        }
        
        // Try to detect via HTTP headers or request method
        // nginx typically uses different headers than Apache
        if (function_exists('apache_get_version')) {
            return false; // Definitely Apache
        }
        
        // Default: assume unknown/not configured
        return false;
    }
    
    /**
     * Get the path to the active nginx configuration file
     * 
     * @return string|false Path to nginx config file or false if not found
     */
    public static function getNginxConfigPath() {
        // Common nginx config locations
        $possible_paths = [
            '/etc/nginx/nginx.conf',
            '/etc/nginx/sites-enabled/default',
            '/usr/local/etc/nginx/nginx.conf',      // macOS brew
            '/opt/homebrew/etc/nginx/nginx.conf',   // Apple Silicon macOS
            '/usr/local/nginx/conf/nginx.conf',
            '/etc/nginx/conf.d/default.conf',
        ];
        
        // Also check for Docker path since this is a WordPress setup
        $docker_paths = [
            '/etc/nginx/conf.d/default.conf',
            '/etc/nginx/conf.d/app.conf',
        ];
        
        foreach (array_merge($docker_paths, $possible_paths) as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Get the nginx configuration snippet for copy/paste
     * 
     * @return string The configuration snippet
     */
    public static function getConfigurationSnippet() {
        $config_path = realpath(dirname(__DIR__) . '/nginx.conf');
        return sprintf("# Modern WebP Thumbnails\ninclude %s;\n", $config_path);
    }
}
