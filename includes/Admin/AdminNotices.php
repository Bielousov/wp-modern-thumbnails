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
        // Display bulk action result notices
        self::displayBulkActionNotices();
        
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
     * Display bulk action result notices
     * 
     * @return void
     */
    private static function displayBulkActionNotices() {
        // Check for bulk action results in query string
        if (!isset($_GET['mmt_regenerated'])) {
            return;
        }
        
        $regenerated = intval($_GET['mmt_regenerated']);
        $errors = isset($_GET['mmt_errors']) ? intval($_GET['mmt_errors']) : 0;
        $total = isset($_GET['mmt_total']) ? intval($_GET['mmt_total']) : 0;
        
        if ($regenerated > 0) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    printf(
                        esc_html__('Successfully regenerated thumbnails for %d of %d image(s).', 'modern-media-thumbnails'),
                        $regenerated,
                        $total
                    );
                    ?>
                </p>
            </div>
            <?php
        }
        
        if ($errors > 0) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    printf(
                        esc_html__('Failed to regenerate thumbnails for %d image(s). They may not be valid image files.', 'modern-media-thumbnails'),
                        $errors
                    );
                    ?>
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
