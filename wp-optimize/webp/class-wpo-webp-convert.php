<?php

if (!defined('WPO_VERSION')) die('No direct access allowed');

if (!class_exists('WPO_WebP_Convert')) :

class WPO_WebP_Convert {

	public $converters = null;

	public function __construct() {
		$this->converters = WP_Optimize()->get_options()->get_option('webp_converters');
	}

	/**
	 * Converts uploaded image to webp format
	 *
	 * @param string $source - path of the source file
	 */
	public function convert($source) {
		if (count($this->converters) < 1) return false;

		$destination = $this->get_destination_path($source);
		$this->check_converters_and_do_conversion($source, $destination);
	}

	/**
	 * Returns the destination full path
	 *
	 * @param string $source - path of the source file
	 *
	 * @return string $destination - path of destination file
	 */
	protected function get_destination_path($source) {
		$path_parts = pathinfo($source);
		$destination =   $path_parts['dirname'] . '/'. basename($source) . '.webp';
		return $destination;
	}

	/**
	 * Loop through available converters and do the conversion
	 *
	 * @param string $source      - path of source file
	 * @param string $destination - path of destination file
	 */
	protected function check_converters_and_do_conversion($source, $destination) {
		foreach ($this->converters as $converter) {
			WPO_WebP_Utils::perform_webp_conversion($converter, $source, $destination);
			break;
		}
	}
}
endif;
