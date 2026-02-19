<?php
/**
 * Upload Hooks
 * 
 * Handles WordPress hooks for automatic thumbnail generation on image upload.
 */

namespace ModernMediaThumbnails\WordPress;

use ModernMediaThumbnails\ImageSizeManager;
use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\ThumbnailGenerator;

class UploadHooks {
    
    /**
     * Hook into image generation to create WebP/AVIF versions
     * 
     * @param array $metadata
     * @param int $attachment_id
     * @return array
     */
    public static function onGenerateAttachmentMetadata($metadata, $attachment_id) {
        // Get the uploaded file
        $file = get_attached_file($attachment_id);
        if (!$file) {
            return $metadata;
        }
        
        $upload_dir = wp_upload_dir();
        $source_path = $upload_dir['basedir'] . '/' . $metadata['file'];
        
        if (!file_exists($source_path)) {
            return $metadata;
        }
        
        // Always generate WebP for all thumbnails
        $image_sizes = ImageSizeManager::getAllImageSizes();
        
        // Process each image size
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (isset($image_sizes[$size_name])) {
                    $size_info = $image_sizes[$size_name];
                    $size_file = str_replace(
                        basename($source_path),
                        $size_data['file'],
                        $source_path
                    );
                    
                    $width = isset($size_info['width']) ? $size_info['width'] : 0;
                    $height = isset($size_info['height']) ? $size_info['height'] : 0;
                    $crop = isset($size_info['crop']) ? $size_info['crop'] : false;
                    
                    if ($width && $height) {
                        // Always generate WebP
                        $webp_file = preg_replace('/\.[^.]+$/', '.webp', $size_file);
                        ThumbnailGenerator::generateWebP(
                            $source_path,
                            $webp_file,
                            $width,
                            $height,
                            $crop
                        );
                        
                        // Generate AVIF if enabled
                        if (FormatManager::shouldGenerateAVIF()) {
                            $avif_file = preg_replace('/\.[^.]+$/', '.avif', $size_file);
                            ThumbnailGenerator::generateAVIF(
                                $source_path,
                                $avif_file,
                                $width,
                                $height,
                                $crop
                            );
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
        }
        
        return $metadata;
    }
    
    /**
     * Register the upload hooks
     * 
     * @return void
     */
    public static function register() {
        add_filter('wp_generate_attachment_metadata', [self::class, 'onGenerateAttachmentMetadata'], 10, 2);
    }
}
