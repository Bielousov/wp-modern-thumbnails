<?php
/**
 * AJAX Handler
 * 
 * Handles AJAX requests for regeneration and format settings.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\WordPress\RegenerationManager;

class Ajax {
    
    /**
     * Handle AJAX regeneration of all thumbnails
     * 
     * @return void
     */
    public static function regenerateAll() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $regenerated = RegenerationManager::regenerateSize();
        
        wp_send_json_success([
            'message' => sprintf(
                'Successfully regenerated %d thumbnails',
                $regenerated
            ),
            'count' => $regenerated
        ]);
    }
    
    /**
     * Handle AJAX regeneration of a specific size
     * 
     * @return void
     */
    public static function regenerateSize() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $size_name = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : null;
        
        if (!$size_name) {
            wp_send_json_error('No size specified');
        }
        
        $regenerated = RegenerationManager::regenerateSize($size_name);
        
        wp_send_json_success([
            'message' => sprintf(
                'Successfully regenerated %d thumbnails for size "%s"',
                $regenerated,
                $size_name
            ),
            'count' => $regenerated,
            'size' => $size_name
        ]);
    }
    
    /**
     * Handle AJAX settings save
     * 
     * @return void
     */
    public static function saveSettings() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $settings = isset($_POST['settings']) ? (array)$_POST['settings'] : [];
        
        // Update settings
        FormatManager::updateSettings([
            'keep_original' => isset($settings['keep_original']),
            'generate_avif' => isset($settings['generate_avif']),
            'convert_gif' => isset($settings['convert_gif']),
        ]);
        
        wp_send_json_success([
            'message' => 'Settings updated successfully'
        ]);
    }
    
    /**
     * Register AJAX handlers
     * 
     * @return void
     */
    public static function register() {
        add_action('wp_ajax_mmt_regenerate_all', [self::class, 'regenerateAll']);
        add_action('wp_ajax_mmt_regenerate_size', [self::class, 'regenerateSize']);
        add_action('wp_ajax_mmt_save_settings', [self::class, 'saveSettings']);
    }
}
