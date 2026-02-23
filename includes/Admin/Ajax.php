<?php
/**
 * AJAX Handler
 * 
 * Handles AJAX requests for regeneration and format settings.
 */

namespace ModernMediaThumbnails\Admin;

use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\Settings;
use ModernMediaThumbnails\WordPress\RegenerationManager;
use ModernMediaThumbnails\WordPress\MetadataManager;
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
            $file_path = get_attached_file($attachment_id);
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = wp_get_attachment_metadata($attachment_id);
            $updated_metadata = MetadataManager::updateMetadataWithWebP($attachment_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($attachment_id, $updated_metadata);
            
            // Detect actual formats on disk
            $size_to_check = $size_name ?: 'original';
            $detected_formats = self::detectFormatsOnDisk($attachment_id, $size_to_check);
            
            wp_send_json_success([
                'message' => sprintf(
                    'Generated %d format(s) for media #%d',
                    $regenerated,
                    $attachment_id
                ),
                'attachment_id' => $attachment_id,
                'count' => $regenerated,
                'file_path' => $file_path,
                'formats' => $detected_formats
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
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                return 0;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata)) {
                return 0;
            }
            
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            if ($size_name) {
                // Regenerate specific size only
                if (!isset($image_sizes[$size_name])) {
                    return 0;
                }
                
                // Get size data from metadata if available, otherwise create default
                if (isset($metadata['sizes'][$size_name])) {
                    $size_data = $metadata['sizes'][$size_name];
                    $size_file = dirname($file) . '/' . $size_data['file'];
                } else {
                    // Generate from registered size info
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $size_file = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $size_name . '.' . $ext;
                }
                
                $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
                $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
                $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
                
                $regenerated = self::generateFormatsForSize($file, $size_file, $width, $height, $crop, $attachment->post_mime_type);
            } else {
                // Regenerate all sizes
                if (!empty($metadata['sizes'])) {
                    // Regenerate from existing metadata sizes
                    foreach ($metadata['sizes'] as $sz_name => $size_data) {
                        if (!isset($image_sizes[$sz_name])) {
                            continue;
                        }
                        
                        $size_file = dirname($file) . '/' . $size_data['file'];
                        $width = isset($image_sizes[$sz_name]['width']) ? $image_sizes[$sz_name]['width'] : 0;
                        $height = isset($image_sizes[$sz_name]['height']) ? $image_sizes[$sz_name]['height'] : 0;
                        $crop = isset($image_sizes[$sz_name]['crop']) ? $image_sizes[$sz_name]['crop'] : false;
                        
                        $regenerated += self::generateFormatsForSize($file, $size_file, $width, $height, $crop, $attachment->post_mime_type);
                    }
                } else {
                    // No metadata sizes - generate from all registered sizes
                    foreach ($image_sizes as $sz_name => $size_info) {
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $size_file = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $sz_name . '.' . $ext;
                        $width = isset($size_info['width']) ? $size_info['width'] : 0;
                        $height = isset($size_info['height']) ? $size_info['height'] : 0;
                        $crop = isset($size_info['crop']) ? $size_info['crop'] : false;
                        
                        $regenerated += self::generateFormatsForSize($file, $size_file, $width, $height, $crop, $attachment->post_mime_type);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error for debugging
            error_log('Error regenerating attachment ' . $attachment_id . ': ' . $e->getMessage());
        }
        
        return $regenerated;
    }
    
    /**
     * Generate formats for a specific size based on user settings
     * 
     * @param string $source_file
     * @param string $size_file
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string $original_mime_type
     * @return int
     */
    private static function generateFormatsForSize($source_file, $size_file, $width, $height, $crop, $original_mime_type = 'image/jpeg') {
        $count = 0;
        
        if (!$width || !$height) {
            return 0;
        }
        
        // Get user settings
        $keep_original = FormatManager::shouldKeepOriginal();
        $generate_avif = FormatManager::shouldGenerateAVIF();
        $convert_gif = FormatManager::shouldConvertGif();
        $all_settings = Settings::getWithDefaults();
        $webp_quality = intval($all_settings['webp_quality']);
        $original_quality = intval($all_settings['original_quality']);
        $avif_quality = intval($all_settings['avif_quality']);
        
        // Map MIME type to format for original generation
        $format_map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        
        $original_format = $format_map[$original_mime_type] ?? 'jpg';
        
        // Remove existing files for this size
        $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
        self::deleteExistingFiles($size_base);
        
        // Handle GIF files specially - if convert_gif is disabled, only create GIF
        if ($original_mime_type === 'image/gif' && !$convert_gif) {
            // For GIFs when not converting, just keep the original GIF thumbnail
            // Don't generate WebP or AVIF versions
            return 0; // No new formats generated, original GIF already exists
        }
        
        // Always generate WebP (unless it's a GIF and we're not converting)
        $webp_file = $size_base . '.webp';
        if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($source_file, $webp_file, $width, $height, $crop, $webp_quality)) {
            $count++;
        }
        
        // Keep original format if enabled
        if ($keep_original) {
            $original_file = $size_base . '.' . $original_format;
            if (\ModernMediaThumbnails\ThumbnailGenerator::generateThumbnail($source_file, $original_file, $width, $height, $crop, $original_format, $original_quality)) {
                $count++;
            }
        }
        
        // Generate AVIF if enabled
        if ($generate_avif) {
            $avif_file = $size_base . '.avif';
            if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($source_file, $avif_file, $width, $height, $crop, $avif_quality)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Delete existing files for a thumbnail size
     * 
     * @param string $size_base Base path without extension
     * @return void
     */
    private static function deleteExistingFiles($size_base) {
        $formats = ['webp', 'avif', 'png', 'jpg', 'jpeg', 'gif'];
        
        foreach ($formats as $format) {
            $file = $size_base . '.' . $format;
            if (file_exists($file)) {
                @unlink($file);
            }
        }
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
        
        // Update settings with actual values (convert 1/0 to true/false and sanitize quality values)
        FormatManager::updateSettings([
            'keep_original' => (bool)($settings['keep_original'] ?? false),
            'generate_avif' => (bool)($settings['generate_avif'] ?? false),
            'convert_gif' => (bool)($settings['convert_gif'] ?? false),
            'webp_quality' => intval($settings['webp_quality'] ?? 80),
            'original_quality' => intval($settings['original_quality'] ?? 85),
            'avif_quality' => intval($settings['avif_quality'] ?? 75),
        ]);
        
        wp_send_json_success([
            'message' => 'Settings updated successfully'
        ]);
    }
    
    /**
     * Detect which formats actually exist on disk for a thumbnail size
     * 
     * @param int $attachment_id
     * @param string $size_name
     * @return array Array with keys 'original', 'webp', 'avif' set to true if files exist
     */
    public static function detectFormatsOnDisk($attachment_id, $size_name) {
        $formats = [
            'original' => false,
            'webp' => false,
            'avif' => false,
        ];
        
        try {
            $attachment = get_post($attachment_id);
            if (!$attachment) {
                return $formats;
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file) {
                return $formats;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata)) {
                return $formats;
            }
            
            // Determine base path for this size
            $size_base = '';
            
            if ($size_name === 'original') {
                // For original image
                $size_base = preg_replace('/\.[^\.]+$/', '', $file);
            } else {
                // For thumbnail sizes
                if (isset($metadata['sizes'][$size_name])) {
                    $size_data = $metadata['sizes'][$size_name];
                    $size_file = dirname($file) . '/' . $size_data['file'];
                    $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                } else {
                    // Fallback to pattern
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $size_base = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $size_name;
                }
            }
            
            // Check for WebP
            if (file_exists($size_base . '.webp')) {
                $formats['webp'] = true;
            }
            
            // Check for AVIF
            if (file_exists($size_base . '.avif')) {
                $formats['avif'] = true;
            }
            
            // Check for original format
            $original_formats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            foreach ($original_formats as $fmt) {
                if (file_exists($size_base . '.' . $fmt)) {
                    // But exclude if it's just the webp we already detected
                    if (!($fmt === 'webp' && $formats['webp'])) {
                        $formats['original'] = true;
                        break;
                    }
                }
            }
            
        } catch (\Exception $e) {
            error_log('Error detecting formats: ' . $e->getMessage());
        }
        
        return $formats;
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
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (file not found)',
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
            
            // Load source image once for all sizes
            try {
                $imagick = new \Imagick($file);
            } catch (\Exception $e) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (unable to open file)',
                ]);
            }
            
            // Process all sizes for this attachment
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            // Get quality settings
            $all_settings = Settings::getWithDefaults();
            $webp_quality = intval($all_settings['webp_quality']);
            $original_quality = intval($all_settings['original_quality']);
            $avif_quality = intval($all_settings['avif_quality']);
            $convert_gif = FormatManager::shouldConvertGif();
            
            // Get source image MIME type
            $source_mime = $attachment ? get_post_mime_type($attachment->ID) : 'image/jpeg';
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (!isset($image_sizes[$size_name])) {
                    continue;
                }
                
                $size_file = dirname($file) . '/' . $size_data['file'];
                $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
                $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
                $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
                
                if ($width && $height) {
                    // Handle GIF files specially
                    if ($source_mime === 'image/gif' && !$convert_gif) {
                        // Don't generate WebP/AVIF for GIFs when not converting
                        continue;
                    }
                    
                    // Generate WebP - pass imagick object
                    $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                    if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($imagick, $webp_file, $width, $height, $crop, $webp_quality)) {
                        $regenerated++;
                    }
                    
                    // Generate original format if enabled - pass imagick object
                    if (FormatManager::shouldKeepOriginal()) {
                        $format_map = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                        ];
                        $original_format = $format_map[$source_mime] ?? 'jpg';
                        $original_file = preg_replace('/\.[^\.]+$/', '.' . $original_format, $size_file);
                        
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateThumbnail($imagick, $original_file, $width, $height, $crop, $original_format, $original_quality)) {
                            $regenerated++;
                        }
                    }
                    
                    // Generate AVIF if enabled - pass imagick object
                    if (FormatManager::shouldGenerateAVIF()) {
                        $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($imagick, $avif_file, $width, $height, $crop, $avif_quality)) {
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
            
            // Destroy imagick object after processing all sizes
            $imagick->destroy();
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = wp_get_attachment_metadata($attachment_id);
            $updated_metadata = MetadataManager::updateMetadataWithWebP($attachment_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($attachment_id, $updated_metadata);
            
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
                return;
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (file not found)',
                ]);
                return;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata) || empty($metadata['sizes'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (no thumbnails)',
                ]);
                return;
            }
            
            // Load source image once
            try {
                $imagick = new \Imagick($file);
            } catch (\Exception $e) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (unable to open file)',
                ]);
                return;
            }
            
            // Process only the specified size for this attachment
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            if (!isset($metadata['sizes'][$size_name]) || !isset($image_sizes[$size_name])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (size not applicable)',
                ]);
                return;
            }
            
            $size_data = $metadata['sizes'][$size_name];
            $size_file = dirname($file) . '/' . $size_data['file'];
            $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
            $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
            $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
            
            if ($width && $height) {
                // Get quality settings
                $all_settings = Settings::getWithDefaults();
                $webp_quality = intval($all_settings['webp_quality']);
                $original_quality = intval($all_settings['original_quality']);
                $avif_quality = intval($all_settings['avif_quality']);
                $convert_gif = FormatManager::shouldConvertGif();
                
                // Get source image MIME type
                $source_mime = $attachment ? get_post_mime_type($attachment->ID) : 'image/jpeg';
                
                // Handle GIF files specially
                if ($source_mime === 'image/gif' && !$convert_gif) {
                    // Don't generate WebP/AVIF for GIFs when not converting
                } else {
                    // Generate WebP - pass imagick object
                    $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                    if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($imagick, $webp_file, $width, $height, $crop, $webp_quality)) {
                        $regenerated++;
                    }
                    
                    // Generate original format if enabled - pass imagick object
                    if (FormatManager::shouldKeepOriginal()) {
                        $format_map = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                        ];
                        $original_format = $format_map[$source_mime] ?? 'jpg';
                        $original_file = preg_replace('/\.[^\.]+$/', '.' . $original_format, $size_file);
                        
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateThumbnail($imagick, $original_file, $width, $height, $crop, $original_format, $original_quality)) {
                            $regenerated++;
                        }
                    }
                    
                    // Generate AVIF if enabled - pass imagick object
                    if (FormatManager::shouldGenerateAVIF()) {
                        $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($imagick, $avif_file, $width, $height, $crop, $avif_quality)) {
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
            
            // Destroy imagick object after processing
            $imagick->destroy();
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = wp_get_attachment_metadata($attachment_id);
            $updated_metadata = MetadataManager::updateMetadataWithWebP($attachment_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($attachment_id, $updated_metadata);
            
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
     * Handle AJAX regeneration of a single attachment (for bulk actions)
     * 
     * @return void
     */
    public static function regenerateSingle() {
        // Check nonce - use correct action name
        check_ajax_referer('mmt_bulk_action_nonce', '_wpnonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        // Verify attachment exists
        $attachment = get_post($post_id);
        if (!$attachment || 'attachment' !== $attachment->post_type) {
            wp_send_json_error('Invalid attachment');
        }
        
        try {
            // Get metadata to count sizes
            $metadata = wp_get_attachment_metadata($post_id);
            $size_count = !empty($metadata['sizes']) ? count($metadata['sizes']) : 0;
            
            // Regenerate all sizes for this attachment
            $regenerated = self::regenerateAttachmentSize($post_id, null);
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = wp_get_attachment_metadata($post_id);
            $updated_metadata = MetadataManager::updateMetadataWithWebP($post_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($post_id, $updated_metadata);
            
            // Build format list based on enabled options
            $enabled_formats = [];
            
            if (FormatManager::shouldGenerateWebP()) {
                $enabled_formats[] = 'WebP';
            }
            
            if (FormatManager::shouldKeepOriginal()) {
                $extension = strtoupper(pathinfo(get_attached_file($post_id), PATHINFO_EXTENSION));
                if ($extension === 'JPG') {
                    $extension = 'JPEG';
                }
                $enabled_formats[] = $extension;
            }
            
            if (FormatManager::shouldGenerateAVIF()) {
                $enabled_formats[] = 'AVIF';
            }
            
            // Build the format string
            $formats_text = '';
            if (count($enabled_formats) === 0) {
                $formats_text = 'configured formats';
            } elseif (count($enabled_formats) === 1) {
                $formats_text = $enabled_formats[0];
            } elseif (count($enabled_formats) === 2) {
                $formats_text = implode(' and ', $enabled_formats);
            } else {
                $last_format = array_pop($enabled_formats);
                $formats_text = implode(', ', $enabled_formats) . ' and ' . $last_format;
            }
            
            // Build message
            if ($size_count > 0) {
                $message = sprintf(
                    'Successfully regenerated %d thumbnail %s in %s',
                    $size_count,
                    _n('size', 'sizes', $size_count, 'modern-media-thumbnails'),
                    $formats_text
                );
            } else {
                $message = 'Attachment processed';
            }
            
            // Get updated metadata to send back the new thumbnail URLs
            $final_metadata = wp_get_attachment_metadata($post_id);
            $attachment_url_base = dirname(wp_get_attachment_url($post_id));
            $attachment_file = get_attached_file($post_id);
            $attachment_dir = dirname($attachment_file);
            
            // Build thumbnail URLs with cache busting, preferring WebP files
            $thumbnails = [];
            $cache_buster = gmdate('Ymdhis');
            if (!empty($final_metadata['sizes'])) {
                foreach ($final_metadata['sizes'] as $size_name => $size_data) {
                    if (!empty($size_data['file'])) {
                        // Check if WebP version exists for this size
                        $size_file = $attachment_dir . '/' . $size_data['file'];
                        $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                        $webp_file = $size_base . '.webp';
                        
                        // Prefer WebP if it exists and WebP generation is enabled
                        if (file_exists($webp_file) && \ModernMediaThumbnails\FormatManager::shouldGenerateWebP()) {
                            $url = $attachment_url_base . '/' . basename($webp_file);
                        } else {
                            $url = $attachment_url_base . '/' . $size_data['file'];
                        }
                        
                        // Add cache buster to ensure fresh fetch
                        $thumbnails[$size_name] = $url . '?v=' . $cache_buster;
                    }
                }
            }
            
            wp_send_json_success([
                'message' => $message,
                'post_id' => $post_id,
                'count' => $regenerated,
                'sizes' => $size_count,
                'formats' => $enabled_formats,
                'thumbnails' => $thumbnails
            ]);
        } catch (\Exception $e) {
            error_log('Error regenerating attachment ' . $post_id . ': ' . $e->getMessage());
            wp_send_json_error('Error regenerating attachment');
        }
    }
    
    /**
     * Get attachment ID from image URL
     * 
     * @return void
     */
    public static function getAttachmentIdByUrl() {
        check_ajax_referer('mmt_regenerate_nonce');
        
        if (!isset($_POST['image_url'])) {
            wp_send_json_error('No image URL provided');
        }
        
        $image_url = sanitize_url($_POST['image_url']);
        
        // Use WordPress built-in function to get attachment ID from URL
        $attachment_id = attachment_url_to_postid($image_url);
        
        if (!$attachment_id) {
            // Try alternative method: query the database directly
            global $wpdb;
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s",
                $image_url
            ));
        }
        
        // If still not found, try to extract base filename and search for that
        // This handles cases where WordPress shows scaled/thumbnail versions
        if (!$attachment_id) {
            // Extract filename from URL (e.g., solar-eclipse-intro from solar-eclipse-intro-1200x800.jpg)
            $basename = pathinfo($image_url, PATHINFO_FILENAME);
            
            // Remove dimensions suffix (e.g., -1200x800 or -1920x1280)
            $base_name_no_dims = preg_replace('/-\d+x\d+$/', '', $basename);
            
            // Search for attachment with this base name
            global $wpdb;
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'attachment' 
                 AND (guid LIKE %s OR post_title LIKE %s OR guid LIKE %s)",
                '%' . $base_name_no_dims . '%',
                '%' . $base_name_no_dims . '%',
                '%' . $basename . '%'
            ));
        }
        
        // Try original file format if webp/avif (remove format suffix)
        if (!$attachment_id && preg_match('/\.(webp|avif)$/i', $image_url)) {
            // Try common formats and remove dimensions
            $basename = pathinfo($image_url, PATHINFO_FILENAME);
            $base_name_no_dims = preg_replace('/-\d+x\d+\.webp$|-\d+x\d+\.avif$/i', '', $basename);
            
            foreach (['.jpg', '.jpeg', '.png', '.gif'] as $ext) {
                // Try with dimensions removed
                $original_url = preg_replace('/\/([^\/]+)\.(webp|avif)$/i', '/' . $base_name_no_dims . $ext, $image_url);
                $attachment_id = attachment_url_to_postid($original_url);
                if ($attachment_id) {
                    break;
                }
                
                // Also try database search
                global $wpdb;
                $attachment_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
                    '%' . $base_name_no_dims . $ext
                ));
                if ($attachment_id) {
                    break;
                }
            }
        }
        
        if (!$attachment_id) {
            wp_send_json_error('Could not find attachment for image URL: ' . $image_url);
        }
        
        wp_send_json_success([
            'attachment_id' => intval($attachment_id),
            'message' => 'Attachment ID found'
        ]);
    }

    /**
     * Register AJAX handlers
     * 
     * @return void
     */
    public static function register() {
        add_action('wp_ajax_mmt_regenerate_all', [self::class, 'regenerateAll']);
        add_action('wp_ajax_mmt_regenerate_size', [self::class, 'regenerateSize']);
        add_action('wp_ajax_mmt_regenerate_single', [self::class, 'regenerateSingle']);
        add_action('wp_ajax_mmt_get_attachment_id_by_url', [self::class, 'getAttachmentIdByUrl']);
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
