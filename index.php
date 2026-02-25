<?php
/**
 * Plugin Name: Modern Thumbnails
 * Description: Generate WebP thumbnails using Imagick library based on theme-defined image sizes
 * Version: 0.0.2
 * Author: Logovo
 * Text Domain: modern-thumbnails
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MMT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MMT_PLUGIN_URL', plugin_dir_url(__FILE__));
define( 'MMT_PLUGIN_VERSION', '0.0.2' );

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Only autoload classes in the ModernMediaThumbnails namespace
    if (strpos($class, 'ModernMediaThumbnails\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $file = str_replace('ModernMediaThumbnails\\', '', $class);
    $file = str_replace('\\', '/', $file);
    $file = __DIR__ . '/includes/' . $file . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load helper functions
require_once __DIR__ . '/includes/Helpers.php';

// Initialize the plugin
\ModernMediaThumbnails\Plugin::init();

// Register activation hook
register_activation_hook(__FILE__, ['\ModernMediaThumbnails\Plugin', 'activate']);
