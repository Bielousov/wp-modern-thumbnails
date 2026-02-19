<?php
/**
 * Format Manager
 * 
 * Handles global format configuration settings.
 */

namespace ModernMediaThumbnails;

class FormatManager {
    
    const OPTION_NAME = 'mmt_format_settings';
    
    /**
     * Get all format settings
     * 
     * @return array
     */
    public static function getFormatSettings() {
        $settings = get_option(self::OPTION_NAME, []);
        
        // Ensure all keys exist with proper types
        if (!is_array($settings)) {
            $settings = [];
        }
        
        // Merge with defaults to ensure all keys exist
        return array_merge(self::getDefaults(), $settings);
    }
    
    /**
     * Get default settings
     * 
     * @return array
     */
    public static function getDefaults() {
        return [
            'keep_original' => false,      // WordPress Default - Keep original JPEG/PNG thumbnails
            'generate_avif' => false,      // Generate AVIF thumbnails
            'convert_gif' => false,        // Convert GIF to WebP
            'webp_quality' => 80,          // WebP quality (0-100)
            'original_quality' => 85,      // Original format quality (0-100)
            'avif_quality' => 75,          // AVIF quality (0-100)
        ];
    }
    
    /**
     * Get a specific setting
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getSetting($key, $default = null) {
        $settings = self::getFormatSettings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Update format settings
     * 
     * @param array $settings
     * @return bool
     */
    public static function updateSettings($settings) {
        $current = self::getFormatSettings();
        
        // Sanitize and validate input (ensure proper boolean conversion and quality range)
        $updated = array_merge($current, [
            'keep_original' => (bool)($settings['keep_original'] ?? false),
            'generate_avif' => (bool)($settings['generate_avif'] ?? false),
            'convert_gif' => (bool)($settings['convert_gif'] ?? false),
            'webp_quality' => self::sanitizeQuality($settings['webp_quality'] ?? 80),
            'original_quality' => self::sanitizeQuality($settings['original_quality'] ?? 85),
            'avif_quality' => self::sanitizeQuality($settings['avif_quality'] ?? 75),
        ]);
        
        // Store in database with autoload for better performance
        return update_option(self::OPTION_NAME, $updated, false);
    }
    
    /**
     * Sanitize quality value to be between 0 and 100
     * 
     * @param mixed $value
     * @return int
     */
    private static function sanitizeQuality($value) {
        $quality = intval($value);
        return max(0, min(100, $quality));
    }
    
    /**
     * Initialize default settings if they don't exist
     * Called on plugin activation
     * 
     * @return void
     */
    public static function maybeInitializeDefaults() {
        if (get_option(self::OPTION_NAME) === false) {
            update_option(self::OPTION_NAME, self::getDefaults(), false);
        }
    }
    
    /**
     * Get if thumbnails should be generated in WebP format
     * WebP is always generated for all thumbnails (it's the default behavior)
     * 
     * @return bool Always true - WebP is always generated
     */
    public static function shouldGenerateWebP() {
        return true;
    }
    
    /**
     * Get if original JPEG/PNG should be kept alongside WebP
     * 
     * @return bool
     */
    public static function shouldKeepOriginal() {
        return self::getSetting('keep_original', false);
    }
    
    /**
     * Get if AVIF should be generated
     * 
     * @return bool
     */
    public static function shouldGenerateAVIF() {
        return self::getSetting('generate_avif', false);
    }
    
    /**
     * Get if GIF should be converted to WebP
     * 
     * @return bool
     */
    public static function shouldConvertGif() {
        return self::getSetting('convert_gif', false);
    }
}

