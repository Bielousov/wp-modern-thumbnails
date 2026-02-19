<?php
/**
 * Thumbnail Generator
 * 
 * Handles generating thumbnails in various formats using Imagick.
 */

namespace ModernMediaThumbnails;

class ThumbnailGenerator {
    
    const QUALITY = 80;
    
    /**
     * Generate thumbnail in specified format using Imagick
     * 
     * @param string $source_path Path to source image
     * @param string $dest_path Path where thumbnail will be saved
     * @param int $width Target width
     * @param int $height Target height
     * @param bool $crop Whether to crop image
     * @param string $format Image format (webp, avif, etc.)
     * @return bool
     */
    public static function generateThumbnail($source_path, $dest_path, $width, $height, $crop, $format) {
        try {
            if (!file_exists($source_path)) {
                return false;
            }
            
            $imagick = new \Imagick($source_path);
            
            // Get original dimensions
            $orig_width = $imagick->getImageWidth();
            $orig_height = $imagick->getImageHeight();
            
            if ($crop) {
                // Calculate crop dimensions to maintain aspect ratio
                $aspect_ratio = $width / $height;
                $orig_aspect = $orig_width / $orig_height;
                
                if ($orig_aspect > $aspect_ratio) {
                    // Original is wider, crop width
                    $new_width = $orig_height * $aspect_ratio;
                    $new_height = $orig_height;
                    $x = ($orig_width - $new_width) / 2;
                    $y = 0;
                } else {
                    // Original is taller, crop height
                    $new_width = $orig_width;
                    $new_height = $orig_width / $aspect_ratio;
                    $x = 0;
                    $y = ($orig_height - $new_height) / 2;
                }
                
                $imagick->cropImage((int)$new_width, (int)$new_height, (int)$x, (int)$y);
            }
            
            // Resize to target dimensions
            $imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, !$crop);
            
            // Set format and quality
            $imagick->setImageFormat(strtolower($format));
            $imagick->setImageCompressionQuality(self::QUALITY);
            
            // Create destination directory if it doesn't exist
            $dest_dir = dirname($dest_path);
            if (!is_dir($dest_dir)) {
                wp_mkdir_p($dest_dir);
            }
            
            // Write the file
            $result = $imagick->writeImage($dest_path);
            $imagick->destroy();
            
            return $result;
        } catch (\Exception $e) {
            error_log('MMT ' . strtoupper($format) . ' Generation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate WebP thumbnail
     * 
     * @param string $source_path
     * @param string $dest_path
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return bool
     */
    public static function generateWebP($source_path, $dest_path, $width, $height, $crop) {
        return self::generateThumbnail($source_path, $dest_path, $width, $height, $crop, 'webp');
    }
    
    /**
     * Generate AVIF thumbnail
     * 
     * @param string $source_path
     * @param string $dest_path
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return bool
     */
    public static function generateAVIF($source_path, $dest_path, $width, $height, $crop) {
        return self::generateThumbnail($source_path, $dest_path, $width, $height, $crop, 'avif');
    }
}
