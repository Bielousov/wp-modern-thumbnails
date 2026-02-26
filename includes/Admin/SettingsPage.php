<?php
/**
 * Settings Page
 * 
 * Renders the admin settings page.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\ImageSizeManager;
use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\Settings;
use ModernMediaThumbnails\SystemCheck;
use ModernMediaThumbnails\NginxConfigCheck;
use ModernMediaThumbnails\ApacheConfigCheck;

class SettingsPage {
    
    /**
     * Render the settings page
     * 
     * @return void
     */
    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permissions to access this page.'));
        }
        
        $image_sizes = ImageSizeManager::getAllImageSizes();
        $imagick_available = SystemCheck::isImagickAvailable();
        $webp_supported = SystemCheck::isWebPSupported();
        $avif_supported = SystemCheck::isAVIFSupported();
        $settings = Settings::getWithDefaults();
        
        // Get a sample image from media library for preview
        $sample_attachment = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => 1,
        ]);
        
        $sample_image_url = null;
        if (!empty($sample_attachment)) {
            $sample_image_url = wp_get_attachment_url($sample_attachment[0]->ID);
        }
        
        // Get active tab from query parameter, default to 'sizes'
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'sizes';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Modern Thumbnails', 'modern-thumbnails' ); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="?page=mmt-settings&tab=sizes" class="nav-tab <?php echo $active_tab === 'sizes' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Theme Image Sizes', 'modern-thumbnails' ); ?>
                </a>
                <a href="?page=mmt-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Plugin Settings', 'modern-thumbnails' ); ?>
                </a>
                <a href="?page=mmt-settings&tab=system" class="nav-tab <?php echo $active_tab === 'system' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'System Status', 'modern-thumbnails' ); ?>
                </a>
            </nav>
            
            <!-- Critical Requirement Alert -->
            <?php if ( ! $imagick_available ): ?>
                <div class="notice notice-error mmt-requirement-alert">
                    <p>
                        <strong>⚠️ <?php esc_html_e( 'CRITICAL: Imagick Not Installed', 'modern-thumbnails' ); ?></strong><br>
                        <?php esc_html_e( 'Modern Thumbnails requires the PHP Imagick extension to function. Without it, the plugin will not work and thumbnails will not be generated.', 'modern-thumbnails' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'Please contact your hosting provider to enable the Imagick extension, then come back to this page to verify it has been installed.', 'modern-thumbnails' ); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Settings Tab -->
            <?php if ($active_tab === 'settings'): ?>
                <div class="tab-content">
                    <form id="mmt-settings-form" method="post">
                        <?php wp_nonce_field('mmt_settings_nonce', 'mmt_settings_nonce'); ?>
                        
                        <h2><?php esc_html_e( 'Global Settings', 'modern-thumbnails' ); ?></h2>
                        <p><?php esc_html_e( 'Configure how the plugin processes and stores image formats.', 'modern-thumbnails' ); ?></p>
                        
                        <div class="mmt-settings-grid">
                            <!-- Generate WebP Format Setting (Always Enabled) -->
                            <div class="mmt-setting-card">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e( 'Generate WebP Format', 'modern-thumbnails' ); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_generate_webp" 
                                               name="settings[generate_webp]" 
                                               value="1"
                                               checked
                                               disabled>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e( 'Generate WebP format for all images. This is always enabled and required for optimal performance.', 'modern-thumbnails' ); ?></p>
                                <div class="mmt-card-footer">
                                    <div class="mmt-quality-control">
                                        <label for="mmt_webp_quality"><?php esc_html_e( 'Quality', 'modern-thumbnails' ); ?></label>
                                        <input type="range" 
                                               id="mmt_webp_quality" 
                                               name="settings[webp_quality]" 
                                               min="0" 
                                               max="100" 
                                               value="<?php echo intval($settings['webp_quality']); ?>"
                                               class="mmt-quality-slider">
                                        <span class="mmt-quality-value"><?php echo intval($settings['webp_quality']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- WordPress Default Setting -->
                            <div class="mmt-setting-card">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e( 'Generate WordPress Default Thumbnails', 'modern-thumbnails' ); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_keep_original" 
                                               name="settings[keep_original]" 
                                               value="1"
                                               <?php checked($settings['keep_original']); ?>>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e( 'Generate JPEG or PNG versions matching the original file format to ensure compatibility with legacy browsers. Attachment metadata would still refer to WebP, intended to be used only for compatibility purposes.', 'modern-thumbnails' ); ?></p>
                                <div class="mmt-card-footer" <?php if ( ! $settings['keep_original'] ) echo 'style="display: none;"'; ?>>
                                    <div class="mmt-quality-control">
                                        <label for="mmt_original_quality"><?php esc_html_e( 'Quality', 'modern-thumbnails' ); ?></label>
                                        <input type="range" 
                                               id="mmt_original_quality" 
                                               name="settings[original_quality]" 
                                               min="0" 
                                               max="100" 
                                               value="<?php echo intval( $settings['original_quality'] ); ?>"
                                               class="mmt-quality-slider">
                                        <span class="mmt-quality-value"><?php echo intval( $settings['original_quality'] ); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Keep EXIF Data Setting -->
                            <div class="mmt-setting-card">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e( 'Preserve EXIF Data', 'modern-thumbnails' ); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_keep_exif" 
                                               name="settings[keep_exif]" 
                                               value="1"
                                               <?php checked($settings['keep_exif']); ?>>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e( 'Keep EXIF metadata in generated thumbnails for better image quality and copyright information. Disable to save disk space.', 'modern-thumbnails' ); ?></p>
                                <?php if ( current_theme_supports( 'post-thumbnails' ) ): ?>
                                    <div class="mmt-card-footer" <?php if ( ! $settings['keep_exif'] ) echo 'style="display: none;"'; ?>>
                                        <label class="mmt-exif-thumbnail-checkbox">
                                            <input type="checkbox" 
                                                   id="mmt_keep_exif_thumbnails" 
                                                   name="settings[keep_exif_thumbnails]" 
                                                   value="1"
                                                   <?php checked( $settings['keep_exif_thumbnails'] ); ?>>
                                            <?php
                                                $thumb_w = get_option( 'thumbnail_size_w' );
                                                $thumb_h = get_option( 'thumbnail_size_h' );
                                                $thumb_label = sprintf(
                                                    esc_html__( 'Keep EXIF for post thumbnails (%dx%d)', 'modern-thumbnails' ),
                                                    intval( $thumb_w ),
                                                    intval( $thumb_h )
                                                );
                                            ?>
                                            <span class="mmt-checkbox-label"><?php echo $thumb_label; ?></span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Generate AVIF Format Setting (Coming Soon) -->
                            <div class="mmt-setting-card" style="opacity: 0.6; pointer-events: none;">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e( 'Generate AVIF Format', 'modern-thumbnails' ); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_generate_avif" 
                                               name="settings[generate_avif]" 
                                               value="1"
                                               disabled>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e( 'Generate AVIF format for supported images (requires external library). AVIF provides superior compression compared to WebP.', 'modern-thumbnails' ); ?></p>
                                <div class="mmt-card-footer">
                                    <span class=\"mmt-badge-coming-soon\"><?php esc_html_e( 'Coming Soon', 'modern-thumbnails' ); ?></span>
                                </div>
                            </div>
                            
                            <!-- Convert GIFs to Video Setting (Coming Soon) -->
                            <div class="mmt-setting-card" style="opacity: 0.6; pointer-events: none;">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e( 'Convert GIFs', 'modern-thumbnails' ); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_convert_gif" 
                                               name="settings[convert_gif]" 
                                               value="1"
                                               disabled>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e( 'Convert animated GIFs to MP4/WebM formats and static to AVIF/WebP for better performance and compatibility', 'modern-thumbnails' ); ?></p>
                                <div class="mmt-card-footer">
                                    <span class=\"mmt-badge-coming-soon\"><?php esc_html_e( 'Coming Soon', 'modern-thumbnails' ); ?></span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Theme Image Sizes Tab -->
            <?php if ($active_tab === 'sizes'): ?>
                <div class="tab-content">
                    <h2><?php esc_html_e( 'Theme Image Sizes', 'modern-thumbnails' ); ?></h2>
                    <p><?php esc_html_e( 'The following image sizes are defined in your theme and will be generated as WebP:', 'modern-thumbnails' ); ?></p>
                    
                    <div style="margin-bottom: 20px;">
                        <button id="mmt-regenerate-all" class="button button-primary">
                            <?php esc_html_e( 'Regenerate All Thumbnails', 'modern-thumbnails' ); ?>
                        </button>
                    </div>
                    
                    <table class="wp-list-table widefat striped mmt-image-sizes-table">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 130px;"><?php esc_html_e( 'Resolution & Crop', 'modern-thumbnails' ); ?></th>
                                <th><?php esc_html_e( 'Size Name', 'modern-thumbnails' ); ?></th>
                                <th><?php esc_html_e( 'Formats', 'modern-thumbnails' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Original Image Row -->
                            <tr style="background-color: #f9f9f9; font-weight: bold;">
                                <td style="text-align: center;">
                                    <div class="mmt-resolution-box">
                                        <span class="mmt-resolution-box-label">
                                            <?php esc_html_e( 'Full', 'modern-thumbnails' ); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php esc_html_e( 'Original Image', 'modern-thumbnails' ); ?></strong>
                                </td>
                                <td>
                                    <div class="mmt-formats-wrapper">                                        
                                        <span class="mmt-format-badge mmt-format-original"><?php esc_html_e( 'Original (Unchanged)', 'modern-thumbnails' ); ?></span>
                                    </div>
                                </td>
                            </tr>
                            
                            <?php if (!empty($image_sizes)): ?>
                                <?php
                                // Sort image sizes by longest side (descending)
                                $sorted_sizes = $image_sizes;
                                uasort($sorted_sizes, function($a, $b) {
                                    $a_longest = max((int)$a['width'], (int)$a['height']);
                                    $b_longest = max((int)$b['width'], (int)$b['height']);
                                    return $b_longest - $a_longest; // Descending order
                                });
                                ?>
                                <?php foreach ($sorted_sizes as $size_name => $size_data): ?>
                                    <?php
                                    $width = isset($size_data['width']) ? $size_data['width'] : 'auto';
                                    $height = isset($size_data['height']) ? $size_data['height'] : 'auto';
                                    $crop = isset($size_data['crop']) ? $size_data['crop'] : false;
                                    ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <div class="mmt-resolution-box <?php echo $crop ? 'hard-crop' : 'no-crop'; ?>">
                                                <span class="mmt-crop-badge <?php echo $crop ? 'hard-crop' : 'no-crop'; ?>">
                                                    <?php echo $crop ? esc_html__( 'Crop', 'modern-thumbnails' ) : esc_html__( 'Fit', 'modern-thumbnails' ); ?>
                                                </span>
                                                <span class="mmt-resolution-box-label">
                                                    <?php echo esc_html($width); ?><small>×</small><?php echo esc_html($height); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $readable_name = ImageSizeManager::getImageSizeName($size_name);
                                            ?>
                                            <strong><?php echo esc_html($readable_name); ?></strong>
                                            <?php if ($readable_name !== $size_name): ?>
                                                <br><small style="color: #666;"><?php echo esc_html($size_name); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="mmt-formats-wrapper">
                                                <div class="mmt-formats">
                                                    <?php if ($settings['keep_original']): ?>
                                                        <span class="mmt-format-badge mmt-format-original"><?php esc_html_e( 'Original (JPEG/PNG)', 'modern-thumbnails' ); ?></span>
                                                    <?php endif; ?>
                                                    <span class="mmt-format-badge mmt-format-webp"><?php esc_html_e( 'WebP', 'modern-thumbnails' ); ?></span>
                                                    <?php if ($settings['generate_avif']): ?>
                                                        <span class="mmt-format-badge mmt-format-avif"><?php esc_html_e( 'AVIF', 'modern-thumbnails' ); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="#" class="mmt-regenerate-size" data-size="<?php echo esc_attr( $size_name ); ?>"><?php esc_html_e( 'Regenerate', 'modern-thumbnails' ); ?></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3">
                                        <em><?php esc_html_e( 'No custom image sizes defined in your theme.', 'modern-thumbnails' ); ?></em>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- System Status Tab -->
            <?php if ( $active_tab === 'system' ): ?>
                <div class="tab-content">
                    <h2><?php esc_html_e( 'System Status', 'modern-thumbnails' ); ?></h2>
                    
                    <!-- Critical Imagick Status Alert -->
                    <?php if ( ! $imagick_available ): ?>
                        <div class="notice notice-error mmt-requirement-alert" style="margin-top: 0;">
                            <p>
                                <strong>⚠️ <?php esc_html_e( 'CRITICAL: Imagick is Required but Not Installed', 'modern-thumbnails' ); ?></strong><br>
                                <?php esc_html_e( 'Modern Thumbnails requires the PHP Imagick extension to function. Without it, the plugin cannot generate thumbnails.', 'modern-thumbnails' ); ?>
                            </p>
                            <p>
                                <strong><?php esc_html_e( 'Action Required:', 'modern-thumbnails' ); ?></strong> 
                                <?php esc_html_e( 'Contact your hosting provider and ask them to enable the PHP Imagick extension. See the "Imagick" row below for more details.', 'modern-thumbnails' ); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mmt-plugin-version-info">
                        <?php esc_html_e( 'Modern Thumbnails Plugin Version:', 'modern-thumbnails' ); ?>
                        <span class="mmt-version-value"><?php echo esc_html( mmt_get_version() ); ?></span>
                    </div>
                    
                    <h3><?php esc_html_e( 'Server Components', 'modern-thumbnails' ); ?></h3>
                    <p><?php esc_html_e( 'Check if your server has the required components installed and properly configured.', 'modern-thumbnails' ); ?></p>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Component', 'modern-thumbnails' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'modern-thumbnails' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'modern-thumbnails' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e( 'PHP Version', 'modern-thumbnails' ); ?></strong></td>
                                <td>
                                    <span style="color: green;">✓ <?php echo esc_html(phpversion()); ?></span>
                                </td>
                                <td><?php esc_html_e( 'Current PHP version running on the server.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Imagick', 'modern-thumbnails' ); ?></strong></td>
                                <td>
                                    <?php echo $imagick_available ? '<span style="color: green;">✓ ' . esc_html__( 'Installed', 'modern-thumbnails' ) . '</span>' : '<span style="color: red;">✗ ' . esc_html__( 'Not Installed', 'modern-thumbnails' ) . '</span>'; ?>
                                </td>
                                <td><strong><?php esc_html_e( 'REQUIRED', 'modern-thumbnails' ); ?></strong> — <?php esc_html_e( 'PHP ImageMagick extension. This is critical—without it, the plugin cannot generate thumbnails. If not installed, contact your hosting provider immediately to enable it.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'WebP', 'modern-thumbnails' ); ?></strong></td>
                                <td>
                                    <?php echo $webp_supported ? '<span style="color: green;">✓ ' . esc_html__( 'Supported', 'modern-thumbnails' ) . '</span>' : '<span style="color: red;">✗ ' . esc_html__( 'Not Supported', 'modern-thumbnails' ) . '</span>'; ?>
                                </td>
                                <td><?php esc_html_e( 'Modern image format with excellent compression. Used for all thumbnails.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'AVIF', 'modern-thumbnails' ); ?></strong></td>
                                <td>
                                    <?php echo $avif_supported ? '<span style="color: green;">✓ ' . esc_html__( 'Supported', 'modern-thumbnails' ) . '</span>' : '<span style="color: orange;">⚠ ' . esc_html__( 'Not Supported', 'modern-thumbnails' ) . '</span>'; ?>
                                </td>
                                <td><?php esc_html_e( 'Next-generation image format with superior compression. Optional enhancement.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Web Server Type', 'modern-thumbnails' ); ?></strong></td>
                                <td>
                                    <?php 
                                        if (NginxConfigCheck::isRunningOnNginx()) {
                                            echo '<span style="color: green;">✓ Nginx</span>';
                                            if (!empty($_SERVER['SERVER_SOFTWARE'])) {
                                                echo ' <small style="color: #666;">(' . esc_html($_SERVER['SERVER_SOFTWARE']) . ')</small>';
                                            }
                                        } elseif (ApacheConfigCheck::isRunningOnApache()) {
                                            echo '<span style="color: green;">✓ Apache</span>';
                                            if (function_exists('apache_get_version')) {
                                                echo ' <small style="color: #666;">(' . esc_html(apache_get_version()) . ')</small>';
                                            } elseif (!empty($_SERVER['SERVER_SOFTWARE'])) {
                                                echo ' <small style="color: #666;">(' . esc_html($_SERVER['SERVER_SOFTWARE']) . ')</small>';
                                            }
                                        } else {
                                            echo '<span style="color: #999;">Unknown</span>';
                                            if (!empty($_SERVER['SERVER_SOFTWARE'])) {
                                                echo ' <small style="color: #666;">(' . esc_html($_SERVER['SERVER_SOFTWARE']) . ')</small>';
                                            }
                                        }
                                    ?>
                                </td>
                                <td><?php esc_html_e( 'The web server software running on your hosting.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <?php if (NginxConfigCheck::isRunningOnNginx()): ?>
                            <tr>
                                <td><strong><?php esc_html_e( 'Nginx Image Negotiation', 'modern-thumbnails' ); ?></strong></td>
                                <td>
                                    <?php 
                                        if (NginxConfigCheck::isNginxConfigured()) {
                                            echo '<span style="color: green;">✓ ' . esc_html__('Configured', 'modern-media-thumbnails') . '</span>';
                                        } else {
                                            echo '<span style="color: orange;">⚠ ' . esc_html__('Not Configured', 'modern-media-thumbnails') . '</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php esc_html_e( 'Automatic serving of AVIF and WebP formats based on browser support. Recommended for best performance. May further improve page load speed by up to 25-35% for compatible browsers.', 'modern-thumbnails' ); ?> <a href="#" class="mmt-config-view-link" data-config="nginx"><?php esc_html_e( 'View Configuration', 'modern-thumbnails' ); ?></a></td>
                            </tr>
                            <?php elseif (ApacheConfigCheck::isRunningOnApache()): ?>
                            <tr>
                                <td><strong><?php esc_html_e( 'Apache Rewrite Rules', 'modern-thumbnails' ); ?></strong></td>
                                <td>
                                    <?php 
                                        if ( ! ApacheConfigCheck::isModRewriteEnabled() ) {
                                            echo '<span style="color: red;">✗ ' . esc_html__( 'mod_rewrite Disabled', 'modern-thumbnails' ) . '</span>';
                                        } elseif ( ApacheConfigCheck::isApacheConfigured() ) {
                                            echo '<span style="color: green;">✓ ' . esc_html__( 'Configured', 'modern-thumbnails' ) . '</span>';
                                        } else {
                                            echo '<span style="color: orange;">⚠ ' . esc_html__( 'Not Configured', 'modern-thumbnails' ) . '</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php esc_html_e('Automatic serving of AVIF or Legacy formats based on browser support via .htaccess. Requires mod_rewrite. May further improve page load speed by up to 25-35% for compatible browsers.', 'modern-media-thumbnails'); ?> <a href="#" class="mmt-config-view-link" data-config="apache"><?php esc_html_e('View Configuration', 'modern-media-thumbnails'); ?></a></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <h3><?php esc_html_e( 'Media Library Statistics', 'modern-thumbnails' ); ?></h3>
                    <table class="wp-list-table widefat striped" id="mmt-media-stats">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Metric', 'modern-thumbnails' ); ?></th>
                                <th><?php esc_html_e( 'Value', 'modern-thumbnails' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'modern-thumbnails' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="mmt-media-stats-body">
                            <tr data-stat="media-count">
                                <td><strong><?php esc_html_e( 'Total Media Files', 'modern-thumbnails' ); ?></strong></td>
                                <td class="mmt-stat-value"><span class="mmt-skeleton mmt-skeleton-text" style="width: 80px;"></span></td>
                                <td><?php esc_html_e( 'Total number of media files in the library.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <tr data-stat="original-size">
                                                               <td><strong><?php esc_html_e( 'Original Files Size', 'modern-thumbnails' ); ?></strong></td>
                                <td class="mmt-stat-value"><span class="mmt-skeleton mmt-skeleton-text" style="width: 90px;"></span></td>
                                <td><?php esc_html_e( 'Disk space occupied by original/main media files.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <tr data-stat="thumbnail-size">
                                <td><strong><?php esc_html_e( 'Generated Thumbnails Size', 'modern-thumbnails' ); ?></strong></td>
                                <td class="mmt-stat-value"><span class="mmt-skeleton mmt-skeleton-text" style="width: 85px;"></span></td>
                                <td><?php esc_html_e( 'Disk space occupied by all thumbnail variants.', 'modern-thumbnails' ); ?></td>
                            </tr>
                            <tr data-stat="total-size" style="background-color: #f0f0f0; font-weight: bold;">
                                <td><strong><?php esc_html_e('Total Media Size', 'modern-media-thumbnails'); ?></strong></td>
                                <td class="mmt-stat-value"><span class="mmt-skeleton mmt-skeleton-text" style="width: 95px;"></span></td>
                                <td><?php esc_html_e('Total disk space occupied by all media files.', 'modern-media-thumbnails'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Configuration Modal Dialog -->
            <div id="mmt-config-modal" class="mmt-config-modal">
                <div class="mmt-config-modal-content">
                    <div class="mmt-config-modal-header">
                        <h2 id="mmt-config-modal-title"></h2>
                        <button class="mmt-config-modal-close" id="mmt-config-modal-close">&times;</button>
                    </div>
                    
                    <!-- Nginx Configuration -->
                    <div id="mmt-config-nginx" style="display: none;">
                        <p><?php esc_html_e('To enable automatic serving of optimized AVIF and WebP formats on nginx, add this configuration to your server block:', 'modern-media-thumbnails'); ?></p>
                        <p><strong><?php esc_html_e('File location:', 'modern-media-thumbnails'); ?></strong> <code>/etc/nginx/sites-enabled/default</code> <?php esc_html_e('or similar', 'modern-media-thumbnails'); ?></p>
                        <p><small><?php esc_html_e('After adding this configuration, reload nginx with: sudo systemctl reload nginx', 'modern-media-thumbnails'); ?></small></p>
                        <p><small style="color: #d63638;"><strong><?php esc_html_e('Important:', 'modern-media-thumbnails'); ?></strong> <?php esc_html_e('Backup your existing nginx configuration file before making changes.', 'modern-media-thumbnails'); ?></small></p>
                        <div class="mmt-config-code-block">
                            <pre class="mmt-config-code"><?php 
                                if (class_exists('ModernMediaThumbnails\\NginxConfigCheck')) {
                                    echo esc_html(NginxConfigCheck::getConfigurationSnippet());
                                } else {
                                    echo esc_html(__("Configuration not available. Please check the plugin installation.", 'modern-media-thumbnails'));
                                }
                            ?></pre>
                        </div>
                        <div class="mmt-config-modal-actions">
                            <button class="mmt-config-copy-btn" id="mmt-copy-nginx"><?php esc_html_e('Copy Code', 'modern-media-thumbnails'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Apache Configuration -->
                    <div id="mmt-config-apache" style="display: none;">
                        <p><?php esc_html_e('To enable automatic serving of optimized AVIF and WebP formats on Apache, add this configuration to your root .htaccess file:', 'modern-media-thumbnails'); ?></p>
                        <p><strong><?php esc_html_e('File location:', 'modern-media-thumbnails'); ?></strong> <code><?php echo esc_html(ABSPATH . '.htaccess'); ?></code></p>
                        <p><small><?php esc_html_e('This feature requires Apache mod_rewrite to be enabled. If mod_rewrite is not available, your site will continue to work normally—this configuration simply won\'t apply.', 'modern-media-thumbnails'); ?></small></p>
                        <p><small style="color: #d63638;"><strong><?php esc_html_e('Important:', 'modern-media-thumbnails'); ?></strong> <?php esc_html_e('Backup your existing .htaccess file before making changes. If something goes wrong, the backup allows you to restore it quickly.', 'modern-media-thumbnails'); ?></small></p>
                        <div class="mmt-config-code-block">
                            <pre class="mmt-config-code"><?php 
                                if (class_exists('ModernMediaThumbnails\\ApacheConfigCheck')) {
                                    echo esc_html(ApacheConfigCheck::getConfigurationSnippet());
                                } else {
                                    echo esc_html(__("Configuration not available. Please check the plugin installation.", 'modern-media-thumbnails'));
                                }
                            ?></pre>
                        </div>
                        <div class="mmt-config-modal-actions">
                            <button class="mmt-config-copy-btn" id="mmt-copy-apache"><?php esc_html_e('Copy Code', 'modern-media-thumbnails'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Global Footer: About This Plugin -->
            <div class="mmt-about-footer">
                <h2><?php esc_html_e('About Modern Thumbnails', 'modern-media-thumbnails'); ?></h2>
                <div class="card">
                    <!-- Logo Icon -->
                    <div class="mmt-about-logo">
                        <?php
                        $logo_path = dirname(dirname(dirname(__FILE__))) . '/assets/logo.svg';
                        if (file_exists($logo_path)) {
                            include $logo_path;
                        }
                        ?>
                    </div>
                    <p>
                        <?php esc_html_e('Unlike other thumbnail plugins that simply generate additional image files, Modern Thumbnails works smarter. It automatically replaces generated JPG/PNG thumbnails with optimized WebP format versions—significantly reducing file sizes without any loss of visual quality.', 'modern-media-thumbnails'); ?>
                    </p>
                    
                    <h3><?php esc_html_e('Key Benefits:', 'modern-media-thumbnails'); ?></h3>
                    <ul>
                        <li><strong><?php esc_html_e('Faster Page Loading', 'modern-media-thumbnails'); ?>:</strong> <?php esc_html_e('WebP files are 25-35% smaller than JPEG, resulting in faster image downloads and improved page performance.', 'modern-media-thumbnails'); ?></li>
                        <li><strong><?php esc_html_e('Save Server Space', 'modern-media-thumbnails'); ?>:</strong> <?php esc_html_e('Thumbnail files consume significantly less disk storage, reducing hosting costs and allowing you to store more media files.', 'modern-media-thumbnails'); ?></li>
                        <li><strong><?php esc_html_e('Automatic Processing', 'modern-media-thumbnails'); ?>:</strong> <?php esc_html_e('WebP versions are generated automatically on image upload for all theme-defined thumbnail sizes.', 'modern-media-thumbnails'); ?></li>
                        <li><strong><?php esc_html_e('Source Files Untouched', 'modern-media-thumbnails'); ?>:</strong> <?php esc_html_e('The original uploaded image file is never modified—only automatically generated thumbnails are optimized.', 'modern-media-thumbnails'); ?></li>
                        <li><strong><?php esc_html_e('Theme-Aware', 'modern-media-thumbnails'); ?>:</strong> <?php esc_html_e('Respects theme-defined crop settings for all image sizes, ensuring proper aspect ratios and alignments.', 'modern-media-thumbnails'); ?></li>
                        <li><strong><?php esc_html_e('High-Performance Processing', 'modern-media-thumbnails'); ?>:</strong> <?php esc_html_e('Powered by Imagick library for fast, efficient image processing.', 'modern-media-thumbnails'); ?></li>
                    </ul>
                    
                    <p style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee;">
                        <em><?php esc_html_e('This plugin is ideal for websites with large media libraries that want to improve performance and reduce storage costs without compromising image quality.', 'modern-media-thumbnails'); ?></em>
                    </p>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            console.log('MMT Settings page loaded');
            console.log('jQuery available:', typeof jQuery !== 'undefined');
            console.log('mmtData available:', typeof mmtData !== 'undefined');
            if (typeof mmtData !== 'undefined') {
                console.log('mmtData.nonce:', mmtData.nonce);
                console.log('mmtData.ajaxUrl:', mmtData.ajaxUrl);
            }
            console.log('Stats table exist:', document.getElementById('mmt-media-stats') !== null);
            
            // Inline fallback - load media stats without relying on external admin.js
            (function() {
                var $ = jQuery;
                
                // Create mmtData if not available from wp_localize_script
                if (typeof mmtData === 'undefined') {
                    console.log('Creating fallback mmtData object');
                    var settingsNonceField = document.querySelector('input[name="mmt_settings_nonce"]');
                    window.mmtData = {
                        nonce: document.querySelector('input[name="mmt_regenerate_nonce"]') ? document.querySelector('input[name="mmt_regenerate_nonce"]').value : '',
                        settingsNonce: settingsNonceField ? settingsNonceField.value : '',
                        ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>'
                    };
                }
                
                // Ensure settingsNonce is available
                if (!window.mmtData.settingsNonce) {
                    var settingsNonceField = document.querySelector('input[name="mmt_settings_nonce"]');
                    if (settingsNonceField) {
                        window.mmtData.settingsNonce = settingsNonceField.value;
                    }
                }
                
                console.log('Using mmtData:', window.mmtData);
                
                // Load individual media metric
                function loadMediaMetric(metricKey, action) {
                    $.ajax({
                        url: window.mmtData.ajaxUrl,
                        type: 'POST',
                        dataType: 'json',
                        timeout: 30000,
                        data: {
                            action: action,
                            nonce: window.mmtData.settingsNonce
                        },
                        success: function(response) {
                            if (response.success && response.data && response.data.value) {
                                $('#mmt-media-stats tr[data-stat="' + metricKey + '"] .mmt-stat-value').text(response.data.value);
                            } else {
                                $('#mmt-media-stats tr[data-stat="' + metricKey + '"] .mmt-stat-value').html('<span style="color: red;">Failed</span>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error for metric ' + metricKey + ':', status, error, xhr.responseText);
                            $('#mmt-media-stats tr[data-stat="' + metricKey + '"] .mmt-stat-value').html('<span style="color: red;">Error</span>');
                        }
                    });
                }
                
                // Load media statistics - each metric as separate request
                function loadMediaStats() {
                    console.log('Loading media stats...');
                    
                    // Show loading state for all metrics
                    $('#mmt-media-stats .mmt-stat-value').html('<span style="color: #999; font-style: italic;">Calculating…</span>');
                    
                    // Load each metric separately
                    loadMediaMetric('media-count', 'mmt_get_media_count');
                    loadMediaMetric('original-size', 'mmt_get_original_size');
                    loadMediaMetric('thumbnail-size', 'mmt_get_thumbnail_size');
                    loadMediaMetric('total-size', 'mmt_get_total_size');
                }
                
                // Run when jQuery is ready
                $(document).ready(function() {
                    loadMediaStats();
                    
                    // Handle dynamic footer rendering for keep_original
                    $('#mmt_keep_original').change(function() {
                        var $card = $(this).closest('.mmt-setting-card');
                        var $footer = $card.find('.mmt-card-footer');
                        
                        if (this.checked) {
                            // Show the existing footer if it exists, or create it
                            if ($footer.length === 0) {
                                var footerHtml = '<div class="mmt-card-footer">' +
                                    '<div class="mmt-quality-control">' +
                                    '<label for="mmt_original_quality"><?php esc_html_e('Quality', 'modern-media-thumbnails'); ?></label>' +
                                    '<input type="range" id="mmt_original_quality" name="settings[original_quality]" min="0" max="100" value="<?php echo intval($settings['original_quality']); ?>" class="mmt-quality-slider">' +
                                    '<span class="mmt-quality-value"><?php echo intval($settings['original_quality']); ?></span>' +
                                    '</div>' +
                                    '</div>';
                                $card.append(footerHtml);
                            } else {
                                $footer.show();
                            }
                        } else {
                            // Hide or remove the footer
                            if ($footer.length > 0) {
                                $footer.remove();
                            }
                        }
                    });
                    
                    
                    // Handle dynamic footer rendering for keep_exif
                    $('#mmt_keep_exif').change(function() {
                        var $card = $(this).closest('.mmt-setting-card');
                        var $footer = $card.find('.mmt-card-footer');
                        
                        if (this.checked) {
                            // Show the existing footer if it exists
                            if ($footer.length > 0) {
                                $footer.show();
                            }
                        } else {
                            // Hide the footer
                            if ($footer.length > 0) {
                                $footer.hide();
                            }
                        }
                    });
                    
                    // Update quality value display on input
                    $(document).on('input', '.mmt-quality-slider', function() {
                        var $slider = $(this);
                        var $valueDisplay = $slider.siblings('.mmt-quality-value');
                        $valueDisplay.text($(this).val());
                    });
                    
                    // Configuration Modal
                    var $modal = $('#mmt-config-modal');
                    var $modalTitle = $('#mmt-config-modal-title');
                    var $modalClose = $('#mmt-config-modal-close');
                    
                    // Open modal on config link click
                    $('.mmt-config-view-link').on('click', function(e) {
                        e.preventDefault();
                        var configType = $(this).data('config');
                        var title = configType === 'nginx' ? 'Nginx Configuration' : 'Apache Configuration';
                        
                        $modalTitle.text(title);
                        $('#mmt-config-nginx, #mmt-config-apache').hide();
                        $('#mmt-config-' + configType).show();
                        $modal.addClass('active');
                    });
                    
                    // Close modal on close button or background click
                    $modalClose.on('click', function() {
                        $modal.removeClass('active');
                    });
                    
                    $modal.on('click', function(e) {
                        if (e.target === this) {
                            $modal.removeClass('active');
                        }
                    });
                    
                    // Copy buttons
                    $('#mmt-copy-nginx').on('click', function() {
                        var code = $('#mmt-config-nginx .mmt-config-code').text();
                        copyToClipboard(code, this);
                    });
                    
                    $('#mmt-copy-apache').on('click', function() {
                        var code = $('#mmt-config-apache .mmt-config-code').text();
                        copyToClipboard(code, this);
                    });
                    
                    function copyToClipboard(text, button) {
                        var $button = $(button);
                        var originalText = $button.text();
                        
                        navigator.clipboard.writeText(text).then(function() {
                            $button.text('<?php esc_html_e('Copied!', 'modern-media-thumbnails'); ?>');
                            setTimeout(function() {
                                $button.text(originalText);
                            }, 2000);
                        }).catch(function(err) {
                            console.error('Failed to copy:', err);
                        });
                    }
                });
            })();
        </script>
        <?php
    }
    
    /**
     * Get total count of media files
     * 
     * @return int Total number of media files
     */
    public static function getMediaFileCount() {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => 'inherit',
        ]);
        
        return count($attachments);
    }
    
    /**
     * Get total size of original media files on disk
     * 
     * @return int Total size in bytes
     */
    public static function getOriginalFileSize() {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => 'inherit',
        ]);
        
        $total_size = 0;
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            if ($file_path && file_exists($file_path)) {
                $total_size += filesize($file_path);
            }
        }
        
        return $total_size;
    }
    
    /**
     * Get total size of thumbnail variants on disk
     * Counts only files matching the thumbnail naming pattern: -WIDTHxHEIGHT.extension
     * This matches what clear-thumbnails.sh actually deletes
     * 
     * @return int Total size in bytes
     */
    public static function getThumbnailFileSize() {
        // Collect all unique directories containing media files
        $media_directories = [];
        
        $attachments = get_posts([
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => 'inherit',
        ]);
        
        foreach ($attachments as $attachment) {
            $original_file = get_attached_file($attachment->ID);
            if ($original_file) {
                $dir = dirname($original_file);
                $media_directories[$dir] = true;
            }
        }
        
        // Count only thumbnail files matching -WIDTHxHEIGHT pattern
        $total_size = 0;
        foreach (array_keys($media_directories) as $dir) {
            $files = @scandir($dir);
            if ($files === false) {
                continue;
            }
            
            foreach ($files as $file) {
                // Match pattern: -WIDTHxHEIGHT.extension (e.g., -150x150.jpg, -300x300.webp)
                if (preg_match('/-\d+x\d+\.(jpg|jpeg|png|gif|webp|avif)$/i', $file)) {
                    $filepath = $dir . DIRECTORY_SEPARATOR . $file;
                    if (file_exists($filepath)) {
                        $total_size += filesize($filepath);
                    }
                }
            }
        }
        
        return $total_size;
    }
    
    /**
     * Recursively calculate directory size
     * 
     * @param string $path Directory path
     * @return int Total size in bytes
     */
    private static function getDirectorySize($path) {
        $size = 0;
        
        if (!is_dir($path)) {
            return 0;
        }
        
        try {
            $files = @scandir($path);
            if ($files === false) {
                return 0;
            }
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $filepath = $path . DIRECTORY_SEPARATOR . $file;
                
                if (is_dir($filepath)) {
                    $size += self::getDirectorySize($filepath);
                } elseif (is_file($filepath)) {
                    $size += filesize($filepath);
                }
            }
        } catch (\Exception $e) {
            // Silently handle errors
        }
        
        return $size;
    }
    
    /**
     * Get total size of media files on disk
     * 
     * @return int Total size in bytes
     */
    public static function getMediaFileSize() {
        return self::getOriginalFileSize() + self::getThumbnailFileSize();
    }
    
    /**
     * Convert bytes to human-readable format
     * 
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Register admin menu
     * 
     * @return void
     */
    public static function registerMenu() {
        add_options_page(
            'Modern Thumbnails',
            'WebP Thumbnails',
            'manage_options',
            'mmt-settings',
            [self::class, 'render']
        );
    }
}
