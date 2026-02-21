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
        // Hook into metadata SAVE to ensure WebP metadata is persisted
        // wp_update_attachment_metadata is the action when metadata is saved to database
        add_action('wp_update_attachment_metadata', [self::class, 'onMetadataUpdate'], 10, 2);
        
        // Also hook into native thumbnail generation
        add_filter('wp_generate_attachment_metadata', [self::class, 'interceptGeneratedMetadata'], 10, 2);
    }
    
    /**
     * Intercept WordPress native thumbnail generation
     * 
     * Hooks into wp_generate_attachment_metadata to catch metadata generation from:
     * - WordPress native regeneration
     * - Other plugins
     * - Any source that generates thumbnails
     * 
     * Automatically generates WebP versions for thumbnails and updates metadata.
     * 
     * @param array $metadata Generated metadata
     * @param int $attachment_id Attachment ID
     * @return array Updated metadata with WebP references
     */
    public static function interceptGeneratedMetadata($metadata, $attachment_id) {
        // Get attachment file
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return $metadata;
        }
        
        $attachment_file = get_attached_file($attachment_id);
        if (!$attachment_file || !file_exists($attachment_file)) {
            return $metadata;
        }
        
        $attachment_dir = dirname($attachment_file);
        $settings = \ModernMediaThumbnails\Settings::getWithDefaults();
        $webp_quality = intval($settings['webp_quality'] ?? 80);
        
        try {
            // Generate WebP for each thumbnail size
            if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_name => $size_data) {
                    if (!is_array($size_data) || empty($size_data['file'])) {
                        continue;
                    }
                    
                    $size_file = $attachment_dir . '/' . $size_data['file'];
                    if (!file_exists($size_file)) {
                        continue;
                    }
                    
                    $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                    $size_webp = $size_base . '.webp';
                    
                    // Generate WebP if it doesn't exist
                    if (!file_exists($size_webp)) {
                        $width = intval($size_data['width'] ?? 0);
                        $height = intval($size_data['height'] ?? 0);
                        
                        if ($width && $height) {
                            // Use original source file to generate WebP thumbnail
                            \ModernMediaThumbnails\ThumbnailGenerator::generateWebP(
                                $attachment_file,
                                $size_webp,
                                $width, $height, false,
                                $webp_quality
                            );
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail - don't break WordPress regeneration if WebP generation fails
        }
        
        // Update metadata to point to WebP files
        return self::updateMetadataWithWebP($attachment_id, $metadata);
    }
    
    /**
     * Action hook: Save updated metadata with WebP references to database
     * 
     * Called by wp_update_attachment_metadata action after metadata has been updated.
     * Ensures WebP references are persisted to database.
     * 
     * @param int $attachment_id Attachment ID
     * @param array $metadata Attachment metadata
     * @return void
     */
    public static function onMetadataUpdate($attachment_id, $metadata) {
        // Check if this metadata needs WebP file references
        $updated_metadata = self::updateMetadataWithWebP($attachment_id, $metadata);
        
        // If metadata was actually updated (WebP files found and linked), save it
        if ($updated_metadata !== $metadata) {
            // Update in database
            update_post_meta($attachment_id, '_wp_attachment_metadata', $updated_metadata);
        }
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
}
