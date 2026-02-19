<?php
/**
 * Bulk Actions Handler
 * 
 * Handles bulk regeneration of thumbnails from the WordPress Media Library.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\ImageSizeManager;

class BulkActions {
    
    /**
     * Register bulk action hooks
     * 
     * @return void
     */
    public static function register() {
        add_filter('bulk_actions-upload', [self::class, 'registerBulkAction']);
        add_filter('handle_bulk_actions-upload', [self::class, 'handleBulkAction'], 10, 3);
    }
    
    /**
     * Register the regenerate thumbnails bulk action
     * 
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public static function registerBulkAction($actions) {
        $actions['mmt_regenerate_thumbnails'] = __('Regenerate Thumbnails', 'modern-media-thumbnails');
        return $actions;
    }
    
    /**
     * Handle the bulk action
     * 
     * @param string $redirect_url Redirect URL
     * @param string $action Bulk action name
     * @param array $post_ids Array of post IDs to process
     * @return string Modified redirect URL with result parameters
     */
    public static function handleBulkAction($redirect_url, $action, $post_ids) {
        if ($action !== 'mmt_regenerate_thumbnails') {
            return $redirect_url;
        }
        
        // Don't process server-side. The JavaScript will handle it via AJAX queue.
        // Just return the redirect URL unchanged, and let the JavaScript handle the processing.
        // This allows the AJAX queue to prevent this from running and show loading states.
        
        error_log('MMT: handleBulkAction called (should be prevented by JavaScript)');
        
        // Return redirect without processing - let AJAX handle it
        return $redirect_url;
    }
    
    /**
     * Regenerate all thumbnail sizes for a single attachment
     * 
     * @param int $attachment_id
     * @return bool True if successful, false otherwise
     */
    private static function regenerateAttachmentThumbnails($attachment_id) {
        try {
            $attachment = get_post($attachment_id);
            
            // Verify it's an image
            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                return false;
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                return false;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata)) {
                return false;
            }
            
            // Load the attachment and regenerate all sizes
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            $new_metadata = wp_generate_attachment_metadata($attachment_id, $file);
            
            if (is_wp_error($new_metadata)) {
                return false;
            }
            
            // Update attachment metadata
            wp_update_attachment_metadata($attachment_id, $new_metadata);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
