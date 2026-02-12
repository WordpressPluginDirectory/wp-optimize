<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

if (!class_exists('WPO_WebP_Test_Run')) :
	/**
	 * Test run
	 */
class WPO_WebP_Test_Run {

	/**
	 * List of working coverters
	 *
	 * @var array
	 */
	private static $working_converters = array();

	/**
	 * Errors array
	 *
	 * @var array
	 */
	private static $errors = array();

	/**
	 * Get a list of converters that don't use shell functions
	 *
	 * @return array
	 */
	public static function get_converters_without_shell() {
		return apply_filters('wpo_converters_without_shell', array(
			'vips',
			'wpc',
			'ewww',
			'imagick',
			'gmagick',
			'gd',
		));
	}

	/**
	 * Get a list of converters that use shell functions
	 *
	 * @return array
	 */
	public static function get_converters_with_shell() {
		return apply_filters('wpo_converters_with_shell', array(
			// 'cwebp',
			'imagemagick',
			'graphicsmagick',
			'ffmpeg',
		));
	}

	/**
	 * Get an array of working and non-working converters list
	 *
	 * @return array
	 */
	public static function get_converter_status() {

		$converters_without_shell = self::get_converters_without_shell();
		$converters_with_shell = self::get_converters_with_shell();

		self::$working_converters = array();
		self::$errors = array();

		self::try_converters($converters_without_shell);

		if (empty(self::$working_converters)) {
			if (WP_Optimize_WebP::get_instance()->shell_functions_available()) {
				self::try_converters($converters_with_shell);
			} else {
				// If no working converters that don’t require shell access are found,
				// and shell functions are unavailable, store the errors.
				foreach ($converters_with_shell as $converter_id) {
					self::$errors[$converter_id] = __('Required WebP shell functions are not available on the server.', 'wp-optimize');
				}
			}
		}

		return array(
			'working_converters' => self::$working_converters,
			'errors' => self::$errors,
		);
	}

	/**
	 * Tries each converter from the list to convert to WebP
	 *
	 * @param array $converters
	 * @return void
	 */
	private static function try_converters($converters) {
		$source = WPO_PLUGIN_MAIN_PATH . 'images/logo/wpo_logo_small.png';
		$upload_dir = wp_upload_dir();
		$destination =  $upload_dir['basedir']. '/wpo/images/wpo_logo_small.png.webp';

		foreach ($converters as $converter_id) {
			$conversion_result = self::try_converter($converter_id, $source, $destination, $upload_dir['basedir']);

			if (true === $conversion_result) {
				self::$working_converters[] = $converter_id;
			} else {
				self::$errors[$converter_id] = $conversion_result;
			}
		}
	}

	/**
	 * Tries to convert to WebP and returns true if the conversion finishes successfully.
	 *
	 * @param string $converter_id
	 * @param string $source
	 * @param string $destination
	 * @param string $target_dir
	 * @return true|string
	 */
	private static function try_converter($converter_id, $source, $destination, $target_dir) {
		try {
			WPO_WebP_Utils::perform_webp_conversion($converter_id, $source, $destination);
			// Copying source file to `uploads` folder. To be used test redirection
			// We're doing it here, to make sure folders already exists `/wpo/images/`
			copy($source, $target_dir. '/wpo/images/wpo_logo_small.png');
			return true;
		} catch (\Exception $e) {
			return $e->getMessage();
		}
	}
}

endif;
