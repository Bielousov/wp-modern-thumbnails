<?php
/**
 * Helper Functions
 *
 * Utility functions for the Modern Thumbnails plugin.
 *
 * @package Modern_Thumbnails
 * @since   0.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the plugin version
 *
 * Returns the current plugin version from the constant, with a fallback value.
 *
 * @since 0.0.2
 *
 * @return string The plugin version string
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Function is properly prefixed with mmt_
function mmt_get_version() {
	return defined( 'MMT_PLUGIN_VERSION' ) ? MMT_PLUGIN_VERSION : '0.0.5';
}

/**
 * Check AJAX permissions with nonce verification
 *
 * Validates both nonce and user capability in one call.
 * Dies with JSON error if checks fail.
 *
 * @param string $nonce_action The nonce action name
 * @param string $nonce_param  The POST parameter containing the nonce
 * @param string $capability   Required user capability (default: manage_options)
 *
 * @return void Exits with error on failure, returns void on success
 */
function mmt_check_ajax_permissions( $nonce_action, $nonce_param = '_wpnonce', $capability = 'manage_options' ) {
	check_ajax_referer( $nonce_action, $nonce_param );
	
	if ( ! current_user_can( $capability ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}
}

/**
 * Verify attachment exists and is owned by current user
 *
 * @param int    $attachment_id The attachment post ID
 * @param string $capability    Required capability (default: edit_posts)
 *
 * @return WP_Post|null The attachment post object or null if invalid
 */
function mmt_get_verified_attachment( $attachment_id, $capability = 'edit_posts' ) {
	$attachment = get_post( $attachment_id );
	
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return null;
	}
	
	if ( ! user_can( get_current_user_id(), $capability, $attachment_id ) ) {
		return null;
	}
	
	return $attachment;
}

/**
 * Check if attachment is a supported image type
 *
 * @param WP_Post|int $attachment The attachment post or ID
 * @param array       $allowed_types Optional array of allowed MIME types
 *
 * @return bool True if attachment is a valid image type
 */
function mmt_is_valid_image_attachment( $attachment, $allowed_types = array() ) {
	if ( is_numeric( $attachment ) ) {
		$attachment = get_post( $attachment );
	}
	
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return false;
	}
	
	if ( empty( $allowed_types ) ) {
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
	}
	
	return in_array( $attachment->post_mime_type, $allowed_types, true );
}

/**
 * Get format name from MIME type
 *
 * Maps MIME types to format identifiers.
 *
 * @param string $mime_type MIME type (e.g., 'image/jpeg')
 * @param string $default   Default format if not found
 *
 * @return string Format identifier (e.g., 'jpg', 'png', 'webp')
 */
function mmt_get_format_from_mime( $mime_type, $default = 'jpg' ) {
	$mime_map = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	);
	
	return isset( $mime_map[ $mime_type ] ) ? $mime_map[ $mime_type ] : $default;
}

/**
 * Get image dimensions from file
 *
 * Attempts to get image dimensions using multiple methods:
 * 1. getimagesize() (faster, native)
 * 2. Imagick (more reliable)
 *
 * @param string $file_path Path to image file
 * @param bool   $use_imagick Whether to try Imagick first
 *
 * @return array|false Array with 'width' and 'height' keys, or false on failure
 */
function mmt_get_image_dimensions( $file_path, $use_imagick = true ) {
	if ( ! file_exists( $file_path ) ) {
		return false;
	}
	
	// Try Imagick first if available
	if ( $use_imagick && extension_loaded( 'imagick' ) ) {
		try {
			$imagick = new Imagick( $file_path );
			$width   = $imagick->getImageWidth();
			$height  = $imagick->getImageHeight();
			
			if ( $width > 0 && $height > 0 ) {
				return array(
					'width'  => $width,
					'height' => $height,
				);
			}
		} catch ( Exception $e ) {
			// Fall through to getimagesize
		}
	}
	
	// Fallback to getimagesize
	$size = @getimagesize( $file_path );
	if ( $size && isset( $size[0], $size[1] ) && $size[0] > 0 && $size[1] > 0 ) {
		return array(
			'width'  => $size[0],
			'height' => $size[1],
		);
	}
	
	return false;
}

/**
 * Validate image dimensions meet minimum threshold
 *
 * @param int $width  Image width in pixels
 * @param int $height Image height in pixels
 * @param int $min    Minimum threshold (default: 1)
 *
 * @return bool True if dimensions are valid
 */
function mmt_validate_dimensions( $width, $height, $min = 1 ) {
	$width  = intval( $width );
	$height = intval( $height );
	
	return $width > $min && $height > $min;
}

/**
 * Extract dimensions from filename pattern
 *
 * Looks for patterns like "filename-1920x1080.jpg" and extracts dimensions.
 *
 * @param string $filename The filename to parse
 *
 * @return array|false Array with 'width' and 'height', or false if pattern not found
 */
function mmt_extract_dimensions_from_filename( $filename ) {
	if ( preg_match( '/-(\d+)x(\d+)\.[a-z]+$/i', $filename, $matches ) ) {
		return array(
			'width'  => intval( $matches[1] ),
			'height' => intval( $matches[2] ),
		);
	}
	
	return false;
}

/**
 * Delete image variants in multiple formats
 *
 * Attempts to delete WebP, AVIF, JPEG, PNG variations of an image.
 *
 * @param string $base_path Base path without extension (e.g., /path/to/image-1920x1080)
 * @param array  $formats   Image formats to delete (default: common formats)
 *
 * @return int Number of files successfully deleted
 */
function mmt_delete_image_variants( $base_path, $formats = array() ) {
	if ( empty( $formats ) ) {
		$formats = array( 'webp', 'avif', 'png', 'jpg', 'jpeg', 'gif' );
	}
	
	$deleted = 0;
	
	foreach ( $formats as $format ) {
		$file = $base_path . '.' . strtolower( $format );
		
		if ( file_exists( $file ) ) {
			if ( wp_delete_file( $file ) ) {
				$deleted++;
			}
		}
	}
	
	return $deleted;
}

/**
 * Scan directory for image variants matching base filename
 *
 * Finds all generated image files (with dimensions in filename) for a given base.
 *
 * @param string $base_filename Base filename without path (e.g., "image-1920x1080")
 * @param string $directory    Directory to scan
 * @param array  $extensions   File extensions to match (default: common image formats)
 *
 * @return array Array of found filenames
 */
function mmt_scan_for_image_variants( $base_filename, $directory, $extensions = array() ) {
	if ( ! is_dir( $directory ) ) {
		return array();
	}
	
	if ( empty( $extensions ) ) {
		$extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' );
	}
	
	$found = array();
	
	try {
		$files = scandir( $directory );
		
		foreach ( $files as $fname ) {
			// Skip directories and hidden files
			if ( is_dir( $directory . '/' . $fname ) || '.' === $fname[0] ) {
				continue;
			}
			
			// Check if filename matches pattern: base-NxM.ext
			if ( preg_match( '/' . preg_quote( $base_filename ) . '-(\d+)x(\d+)\.[a-z]+$/i', $fname ) ) {
				$found[] = $fname;
			}
		}
	} catch ( Exception $e ) {
		return array();
	}
	
	return $found;
}

/**
 * Get attachment metadata safely
 *
 * Retrieves metadata with validation to ensure it's properly structured.
 *
 * @param int $attachment_id The attachment post ID
 *
 * @return array|false Metadata array or false if not available
 */
function mmt_get_metadata_safe( $attachment_id ) {
	$metadata = wp_get_attachment_metadata( intval( $attachment_id ) );
	
	if ( false === $metadata || ! is_array( $metadata ) ) {
		return false;
	}
	
	return $metadata;
}

/**
 * Update metadata safely
 *
 * Updates metadata using update_post_meta directly for better reliability.
 *
 * @param int   $attachment_id The attachment post ID
 * @param array $metadata      The metadata array to save
 *
 * @return bool|int Result from update_post_meta
 */
function mmt_update_metadata_safe( $attachment_id, $metadata ) {
	if ( ! is_array( $metadata ) ) {
		return false;
	}
	
	return update_post_meta( intval( $attachment_id ), '_wp_attachment_metadata', $metadata );
}

/**
 * Send AJAX success response with standard data structure
 *
 * @param string $message The success message
 * @param array  $data    Additional data to include in response
 *
 * @return void Exits with JSON response
 */
function mmt_send_ajax_success( $message, $data = array() ) {
	$response = array(
		'message' => $message,
	);
	
	// Merge additional data, but don't allow overwriting message
	unset( $data['message'] );
	$response = array_merge( $response, $data );
	
	wp_send_json_success( $response );
}

/**
 * Send AJAX error response with standard data structure
 *
 * @param string $message    The error message
 * @param string $error_code Optional error code for client-side handling
 *
 * @return void Exits with JSON error response
 */
function mmt_send_ajax_error( $message, $error_code = '' ) {
	$response = array(
		'message' => $message,
	);
	
	if ( ! empty( $error_code ) ) {
		$response['code'] = $error_code;
	}
	
	wp_send_json_error( $response );
}
