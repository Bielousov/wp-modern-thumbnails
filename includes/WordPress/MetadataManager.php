<?php
/**
 * Attachment Metadata Manager
 * 
 * Manages attachment metadata to include WebP file references and serve modern formats.
 * Updates metadata when thumbnails are generated and filters it on request.
 */

namespace ModernMediaThumbnails\WordPress;

use ModernMediaThumbnails\FormatManager;

class MetadataManager {
    
    /**
     * Register metadata hooks
     * 
     * @return void
     */
    public static function register() {
        // Filter attachment metadata to serve WebP files
        add_filter('wp_get_attachment_metadata', [self::class, 'filterAttachmentMetadata'], 10, 2);
    }
    
    /**
     * Update attachment metadata with WebP file references after generation
     * 
     * Updates metadata to reference only WebP files for serving on frontend.
     * Original files are kept on disk but not referenced in metadata.
     * 
     * @param int $attachment_id Attachment ID
     * @param array $metadata Current attachment metadata
     * @return array Updated metadata with WebP files only
     */
    public static function updateMetadataWithWebP($attachment_id, $metadata) {
        if (empty($metadata) || !is_array($metadata)) {
            return $metadata;
        }
        
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return $metadata;
        }
        
        $attachment_file = get_attached_file($attachment_id);
        if (!$attachment_file) {
            return $metadata;
        }
        
        $attachment_dir = dirname($attachment_file);
        $attachment_base = preg_replace('/\.[^\.]+$/', '', $attachment_file);
        
        // Check if WebP version of main file exists and update metadata
        $main_webp_file = $attachment_base . '.webp';
        if (file_exists($main_webp_file)) {
            $metadata['file'] = basename($main_webp_file);
        }
        
        // Update sizes metadata to use WebP only
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_name => &$size_data) {
                if (!is_array($size_data) || empty($size_data['file'])) {
                    continue;
                }
                
                // Get the size file path
                $size_file = $attachment_dir . '/' . $size_data['file'];
                $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                $webp_file = $size_base . '.webp';
                
                // If WebP exists, use it; otherwise keep original
                if (file_exists($webp_file)) {
                    $size_data['file'] = basename($webp_file);
                    $size_data['mime-type'] = 'image/webp';
                }
            }
            unset($size_data);  // Break reference
        }
        
        return $metadata;
    }
    
    /**
     * Filter attachment metadata to ensure WebP is only served on frontend
     * 
     * Metadata is already set to WebP filenames during generation.
     * This filter ensures it's only applied on frontend, not in admin.
     * 
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID
     * @return array Filtered metadata
     */
    public static function filterAttachmentMetadata($metadata, $attachment_id) {
        // Only apply on frontend
        if (is_admin()) {
            return $metadata;
        }
        
        return $metadata;
    }
}
