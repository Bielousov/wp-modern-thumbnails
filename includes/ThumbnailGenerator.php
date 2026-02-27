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
     * @param string|object $source Source - either file path (string) or Imagick object
     * @param string $dest_path Path where thumbnail will be saved
     * @param int $width Target width
     * @param int $height Target height
     * @param bool $crop Whether to crop image
     * @param string $format Image format (webp, avif, etc.)
     * @param int $quality Image quality (0-100), defaults to 80
     * @return bool
     */
    public static function generateThumbnail($source, $dest_path, $width, $height, $crop, $format, $quality = 80) {
        try {
            $source_path = null;
            
            // If source is a string, it's a file path - load it
            // If it's an Imagick object, use it directly
            if (is_string($source)) {
                if (!file_exists($source)) {
                    return false;
                }
                $source_path = $source;
                $imagick = new \Imagick($source);
                $should_destroy = true;
            } else {
                // Assume it's an Imagick object
                $imagick = $source;
                $should_destroy = false;
            }
            
            // Clone the imagick object to avoid modifying the original
            $imagick = clone $imagick;
            
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
            $imagick->setImageCompressionQuality($quality);
            
            // Create destination directory if it doesn't exist
            $dest_dir = dirname($dest_path);
            if (!is_dir($dest_dir)) {
                wp_mkdir_p($dest_dir);
            }
            
            // Write the file
            $result = $imagick->writeImage($dest_path);
            $imagick->destroy();
            
            // Preserve source file permissions on the generated thumbnail
            if ($result && $source_path && file_exists($source_path) && file_exists($dest_path)) {
                self::applySourcePermissions($source_path, $dest_path);
            }
            
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Apply source file permissions to destination file
     * 
     * @param string $source_path Path to source file
     * @param string $dest_path Path to destination file
     * @return bool True if permissions were applied, false otherwise
     */
    private static function applySourcePermissions($source_path, $dest_path) {
        try {
            $source_perms = @fileperms($source_path);
            if ($source_perms !== false) {
                @chmod($dest_path, $source_perms);
                return true;
            }
        } catch (\Exception $e) {
            // Silently fail - permissions are not critical
        }
        return false;
    }
    
    /**
     * Generate WebP thumbnail
     * 
     * @param string $source_path
     * @param string $dest_path
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param int $quality Image quality (0-100), defaults to 80
     * @return bool
     */
    public static function generateWebP($source_path, $dest_path, $width, $height, $crop, $quality = 80) {
        return self::generateThumbnail($source_path, $dest_path, $width, $height, $crop, 'webp', $quality);
    }
    
    /**
     * Generate AVIF thumbnail
     * 
     * @param string $source_path
     * @param string $dest_path
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param int $quality Image quality (0-100), defaults to 75
     * @return bool
     */
    public static function generateAVIF($source_path, $dest_path, $width, $height, $crop, $quality = 75) {
        return self::generateThumbnail($source_path, $dest_path, $width, $height, $crop, 'avif', $quality);
    }
}
