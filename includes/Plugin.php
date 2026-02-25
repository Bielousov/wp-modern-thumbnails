<?php
/**
 * Modern Thumbnails Plugin Core
 *
 * Main plugin class that bootstraps all components.
 *
 * @package Modern_Thumbnails
 * @since   0.0.1
 */

namespace ModernMediaThumbnails;

use ModernMediaThumbnails\WordPress\UploadHooks;
use ModernMediaThumbnails\WordPress\MetadataManager;
use ModernMediaThumbnails\WordPress\DeletionHandler;
use ModernMediaThumbnails\Admin\SettingsPage;
use ModernMediaThumbnails\Admin\MediaSettings;
use ModernMediaThumbnails\Admin\BulkActions;
use ModernMediaThumbnails\Admin\MediaDetails;
use ModernMediaThumbnails\Admin\MediaModal;
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
        MetadataManager::register();
        DeletionHandler::register();
        
        // Register admin components
        add_action('admin_menu', [SettingsPage::class, 'registerMenu']);
        MediaSettings::register();
        BulkActions::register();
        MediaDetails::register();
        MediaModal::register();
        Ajax::register();
        Assets::register();
        AdminNotices::register();
        
        // Register plugin action links
        add_filter( 'plugin_action_links_modern-thumbnails/index.php', [ self::class, 'add_plugin_action_links' ] );
        
        // Load text domain
        add_action( 'plugins_loaded', [ self::class, 'load_text_domain' ] );
    }
    
    /**
     * Load plugin text domain for translations.
     * 
     * @since 0.0.1
     * 
     * @return void
     */
    public static function load_text_domain() {
        load_plugin_textdomain(
            'modern-thumbnails',
            false,
            dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages'
        );
    }
    
    /**
     * Handles plugin activation.
     * 
     * @since 0.0.1
     * 
     * @return void
     */
    public static function activate() {
        if ( ! SystemCheck::isImagickAvailable() ) {
            wp_die( 'This plugin requires the Imagick PHP extension to be installed and enabled. Please contact your hosting provider.' );
        }
        
        // Initialize default settings on plugin activation
        FormatManager::maybeInitializeDefaults();
        
        // Check server configuration and set transient if needed
        if ( NginxConfigCheck::isRunningOnNginx() && ! NginxConfigCheck::isNginxConfigured() ) {
            set_transient( 'mmt_nginx_config_notice', true, 7 * 24 * 3600 ); // Show for 7 days
        } elseif ( ApacheConfigCheck::isRunningOnApache() && ApacheConfigCheck::isModRewriteEnabled() && ! ApacheConfigCheck::isApacheConfigured() ) {
            set_transient( 'mmt_apache_config_notice', true, 7 * 24 * 3600 ); // Show for 7 days
        }
    }
    
    /**
     * Add plugin action links to the plugins page.
     * 
     * Adds a Settings link to the plugin list item on the WordPress plugins page.
     * 
     * @since 0.0.1
     * 
     * @param array $links Existing plugin action links.
     * @return array Modified action links.
     */
    public static function add_plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=mmt-settings' ),
            __( 'Settings', 'modern-thumbnails' )
        );
        
        array_unshift( $links, $settings_link );
        return $links;
    }
}
