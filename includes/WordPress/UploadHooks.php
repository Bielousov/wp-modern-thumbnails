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
        
        // Load source image once for all sizes
        try {
            $imagick = new \Imagick($source_path);
        } catch (\Exception $e) {
            error_log('MMT Upload Hook: Unable to load source image: ' . $e->getMessage());
            return $metadata;
        }
        
        // Get format settings
        $all_settings = FormatManager::getFormatSettings();
        $webp_quality = intval($all_settings['webp_quality'] ?? 80);
        $original_quality = intval($all_settings['original_quality'] ?? 85);
        $avif_quality = intval($all_settings['avif_quality'] ?? 75);
        $convert_gif = FormatManager::shouldConvertGif();
        
        // Get source image MIME type
        $source_mime = get_post_mime_type(get_post()->ID) ?: 'image/jpeg';
        
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
                        // Handle GIF files specially
                        if ($source_mime === 'image/gif' && !$convert_gif) {
                            // Don't generate WebP/AVIF for GIFs when not converting
                            continue;
                        }
                        
                        // Always generate WebP - pass imagick object
                        $webp_file = preg_replace('/\.[^.]+$/', '.webp', $size_file);
                        ThumbnailGenerator::generateWebP(
                            $imagick,
                            $webp_file,
                            $width,
                            $height,
                            $crop,
                            $webp_quality
                        );
                        
                        // Generate original format if enabled - pass imagick object
                        if (FormatManager::shouldKeepOriginal()) {
                            $format_map = [
                                'image/jpeg' => 'jpg',
                                'image/png' => 'png',
                                'image/gif' => 'gif',
                                'image/webp' => 'webp',
                            ];
                            $original_format = $format_map[$source_mime] ?? 'jpg';
                            $original_file = preg_replace('/\.[^.]+$/', '.' . $original_format, $size_file);
                            
                            ThumbnailGenerator::generateThumbnail(
                                $imagick,
                                $original_file,
                                $width,
                                $height,
                                $crop,
                                $original_format,
                                $original_quality
                            );
                        }
                        
                        // Generate AVIF if enabled - pass imagick object
                        if (FormatManager::shouldGenerateAVIF()) {
                            $avif_file = preg_replace('/\.[^.]+$/', '.avif', $size_file);
                            ThumbnailGenerator::generateAVIF(
                                $imagick,
                                $avif_file,
                                $width,
                                $height,
                                $crop,
                                $avif_quality
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
        
        // Destroy imagick object after processing all sizes
        $imagick->destroy();
        
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
