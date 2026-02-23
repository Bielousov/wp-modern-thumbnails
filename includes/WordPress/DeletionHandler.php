<?php
/**
 * Attachment Deletion Handler
 * 
 * Handles cleanup of WebP/AVIF format files when attachments are deleted.
 */

namespace ModernMediaThumbnails\WordPress;

class DeletionHandler {
    
    /**
     * Register deletion hooks
     * 
     * @return void
     */
    public static function register() {
        add_action('delete_attachment', [self::class, 'onDeleteAttachment']);
    }
    
    /**
     * Clean up WebP/AVIF files when attachment is deleted
     * 
     * @param int $attachment_id Attachment ID being deleted
     * @return void
     */
    public static function onDeleteAttachment($attachment_id) {
        $attachment_file = get_attached_file($attachment_id);
        if (!$attachment_file || !file_exists($attachment_file)) {
            return;
        }
        
        $attachment_dir = dirname($attachment_file);
        $attachment_base = preg_replace('/\.[^\.]+$/', '', $attachment_file);
        
        // Delete main WebP/AVIF files
        foreach (['.webp', '.avif'] as $ext) {
            $file = $attachment_base . $ext;
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        
        // Delete WebP/AVIF versions of all thumbnails
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_data) {
                if (!empty($size_data['file'])) {
                    $size_file = $attachment_dir . '/' . $size_data['file'];
                    $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                    
                    // Delete WebP version
                    $webp_file = $size_base . '.webp';
                    if (file_exists($webp_file)) {
                        @unlink($webp_file);
                    }
                    
                    // Delete AVIF version
                    $avif_file = $size_base . '.avif';
                    if (file_exists($avif_file)) {
                        @unlink($avif_file);
                    }
                    
                    // If original format is kept separately, delete it too
                    foreach (['.jpg', '.jpeg', '.png', '.gif'] as $ext) {
                        $orig_file = $size_base . $ext;
                        if (file_exists($orig_file) && $orig_file !== $size_file) {
                            @unlink($orig_file);
                        }
                    }
                }
            }
        }
    }
}
