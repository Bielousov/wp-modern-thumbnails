<?php
/**
 * Admin Notices
 * 
 * Handles displaying admin notices.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\SystemCheck;
use ModernMediaThumbnails\NginxConfigCheck;
use ModernMediaThumbnails\ApacheConfigCheck;

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
                    <strong><?php esc_html_e( 'Modern Thumbnails:', 'modern-thumbnails' ); ?></strong>
                    <?php esc_html_e( 'The Imagick PHP extension is not installed. Please contact your hosting provider to enable it.', 'modern-thumbnails' ); ?>
                </p>
            </div>
            <?php
        }
        
        if (!SystemCheck::isWebPSupported()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'Modern Thumbnails:', 'modern-thumbnails' ); ?></strong>
                    <?php esc_html_e( 'WebP format is not supported by your Imagick installation. Please update Imagick and ImageMagick library.', 'modern-thumbnails' ); ?>
                </p>
            </div>
            <?php
        }
        
        // Check nginx configuration if running on nginx
        if (NginxConfigCheck::isRunningOnNginx() && !NginxConfigCheck::isNginxConfigured() && get_transient('mmt_nginx_config_notice') && !get_transient('mmt_nginx_config_notice_dismissed')) {
            $settings_url = admin_url('options-general.php?page=mmt-settings&tab=system');
            ?>
            <div class="notice notice-info is-dismissible" data-notice="mmt_nginx_notice">
                <p>
                    <strong><?php esc_html_e( 'Modern Thumbnails:', 'modern-thumbnails' ); ?></strong>
                    <?php esc_html_e( 'Your server is running nginx, but the image format content negotiation configuration has not been detected. This is optional but recommended for optimal performance.', 'modern-thumbnails' ); ?>
                    <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'View Configuration Guide', 'modern-thumbnails' ); ?></a>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                // Hide nginx notice for 30 days when dismissed
                $('.notice[data-notice="mmt_nginx_notice"] .notice-dismiss').click(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mmt_dismiss_nginx_notice',
                            nonce: '<?php echo wp_create_nonce('mmt_dismiss_notice'); ?>'
                        }
                    });
                });
            });
            </script>
            <?php
        }
        
        // Check Apache configuration if running on Apache
        if (ApacheConfigCheck::isRunningOnApache() && ApacheConfigCheck::isModRewriteEnabled() && !ApacheConfigCheck::isApacheConfigured() && get_transient('mmt_apache_config_notice') && !get_transient('mmt_apache_config_notice_dismissed')) {
            $settings_url = admin_url('options-general.php?page=mmt-settings&tab=system');
            ?>
            <div class="notice notice-info is-dismissible" data-notice="mmt_apache_notice">
                <p>
                    <strong><?php esc_html_e( 'Modern Thumbnails:', 'modern-thumbnails' ); ?></strong>
                    <?php esc_html_e( 'Your server is running Apache with mod_rewrite enabled, but the image format content negotiation configuration has not been detected. This is optional but recommended for optimal performance.', 'modern-thumbnails' ); ?>
                    <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'View Configuration Guide', 'modern-thumbnails' ); ?></a>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                // Hide Apache notice for 30 days when dismissed
                $('.notice[data-notice="mmt_apache_notice"] .notice-dismiss').click(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mmt_dismiss_apache_notice',
                            nonce: '<?php echo wp_create_nonce('mmt_dismiss_notice'); ?>'
                        }
                    });
                });
            });
            </script>
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
                        esc_html__( 'Successfully regenerated thumbnails for %d of %d image(s).', 'modern-thumbnails' ),
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
                        esc_html__( 'Failed to regenerate thumbnails for %d image(s). They may not be valid image files.', 'modern-thumbnails' ),
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
