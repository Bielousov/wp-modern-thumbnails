<?php
/**
 * Image Size Manager
 * 
 * Handles detection and retrieval of image sizes defined in themes and plugins.
 */

namespace ModernMediaThumbnails;

class ImageSizeManager {
    
    /**
     * Get all image sizes defined in theme and plugins
     * 
     * @return array
     */
    public static function getAllImageSizes() {
        global $_wp_additional_image_sizes;
        
        $sizes = wp_get_registered_image_subsizes();
        
        if (empty($sizes)) {
            $sizes = array();
        }
        
        // Add custom image sizes from theme
        if (!empty($_wp_additional_image_sizes)) {
            foreach ($_wp_additional_image_sizes as $name => $size) {
                if (!isset($sizes[$name])) {
                    $sizes[$name] = $size;
                }
            }
        }
        
        return $sizes;
    }
    
    /**
     * Get readable names for all image sizes
     * 
     * @return array
     */
    public static function getImageSizeNames() {
        $sizes = self::getAllImageSizes();
        $size_names = array();
        
        // Build an array with size slugs as keys
        foreach ($sizes as $name => $size_data) {
            $size_names[$name] = $name; // Default to the slug if no custom name is found
        }
        
        // Apply WordPress filter to get user-friendly names
        $size_names = apply_filters('image_size_names_choose', $size_names);
        
        return $size_names;
    }
    
    /**
     * Get readable name for a specific image size
     * 
     * @param string $size_slug
     * @return string
     */
    public static function getImageSizeName($size_slug) {
        $size_names = self::getImageSizeNames();
        return isset($size_names[$size_slug]) ? $size_names[$size_slug] : $size_slug;
    }
}
