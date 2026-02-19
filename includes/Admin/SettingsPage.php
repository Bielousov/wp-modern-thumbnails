<?php
/**
 * Settings Page
 * 
 * Renders the admin settings page.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\ImageSizeManager;
use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\SystemCheck;

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
        $settings = FormatManager::getFormatSettings();
        
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
            <h1><?php esc_html_e('Modern Media Thumbnails', 'modern-media-thumbnails'); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="?page=mmt-settings&tab=sizes" class="nav-tab <?php echo $active_tab === 'sizes' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Theme Image Sizes', 'modern-media-thumbnails'); ?>
                </a>
                <a href="?page=mmt-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Plugin Settings', 'modern-media-thumbnails'); ?>
                </a>
                <a href="?page=mmt-settings&tab=system" class="nav-tab <?php echo $active_tab === 'system' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('System Status', 'modern-media-thumbnails'); ?>
                </a>
            </nav>
            
            <!-- Settings Tab -->
            <?php if ($active_tab === 'settings'): ?>
                <div class="tab-content">
                    <form id="mmt-settings-form" method="post">
                        <?php wp_nonce_field('mmt_settings_nonce', 'mmt_settings_nonce'); ?>
                        
                        <h2><?php esc_html_e('Global Settings', 'modern-media-thumbnails'); ?></h2>
                        <p><?php esc_html_e('Configure how the plugin processes and stores image formats.', 'modern-media-thumbnails'); ?></p>
                        
                        <div class="mmt-settings-grid">
                            <!-- Keep Original Images Setting -->
                            <div class="mmt-setting-card">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e('Keep Original Images', 'modern-media-thumbnails'); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_keep_original" 
                                               name="settings[keep_original]" 
                                               value="1"
                                               <?php checked($settings['keep_original'] ?? false); ?>>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e('Keep original image files even after generating optimized formats', 'modern-media-thumbnails'); ?></p>
                            </div>
                            
                            <!-- Generate AVIF Format Setting -->
                            <div class="mmt-setting-card">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e('Generate AVIF Format', 'modern-media-thumbnails'); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_generate_avif" 
                                               name="settings[generate_avif]" 
                                               value="1"
                                               <?php checked($settings['generate_avif'] ?? false); ?>>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e('Generate AVIF format for supported images (requires external library). AVIF provides superior compression compared to WebP.', 'modern-media-thumbnails'); ?></p>
                            </div>
                            
                            <!-- Convert GIFs to Video Setting -->
                            <div class="mmt-setting-card">
                                <div class="mmt-card-header">
                                    <h3><?php esc_html_e('Convert GIFs to Video', 'modern-media-thumbnails'); ?></h3>
                                    <label class="mmt-switch mmt-switch-compact">
                                        <input type="checkbox" 
                                               id="mmt_convert_gif" 
                                               name="settings[convert_gif]" 
                                               value="1"
                                               <?php checked($settings['convert_gif'] ?? false); ?>>
                                        <span class="toggle"></span>
                                    </label>
                                </div>
                                <p class="mmt-card-description"><?php esc_html_e('Convert animated GIFs to MP4/WebM formats for better performance and compatibility', 'modern-media-thumbnails'); ?></p>
                            </div>
                        </div>
                                    <span><?php esc_html_e('Convert animated GIFs to MP4/WebM formats for better performance and compatibility', 'modern-media-thumbnails'); ?></span>
                                </label>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Information Box -->
                    <h2><?php esc_html_e('About This Plugin', 'modern-media-thumbnails'); ?></h2>
                    <div class="card">
                        <p>
                            <?php esc_html_e('This plugin automatically generates WebP versions of all thumbnails defined in your theme, using the Imagick library for fast and efficient image processing.', 'modern-media-thumbnails'); ?>
                        </p>
                        <h3><?php esc_html_e('Features:', 'modern-media-thumbnails'); ?></h3>
                        <ul>
                            <li><?php esc_html_e('Automatic WebP generation on image upload', 'modern-media-thumbnails'); ?></li>
                            <li><?php esc_html_e('Respects theme-defined crop settings', 'modern-media-thumbnails'); ?></li>
                            <li><?php esc_html_e('High-performance Imagick-based processing', 'modern-media-thumbnails'); ?></li>
                            <li><?php esc_html_e('Complete theme image size detection', 'modern-media-thumbnails'); ?></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Theme Image Sizes Tab -->
            <?php if ($active_tab === 'sizes'): ?>
                <div class="tab-content">
                    <h2><?php esc_html_e('Theme Image Sizes', 'modern-media-thumbnails'); ?></h2>
                    <p><?php esc_html_e('The following image sizes are defined in your theme and will be generated as WebP:', 'modern-media-thumbnails'); ?></p>
                    
                    <div style="margin-bottom: 20px;">
                        <button id="mmt-regenerate-all" class="button button-primary">
                            <?php esc_html_e('Regenerate All Thumbnails', 'modern-media-thumbnails'); ?>
                        </button>
                    </div>
                    
                    <div id="mmt-regenerate-message" style="display: none; margin-bottom: 15px;"></div>
                    
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 130px;"><?php esc_html_e('Resolution & Crop', 'modern-media-thumbnails'); ?></th>
                                <th><?php esc_html_e('Size Name', 'modern-media-thumbnails'); ?></th>
                                <th><?php esc_html_e('Formats', 'modern-media-thumbnails'); ?></th>
                                <th style="text-align: center;"><?php esc_html_e('Action', 'modern-media-thumbnails'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Original Image Row -->
                            <tr style="background-color: #f9f9f9; font-weight: bold;">
                                <td style="text-align: center;">
                                    <div class="mmt-resolution-box" style="width: 120px; height: 120px;">
                                        <div>
                                            <?php esc_html_e('Full', 'modern-media-thumbnails'); ?><br><span style="font-size: 14px;">×</span><br><?php esc_html_e('Full', 'modern-media-thumbnails'); ?>
                                            <small>px</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php esc_html_e('Original Image', 'modern-media-thumbnails'); ?></strong>
                                </td>
                                <td>
                                    <span class="mmt-format-badge mmt-format-original"><?php esc_html_e('Original (JPEG/PNG/GIF)', 'modern-media-thumbnails'); ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <span style="color: #999;"><?php esc_html_e('Auto-kept', 'modern-media-thumbnails'); ?></span>
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
                                    $crop_badge = $crop ? esc_html__('Crop', 'modern-media-thumbnails') : esc_html__('No Crop', 'modern-media-thumbnails');
                                    $crop_bg_color = $crop ? '#fee' : '#efe';
                                    ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <div class="mmt-resolution-box <?php echo $crop ? 'hard-crop' : 'soft-crop'; ?>" style="width: 120px; height: 120px;">
                                                <span style="position: absolute; top: 6px; left: 6px; background-color: <?php echo $crop_bg_color; ?>; padding: 3px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;">
                                                    <?php echo $crop_badge; ?>
                                                </span>
                                                <div>
                                                    <?php echo esc_html($width); ?><br><span style="font-size: 14px;">×</span><br><?php echo esc_html($height); ?>
                                                    <small>px</small>
                                                </div>
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
                                            <span class="mmt-format-badge mmt-format-webp"><?php esc_html_e('WebP + Original', 'modern-media-thumbnails'); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <button class="button mmt-regenerate-size" data-size="<?php echo esc_attr($size_name); ?>">
                                                <?php esc_html_e('Regenerate', 'modern-media-thumbnails'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <em><?php esc_html_e('No custom image sizes defined in your theme.', 'modern-media-thumbnails'); ?></em>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- System Status Tab -->
            <?php if ($active_tab === 'system'): ?>
                <div class="tab-content">
                    <h2><?php esc_html_e('System Status', 'modern-media-thumbnails'); ?></h2>
                    <p><?php esc_html_e('Check if your server has the required components installed and properly configured.', 'modern-media-thumbnails'); ?></p>
                    
                    <h3><?php esc_html_e('Server Components', 'modern-media-thumbnails'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Component', 'modern-media-thumbnails'); ?></th>
                                <th><?php esc_html_e('Status', 'modern-media-thumbnails'); ?></th>
                                <th><?php esc_html_e('Description', 'modern-media-thumbnails'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e('PHP Version', 'modern-media-thumbnails'); ?></strong></td>
                                <td>
                                    <span style="color: green;">✓ <?php echo esc_html(phpversion()); ?></span>
                                </td>
                                <td><?php esc_html_e('Current PHP version running on the server.', 'modern-media-thumbnails'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Imagick', 'modern-media-thumbnails'); ?></strong></td>
                                <td>
                                    <?php echo $imagick_available ? '<span style="color: green;">✓ ' . esc_html__('Installed', 'modern-media-thumbnails') . '</span>' : '<span style="color: red;">✗ ' . esc_html__('Not Installed', 'modern-media-thumbnails') . '</span>'; ?>
                                </td>
                                <td><?php esc_html_e('Image manipulation library required for processing. Necessary for plugin functionality.', 'modern-media-thumbnails'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('WebP', 'modern-media-thumbnails'); ?></strong></td>
                                <td>
                                    <?php echo $webp_supported ? '<span style="color: green;">✓ ' . esc_html__('Supported', 'modern-media-thumbnails') . '</span>' : '<span style="color: red;">✗ ' . esc_html__('Not Supported', 'modern-media-thumbnails') . '</span>'; ?>
                                </td>
                                <td><?php esc_html_e('Modern image format with excellent compression. Used for all thumbnails.', 'modern-media-thumbnails'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('AVIF', 'modern-media-thumbnails'); ?></strong></td>
                                <td>
                                    <?php echo $avif_supported ? '<span style="color: green;">✓ ' . esc_html__('Supported', 'modern-media-thumbnails') . '</span>' : '<span style="color: orange;">⚠ ' . esc_html__('Not Supported', 'modern-media-thumbnails') . '</span>'; ?>
                                </td>
                                <td><?php esc_html_e('Next-generation image format with superior compression. Optional enhancement.', 'modern-media-thumbnails'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Register admin menu
     * 
     * @return void
     */
    public static function registerMenu() {
        add_options_page(
            'Modern Media Thumbnails',
            'WebP Thumbnails',
            'manage_options',
            'mmt-settings',
            [self::class, 'render']
        );
    }
}
