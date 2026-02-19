<?php
/**
 * Admin Notices
 * 
 * Handles displaying admin notices.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\SystemCheck;

class AdminNotices {
    
    /**
     * Display admin notices
     * 
     * @return void
     */
    public static function display() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (!SystemCheck::isImagickAvailable()) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e('Modern Media Thumbnails:', 'modern-media-thumbnails'); ?></strong>
                    <?php esc_html_e('The Imagick PHP extension is not installed. Please contact your hosting provider to enable it.', 'modern-media-thumbnails'); ?>
                </p>
            </div>
            <?php
        }
        
        if (!SystemCheck::isWebPSupported()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('Modern Media Thumbnails:', 'modern-media-thumbnails'); ?></strong>
                    <?php esc_html_e('WebP format is not supported by your Imagick installation. Please update Imagick and ImageMagick library.', 'modern-media-thumbnails'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Register admin notices hook
     * 
     * @return void
     */
    public static function register() {
        add_action('admin_notices', [self::class, 'display']);
    }
}
