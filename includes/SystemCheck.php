<?php
/**
 * System Check
 * 
 * Checks system requirements (Imagick availability, WebP support).
 */

namespace ModernMediaThumbnails;

class SystemCheck {
    
    /**
     * Check if Imagick is available
     * 
     * @return bool
     */
    public static function isImagickAvailable() {
        return extension_loaded('imagick') && class_exists('Imagick');
    }
    
    /**
     * Check if WebP is supported by Imagick
     * 
     * @return bool
     */
    public static function isWebPSupported() {
        if (!self::isImagickAvailable()) {
            return false;
        }
        
        try {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats();
            return in_array('WEBP', $formats);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if AVIF is supported by Imagick
     * 
     * @return bool
     */
    public static function isAVIFSupported() {
        if (!self::isImagickAvailable()) {
            return false;
        }
        
        try {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats();
            return in_array('AVIF', $formats);
        } catch (\Exception $e) {
            return false;
        }
    }
}
