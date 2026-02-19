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
            // Get quality settings
            $all_settings = FormatManager::getFormatSettings();
            $webp_quality = intval($all_settings['webp_quality'] ?? 80);
            $original_quality = intval($all_settings['original_quality'] ?? 85);
            $avif_quality = intval($all_settings['avif_quality'] ?? 75);
            $convert_gif = FormatManager::shouldConvertGif();
            
            // Get source image MIME type
            $attachment = get_post(get_post()->ID);
            $source_mime = $attachment ? get_post_mime_type($attachment->ID) : 'image/jpeg';
            
            // Handle GIF files specially
            if ($source_mime === 'image/gif' && !$convert_gif) {
                // Don't generate WebP/AVIF for GIFs when not converting
                return 0;
            }
            
            // Always generate WebP
            $webp_file = preg_replace('/\.[^.]+$/', '.webp', $size_file);
            if (ThumbnailGenerator::generateWebP($file, $webp_file, $width, $height, $crop, $webp_quality)) {
                $regenerated++;
            }
            
            // Generate original format if enabled
            if (FormatManager::shouldKeepOriginal()) {
                $format_map = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                $original_format = $format_map[$source_mime] ?? 'jpg';
                $original_file = preg_replace('/\.[^.]+$/', '.' . $original_format, $size_file);
                
                if (ThumbnailGenerator::generateThumbnail($file, $original_file, $width, $height, $crop, $original_format, $original_quality)) {
                    $regenerated++;
                }
            }
            
            // Generate AVIF if enabled
            if (FormatManager::shouldGenerateAVIF()) {
                $avif_file = preg_replace('/\.[^.]+$/', '.avif', $size_file);
                if (ThumbnailGenerator::generateAVIF($file, $avif_file, $width, $height, $crop, $avif_quality)) {
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
