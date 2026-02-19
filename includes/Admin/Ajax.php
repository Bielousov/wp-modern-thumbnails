<?php
/**
 * AJAX Handler
 * 
 * Handles AJAX requests for regeneration and format settings.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\WordPress\RegenerationManager;
use ModernMediaThumbnails\Admin\SettingsPage;

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
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        // If attachment_id is provided, regenerate only that specific attachment
        if ($attachment_id) {
            $regenerated = self::regenerateAttachmentSize($attachment_id, $size_name);
            
            wp_send_json_success([
                'message' => sprintf(
                    'Generated %d format(s) for media #%d',
                    $regenerated,
                    $attachment_id
                ),
                'attachment_id' => $attachment_id,
                'count' => $regenerated
            ]);
        } else {
            // Original behavior - regenerate all attachments for a size (deprecated)
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
    }
    
    /**
     * Regenerate a specific size for a single attachment
     * 
     * @param int $attachment_id
     * @param string|null $size_name
     * @return int Number of formats generated
     */
    private static function regenerateAttachmentSize($attachment_id, $size_name = null) {
        $regenerated = 0;
        
        try {
            $attachment = get_post($attachment_id);
            
            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                return 0;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata) || empty($metadata['sizes'])) {
                return 0;
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                return 0;
            }
            
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            if ($size_name && isset($metadata['sizes'][$size_name]) && isset($image_sizes[$size_name])) {
                // Regenerate specific size only
                $size_data = $metadata['sizes'][$size_name];
                $size_file = dirname($file) . '/' . $size_data['file'];
                $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
                $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
                $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
                
                if ($width && $height) {
                    // Generate WebP
                    $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                    if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($file, $webp_file, $width, $height, $crop)) {
                        $regenerated++;
                    }
                    
                    // Generate AVIF if enabled
                    if (FormatManager::shouldGenerateAVIF()) {
                        $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($file, $avif_file, $width, $height, $crop)) {
                            $regenerated++;
                        }
                    }
                    
                    // Delete original if not keeping it
                    if (!FormatManager::shouldKeepOriginal()) {
                        if (file_exists($size_file)) {
                            @unlink($size_file);
                        }
                    }
                }
            } else if (!$size_name) {
                // Regenerate all sizes for this attachment
                foreach ($metadata['sizes'] as $sz_name => $size_data) {
                    if (!isset($image_sizes[$sz_name])) {
                        continue;
                    }
                    
                    $size_file = dirname($file) . '/' . $size_data['file'];
                    $width = isset($image_sizes[$sz_name]['width']) ? $image_sizes[$sz_name]['width'] : 0;
                    $height = isset($image_sizes[$sz_name]['height']) ? $image_sizes[$sz_name]['height'] : 0;
                    $crop = isset($image_sizes[$sz_name]['crop']) ? $image_sizes[$sz_name]['crop'] : false;
                    
                    if ($width && $height) {
                        // Generate WebP
                        $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($file, $webp_file, $width, $height, $crop)) {
                            $regenerated++;
                        }
                        
                        // Generate AVIF if enabled
                        if (FormatManager::shouldGenerateAVIF()) {
                            $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                            if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($file, $avif_file, $width, $height, $crop)) {
                                $regenerated++;
                            }
                        }
                        
                        // Delete original if not keeping it
                        if (!FormatManager::shouldKeepOriginal()) {
                            if (file_exists($size_file)) {
                                @unlink($size_file);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error silently
            error_log('Error regenerating attachment ' . $attachment_id . ': ' . $e->getMessage());
        }
        
        return $regenerated;
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
        
        // Update settings with actual values (convert 1/0 to true/false)
        FormatManager::updateSettings([
            'keep_original' => (bool)($settings['keep_original'] ?? false),
            'generate_avif' => (bool)($settings['generate_avif'] ?? false),
            'convert_gif' => (bool)($settings['convert_gif'] ?? false),
        ]);
        
        wp_send_json_success([
            'message' => 'Settings updated successfully'
        ]);
    }
    
    /**
     * Get list of all media files in the library
     * 
     * @return void
     */
    public static function getMediaFilesList() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
        ]);
        
        wp_send_json_success([
            'media_ids' => $attachments,
            'total' => count($attachments),
        ]);
    }
    
    /**
     * Get media file count
     * 
     * @return void
     */
    public static function getMediaCount() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $media_count = SettingsPage::getMediaFileCount();
            wp_send_json_success([
                'value' => $media_count,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating media count: ' . $e->getMessage());
        }
    }
    
    /**
     * Get original file size
     * 
     * @return void
     */
    public static function getOriginalSize() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $original_size = SettingsPage::getOriginalFileSize();
            wp_send_json_success([
                'value' => SettingsPage::formatBytes($original_size),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating original size: ' . $e->getMessage());
        }
    }
    
    /**
     * Get thumbnail file size
     * 
     * @return void
     */
    public static function getThumbnailSize() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $thumbnail_size = SettingsPage::getThumbnailFileSize();
            wp_send_json_success([
                'value' => SettingsPage::formatBytes($thumbnail_size),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating thumbnail size: ' . $e->getMessage());
        }
    }
    
    /**
     * Get total media file size
     * 
     * @return void
     */
    public static function getTotalSize() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $original_size = SettingsPage::getOriginalFileSize();
            $thumbnail_size = SettingsPage::getThumbnailFileSize();
            $total_size = $original_size + $thumbnail_size;
            
            wp_send_json_success([
                'value' => SettingsPage::formatBytes($total_size),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating total size: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize the regeneration queue - returns all attachment IDs to process
     * 
     * @return void
     */
    public static function regenerateQueueStart() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
        ]);
        
        wp_send_json_success([
            'attachment_ids' => $attachments,
            'total_count' => count($attachments),
            'message' => 'Queue initialized with ' . count($attachments) . ' media items',
        ]);
    }
    
    /**
     * Process a single attachment in the regeneration queue
     * 
     * @return void
     */
    public static function regenerateQueueProcess() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error('No attachment ID specified');
        }
        
        $regenerated = 0;
        
        try {
            // Regenerate thumbnails for this single attachment
            $attachment = get_post($attachment_id);
            
            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (not an image)',
                ]);
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata) || empty($metadata['sizes'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (no thumbnails)',
                ]);
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (file not found)',
                ]);
            }
            
            // Process all sizes for this attachment
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (!isset($image_sizes[$size_name])) {
                    continue;
                }
                
                $size_file = dirname($file) . '/' . $size_data['file'];
                $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
                $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
                $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
                
                if ($width && $height) {
                    // Generate WebP
                    $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                    if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($file, $webp_file, $width, $height, $crop)) {
                        $regenerated++;
                    }
                    
                    // Generate AVIF if enabled
                    if (FormatManager::shouldGenerateAVIF()) {
                        $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($file, $avif_file, $width, $height, $crop)) {
                            $regenerated++;
                        }
                    }
                    
                    // Delete original if not keeping it
                    if (!FormatManager::shouldKeepOriginal()) {
                        if (file_exists($size_file)) {
                            @unlink($size_file);
                        }
                    }
                }
            }
            
            wp_send_json_success([
                'attachment_id' => $attachment_id,
                'regenerated' => $regenerated,
                'message' => 'Processed media item (generated ' . $regenerated . ' formats)',
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error processing attachment ' . $attachment_id . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize the regeneration queue for a specific size - returns all attachment IDs to process
     * 
     * @return void
     */
    public static function regenerateQueueSizeStart() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $size_name = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : null;
        
        if (!$size_name) {
            wp_send_json_error('No size specified');
        }
        
        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
        ]);
        
        wp_send_json_success([
            'attachment_ids' => $attachments,
            'total_count' => count($attachments),
            'size' => $size_name,
            'message' => 'Queue initialized with ' . count($attachments) . ' media items for size "' . $size_name . '"',
        ]);
    }
    
    /**
     * Process a single attachment for a specific size in the regeneration queue
     * 
     * @return void
     */
    public static function regenerateQueueProcessSize() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $size_name = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : null;
        
        if (!$attachment_id || !$size_name) {
            wp_send_json_error('No attachment ID or size specified');
        }
        
        $regenerated = 0;
        
        try {
            // Regenerate thumbnails for this single attachment and specific size
            $attachment = get_post($attachment_id);
            
            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (not an image)',
                ]);
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata) || empty($metadata['sizes'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (no thumbnails)',
                ]);
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (file not found)',
                ]);
            }
            
            // Process only the specified size for this attachment
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            if (!isset($metadata['sizes'][$size_name]) || !isset($image_sizes[$size_name])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (size not applicable)',
                ]);
            }
            
            $size_data = $metadata['sizes'][$size_name];
            $size_file = dirname($file) . '/' . $size_data['file'];
            $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
            $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
            $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
            
            if ($width && $height) {
                // Generate WebP
                $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($file, $webp_file, $width, $height, $crop)) {
                    $regenerated++;
                }
                
                // Generate AVIF if enabled
                if (FormatManager::shouldGenerateAVIF()) {
                    $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                    if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($file, $avif_file, $width, $height, $crop)) {
                        $regenerated++;
                    }
                }
                
                // Delete original if not keeping it
                if (!FormatManager::shouldKeepOriginal()) {
                    if (file_exists($size_file)) {
                        @unlink($size_file);
                    }
                }
            }
            
            wp_send_json_success([
                'attachment_id' => $attachment_id,
                'regenerated' => $regenerated,
                'message' => 'Processed media item for size "' . $size_name . '" (generated ' . $regenerated . ' formats)',
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error processing attachment ' . $attachment_id . ': ' . $e->getMessage());
        }
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
        add_action('wp_ajax_mmt_get_media_count', [self::class, 'getMediaCount']);
        add_action('wp_ajax_mmt_get_original_size', [self::class, 'getOriginalSize']);
        add_action('wp_ajax_mmt_get_thumbnail_size', [self::class, 'getThumbnailSize']);
        add_action('wp_ajax_mmt_get_total_size', [self::class, 'getTotalSize']);
        add_action('wp_ajax_mmt_get_media_files_list', [self::class, 'getMediaFilesList']);
        add_action('wp_ajax_mmt_regenerate_queue_start', [self::class, 'regenerateQueueStart']);
        add_action('wp_ajax_mmt_regenerate_queue_process', [self::class, 'regenerateQueueProcess']);
        add_action('wp_ajax_mmt_regenerate_queue_size_start', [self::class, 'regenerateQueueSizeStart']);
        add_action('wp_ajax_mmt_regenerate_queue_process_size', [self::class, 'regenerateQueueProcessSize']);
    }
}
