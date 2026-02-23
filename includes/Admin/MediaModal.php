<?php
/**
 * Media Modal Integration
 * 
 * Adds regenerate thumbnails button to the media insert modal in post editor.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\FormatManager;

class MediaModal {
    
    /**
     * Register media modal hooks
     * 
     * @return void
     */
    public static function register() {
        // Enqueue scripts on post/page edit pages
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }
    
    /**
     * Enqueue scripts and styles for media modal
     * 
     * @param string $hook Page hook
     * @return void
     */
    public static function enqueue($hook) {
        // Load on post/page edit pages and new post pages
        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'])) {
            return;
        }
        
        // Enqueue the media modal script
        $plugin_url = defined('MMT_PLUGIN_URL') ? MMT_PLUGIN_URL : plugin_dir_url(dirname(dirname(dirname(__FILE__))));
        $plugin_version = defined('MMT_PLUGIN_VERSION') ? MMT_PLUGIN_VERSION : '1.0.0';
        
        wp_enqueue_script(
            'mmt-media-modal',
            $plugin_url . 'js/media-modal.js',
            ['jquery'],
            $plugin_version,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script(
            'mmt-media-modal',
            'mmtMediaModal',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mmt_regenerate_nonce'),
            ]
        );
        
        // Enqueue styles
        wp_enqueue_style(
            'mmt-media-modal',
            $plugin_url . 'css/media-modal.css',
            [],
            $plugin_version
        );
    }
}
