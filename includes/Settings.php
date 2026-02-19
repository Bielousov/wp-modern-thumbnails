<?php
/**
 * Settings Manager
 * 
 * Provides centralized settings management with defaults.
 */

namespace ModernMediaThumbnails;

class Settings {
    
    /**
     * Default quality values
     */
    const DEFAULT_WEBP_QUALITY = 80;
    const DEFAULT_ORIGINAL_QUALITY = 85;
    const DEFAULT_AVIF_QUALITY = 75;
    
    /**
     * Default boolean settings
     */
    const DEFAULT_KEEP_ORIGINAL = false;
    const DEFAULT_GENERATE_AVIF = false;
    const DEFAULT_CONVERT_GIF = false;
    const DEFAULT_KEEP_EXIF = true;
    const DEFAULT_KEEP_EXIF_FOR_THUMBNAILS = false;
    
    /**
     * Get plugin settings with defaults applied
     * 
     * Ensures all settings (boolean and quality) are set with sensible defaults.
     * 
     * @return array Settings array with defaults
     */
    public static function getWithDefaults() {
        $settings = FormatManager::getFormatSettings();
        
        // Apply defaults for all settings
        if (!isset($settings['keep_original'])) {
            $settings['keep_original'] = self::DEFAULT_KEEP_ORIGINAL;
        }
        if (!isset($settings['generate_avif'])) {
            $settings['generate_avif'] = self::DEFAULT_GENERATE_AVIF;
        }
        if (!isset($settings['convert_gif'])) {
            $settings['convert_gif'] = self::DEFAULT_CONVERT_GIF;
        }
        if (!isset($settings['keep_exif'])) {
            $settings['keep_exif'] = self::DEFAULT_KEEP_EXIF;
        }
        if (!isset($settings['keep_exif_thumbnails'])) {
            $settings['keep_exif_thumbnails'] = self::DEFAULT_KEEP_EXIF_FOR_THUMBNAILS;
        }
        if (!isset($settings['webp_quality'])) {
            $settings['webp_quality'] = self::DEFAULT_WEBP_QUALITY;
        }
        if (!isset($settings['original_quality'])) {
            $settings['original_quality'] = self::DEFAULT_ORIGINAL_QUALITY;
        }
        if (!isset($settings['avif_quality'])) {
            $settings['avif_quality'] = self::DEFAULT_AVIF_QUALITY;
        }
        
        return $settings;
    }
    
    /**
     * Get a specific setting with default fallback
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value or default
     */
    public static function get($key, $default = null) {
        $settings = self::getWithDefaults();
        
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        
        return $default;
    }
    
    /**
     * Get all default quality values
     * 
     * @return array Array with webp_quality, original_quality, avif_quality keys
     */
    public static function getQualityDefaults() {
        return [
            'webp_quality' => self::DEFAULT_WEBP_QUALITY,
            'original_quality' => self::DEFAULT_ORIGINAL_QUALITY,
            'avif_quality' => self::DEFAULT_AVIF_QUALITY,
        ];
    }
}
