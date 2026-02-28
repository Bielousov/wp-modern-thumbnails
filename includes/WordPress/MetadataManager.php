<?php
/**
 * Attachment Metadata Manager
 * 
 * Manages attachment metadata to include WebP file references and serve modern formats.
 * Updates metadata when thumbnails are generated and filters it on request.
 */

namespace ModernMediaThumbnails\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ModernMediaThumbnails\FormatManager;

class MetadataManager {
    
    /**
     * Register metadata hooks
     * 
     * @return void
     */
    public static function register() {
        // Fix metadata early in generation process (priority 5)
        add_filter('wp_generate_attachment_metadata', [self::class, 'ensureValidMetadata'], 5, 2);
        
        // Process WebP generation after metadata is fixed (priority 10)
        add_filter('wp_generate_attachment_metadata', [self::class, 'interceptGeneratedMetadata'], 10, 2);
        
        // Save corrected metadata to database (priority 20, after all other processing)
        add_filter('wp_generate_attachment_metadata', [self::class, 'saveMetadataToDatabaseAfterFix'], 20, 2);

        // Also ensure metadata is corrected when metadata is updated via wp_update_attachment_metadata
        // (covers REST API / Gutenberg upload flows which may update metadata via action)
        add_action('wp_update_attachment_metadata', [self::class, 'onMetadataUpdate'], 10, 2);

        // Ensure media JS and REST responses reflect WebP/AVIF metadata when present
        add_filter('wp_prepare_attachment_for_js', [self::class, 'filterPrepareAttachmentForJS'], 10, 3);
        add_filter('rest_prepare_attachment', [self::class, 'filterRestPrepareAttachment'], 10, 3);
    }
    
    /**
     * Save fixed and processed metadata directly to database
     * 
     * By saving in the generation hook, we ensure all future retrievals get correct data.
     * Runs at priority 20, after dimension fixes and WebP generation.
     * 
     * @param array $metadata Fixed metadata
     * @param int $attachment_id Attachment ID
     * @return array Unchanged metadata (just triggers the save)
     */
    public static function saveMetadataToDatabaseAfterFix($metadata, $attachment_id) {
        if ($metadata && is_array($metadata)) {
            update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
        }
        return $metadata;
    }

    /**
     * Filter the data returned to wp_prepare_attachment_for_js so the block editor
     * sees WebP/AVIF files when they exist on disk even if DB metadata hasn't been
     * updated yet.
     *
     * @param array $response Prepared attachment data
     * @param int|WP_Post $attachment Attachment ID or post object
     * @param array $meta Attachment metadata
     * @return array Modified response
     */
    public static function filterPrepareAttachmentForJS($response, $attachment, $meta) {
        if (empty($meta) || !is_array($meta)) {
            return $response;
        }

        $attachment_id = is_object($attachment) ? intval($attachment->ID) : intval($attachment);
        if (! $attachment_id) {
            return $response;
        }

        $updated_meta = self::updateMetadataWithWebP($attachment_id, $meta);
        if ($updated_meta === $meta) {
            return $response;
        }

        // Patch response fields from updated metadata
        if (!empty($updated_meta['file'])) {
            $uploads = wp_get_upload_dir();
            $response['url'] = trailingslashit($uploads['baseurl']) . ltrim($updated_meta['file'], '/');
        }

        if (!empty($updated_meta['sizes']) && is_array($updated_meta['sizes'])) {
            $response['sizes'] = [];
            foreach ($updated_meta['sizes'] as $size_name => $size_data) {
                if (!is_array($size_data) || empty($size_data['file'])) {
                    continue;
                }

                $response['sizes'][$size_name] = [
                    'file' => $size_data['file'],
                    'width' => $size_data['width'] ?? 0,
                    'height' => $size_data['height'] ?? 0,
                    'mime-type' => $size_data['mime-type'] ?? '',
                    'source_url' => isset($uploads) ? trailingslashit($uploads['baseurl']) . ltrim($updated_meta['file'], '/') : '',
                ];
            }
        }

        return $response;
    }

    /**
     * Filter REST attachment response to prefer WebP/AVIF when present on disk.
     *
     * @param WP_REST_Response $response
     * @param WP_Post $post
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function filterRestPrepareAttachment($response, $post, $request) {
        $data = $response->get_data();
        $meta = $data['media_details'] ?? [];

        if (empty($meta) || !is_array($meta)) {
            return $response;
        }

        $attachment_id = intval($post->ID ?? 0);
        if (! $attachment_id) {
            return $response;
        }

        $updated_meta = self::updateMetadataWithWebP($attachment_id, $meta);
        if ($updated_meta === $meta) {
            return $response;
        }

        // Replace media_details in REST response
        $data['media_details'] = $updated_meta;
        // Also update source URL if main file changed
        if (!empty($updated_meta['file'])) {
            $uploads = wp_get_upload_dir();
            $data['source_url'] = trailingslashit($uploads['baseurl']) . ltrim($updated_meta['file'], '/');
        }

        $response->set_data($data);
        return $response;
    }
    
    /**
     * Ensure metadata contains valid dimensions
     * 
     * Fixes broken metadata (1x1px) by reading actual image dimensions.
     * Runs early (priority 5) before other hooks.
     * 
     * @param array $metadata Generated metadata
     * @param int $attachment_id Attachment ID
     * @return array Metadata with valid dimensions
     */
    public static function ensureValidMetadata($metadata, $attachment_id) {
        // If metadata is empty or dimensions are invalid (0, 1, or missing)
        $width = intval($metadata['width'] ?? 0);
        $height = intval($metadata['height'] ?? 0);
        
        if ($width <= 1 || $height <= 1) {
            // Try to get real dimensions from the actual image file
            $real_dims = self::getImageDimensionsFromFile($attachment_id);
            if ($real_dims) {
                $metadata['width'] = $real_dims['width'];
                $metadata['height'] = $real_dims['height'];
            }
        }
        
        return $metadata;
    }
    
    /**
     * Get actual image dimensions from file using Imagick
     * 
     * @param int $attachment_id Attachment ID
     * @return array|false Array with 'width' and 'height', or false if unable to read
     */
    private static function getImageDimensionsFromFile($attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return false;
        }
        
        try {
            $imagick = new \Imagick($file);
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $imagick->destroy();
            
            if ($width > 0 && $height > 0) {
                return ['width' => $width, 'height' => $height];
            }
        } catch (\Exception $e) {
            // Fall back to getimagesize if Imagick fails
            $size = @getimagesize($file);
            if ($size && isset($size[0], $size[1]) && $size[0] > 0 && $size[1] > 0) {
                return ['width' => $size[0], 'height' => $size[1]];
            }
        }
        
        return false;
    }
    
    /**
     * Intercept WordPress native thumbnail generation
     * 
     * Hooks into wp_generate_attachment_metadata to catch metadata generation from:
     * - WordPress native regeneration
     * - Other plugins
     * - Any source that generates thumbnails
     * 
     * Automatically generates WebP versions for thumbnails and updates metadata.
     * 
     * @param array $metadata Generated metadata
     * @param int $attachment_id Attachment ID
     * @return array Updated metadata with WebP references
     */
    public static function interceptGeneratedMetadata($metadata, $attachment_id) {
        // Get attachment file
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return $metadata;
        }
        
        $attachment_file = get_attached_file($attachment_id);
        if (!$attachment_file || !file_exists($attachment_file)) {
            return $metadata;
        }
        
        $attachment_dir = dirname($attachment_file);
        $settings = \ModernMediaThumbnails\Settings::getWithDefaults();
        $webp_quality = intval($settings['webp_quality'] ?? 80);
        
        try {
            // Generate WebP for each thumbnail size
            if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_name => $size_data) {
                    if (!is_array($size_data) || empty($size_data['file'])) {
                        continue;
                    }
                    
                    $size_file = $attachment_dir . '/' . $size_data['file'];
                    if (!file_exists($size_file)) {
                        continue;
                    }
                    
                    $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                    $size_webp = $size_base . '.webp';
                    
                    // Generate WebP if it doesn't exist
                    if (!file_exists($size_webp)) {
                        $width = intval($size_data['width'] ?? 0);
                        $height = intval($size_data['height'] ?? 0);
                        
                        if ($width && $height) {
                            // Use original source file to generate WebP thumbnail
                            $result = \ModernMediaThumbnails\ThumbnailGenerator::generateWebP(
                                $attachment_file,
                                $size_webp,
                                $width, $height, false,
                                $webp_quality
                            );
                            
                            // If actual dimensions differ, rename file
                            if ($result && is_array($result)) {
                                if (isset($result['actual_width']) && isset($result['actual_height'])) {
                                    $actual_w = $result['actual_width'];
                                    $actual_h = $result['actual_height'];
                                    if ($actual_w !== $width || $actual_h !== $height) {
                                        $size_webp_new = dirname($size_webp) . '/' . pathinfo($size_webp, PATHINFO_FILENAME) . '-' . $actual_w . 'x' . $actual_h . '.webp';
                                        rename($size_webp, $size_webp_new);
                                        $size_webp = $size_webp_new;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail - don't break WordPress regeneration if WebP generation fails
        }
        
        // Update metadata to point to WebP files
        return self::updateMetadataWithWebP($attachment_id, $metadata);
    }
    
    /**
     * Note: The wp_update_attachment_metadata action hook is no longer used.
     * WebP references are now added to metadata in Ajax.php BEFORE calling
     * wp_update_attachment_metadata(), ensuring proper database persistence.
     * 
     * This action hook fires AFTER metadata is saved to database and cannot
     * reliably modify what gets persisted, so we handle it upstream instead.
     */
    public static function onMetadataUpdate($metadata, $attachment_id) {
        // Hook is no longer needed - WebP processing happens in Ajax.php before save
    }
    
    /**
     * Update attachment metadata with WebP file references after generation
     * 
     * Updates metadata to reference WebP files while preserving dimensions.
     * Original files are kept on disk but not referenced in metadata.
     * 
     * @param int $attachment_id Attachment ID
     * @param array $metadata Current attachment metadata
     * @return array Updated metadata with WebP files and preserved dimensions
     */
    public static function updateMetadataWithWebP($attachment_id, $metadata) {
        if (empty($metadata) || !is_array($metadata)) {
            return $metadata;
        }
        
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return $metadata;
        }
        
        $attachment_file = get_attached_file($attachment_id);
        if (!$attachment_file) {
            return $metadata;
        }
        
        // Ensure main image dimensions are valid
        $width = intval($metadata['width'] ?? 0);
        $height = intval($metadata['height'] ?? 0);
        if ($width <= 1 || $height <= 1) {
            $real_dims = self::getImageDimensionsFromFile($attachment_id);
            if ($real_dims) {
                $metadata['width'] = $real_dims['width'];
                $metadata['height'] = $real_dims['height'];
            }
        }
        
        $attachment_dir = dirname($attachment_file);
        $attachment_base = preg_replace('/\.[^\.]+$/', '', $attachment_file);
        
        // Check if WebP version of main file exists and update metadata
        $main_webp_file = $attachment_base . '.webp';
        if (file_exists($main_webp_file)) {
            $metadata['file'] = basename($main_webp_file);
        }
        
        // Update sizes metadata to use WebP only while preserving dimensions
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_name => &$size_data) {
                if (!is_array($size_data) || empty($size_data['file'])) {
                    continue;
                }
                
                // Preserve original dimensions
                $size_width = intval($size_data['width'] ?? 0);
                $size_height = intval($size_data['height'] ?? 0);
                
                // Get the size file path
                $size_file = $attachment_dir . '/' . $size_data['file'];
                $size_base = preg_replace('/\.[^\.]+$/', '', $size_file);
                $webp_file = $size_base . '.webp';
                
                // If WebP exists, use it; otherwise keep original
                if (file_exists($webp_file)) {
                    $size_data['file'] = basename($webp_file);
                    $size_data['mime-type'] = 'image/webp';
                    // Ensure dimensions are preserved
                    $size_data['width'] = $size_width;
                    $size_data['height'] = $size_height;
                }
            }
            unset($size_data);  // Break reference
        }
        
        return $metadata;
    }
}
