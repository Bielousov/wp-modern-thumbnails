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
