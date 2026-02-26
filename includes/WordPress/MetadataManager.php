<?php
/**
 * Attachment Metadata Manager
 * 
 * Manages attachment metadata to include WebP file references and serve modern formats.
 * Updates metadata when thumbnails are generated and filters it on request.
 */

namespace ModernMediaThumbnails\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ModernMediaThumbnails\FormatManager;

class MetadataManager {
    
    /**
     * Register metadata hooks
     * 
     * @return void
     */
    public static function register() {
        // Fix metadata early in generation process (priority 5)
        add_filter('wp_generate_attachment_metadata', [self::class, 'ensureValidMetadata'], 5, 2);
        
        // Process WebP generation after metadata is fixed (priority 10)
        add_filter('wp_generate_attachment_metadata', [self::class, 'interceptGeneratedMetadata'], 10, 2);
        
        // Save corrected metadata to database (priority 20, after all other processing)
        add_filter('wp_generate_attachment_metadata', [self::class, 'saveMetadataToDatabaseAfterFix'], 20, 2);
    }
    
    /**
     * Save fixed and processed metadata directly to database
     * 
     * By saving in the generation hook, we ensure all future retrievals get correct data.
     * Runs at priority 20, after dimension fixes and WebP generation.
     * 
     * @param array $metadata Fixed metadata
     * @param int $attachment_id Attachment ID
     * @return array Unchanged metadata (just triggers the save)
     */
    public static function saveMetadataToDatabaseAfterFix($metadata, $attachment_id) {
        if ($metadata && is_array($metadata)) {
            update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
        }
        return $metadata;
    }
    
    /**
     * Ensure metadata contains valid dimensions
     * 
     * Fixes broken metadata (1x1px) by reading actual image dimensions.
     * Runs early (priority 5) before other hooks.
     * 
     * @param array $metadata Generated metadata
     * @param int $attachment_id Attachment ID
     * @return array Metadata with valid dimensions
     */
    public static function ensureValidMetadata($metadata, $attachment_id) {
        // If metadata is empty or dimensions are invalid (0, 1, or missing)
        $width = intval($metadata['width'] ?? 0);
        $height = intval($metadata['height'] ?? 0);
        
        if ($width <= 1 || $height <= 1) {
            // Try to get real dimensions from the actual image file
            $real_dims = self::getImageDimensionsFromFile($attachment_id);
            if ($real_dims) {
                $metadata['width'] = $real_dims['width'];
                $metadata['height'] = $real_dims['height'];
            }
        }
        
        return $metadata;
    }
    
    /**
     * Get actual image dimensions from file using Imagick
     * 
     * @param int $attachment_id Attachment ID
     * @return array|false Array with 'width' and 'height', or false if unable to read
     */
    private static function getImageDimensionsFromFile($attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return false;
        }
        
        try {
            $imagick = new \Imagick($file);
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $imagick->destroy();
            
            if ($width > 0 && $height > 0) {
                return ['width' => $width, 'height' => $height];
            }
        } catch (\Exception $e) {
            // Fall back to getimagesize if Imagick fails
            $size = @getimagesize($file);
            if ($size && isset($size[0], $size[1]) && $size[0] > 0 && $size[1] > 0) {
                return ['width' => $size[0], 'height' => $size[1]];
            }
        }
        
        return false;
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
     * Updates metadata to reference WebP files while preserving dimensions.
     * Original files are kept on disk but not referenced in metadata.
     * 
     * @param int $attachment_id Attachment ID
     * @param array $metadata Current attachment metadata
     * @return array Updated metadata with WebP files and preserved dimensions
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
        
        // Ensure main image dimensions are valid
        $width = intval($metadata['width'] ?? 0);
        $height = intval($metadata['height'] ?? 0);
        if ($width <= 1 || $height <= 1) {
            $real_dims = self::getImageDimensionsFromFile($attachment_id);
            if ($real_dims) {
                $metadata['width'] = $real_dims['width'];
                $metadata['height'] = $real_dims['height'];
            }
        }
        
        $attachment_dir = dirname($attachment_file);
        $attachment_base = preg_replace('/\.[^\.]+$/', '', $attachment_file);
        
        // Check if WebP version of main file exists and update metadata
        $main_webp_file = $attachment_base . '.webp';
        if (file_exists($main_webp_file)) {
            $metadata['file'] = basename($main_webp_file);
        }
        
        // Update sizes metadata to use WebP only while preserving dimensions
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_name => &$size_data) {
                if (!is_array($size_data) || empty($size_data['file'])) {
                    continue;
                }
                
                // Preserve original dimensions
                $size_width = intval($size_data['width'] ?? 0);
                $size_height = intval($size_data['height'] ?? 0);
                
                // Get the size file path
                $size_file = $attachment_dir . '/' . $size_data['file'];
                $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                $webp_file = $size_base . '.webp';
                
                // If WebP exists, use it; otherwise keep original
                if (file_exists($webp_file)) {
                    $size_data['file'] = basename($webp_file);
                    $size_data['mime-type'] = 'image/webp';
                    // Ensure dimensions are preserved
                    $size_data['width'] = $size_width;
                    $size_data['height'] = $size_height;
                }
            }
            unset($size_data);  // Break reference
        }
        
        return $metadata;
    }
}
