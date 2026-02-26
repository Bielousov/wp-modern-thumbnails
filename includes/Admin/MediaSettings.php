<?php
/**
 * Media Settings Integration
 * 
 * Adds Image Formats section to Settings > Media page.
 */

namespace ModernMediaThumbnails\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ModernMediaThumbnails\Settings;

class MediaSettings {
    
    /**
     * Register the settings section and field
     * 
     * @return void
     */
    public static function register() {
        add_action('admin_init', [self::class, 'registerSection']);
    }
    
    /**
     * Register settings section on admin_init
     * 
     * @return void
     */
    public static function registerSection() {
        // Add settings section to Media options page
        add_settings_section(
            'mmt_image_formats',
            __( 'Image Formats', 'modern-thumbnails' ),
            [self::class, 'renderSection'],
            'media'
        );
        
        // Add settings field
        add_settings_field(
            'mmt_image_formats_info',
            __( 'Enabled Formats', 'modern-thumbnails' ),
            [self::class, 'renderField'],
            'media',
            'mmt_image_formats'
        );
    }
    
    /**
     * Render the section description
     * 
     * @return void
     */
    public static function renderSection() {
        echo '<p>' . esc_html__( 'Configure which image formats are automatically generated for uploaded images.', 'modern-thumbnails' ) . '</p>';
    }
    
    /**
     * Render the field content
     * 
     * @return void
     */
    public static function renderField() {
        $settings = Settings::getWithDefaults();
        
        // Build the list of enabled formats
        $enabled_formats = [];
        
        // WebP is always enabled
        $enabled_formats[] = sprintf(
            '<span class="mmt-media-format mmt-format-webp">%s <span class="mmt-quality-badge">%d%%</span></span>',
            esc_html__( 'WebP', 'modern-thumbnails' ),
            intval($settings['webp_quality'])
        );
        
        // Check for original format
        if ($settings['keep_original']) {
            $enabled_formats[] = sprintf(
                '<span class="mmt-media-format mmt-format-original">%s <span class="mmt-quality-badge">%d%%</span></span>',
                esc_html__( 'Original (JPEG/PNG)', 'modern-thumbnails' ),
                intval($settings['original_quality'])
            );
        }
        
        // Check for AVIF format
        if ($settings['generate_avif']) {
            $enabled_formats[] = sprintf(
                '<span class="mmt-media-format mmt-format-avif">%s <span class="mmt-quality-badge">%d%%</span></span>',
                esc_html__( 'AVIF', 'modern-thumbnails' ),
                intval($settings['avif_quality'])
            );
        }
        
        // Display the enabled formats
        echo '<div class="mmt-media-formats-list">';
        echo wp_kses_post( implode( ' ', $enabled_formats ) );
        echo '</div>';
        
        // Provide link to plugin settings
        $settings_url = admin_url('options-general.php?page=mmt-settings&tab=settings');
        echo '<p style="margin-top: 12px;">';
        printf(
            '<a href="%s" class="button button-secondary">%s</a>',
            esc_url($settings_url),
            esc_html__( 'Configure Image Format Settings', 'modern-thumbnails' )
        );
        echo '</p>';
    }
}
