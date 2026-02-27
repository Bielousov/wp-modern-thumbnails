<?php
/**
 * AJAX Handler
 * 
 * Handles AJAX requests for regeneration and format settings.
 */

namespace ModernMediaThumbnails\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ModernMediaThumbnails\FormatManager;
use ModernMediaThumbnails\Settings;
use ModernMediaThumbnails\WordPress\RegenerationManager;
use ModernMediaThumbnails\WordPress\MetadataManager;
use ModernMediaThumbnails\Admin\SettingsPage;

class Ajax {
    
    /**
     * Handle AJAX regeneration of all thumbnails
     * 
     * @return void
     */
    public static function regenerateAll() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $regenerated = RegenerationManager::regenerateSize();
        
        wp_send_json_success([
            'message' => sprintf(
                'Successfully regenerated %d thumbnails',
                $regenerated
            ),
            'count' => $regenerated
        ]);
    }
    
    /**
     * Handle AJAX regeneration of a specific size
     * 
     * @return void
     */
    public static function regenerateSize() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $size_name = isset($_POST['size']) ? sanitize_text_field(wp_unslash($_POST['size'])) : null;
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        // If attachment_id is provided, regenerate only that specific attachment
        if ($attachment_id) {
            $file_path = get_attached_file($attachment_id);
            $generation_debug = null;

            // Attempt metadata generation if missing (covers REST/Gutenberg uploads)
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata)) {
                $generated_meta = self::generateMetadataWithEditor($attachment_id, $file_path);
                if (!empty($generated_meta) && is_array($generated_meta)) {
                    if (!empty($generated_meta['metadata'])) {
                        $metadata = $generated_meta['metadata'];
                    }
                    $generation_debug = $generated_meta['debug'] ?? $generated_meta;
                }
            }

            $regenerated = self::regenerateAttachmentSize($attachment_id, $size_name);
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = wp_get_attachment_metadata($attachment_id);
            $updated_metadata = MetadataManager::updateMetadataWithWebP($attachment_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($attachment_id, $updated_metadata);
            
            // Detect actual formats on disk
            $size_to_check = $size_name ?: 'original';
            $detected_formats = self::detectFormatsOnDisk($attachment_id, $size_to_check);
                $response = [
                    'message' => sprintf(
                        'Generated %d format(s) for media #%d',
                        $regenerated,
                        $attachment_id
                    ),
                    'attachment_id' => $attachment_id,
                    'count' => $regenerated,
                    'file_path' => $file_path,
                    'formats' => $detected_formats
                ];

                // If nothing was generated, include diagnostic info to help debugging
                if ($regenerated === 0) {
                    $attached_exists = $file_path ? file_exists($file_path) : false;
                    $response['diagnostics'] = [
                        'attached_file' => $file_path,
                        'attached_file_exists' => $attached_exists,
                        'attached_file_realpath' => $attached_exists ? @realpath($file_path) : null,
                        'attached_file_readable' => $attached_exists ? is_readable($file_path) : false,
                        'attached_file_perms' => $attached_exists ? substr(sprintf('%o', @fileperms($file_path)), -4) : null,
                        'post_mime_type' => get_post_mime_type($attachment_id),
                        'metadata' => wp_get_attachment_metadata($attachment_id),
                        'upload_dir' => wp_upload_dir(),
                    ];
                    if ($generation_debug) {
                        $response['generation_debug'] = $generation_debug;
                    }
                }

                wp_send_json_success($response);
        } else {
            // Original behavior - regenerate all attachments for a size (deprecated)
            if (!$size_name) {
                wp_send_json_error('No size specified');
            }
            
            $regenerated = RegenerationManager::regenerateSize($size_name);
            
            wp_send_json_success([
                'message' => sprintf(
                    'Successfully regenerated %d thumbnails for size "%s"',
                    $regenerated,
                    $size_name
                ),
                'count' => $regenerated,
                'size' => $size_name
            ]);
        }
    }
    
    /**
     * Regenerate a specific size for a single attachment
     * 
     * @param int $attachment_id
     * @param string|null $size_name
     * @return int Number of formats generated
     */
    private static function regenerateAttachmentSize($attachment_id, $size_name = null) {
        $regenerated = 0;
        
        try {
            $attachment = get_post($attachment_id);
            
            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                return 0;
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                return 0;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata)) {
                // Attempt metadata generation fallback (covers REST/Gutenberg uploads)
                $generated_meta = self::generateMetadataWithEditor($attachment_id, $file);
                if (!empty($generated_meta) && is_array($generated_meta) && !empty($generated_meta['metadata'])) {
                    $metadata = $generated_meta['metadata'];
                } else {
                    return 0;
                }
            }
            
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            if ($size_name) {
                // Regenerate specific size only
                if (!isset($image_sizes[$size_name])) {
                    return 0;
                }
                
                // Get width/height first to generate proper filename
                $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
                $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
                
                // Generate size filename using dimensions
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $size_file = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $ext;
                $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
                
                $regenerated = self::generateFormatsForSize($file, $size_file, $width, $height, $crop, $attachment->post_mime_type);
            } else {
                // Regenerate all sizes
                if (!empty($metadata['sizes'])) {
                    // Regenerate from existing metadata sizes
                    foreach ($metadata['sizes'] as $sz_name => $size_data) {
                        if (!isset($image_sizes[$sz_name])) {
                            continue;
                        }
                        
                        $width = isset($image_sizes[$sz_name]['width']) ? $image_sizes[$sz_name]['width'] : 0;
                        $height = isset($image_sizes[$sz_name]['height']) ? $image_sizes[$sz_name]['height'] : 0;
                        
                        // Generate size filename using dimensions
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $size_file = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $ext;
                        $crop = isset($image_sizes[$sz_name]['crop']) ? $image_sizes[$sz_name]['crop'] : false;
                        
                        $regenerated += self::generateFormatsForSize($file, $size_file, $width, $height, $crop, $attachment->post_mime_type);
                    }
                } else {
                    // No metadata sizes - generate from all registered sizes
                    foreach ($image_sizes as $sz_name => $size_info) {
                        $width = isset($size_info['width']) ? $size_info['width'] : 0;
                        $height = isset($size_info['height']) ? $size_info['height'] : 0;
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $size_file = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $ext;
                        $crop = isset($size_info['crop']) ? $size_info['crop'] : false;
                        
                        $regenerated += self::generateFormatsForSize($file, $size_file, $width, $height, $crop, $attachment->post_mime_type);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently handle error 
        }
        
        return $regenerated;
    }
    
    /**
     * Generate formats for a specific size based on user settings
     * 
     * @param string $source_file
     * @param string $size_file
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string $original_mime_type
     * @return int
     */
    private static function generateFormatsForSize($source_file, $size_file, $width, $height, $crop, $original_mime_type = 'image/jpeg') {
        $count = 0;
        
        if (!$width || !$height) {
            return 0;
        }
        
        // Get user settings
        $keep_original = FormatManager::shouldKeepOriginal();
        $generate_avif = FormatManager::shouldGenerateAVIF();
        $convert_gif = FormatManager::shouldConvertGif();
        $all_settings = Settings::getWithDefaults();
        $webp_quality = intval($all_settings['webp_quality']);
        $original_quality = intval($all_settings['original_quality']);
        $avif_quality = intval($all_settings['avif_quality']);
        
        // Map MIME type to format for original generation
        $format_map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        
        $original_format = $format_map[$original_mime_type] ?? 'jpg';
        
        // Remove existing files for this size
        $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
        self::deleteExistingFiles($size_base);
        
        // Handle GIF files specially - if convert_gif is disabled, only create GIF
        if ($original_mime_type === 'image/gif' && !$convert_gif) {
            // For GIFs when not converting, just keep the original GIF thumbnail
            // Don't generate WebP or AVIF versions
            return 0; // No new formats generated, original GIF already exists
        }
        
        // Always generate WebP (unless it's a GIF and we're not converting)
        $webp_file = $size_base . '.webp';
        if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($source_file, $webp_file, $width, $height, $crop, $webp_quality)) {
            $count++;
        }
        
        // Keep original format if enabled
        if ($keep_original) {
            $original_file = $size_base . '.' . $original_format;
            if (\ModernMediaThumbnails\ThumbnailGenerator::generateThumbnail($source_file, $original_file, $width, $height, $crop, $original_format, $original_quality)) {
                $count++;
            }
        }
        
        // Generate AVIF if enabled
        if ($generate_avif) {
            $avif_file = $size_base . '.avif';
            if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($source_file, $avif_file, $width, $height, $crop, $avif_quality)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Delete existing files for a thumbnail size
     * 
     * Aggressively removes all existing formats (webp, avif, png, jpg, jpeg, gif)
     * for the given size base, plus scans directory for any other matching files.
     * 
     * @param string $size_base Base path without extension (e.g. /path/to/image-medium)
     * @return void
     */
    private static function deleteExistingFiles($size_base) {
        $formats = ['webp', 'avif', 'png', 'jpg', 'jpeg', 'gif'];
        
        // Delete known formats
        foreach ($formats as $format) {
            $file = $size_base . '.' . $format;
            if (file_exists($file)) {
                @wp_delete_file($file);
            }
        }
        
        // Additional aggressive cleanup: find and remove any other files in the same directory
        // that match the size basename pattern (covers edge cases where wp_get_image_editor 
        // may have saved with a different naming scheme)
        $dir = dirname($size_base);
        $basename = basename($size_base);
        
        if (is_dir($dir)) {
            $files = @scandir($dir);
            if ($files && is_array($files)) {
                foreach ($files as $file_name) {
                    // Match files that start with the size basename and may have any extension
                    if (strpos($file_name, $basename) === 0) {
                        $full_path = $dir . '/' . $file_name;
                        // Don't delete directories or the original fullsize file
                        if (is_file($full_path) && $file_name !== $basename) {
                            @wp_delete_file($full_path);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Handle AJAX settings save
     * 
     * @return void
     */
    public static function saveSettings() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified via check_ajax_referer
        $settings = isset($_POST['settings']) ? (array)wp_unslash($_POST['settings']) : [];
        
        // Sanitize settings array - cast values to appropriate types (sanitization happens in updateSettings)
        $settings = array_map('sanitize_text_field', $settings);
        
        // Update settings with actual values (convert 1/0 to true/false and sanitize quality values)
        FormatManager::updateSettings([
            'keep_original' => (bool)($settings['keep_original'] ?? false),
            'generate_avif' => (bool)($settings['generate_avif'] ?? false),
            'convert_gif' => (bool)($settings['convert_gif'] ?? false),
            'keep_exif' => (bool)($settings['keep_exif'] ?? true),
            'keep_exif_thumbnails' => (bool)($settings['keep_exif_thumbnails'] ?? false),
            'webp_quality' => intval($settings['webp_quality'] ?? 80),
            'original_quality' => intval($settings['original_quality'] ?? 85),
            'avif_quality' => intval($settings['avif_quality'] ?? 75),
        ]);
        
        wp_send_json_success([
            'message' => 'Settings updated successfully'
        ]);
    }
    
    /**
     * Detect which formats actually exist on disk for a thumbnail size
     * 
     * @param int $attachment_id
     * @param string $size_name
     * @return array Array with keys 'original', 'webp', 'avif' set to true if files exist
     */
    public static function detectFormatsOnDisk($attachment_id, $size_name) {
        $formats = [
            'original' => false,
            'webp' => false,
            'avif' => false,
        ];
        
        try {
            $attachment = get_post($attachment_id);
            if (!$attachment) {
                return $formats;
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file) {
                return $formats;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata)) {
                return $formats;
            }
            
            // Determine base path for this size
            $size_base = '';
            
            if ($size_name === 'original') {
                // For original image
                $size_base = preg_replace('/\.[^\.]+$/', '', $file);
            } else {
                // For thumbnail sizes - use dimensions for consistent naming
                if (isset($metadata['sizes'][$size_name])) {
                    $size_data = $metadata['sizes'][$size_name];
                    $width = isset($size_data['width']) ? intval($size_data['width']) : 0;
                    $height = isset($size_data['height']) ? intval($size_data['height']) : 0;
                    
                    if ($width && $height) {
                        // Use dimension-based naming
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $size_base = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height;
                    } else {
                        // Fallback to old naming from metadata if no dimensions
                        $size_file = dirname($file) . '/' . $size_data['file'];
                        $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                    }
                } else {
                    // Fallback when size not in metadata
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $size_base = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $size_name;
                }
            }
            
            // Check for WebP
            if (file_exists($size_base . '.webp')) {
                $formats['webp'] = true;
            }
            
            // Check for AVIF
            if (file_exists($size_base . '.avif')) {
                $formats['avif'] = true;
            }
            
            // Check for original format
            $original_formats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            foreach ($original_formats as $fmt) {
                if (file_exists($size_base . '.' . $fmt)) {
                    // But exclude if it's just the webp we already detected
                    if (!($fmt === 'webp' && $formats['webp'])) {
                        $formats['original'] = true;
                        break;
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Silently handle error
        }
        
        return $formats;
    }
    
    /**

     * Get list of all media files in the library
     * 
     * @return void
     */
    public static function getMediaFilesList() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
        ]);
        
        wp_send_json_success([
            'media_ids' => $attachments,
            'total' => count($attachments),
        ]);
    }
    
    /**
     * Get media file count
     * 
     * @return void
     */
    public static function getMediaCount() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $media_count = SettingsPage::getMediaFileCount();
            wp_send_json_success([
                'value' => $media_count,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating media count: ' . $e->getMessage());
        }
    }
    
    /**
     * Get original file size
     * 
     * @return void
     */
    public static function getOriginalSize() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $original_size = SettingsPage::getOriginalFileSize();
            wp_send_json_success([
                'value' => SettingsPage::formatBytes($original_size),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating original size: ' . $e->getMessage());
        }
    }
    
    /**
     * Get thumbnail file size
     * 
     * @return void
     */
    public static function getThumbnailSize() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $thumbnail_size = SettingsPage::getThumbnailFileSize();
            wp_send_json_success([
                'value' => SettingsPage::formatBytes($thumbnail_size),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating thumbnail size: ' . $e->getMessage());
        }
    }
    
    /**
     * Get total media file size
     * 
     * @return void
     */
    public static function getTotalSize() {
        check_ajax_referer('mmt_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $original_size = SettingsPage::getOriginalFileSize();
            $thumbnail_size = SettingsPage::getThumbnailFileSize();
            $total_size = $original_size + $thumbnail_size;
            
            wp_send_json_success([
                'value' => SettingsPage::formatBytes($total_size),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error calculating total size: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize the regeneration queue - returns all attachment IDs to process
     * 
     * @return void
     */
    public static function regenerateQueueStart() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
        ]);
        
        wp_send_json_success([
            'attachment_ids' => $attachments,
            'total_count' => count($attachments),
            'message' => 'Queue initialized with ' . count($attachments) . ' media items',
        ]);
    }
    
    /**
     * Process a single attachment in the regeneration queue
     * 
     * @return void
     */
    public static function regenerateQueueProcess() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error('No attachment ID specified');
        }
        
        $regenerated = 0;
        
        try {
            // Regenerate thumbnails for this single attachment
            $attachment = get_post($attachment_id);
            
            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (not an image)',
                ]);
            }
            
            $file = get_attached_file($attachment_id);

            // Diagnostics list to return paths we checked and whether they existed
            $diagnostics = [];
            $upload_dir = wp_upload_dir();

            if ($file) {
                $diagnostics[] = ['path' => $file, 'exists' => file_exists($file)];
            } else {
                $diagnostics[] = ['path' => null, 'exists' => false];
            }

            // Robust fallback: if get_attached_file() points to a missing path, try metadata and common extensions.
            if (!$file || !file_exists($file)) {
                $metadata = wp_get_attachment_metadata($attachment_id);

                // Try metadata 'file' (may be relative path)
                if (!empty($metadata['file'])) {
                    $try = trailingslashit($upload_dir['basedir']) . ltrim($metadata['file'], '/');
                    $diagnostics[] = ['path' => $try, 'exists' => file_exists($try)];
                    if (file_exists($try)) {
                        $file = $try;
                    }
                }

                // If still not found, try common extensions for same basename
                if ((!$file || !file_exists($file)) && !empty($metadata['file'])) {
                    $base = preg_replace('/\.[^\.]+$/', '', $metadata['file']);
                    foreach (array('jpg', 'jpeg', 'png', 'gif', 'webp') as $ext) {
                        $candidate = trailingslashit($upload_dir['basedir']) . $base . '.' . $ext;
                        $diagnostics[] = ['path' => $candidate, 'exists' => file_exists($candidate)];
                        if (file_exists($candidate)) {
                            $file = $candidate;
                            break;
                        }
                    }
                }

                if (!$file || !file_exists($file)) {
                    wp_send_json_success([
                        'attachment_id' => $attachment_id,
                        'regenerated' => 0,
                        'message' => 'Skipped (file not found)',
                        'diagnostics' => $diagnostics,
                    ]);
                }
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);

            // Validate and repair broken metadata (detects orphaned sizes, invalid dimensions, etc.)
            if (!empty($metadata) && is_array($metadata)) {
                $metadata = self::validateAndRepairMetadata($attachment_id, $file, $metadata);
                if ($metadata) {
                    wp_update_attachment_metadata($attachment_id, $metadata);
                }
            }

            // If metadata is missing or lacks sizes, attempt to generate it (covers Gutenberg/REST uploads)
            $metadata_generated = false;
            if (empty($metadata) || empty($metadata['sizes'])) {
                try {
                    $generated = wp_generate_attachment_metadata($attachment_id, $file);
                    if (!empty($generated) && is_array($generated)) {
                        wp_update_attachment_metadata($attachment_id, $generated);
                        $metadata = $generated;
                        $metadata_generated = true;
                    }
                } catch (\Exception $e) {
                    // ignore generation errors
                }
            }

            if (empty($metadata) || empty($metadata['sizes'])) {
                // Collect diagnostics about available image editors and PHP extensions
                $editor = wp_get_image_editor($file);
                $editor_info = [];
                if (is_wp_error($editor)) {
                    $editor_info['error'] = $editor->get_error_message();
                } else {
                    $editor_info['class'] = is_object($editor) ? get_class($editor) : null;
                }

                $gd_info = null;
                if (function_exists('gd_info')) {
                    $gd_info = @gd_info();
                }

                $file_type = wp_check_filetype($file);
                $mime_content = function_exists('mime_content_type') ? @mime_content_type($file) : null;

                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (no thumbnails)',
                    'diagnostics' => [
                        'metadata_generated' => $metadata_generated,
                        'metadata' => $metadata,
                        'image_editor' => $editor_info,
                        'imagick_available' => class_exists('Imagick'),
                        'gd_available' => function_exists('gd_info'),
                        'gd_info' => $gd_info,
                        'file_checktype' => $file_type,
                        'mime_content_type' => $mime_content,
                    ],
                ]);
            }

            /**
             * Fallback: when metadata generation failed, try to build thumbnails
             * using WP_Image_Editor and persist metadata so processing can continue.
             */
            if (empty($metadata) || empty($metadata['sizes'])) {
                $generated_meta = self::generateMetadataWithEditor($attachment_id, $file);
                if (!empty($generated_meta) && is_array($generated_meta) && !empty($generated_meta['metadata'])) {
                    $metadata = $generated_meta['metadata'];
                } else {
                    // Attach generation debug info to diagnostics for later reporting
                    $generation_debug = is_array($generated_meta) ? ($generated_meta['debug'] ?? $generated_meta) : ['error' => 'generation_failed'];
                }
            }
            
            // Load source image once for all sizes
            try {
                $imagick = new \Imagick($file);
            } catch (\Exception $e) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (unable to open file)',
                ]);
            }
            
            // Process all sizes for this attachment
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            // Get quality settings
            $all_settings = Settings::getWithDefaults();
            $webp_quality = intval($all_settings['webp_quality']);
            $original_quality = intval($all_settings['original_quality']);
            $avif_quality = intval($all_settings['avif_quality']);
            $convert_gif = FormatManager::shouldConvertGif();
            
            // Get source image MIME type
            $source_mime = $attachment ? get_post_mime_type($attachment->ID) : 'image/jpeg';
            
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (!isset($image_sizes[$size_name])) {
                    continue;
                }
                
                $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
                $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
                $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
                
                // Generate size filename using dimensions
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $size_file = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $ext;
                
                if ($width && $height) {
                    // Handle GIF files specially
                    if ($source_mime === 'image/gif' && !$convert_gif) {
                        // Don't generate WebP/AVIF for GIFs when not converting
                        continue;
                    }
                    
                    // Generate WebP - pass imagick object
                    $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                    if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($imagick, $webp_file, $width, $height, $crop, $webp_quality)) {
                        $regenerated++;
                    }
                    
                    // Generate original format if enabled - pass imagick object
                    if (FormatManager::shouldKeepOriginal()) {
                        $format_map = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                        ];
                        $original_format = $format_map[$source_mime] ?? 'jpg';
                        $original_file = preg_replace('/\.[^\.]+$/', '.' . $original_format, $size_file);
                        
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateThumbnail($imagick, $original_file, $width, $height, $crop, $original_format, $original_quality)) {
                            $regenerated++;
                        }
                    }
                    
                    // Generate AVIF if enabled - pass imagick object
                    if (FormatManager::shouldGenerateAVIF()) {
                        $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($imagick, $avif_file, $width, $height, $crop, $avif_quality)) {
                            $regenerated++;
                        }
                    }
                    
                    // Delete original if not keeping it
                    if (!FormatManager::shouldKeepOriginal()) {
                        if (file_exists($size_file)) {
                            wp_delete_file($size_file);
                        }
                    }
                }
            }
            
            // Destroy imagick object after processing all sizes
            $imagick->destroy();
            
            // Update metadata to reflect new dimension-based filenames
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (isset($image_sizes[$size_name])) {
                    $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
                    $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
                    
                    // Update with new dimension-based filename
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $new_filename = pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $ext;
                    
                    if ($width && $height) {
                        $metadata['sizes'][$size_name]['file'] = $new_filename;
                        $metadata['sizes'][$size_name]['width'] = $width;
                        $metadata['sizes'][$size_name]['height'] = $height;
                    }
                }
            }
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = $metadata;
            $updated_metadata = MetadataManager::updateMetadataWithWebP($attachment_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($attachment_id, $updated_metadata);
            
            $response = [
                'attachment_id' => $attachment_id,
                'regenerated' => $regenerated,
                'message' => 'Processed media item (generated ' . $regenerated . ' formats)',
            ];

            // Include generation debug/diagnostics if available
            if (!empty($generation_debug)) {
                $response['generation_debug'] = $generation_debug;
            }

            wp_send_json_success($response);
        } catch (\Exception $e) {
            wp_send_json_error('Error processing attachment ' . $attachment_id . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Initialize the regeneration queue for a specific size - returns all attachment IDs to process
     * 
     * @return void
     */
    public static function regenerateQueueSizeStart() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $size_name = isset($_POST['size']) ? sanitize_text_field(wp_unslash($_POST['size'])) : null;
        
        if (!$size_name) {
            wp_send_json_error('No size specified');
        }
        
        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
        ]);
        
        wp_send_json_success([
            'attachment_ids' => $attachments,
            'total_count' => count($attachments),
            'size' => $size_name,
            'message' => 'Queue initialized with ' . count($attachments) . ' media items for size "' . $size_name . '"',
        ]);
    }
    
    /**
     * Process a single attachment for a specific size in the regeneration queue
     * 
     * @return void
     */
    public static function regenerateQueueProcessSize() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $size_name = isset($_POST['size']) ? sanitize_text_field(wp_unslash($_POST['size'])) : null;
        
        if (!$attachment_id || !$size_name) {
            wp_send_json_error('No attachment ID or size specified');
        }
        
        $regenerated = 0;
        
        try {
            // Regenerate thumbnails for this single attachment and specific size
            $attachment = get_post($attachment_id);
            
            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (not an image)',
                ]);
                return;
            }
            
            $file = get_attached_file($attachment_id);
            if (!$file || !file_exists($file)) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (file not found)',
                ]);
                return;
            }
            
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata) || empty($metadata['sizes'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (no thumbnails)',
                ]);
                return;
            }
            
            // Load source image once
            try {
                $imagick = new \Imagick($file);
            } catch (\Exception $e) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (unable to open file)',
                ]);
                return;
            }
            
            // Process only the specified size for this attachment
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
            
            if (!isset($metadata['sizes'][$size_name]) || !isset($image_sizes[$size_name])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'regenerated' => 0,
                    'message' => 'Skipped (size not applicable)',
                ]);
                return;
            }
            
            $size_data = $metadata['sizes'][$size_name];
            $width = isset($image_sizes[$size_name]['width']) ? $image_sizes[$size_name]['width'] : 0;
            $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
            
            // Generate size filename using dimensions
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $size_file = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $ext;
            $height = isset($image_sizes[$size_name]['height']) ? $image_sizes[$size_name]['height'] : 0;
            $crop = isset($image_sizes[$size_name]['crop']) ? $image_sizes[$size_name]['crop'] : false;
            
            if ($width && $height) {
                // Get quality settings
                $all_settings = Settings::getWithDefaults();
                $webp_quality = intval($all_settings['webp_quality']);
                $original_quality = intval($all_settings['original_quality']);
                $avif_quality = intval($all_settings['avif_quality']);
                $convert_gif = FormatManager::shouldConvertGif();
                
                // Get source image MIME type
                $source_mime = $attachment ? get_post_mime_type($attachment->ID) : 'image/jpeg';
                
                // Handle GIF files specially
                if ($source_mime === 'image/gif' && !$convert_gif) {
                    // Don't generate WebP/AVIF for GIFs when not converting
                } else {
                    // Generate WebP - pass imagick object
                    $webp_file = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                    if (\ModernMediaThumbnails\ThumbnailGenerator::generateWebP($imagick, $webp_file, $width, $height, $crop, $webp_quality)) {
                        $regenerated++;
                    }
                    
                    // Generate original format if enabled - pass imagick object
                    if (FormatManager::shouldKeepOriginal()) {
                        $format_map = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                        ];
                        $original_format = $format_map[$source_mime] ?? 'jpg';
                        $original_file = preg_replace('/\.[^\.]+$/', '.' . $original_format, $size_file);
                        
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateThumbnail($imagick, $original_file, $width, $height, $crop, $original_format, $original_quality)) {
                            $regenerated++;
                        }
                    }
                    
                    // Generate AVIF if enabled - pass imagick object
                    if (FormatManager::shouldGenerateAVIF()) {
                        $avif_file = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                        if (\ModernMediaThumbnails\ThumbnailGenerator::generateAVIF($imagick, $avif_file, $width, $height, $crop, $avif_quality)) {
                            $regenerated++;
                        }
                    }
                    
                    // Delete original if not keeping it
                    if (!FormatManager::shouldKeepOriginal()) {
                        if (file_exists($size_file)) {
                            wp_delete_file($size_file);
                        }
                    }
                }
            }
            
            // Destroy imagick object after processing
            $imagick->destroy();
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = wp_get_attachment_metadata($attachment_id);
            $updated_metadata = MetadataManager::updateMetadataWithWebP($attachment_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($attachment_id, $updated_metadata);
            
            wp_send_json_success([
                'attachment_id' => $attachment_id,
                'regenerated' => $regenerated,
                'message' => 'Processed media item for size "' . $size_name . '" (generated ' . $regenerated . ' formats)',
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error processing attachment ' . $attachment_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Initialize restore queue - returns all attachment IDs to process (same list as regenerate)
     *
     * @return void
     */
    public static function restoreQueueStart() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get all image attachments
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
        ]);

        wp_send_json_success([
            'attachment_ids' => $attachments,
            'total_count' => count($attachments),
            'message' => 'Restore queue initialized with ' . count($attachments) . ' media items',
        ]);
    }

    /**
     * Process a single attachment in the restore queue
     * Removes WebP/AVIF variants and regenerates WordPress default thumbnails using the GD editor directly.
     *
     * @return void
     */
    public static function restoreQueueProcess() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error('No attachment ID specified');
        }

        try {
            $attachment = get_post($attachment_id);

            if (!$attachment || !in_array($attachment->post_mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'restored' => 0,
                    'message' => 'Skipped (not an image)',
                ]);
            }

            $file = get_attached_file($attachment_id);

            // Diagnostics for missing file resolution
            $diagnostics = [];
            $upload_dir = wp_upload_dir();

            if ($file) {
                $diagnostics[] = ['path' => $file, 'exists' => file_exists($file)];
            } else {
                $diagnostics[] = ['path' => null, 'exists' => false];
            }

            if (!$file || !file_exists($file)) {
                // Try metadata 'file' path
                $metadata_try = wp_get_attachment_metadata($attachment_id);
                if (!empty($metadata_try['file'])) {
                    $try = trailingslashit($upload_dir['basedir']) . ltrim($metadata_try['file'], '/');
                    $diagnostics[] = ['path' => $try, 'exists' => file_exists($try)];
                }

                // Return diagnostics so the caller can inspect attempted paths
                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'restored' => 0,
                    'message' => 'Skipped (file not found)',
                    'diagnostics' => $diagnostics,
                ]);
            }

            $metadata = wp_get_attachment_metadata($attachment_id);

            // If metadata is missing, attempt to generate it (covers REST/Gutenberg uploads)
            $metadata_generated = false;
            if (empty($metadata)) {
                try {
                    $generated = wp_generate_attachment_metadata($attachment_id, $file);
                    if (!empty($generated) && is_array($generated)) {
                        wp_update_attachment_metadata($attachment_id, $generated);
                        $metadata = $generated;
                        $metadata_generated = true;
                    }
                } catch (\Exception $e) {
                    // ignore failures and fall through to diagnostic response
                }
            }

            if (empty($metadata)) {
                $editor = wp_get_image_editor($file);
                $editor_info = [];
                if (is_wp_error($editor)) {
                    $editor_info['error'] = $editor->get_error_message();
                } else {
                    $editor_info['class'] = is_object($editor) ? get_class($editor) : null;
                }

                $gd_info = null;
                if (function_exists('gd_info')) {
                    $gd_info = @gd_info();
                }

                $file_type = wp_check_filetype($file);
                $mime_content = function_exists('mime_content_type') ? @mime_content_type($file) : null;

                wp_send_json_success([
                    'attachment_id' => $attachment_id,
                    'restored' => 0,
                    'message' => 'Skipped (no metadata)',
                    'diagnostics' => [
                        'metadata_generated' => $metadata_generated,
                        'metadata' => $metadata,
                        'image_editor' => $editor_info,
                        'imagick_available' => class_exists('Imagick'),
                        'gd_available' => function_exists('gd_info'),
                        'gd_info' => $gd_info,
                        'file_checktype' => $file_type,
                        'mime_content_type' => $mime_content,
                    ],
                ]);
            }

            $deleted = 0;
            $restored = 0;

            // Build potential candidate paths to delete. Consider both the stored metadata
            // (which may already reference .webp/.avif) and the on-disk attached file path.
            $candidates = [];
            $upload_dir = wp_upload_dir();

            // Candidate from metadata 'file' (may be relative path)
            if (!empty($metadata['file'])) {
                $meta_rel = ltrim($metadata['file'], '/');
                $meta_full = trailingslashit($upload_dir['basedir']) . $meta_rel;
                $candidates[] = $meta_full;
                $candidates[] = preg_replace('/\.[^\.]+$/', '.webp', $meta_full);
                $candidates[] = preg_replace('/\.[^\.]+$/', '.avif', $meta_full);

                // If metadata currently points to a modern format, also include likely original extensions
                if (preg_match('/\.(webp|avif)$/i', $meta_full)) {
                    foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
                        $candidates[] = preg_replace('/\.[^\.]+$/', '.' . $ext, $meta_full);
                    }
                }
            }

            // Candidate based on get_attached_file() path
            $candidates[] = $file;
            $candidates[] = preg_replace('/\.[^\.]+$/', '.webp', $file);
            $candidates[] = preg_replace('/\.[^\.]+$/', '.avif', $file);

            // Sizes from metadata (each may already reference .webp)
            if (!empty($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_data) {
                    if (empty($size_data['file'])) {
                        continue;
                    }

                    $size_rel = ltrim($size_data['file'], '/');
                    $size_full = dirname($file) . '/' . $size_rel;
                    $candidates[] = $size_full;
                    $candidates[] = preg_replace('/\.[^\.]+$/', '.webp', $size_full);
                    $candidates[] = preg_replace('/\.[^\.]+$/', '.avif', $size_full);

                    if (preg_match('/\.(webp|avif)$/i', $size_rel)) {
                        foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
                            $candidates[] = preg_replace('/\.[^\.]+$/', '.' . $ext, $size_full);
                        }
                    }
                }
            }

            // Deduplicate candidate list
            $candidates = array_values(array_unique(array_filter($candidates)));

            foreach ($candidates as $del_file) {
                if ( ! $del_file ) {
                    continue;
                }

                // Try the candidate path and a fallback in upload basedir
                $paths_to_try = array_unique([
                    $del_file,
                    trailingslashit($upload_dir['basedir']) . ltrim(basename($del_file), '/'),
                ]);

                foreach ($paths_to_try as $try_path) {
                    if (! $try_path) {
                        continue;
                    }

                    clearstatcache(true, $try_path);

                    if (file_exists($try_path)) {
                        $deleted_ok = false;
                        // Prefer wp_delete_file when available
                        if (function_exists('wp_delete_file')) {
                            $deleted_ok = @wp_delete_file($try_path);
                        }

                        if (! $deleted_ok) {
                            // Fallback to unlink if wp_delete_file didn't remove it
                            $deleted_ok = @unlink($try_path);
                        }

                        if ($deleted_ok) {
                            $deleted++;
                            break; // stop trying other fallbacks for this candidate
                        }
                    }
                }
            }

            // Bypass Modern Thumbnails filters: restore metadata to reference original filenames
            // Do NOT attempt to regenerate using GD here — simply remove modern format files and
            // update metadata so WordPress will use the original thumbnails (if present).
            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();

            $attachment_dir = dirname($file);
            $orig_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            // Ensure main metadata points to the original uploaded file
            $metadata['file'] = basename($file);

            $restored = 0;

            if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_name => &$size_data) {
                    if (!is_array($size_data)) {
                        continue;
                    }

                    // Get dimension info for proper filename
                    $width = isset($size_data['width']) ? intval($size_data['width']) : 0;
                    $height = isset($size_data['height']) ? intval($size_data['height']) : 0;
                    
                    // Generate dimension-based filename
                    $size_filename = pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $orig_ext;

                    // First, try to find an existing original-format file for this size
                    $found = false;
                    $candidates = [];
                    $candidates[] = $attachment_dir . '/' . $size_filename;
                    
                    // Also check for old naming convention
                    if (!empty($size_data['file'])) {
                        $size_base = preg_replace('/\.[^\.]+$/', '', $size_data['file']);
                        foreach (['jpg', 'jpeg', 'png', 'gif'] as $ext) {
                            $candidates[] = $attachment_dir . '/' . $size_base . '.' . $ext;
                        }
                    }

                    foreach ($candidates as $cand) {
                        if ($cand && file_exists($cand)) {
                            $size_data['file'] = basename($cand);
                            $size_data['mime-type'] = $attachment->post_mime_type ?? ($size_data['mime-type'] ?? null);
                            $restored++;
                            $found = true;
                            break;
                        }
                    }

                    if ($found || !($width && $height)) {
                        continue;
                    }

                    // If no original file found, attempt to generate a JPEG for this size using WP_Image_Editor
                    try {
                        $editor = wp_get_image_editor($file);
                        if (!is_wp_error($editor)) {
                            // Create a fresh editor instance from original each iteration
                            $editor = wp_get_image_editor($file);
                            if (!is_wp_error($editor)) {
                                $crop = isset($size_data['crop']) ? (bool) $size_data['crop'] : false;
                                $resized = $editor->resize($width, $height, $crop);
                                if (!is_wp_error($resized)) {
                                    $target_jpg = $attachment_dir . '/' . $size_filename;
                                    wp_mkdir_p(dirname($target_jpg));
                                    $saved = $editor->save($target_jpg);
                                    if (!is_wp_error($saved) && !empty($saved['path']) && file_exists($saved['path'])) {
                                        $size_data['file'] = basename($saved['path']);
                                        $size_data['mime-type'] = 'image/jpeg';
                                        $restored++;
                                    }
                                }
                                if (method_exists($editor, 'clear')) {
                                    $editor->clear();
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore generation errors for this size
                    }
                }
                unset($size_data);
            }

            // Save updated metadata to database
            wp_update_attachment_metadata($attachment_id, $metadata);

            wp_send_json_success([
                'attachment_id' => $attachment_id,
                'deleted' => $deleted,
                'restored' => $restored,
                'file_path' => $file,
                'message' => 'Restore completed (deleted ' . $deleted . ' files, regenerated or restored ' . $restored . ' sizes)'
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error restoring attachment ' . $attachment_id . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX regeneration of a single attachment (for bulk actions)
     * 
     * @return void
     */
    public static function regenerateSingle() {
        // Check nonce - use correct action name
        check_ajax_referer('mmt_bulk_action_nonce', '_wpnonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        // Verify attachment exists
        $attachment = get_post($post_id);
        if (!$attachment || 'attachment' !== $attachment->post_type) {
            wp_send_json_error('Invalid attachment');
        }
        
        try {
            // Get metadata to count sizes
            $metadata = wp_get_attachment_metadata($post_id);
            $size_count = !empty($metadata['sizes']) ? count($metadata['sizes']) : 0;
            
            // Regenerate all sizes for this attachment
            $regenerated = self::regenerateAttachmentSize($post_id, null);
            
            // Update attachment metadata to include WebP file references
            $updated_metadata = wp_get_attachment_metadata($post_id);
            $updated_metadata = MetadataManager::updateMetadataWithWebP($post_id, $updated_metadata);
            
            // Save metadata to database
            wp_update_attachment_metadata($post_id, $updated_metadata);
            
            // Build format list based on enabled options
            $enabled_formats = [];
            
            if (FormatManager::shouldGenerateWebP()) {
                $enabled_formats[] = 'WebP';
            }
            
            if (FormatManager::shouldKeepOriginal()) {
                $extension = strtoupper(pathinfo(get_attached_file($post_id), PATHINFO_EXTENSION));
                if ($extension === 'JPG') {
                    $extension = 'JPEG';
                }
                $enabled_formats[] = $extension;
            }
            
            if (FormatManager::shouldGenerateAVIF()) {
                $enabled_formats[] = 'AVIF';
            }
            
            // Build the format string
            $formats_text = '';
            if (count($enabled_formats) === 0) {
                $formats_text = 'configured formats';
            } elseif (count($enabled_formats) === 1) {
                $formats_text = $enabled_formats[0];
            } elseif (count($enabled_formats) === 2) {
                $formats_text = implode(' and ', $enabled_formats);
            } else {
                $last_format = array_pop($enabled_formats);
                $formats_text = implode(', ', $enabled_formats) . ' and ' . $last_format;
            }
            
            // Build message
            if ($size_count > 0) {
                $message = sprintf(
                    'Successfully regenerated %d thumbnail %s in %s',
                    $size_count,
                    _n( 'size', 'sizes', $size_count, 'modern-thumbnails' ),
                    $formats_text
                );
            } else {
                $message = 'Attachment processed';
            }
            
            // Get updated metadata to send back the new thumbnail URLs
            $final_metadata = wp_get_attachment_metadata($post_id);
            $attachment_url_base = dirname(wp_get_attachment_url($post_id));
            $attachment_file = get_attached_file($post_id);
            $attachment_dir = dirname($attachment_file);
            
            // Build thumbnail URLs with cache busting, preferring WebP files
            $thumbnails = [];
            $cache_buster = gmdate('Ymdhis');
            if (!empty($final_metadata['sizes'])) {
                foreach ($final_metadata['sizes'] as $size_name => $size_data) {
                    if (!empty($size_data['file'])) {
                        // Check if WebP version exists for this size
                        $size_file = $attachment_dir . '/' . $size_data['file'];
                        $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                        $webp_file = $size_base . '.webp';
                        
                        // Prefer WebP if it exists and WebP generation is enabled
                        if (file_exists($webp_file) && \ModernMediaThumbnails\FormatManager::shouldGenerateWebP()) {
                            $url = $attachment_url_base . '/' . basename($webp_file);
                        } else {
                            $url = $attachment_url_base . '/' . $size_data['file'];
                        }
                        
                        // Add cache buster to ensure fresh fetch
                        $thumbnails[$size_name] = $url . '?v=' . $cache_buster;
                    }
                }
            }
            
            wp_send_json_success([
                'message' => $message,
                'post_id' => $post_id,
                'count' => $regenerated,
                'sizes' => $size_count,
                'formats' => $enabled_formats,
                'thumbnails' => $thumbnails
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Error regenerating attachment');
        }
    }

    /**
     * Validate and repair broken metadata
     * 
     * Detects and fixes:
     * - Invalid dimensions (0, 1, or missing)
     * - Orphaned sizes (not matching registered image sizes)
     * - Missing size files on disk
     * - Inconsistent metadata structure
     * 
     * @param int $attachment_id
     * @param string $file Absolute path to source file
     * @param array $metadata Existing (potentially broken) metadata
     * @return array|false Repaired metadata or false if unrecoverable
     */
    private static function validateAndRepairMetadata($attachment_id, $file, $metadata) {
        if (empty($metadata) || !is_array($metadata)) {
            return $metadata;
        }

        $attachment_dir = dirname($file);
        $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();
        $repaired = false;

        // Fix invalid main image dimensions
        $width = intval($metadata['width'] ?? 0);
        $height = intval($metadata['height'] ?? 0);
        if ($width <= 1 || $height <= 1) {
            $real_dims = self::getImageDimensionsFromFile($attachment_id, $file);
            if ($real_dims) {
                $metadata['width'] = $real_dims['width'];
                $metadata['height'] = $real_dims['height'];
                $repaired = true;
            }
        }

        // Validate and repair sizes
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $valid_sizes = [];

            foreach ($metadata['sizes'] as $size_name => $size_data) {
                // Skip sizes that don't match registered image sizes
                if (!isset($image_sizes[$size_name])) {
                    $repaired = true;
                    continue; // Skip orphaned size
                }

                if (!is_array($size_data)) {
                    $repaired = true;
                    continue;
                }

                // Check if size file exists on disk
                if (!empty($size_data['file'])) {
                    $size_file = $attachment_dir . '/' . $size_data['file'];
                    if (!file_exists($size_file)) {
                        // File missing - will be regenerated, so skip for now
                        $repaired = true;
                        continue;
                    }
                }

                // Fix invalid size dimensions
                $s_width = intval($size_data['width'] ?? 0);
                $s_height = intval($size_data['height'] ?? 0);
                if ($s_width <= 0 || $s_height <= 0) {
                    // Try to read actual dimensions from file
                    if (!empty($size_data['file'])) {
                        $size_file = $attachment_dir . '/' . $size_data['file'];
                        $size_dims = @getimagesize($size_file);
                        if ($size_dims && isset($size_dims[0], $size_dims[1])) {
                            $size_data['width'] = $size_dims[0];
                            $size_data['height'] = $size_dims[1];
                            $repaired = true;
                        } else {
                            // Can't read dimensions and they're invalid, skip this size
                            $repaired = true;
                            continue;
                        }
                    } else {
                        // No file, skip
                        $repaired = true;
                        continue;
                    }
                }

                $valid_sizes[$size_name] = $size_data;
            }

            $metadata['sizes'] = $valid_sizes;
        }

        return $repaired ? $metadata : $metadata;
    }

    /**
     * Get image dimensions from file using getimagesize
     * 
     * @param int $attachment_id
     * @param string $file Absolute path
     * @return array|false Array with 'width' and 'height', or false
     */
    private static function getImageDimensionsFromFile($attachment_id, $file) {
        $size = @getimagesize($file);
        if ($size && isset($size[0], $size[1]) && $size[0] > 0 && $size[1] > 0) {
            return ['width' => $size[0], 'height' => $size[1]];
        }
        return false;
    }

    /**
     * Try to generate attachment metadata and sizes using WP_Image_Editor.
     * Returns generated metadata array on success or false on failure.
     *
     * @param int $attachment_id
     * @param string $file Absolute path to source file
     * @return array|false
     */
    private static function generateMetadataWithEditor($attachment_id, $file) {
        $debug = ['sizes' => []];
        try {
            $editor_class = wp_get_image_editor($file);
            if (is_wp_error($editor_class)) {
                $debug['error'] = 'no_image_editor';
                $debug['editor_error'] = $editor_class->get_error_message();
                return ['metadata' => false, 'debug' => $debug];
            }

            $image_sizes = \ModernMediaThumbnails\ImageSizeManager::getAllImageSizes();

            // Get source dimensions
            $size = @getimagesize($file);
            if (!$size || !isset($size[0], $size[1])) {
                $debug['error'] = 'unable_to_read_dimensions';
                return ['metadata' => false, 'debug' => $debug];
            }

            $metadata = [];
            $upload_dir = wp_get_upload_dir();
            $metadata['file'] = ltrim(str_replace(trailingslashit($upload_dir['basedir']), '', $file), '/');
            $metadata['width'] = intval($size[0]);
            $metadata['height'] = intval($size[1]);
            $metadata['sizes'] = [];

            $attachment_dir = dirname($file);

            foreach ($image_sizes as $size_name => $size_info) {
                $width = isset($size_info['width']) ? intval($size_info['width']) : 0;
                $height = isset($size_info['height']) ? intval($size_info['height']) : 0;
                $crop = isset($size_info['crop']) ? (bool)$size_info['crop'] : false;

                if (!$width || !$height) {
                    $debug['sizes'][$size_name] = ['skipped' => 'invalid_dimensions'];
                    continue;
                }

                // Create a fresh editor instance per size to avoid stale state
                $editor = wp_get_image_editor($file);
                if (is_wp_error($editor)) {
                    $debug['sizes'][$size_name] = ['error' => 'editor_init_failed', 'message' => $editor->get_error_message()];
                    continue;
                }

                $resized = $editor->resize($width, $height, $crop);
                if (is_wp_error($resized)) {
                    $debug['sizes'][$size_name] = ['error' => 'resize_failed', 'message' => $resized->get_error_message()];
                    continue;
                }

                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $size_filename = pathinfo($file, PATHINFO_FILENAME) . '-' . $width . 'x' . $height . '.' . $ext;
                $target = $attachment_dir . '/' . $size_filename;
                wp_mkdir_p(dirname($target));

                // Ensure directory is writable before attempting save
                if (is_dir($attachment_dir) && !is_writable($attachment_dir)) {
                    @chmod($attachment_dir, 0755);
                }

                $saved = $editor->save($target);
                if (is_wp_error($saved)) {
                    $error_msg = $saved->get_error_message();
                    // Check if this is a permission denied error
                    if (strpos($error_msg, 'Permission denied') !== false || strpos($error_msg, 'permission') !== false) {
                        $debug['sizes'][$size_name] = [
                            'error' => 'save_failed_permission',
                            'message' => $error_msg,
                            'dir_exists' => is_dir($attachment_dir),
                            'dir_writable' => is_writable($attachment_dir),
                            'dir_perms' => is_dir($attachment_dir) ? substr(sprintf('%o', @fileperms($attachment_dir)), -4) : null,
                        ];
                    } else {
                        $debug['sizes'][$size_name] = ['error' => 'save_failed', 'message' => $error_msg];
                    }
                    continue;
                }

                if (empty($saved['path']) || !file_exists($saved['path'])) {
                    $debug['sizes'][$size_name] = ['error' => 'saved_file_missing', 'path' => $saved['path'] ?? null];
                    continue;
                }

                // Preserve source file permissions on generated thumbnail
                $source_perms = @fileperms($file);
                if ($source_perms !== false) {
                    @chmod($saved['path'], $source_perms);
                }

                $metadata['sizes'][$size_name] = [
                    'file' => basename($saved['path']),
                    'width' => $saved['width'] ?? $width,
                    'height' => $saved['height'] ?? $height,
                ];

                $debug['sizes'][$size_name] = ['saved' => true, 'path' => $saved['path'], 'width' => $saved['width'] ?? $width, 'height' => $saved['height'] ?? $height];

                if (method_exists($editor, 'clear')) {
                    $editor->clear();
                }
            }

            if (empty($metadata['sizes'])) {
                $debug['error'] = 'no_sizes_saved';
                return ['metadata' => false, 'debug' => $debug];
            }

            // Persist metadata
            wp_update_attachment_metadata($attachment_id, $metadata);
            return ['metadata' => $metadata, 'debug' => $debug];
        } catch (\Exception $e) {
            $debug['exception'] = $e->getMessage();
            return ['metadata' => false, 'debug' => $debug];
        }
    }
    
    /**
     * Get attachment ID from image URL
     * 
     * @return void
     */
    public static function getAttachmentIdByUrl() {
        check_ajax_referer('mmt_regenerate_nonce');
        
        if (!isset($_POST['image_url'])) {
            wp_send_json_error('No image URL provided');
        }
        
        $image_url = sanitize_url(wp_unslash($_POST['image_url']));
        
        // Use WordPress built-in function to get attachment ID from URL
        $attachment_id = attachment_url_to_postid($image_url);
        
        if (!$attachment_id) {
            // Try alternative method: query the database directly with caching
            global $wpdb;
            $cache_key = 'mmt_attachment_guid_' . md5($image_url);
            $attachment_id = wp_cache_get($cache_key);
            
            if (false === $attachment_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached with wp_cache
                $attachment_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s",
                    $image_url
                ));
                wp_cache_set($cache_key, $attachment_id, '', 3600); // Cache for 1 hour
            }
        }
        
        // If still not found, try to extract base filename and search for that
        // This handles cases where WordPress shows scaled/thumbnail versions
        if (!$attachment_id) {
            // Extract filename from URL (e.g., solar-eclipse-intro from solar-eclipse-intro-1200x800.jpg)
            $basename = pathinfo($image_url, PATHINFO_FILENAME);
            
            // Remove dimensions suffix (e.g., -1200x800 or -1920x1280)
            $base_name_no_dims = preg_replace('/-\d+x\d+$/', '', $basename);
            
            // Search for attachment with this base name using cache
            global $wpdb;
            $cache_key = 'mmt_attachment_basename_' . md5($base_name_no_dims);
            $attachment_id = wp_cache_get($cache_key);
            
            if (false === $attachment_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached with wp_cache
                $attachment_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                     WHERE post_type = 'attachment' 
                     AND (guid LIKE %s OR post_title LIKE %s OR guid LIKE %s)",
                    '%' . $base_name_no_dims . '%',
                    '%' . $base_name_no_dims . '%',
                    '%' . $basename . '%'
                ));
                wp_cache_set($cache_key, $attachment_id, '', 3600); // Cache for 1 hour
            }
        }
        
        // Try original file format if webp/avif (remove format suffix)
        if (!$attachment_id && preg_match('/\.(webp|avif)$/i', $image_url)) {
            // Try common formats and remove dimensions
            $basename = pathinfo($image_url, PATHINFO_FILENAME);
            $base_name_no_dims = preg_replace('/-\d+x\d+\.webp$|-\d+x\d+\.avif$/i', '', $basename);
            
            foreach (['.jpg', '.jpeg', '.png', '.gif'] as $ext) {
                // Try with dimensions removed
                $original_url = preg_replace('/\/([^\/]+)\.(webp|avif)$/i', '/' . $base_name_no_dims . $ext, $image_url);
                $attachment_id = attachment_url_to_postid($original_url);
                if ($attachment_id) {
                    break;
                }
                
                // Also try database search with caching
                global $wpdb;
                $cache_key = 'mmt_attachment_ext_' . md5($base_name_no_dims . $ext);
                $attachment_id = wp_cache_get($cache_key);
                
                if (false === $attachment_id) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached with wp_cache
                    $attachment_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
                        '%' . $base_name_no_dims . $ext
                    ));
                    wp_cache_set($cache_key, $attachment_id, '', 3600); // Cache for 1 hour
                }
                if ($attachment_id) {
                    break;
                }
            }
        }
        
        if (!$attachment_id) {
            wp_send_json_error('Could not find attachment for image URL: ' . $image_url);
        }
        
        wp_send_json_success([
            'attachment_id' => intval($attachment_id),
            'message' => 'Attachment ID found'
        ]);
    }

    /**
     * Register AJAX handlers
     * 
     * @return void
     */
    public static function register() {
        add_action('wp_ajax_mmt_regenerate_all', [self::class, 'regenerateAll']);
        add_action('wp_ajax_mmt_regenerate_size', [self::class, 'regenerateSize']);
        add_action('wp_ajax_mmt_regenerate_single', [self::class, 'regenerateSingle']);
        add_action('wp_ajax_mmt_get_attachment_id_by_url', [self::class, 'getAttachmentIdByUrl']);
        add_action('wp_ajax_mmt_save_settings', [self::class, 'saveSettings']);
        add_action('wp_ajax_mmt_get_media_count', [self::class, 'getMediaCount']);
        add_action('wp_ajax_mmt_get_original_size', [self::class, 'getOriginalSize']);
        add_action('wp_ajax_mmt_get_thumbnail_size', [self::class, 'getThumbnailSize']);
        add_action('wp_ajax_mmt_get_total_size', [self::class, 'getTotalSize']);
        add_action('wp_ajax_mmt_get_media_files_list', [self::class, 'getMediaFilesList']);
        add_action('wp_ajax_mmt_regenerate_queue_start', [self::class, 'regenerateQueueStart']);
        add_action('wp_ajax_mmt_regenerate_queue_process', [self::class, 'regenerateQueueProcess']);
        add_action('wp_ajax_mmt_regenerate_queue_size_start', [self::class, 'regenerateQueueSizeStart']);
        add_action('wp_ajax_mmt_regenerate_queue_process_size', [self::class, 'regenerateQueueProcessSize']);
        add_action('wp_ajax_mmt_restore_queue_start', [self::class, 'restoreQueueStart']);
        add_action('wp_ajax_mmt_restore_queue_process', [self::class, 'restoreQueueProcess']);
        add_action('wp_ajax_mmt_dismiss_nginx_notice', [self::class, 'dismissNginxNotice']);
        add_action('wp_ajax_mmt_dismiss_apache_notice', [self::class, 'dismissApacheNotice']);
        add_action('wp_ajax_mmt_get_diagnostics', [self::class, 'getDiagnosticsHandler']);
    }
    
    /**
     * Handle dismissal of nginx configuration notice
     * 
     * @return void
     */
    public static function dismissNginxNotice() {
        // Verify nonce (already sanitized via wp_verify_nonce)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce is verified via wp_verify_nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'mmt_dismiss_notice')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Set transient for 30 days (don't show notice again)
        set_transient('mmt_nginx_config_notice_dismissed', true, 30 * 24 * 3600);
        delete_transient('mmt_nginx_config_notice'); // Clear the show notice transient
        
        wp_send_json_success(['message' => 'Notice dismissed']);
    }
    
    /**
     * Handle dismissal of Apache configuration notice
     * 
     * @return void
     */
    public static function dismissApacheNotice() {
        // Verify nonce (already sanitized via wp_verify_nonce)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce is verified via wp_verify_nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'mmt_dismiss_notice')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Set transient for 30 days (don't show notice again)
        set_transient('mmt_apache_config_notice_dismissed', true, 30 * 24 * 3600);
        delete_transient('mmt_apache_config_notice'); // Clear the show notice transient
        
        wp_send_json_success(['message' => 'Notice dismissed']);
    }

    /**
     * AJAX handler to return permissions and environment diagnostics.
     * Helps user debug why thumbnail generation is failing with permission errors.
     *
     * @return void
     */
    public static function getDiagnosticsHandler() {
        check_ajax_referer('mmt_regenerate_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $diagnostics = self::gatherPermissionsDiagnostics();
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if ($attachment_id) {
            $diagnostics['attachment_dir'] = self::getAttachmentDirDiagnostics($attachment_id);
        }

        wp_send_json_success([
            'diagnostics' => $diagnostics,
            'message' => 'Diagnostics gathered'
        ]);
    }

    /**
     * Gather diagnostics about file permissions and environment.
     * Helps identify why ImageMagick/GD cannot save thumbnails.
     *
     * @return array Diagnostic information
     */
    public static function gatherPermissionsDiagnostics() {
        $upload_dir = wp_upload_dir();
        $uploads_base = $upload_dir['basedir'];
        $diagnostics = [
            'uploads_base'        => $uploads_base,
            'uploads_exists'      => is_dir($uploads_base),
            'uploads_writable'    => is_writable($uploads_base),
            'uploads_perms'       => is_dir($uploads_base) ? substr(sprintf('%o', @fileperms($uploads_base)), -4) : null,
            'uploads_owner_uid'   => is_dir($uploads_base) ? @fileowner($uploads_base) : null,
            'php_user_uid'        => function_exists('posix_getuid') ? posix_getuid() : 'unknown',
            'php_user'            => function_exists('posix_getpwuid') ? posix_getpwuid(posix_getuid())['name'] : 'unknown',
            'temp_dir'            => sys_get_temp_dir(),
            'temp_writable'       => is_writable(sys_get_temp_dir()),
            'gd_available'        => extension_loaded('gd'),
            'imagick_available'   => extension_loaded('imagick'),
        ];

        // Check if ImageMagick policy restricts file operations
        $policy_paths = [
            '/etc/ImageMagick-6/policy.xml',
            '/etc/ImageMagick/policy.xml',
            '/etc/ImageMagick-7/policy.xml',
        ];
        foreach ($policy_paths as $path) {
            if (file_exists($path)) {
                $policy_content = file_get_contents($path);
                if (strpos($policy_content, '<policy') !== false) {
                    $diagnostics['imagick_policy_found'] = $path;
                    // Check for file:// or blob: restrictions
                    if (preg_match('/<policy.*domain="coder"\s+rights="none".*pattern="(file|blob)"/', $policy_content)) {
                        $diagnostics['imagick_file_policy_blocked'] = true;
                    }
                    break;
                }
            }
        }

        return $diagnostics;
    }

    /**
     * Get information about target attachment directory.
     *
     * @param int $attachment_id Attachment post ID.
     * @return array Directory info
     */
    public static function getAttachmentDirDiagnostics($attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file) {
            return ['error' => 'no_attached_file'];
        }
        $attachment_dir = dirname($file);
        return [
            'dir'            => $attachment_dir,
            'exists'         => is_dir($attachment_dir),
            'writable'       => is_writable($attachment_dir),
            'perms'          => is_dir($attachment_dir) ? substr(sprintf('%o', @fileperms($attachment_dir)), -4) : null,
            'owner_uid'      => is_dir($attachment_dir) ? @fileowner($attachment_dir) : null,
        ];
    }
}
