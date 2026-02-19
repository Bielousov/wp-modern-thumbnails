<?php
/**
 * Admin Assets
 * 
 * Handles enqueuing admin scripts and styles.
 */

namespace ModernMediaThumbnails\Admin;

class Assets {
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook
     * @return void
     */
    public static function enqueue($hook) {
        if ($hook !== 'settings_page_mmt-settings') {
            return;
        }
        
        // Use the constant defined in the main plugin file
        $plugin_url = defined('MMT_PLUGIN_URL') ? MMT_PLUGIN_URL : plugin_dir_url(dirname(dirname(dirname(__FILE__))));
        $plugin_version = defined('MMT_PLUGIN_VERSION') ? MMT_PLUGIN_VERSION : '1.0.0';
        
        wp_enqueue_script(
            'mmt-admin',
            $plugin_url . 'js/admin.js',
            ['jquery'],
            $plugin_version,
            true
        );
        
        wp_localize_script('mmt-admin', 'mmtData', [
            'nonce' => wp_create_nonce('mmt_regenerate_nonce'),
            'settingsNonce' => wp_create_nonce('mmt_settings_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n' => [
                'regenerating' => __('Regenerating...', 'modern-media-thumbnails'),
                'success' => __('Regeneration completed!', 'modern-media-thumbnails'),
                'error' => __('Error during regeneration', 'modern-media-thumbnails'),
            ]
        ]);
        
        wp_enqueue_style(
            'mmt-admin',
            $plugin_url . 'css/admin.css',
            [],
            $plugin_version
        );
    }
    
    /**
     * Register assets enqueue hook
     * 
     * @return void
     */
    public static function register() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }
}
