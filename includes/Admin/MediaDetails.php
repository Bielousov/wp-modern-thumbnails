<?php
/**
 * Media Details Integration
 * 
 * Adds regenerate thumbnails button to individual media attachment edit page.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\FormatManager;

class MediaDetails {
    
    /**
     * Register media details hooks
     * 
     * @return void
     */
    public static function register() {
        // Add button to edit media page - use high priority to place it at the end
        add_action('attachment_submitbox_misc_actions', [self::class, 'addRegenerateButton'], 999);
        
        // Enqueue script for media details page
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }
    
    /**
     * Enqueue scripts and styles for media details page
     * 
     * @param string $hook Page hook
     * @return void
     */
    public static function enqueue($hook) {
        // Only load on attachment edit page
        if ($hook !== 'post.php') {
            return;
        }
        
        // Check if we're editing an attachment
        if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'edit') {
            return;
        }
        
        $post_id = intval($_GET['post']);
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'attachment') {
            return;
        }
        
        // Enqueue the media details script
        $plugin_url = defined('MMT_PLUGIN_URL') ? MMT_PLUGIN_URL : plugin_dir_url(dirname(dirname(dirname(__FILE__))));
        $plugin_version = mmt_get_version();
        
        wp_enqueue_script(
            'mmt-media-details',
            $plugin_url . 'js/media-details.js',
            [],
            $plugin_version,
            true
        );
        
        // Localize script with AJAX URL, nonce, and post ID
        wp_localize_script(
            'mmt-media-details',
            'mmtMediaDetails',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mmt_bulk_action_nonce'),
                'postId' => $post_id,
            ]
        );
        
        // Enqueue styles
        wp_enqueue_style(
            'mmt-media-details',
            $plugin_url . 'css/media-details.css',
            [],
            $plugin_version
        );
    }
    
    /**
     * Get list of enabled formats
     * 
     * @return array
     */
    private static function getEnabledFormats() {
        $formats = [];
        
        if (FormatManager::shouldGenerateWebP()) {
            $formats[] = 'WebP';
        }
        
        if (FormatManager::shouldKeepOriginal()) {
            $formats[] = 'JPEG/PNG';
        }
        
        if (FormatManager::shouldGenerateAVIF()) {
            $formats[] = 'AVIF';
        }
        
        return $formats;
    }
    
    /**
     * Add regenerate button to media details page
     * 
     * @return void
     */
    public static function addRegenerateButton() {
        global $post;
        
        if (!$post || $post->post_type !== 'attachment') {
            return;
        }
        
        // Check if it's an image
        if (!preg_match('~^image/~', $post->post_mime_type)) {
            return;
        }
        
        // Check user capability
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }
        
        // Get enabled formats
        $formats = self::getEnabledFormats();
        $formats_text = implode(', ', $formats);
        
        ?>
        <div class="mmt-media-details-action">
            <button type="button" id="mmt-regenerate-btn" class="button button-secondary mmt-regenerate-button">
                <?php esc_html_e( 'Regenerate Thumbnails', 'modern-thumbnails' ); ?>
            </button>
            <span id="mmt-regenerate-status" class="mmt-regenerate-status" style="display: none;"></span>
            <?php if (!empty($formats)): ?>
                <span class="mmt-formats-list">
                    <?php esc_html_e( 'Enabled Formats:', 'modern-thumbnails' ); ?>
                    <span class="mmt-formats-items"><?php echo esc_html($formats_text); ?></span>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
}
