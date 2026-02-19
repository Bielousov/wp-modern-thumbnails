<?php
/**
 * Modern Media Thumbnails Plugin Core
 * 
 * Main plugin class that bootstraps all components.
 */

namespace ModernMediaThumbnails;

use ModernMediaThumbnails\WordPress\UploadHooks;
use ModernMediaThumbnails\Admin\SettingsPage;
use ModernMediaThumbnails\Admin\Ajax;
use ModernMediaThumbnails\Admin\Assets;
use ModernMediaThumbnails\Admin\AdminNotices;

class Plugin {
    
    /**
     * Initialize the plugin
     * 
     * @return void
     */
    public static function init() {
        // Initialize default settings if needed
        FormatManager::maybeInitializeDefaults();
        
        // Register WordPress hooks
        UploadHooks::register();
        
        // Register admin components
        add_action('admin_menu', [SettingsPage::class, 'registerMenu']);
        Ajax::register();
        Assets::register();
        AdminNotices::register();
        
        // Load text domain
        add_action('plugins_loaded', [self::class, 'loadTextDomain']);
    }
    
    /**
     * Load plugin text domain
     * 
     * @return void
     */
    public static function loadTextDomain() {
        load_plugin_textdomain(
            'modern-media-thumbnails',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages'
        );
    }
    
    /**
     * Handles plugin activation
     * 
     * @return void
     */
    public static function activate() {
        if (!SystemCheck::isImagickAvailable()) {
            wp_die('This plugin requires the Imagick PHP extension to be installed and enabled. Please contact your hosting provider.');
        }
        
        // Initialize default settings on plugin activation
        FormatManager::maybeInitializeDefaults();
    }
}
