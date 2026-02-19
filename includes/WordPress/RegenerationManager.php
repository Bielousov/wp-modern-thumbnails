<?php
/**
 * Regeneration Manager
 * 
 * Handles regenerating thumbnails for existing images.
 */

namespace ModernMediaThumbnails\WordPress;

use ModernMediaThumbnails\ImageSizeManager;
use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\ThumbnailGenerator;

class RegenerationManager {
    
    /**
     * Regenerate thumbnails for a specific image size
     * 
     * @param string|null $size_name
     * @return int Number of thumbnails regenerated
     */
    public static function regenerateSize($size_name = null) {
        $image_sizes = ImageSizeManager::getAllImageSizes();
        
        // Get all attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
        ]);
        
        $regenerated = 0;
        
        foreach ($attachments as $attachment) {
            $metadata = wp_get_attachment_metadata($attachment->ID);
            if (empty($metadata)) {
                continue;
            }
            
            $file = get_attached_file($attachment->ID);
            if (!$file) {
                continue;
            }
            
            // If size is specified, only regenerate that size
            if ($size_name) {
                if (isset($metadata['sizes'][$size_name]) && isset($image_sizes[$size_name])) {
                    $regenerated += self::regenerateSizeForAttachment(
                        $file,
                        $metadata['sizes'][$size_name],
                        $image_sizes[$size_name],
                        $size_name
                    );
                }
            } else {
                // Regenerate all sizes for this attachment
                if (!empty($metadata['sizes'])) {
                    foreach ($metadata['sizes'] as $sz_name => $size_data) {
                        if (isset($image_sizes[$sz_name])) {
                            $regenerated += self::regenerateSizeForAttachment(
                                $file,
                                $size_data,
                                $image_sizes[$sz_name],
                                $sz_name
                            );
                        }
                    }
                }
            }
        }
        
        return $regenerated;
    }
    
    /**
     * Regenerate a specific size for an attachment
     * 
     * @param string $file
     * @param array $size_data
     * @param array $size_info
     * @param string $size_name
     * @return int
     */
    private static function regenerateSizeForAttachment($file, $size_data, $size_info, $size_name) {
        $regenerated = 0;
        
        $size_file = str_replace(
            basename($file),
            $size_data['file'],
            $file
        );
        
        $width = isset($size_info['width']) ? $size_info['width'] : 0;
        $height = isset($size_info['height']) ? $size_info['height'] : 0;
        $crop = isset($size_info['crop']) ? $size_info['crop'] : false;
        
        if ($width && $height && file_exists($file)) {
            // Always generate WebP
            $webp_file = preg_replace('/\.[^.]+$/', '.webp', $size_file);
            if (ThumbnailGenerator::generateWebP($file, $webp_file, $width, $height, $crop)) {
                $regenerated++;
            }
            
            // Generate AVIF if enabled
            if (FormatManager::shouldGenerateAVIF()) {
                $avif_file = preg_replace('/\.[^.]+$/', '.avif', $size_file);
                if (ThumbnailGenerator::generateAVIF($file, $avif_file, $width, $height, $crop)) {
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
        
        return $regenerated;
    }
}
