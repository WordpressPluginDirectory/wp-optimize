<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * Handles cache configuration and related I/O
 */

if (!class_exists('WPO_Cache_Config')) :

class WPO_Cache_Config {

	/**
	 * Defaults
	 *
	 * @var array
	 */
	public $defaults;

	/**
	 * Instance of this class
	 *
	 * @var mixed
	 */
	public static $instance;

	/**
	 * @var array
	 */
	public $config;


	/**
	 * Set config defaults
	 */
	public function __construct() {
		$this->defaults = $this->get_defaults();
	}

	/**
	 * Get config from file or cache
	 *
	 * @return array
	 */
	public function get() {

		if (is_multisite()) {
			$config = get_site_option('wpo_cache_config', $this->defaults);
		} else {
			$config = get_option('wpo_cache_config', $this->defaults);
		}

		return wp_parse_args($config, $this->defaults);
	}

	/**
	 * Get a specific configuration option
	 *
	 * @param string  $option_key The option identifier
	 * @param boolean $default    Default value if the option doesn't exist (Default to false)
	 * @return mixed
	 */
	public function get_option($option_key, $default = false) {
		$options = $this->get();
		return apply_filters("wpo_option_key_{$option_key}", (isset($options[$option_key]) ? $options[$option_key] : $default));
	}

	/**
	 * Updates the given config object in file and DB
	 *
	 * @param array	  $config						- the cache configuration
	 * @param boolean $skip_disk_if_not_yet_present - only write the configuration file to disk if it already exists. This presents PHP notices if the cache has never been on, and settings are saved.
	 *
	 * @return true|WP_Error                        - returns true on success or WP_Error if the config cannot be written to disk
	 */
	public function update($config, $skip_disk_if_not_yet_present = false) {
		$config = wp_parse_args($config, $this->defaults);

		$config['page_cache_length_value'] = intval($config['page_cache_length_value']);
		$config['page_cache_length'] = $this->calculate_page_cache_length($config['page_cache_length_value'], $config['page_cache_length_unit']);
		
		$fields_to_trim = array('cache_exception_conditional_tags', 'cache_exception_urls', 'cache_ignore_query_variables', 'cache_exception_cookies', 'cache_exception_browser_agents');
		foreach ($fields_to_trim as $field) {
			if (!empty($config[$field]) && is_array($config[$field])) {
				$config[$field] = array_map('trim', $config[$field]);
			}
		}

		/**
		 * Filters the cookies used to set cache file names
		 *
		 * @param array $cookies - The cookies
		 * @param array $config  - The new config
		 */
		$wpo_cache_cookies = apply_filters('wpo_cache_cookies', array(), $config);
		sort($wpo_cache_cookies);

		/**
		 * Filters the query variables used to set cache file names
		 *
		 * @param array $wpo_query_variables - The variables
		 * @param array $config              - The new config
		 */
		$wpo_query_variables = apply_filters('wpo_cache_query_variables', array(), $config);
		sort($wpo_query_variables);

		$config['wpo_cache_cookies'] = $wpo_cache_cookies;
		$config['wpo_cache_query_variables'] = $wpo_query_variables;
		
		$config = apply_filters('wpo_cache_update_config', $config);

		if (is_multisite()) {
			update_site_option('wpo_cache_config', $config);
		} else {
			update_option('wpo_cache_config', $config);
		}

		do_action('wpo_cache_config_updated', $config);

		return $this->write($config, $skip_disk_if_not_yet_present);
	}

	/**
	 * Calculate cache expiration value in seconds.
	 *
	 * @param int    $value
	 * @param string $unit  ( hours | days | months )
	 *
	 * @return int
	 */
	private function calculate_page_cache_length($value, $unit) {
		$cache_length_units = array(
			'hours' => 3600,
			'days' => 86400,
			'months' => 2629800, // 365.25 * 86400 / 12
		);

		return $value * $cache_length_units[$unit];
	}

	/**
	 * Deletes config files and options
	 *
	 * @return bool
	 */
	public function delete() {

		if (is_multisite()) {
			delete_site_option('wpo_cache_config');
		} else {
			delete_option('wpo_cache_config');
		}
		
		if (!WPO_Page_Cache::delete(WPO_CACHE_CONFIG_DIR)) {
			return false;
		}

		return true;
	}

	/**
	 * Writes config to file
	 *
	 * @param array	  $config		   - Configuration array.
	 * @param boolean $only_if_present - only writes to the disk if the configuration file already exists
	 *
	 * @return true|WP_Error           - returns true on success or WP_Error if an attempt to write failed
	 */
	public function write($config, $only_if_present = false) {

		$config_file = $this->get_config_file_path();

		$this->config = wp_parse_args($config, $this->defaults);

		// from 3.0.17 we use more secure way to store cache config files.
		$advanced_cache_version = WPO_Page_Cache::instance()->get_advanced_cache_version();
		// if advanced-cache.php exists and has at least 3.0.17 version or
		// advanced-cache.php doesn't exist then
		// we write the cache config in a new format.
		if (($advanced_cache_version && (version_compare($advanced_cache_version, '3.0.17', '>='))) || !$advanced_cache_version) {
			// Apply the encoding required for placing within PHP single quotes - https://www.php.net/manual/en/language.types.string.php#language.types.string.syntax.single
			$json_encoded_string = str_replace(array('\\', "'"), array('\\\\', '\\\''), wp_json_encode($this->config));

			$config_content = '<?php' . "\n"
				. 'if (!defined(\'ABSPATH\')) die(\'No direct access allowed\');' . "\n\n"
				. '$GLOBALS[\'wpo_cache_config\'] = json_decode(\'' . $json_encoded_string . '\', true);' . "\n";
		} else {
			$config_content = wp_json_encode($this->config);
		}

		if ((!$only_if_present || file_exists($config_file)) && (!wp_is_writable(WPO_CACHE_CONFIG_DIR) || !file_put_contents($config_file, $config_content))) {
			// translators: %s is the path to the cache config file
			return new WP_Error('write_cache_config', sprintf(__('The cache configuration file could not be saved to the disk; please check the file/folder permissions of %s .', 'wp-optimize'), $config_file));
		}

		return true;
	}

	/**
	 * Get config file name with full path.
	 *
	 * @return string
	 */
	public function get_config_file_path() {
		return WPO_CACHE_CONFIG_DIR . '/' . $this->get_cache_config_filename();
	}
	
	/**
	 * Return defaults
	 *
	 * @return array
	 */
	public function get_defaults() {
		
		$defaults = array(
			'enable_page_caching'              => false,
			'page_cache_length_value'          => 24,
			'page_cache_length_unit'           => 'hours',
			'page_cache_length'                => 86400,
			'cache_exception_conditional_tags' => array(),
			'cache_exception_urls'             => array(),
			'cache_ignore_query_variables' 	   => array(),
			'cache_exception_cookies'          => array(),
			'cache_exception_browser_agents'   => array(),
			'enable_sitemap_preload'           => false,
			'enable_schedule_preload'          => false,
			'preload_schedule_type'            => '',
			'enable_mobile_caching'            => false,
			'enable_user_caching'              => false,
			'site_url'                         => network_home_url('/'),
			'enable_cache_per_country'         => false,
			'enable_cache_aelia_currency'      => false,
			'permalink_structure'              => get_option('permalink_structure'),
			'uploads'                          => wp_normalize_path(wp_upload_dir()['basedir']),
			'gmt_offset'                       => get_option('gmt_offset'),
			'timezone_string'                  => get_option('timezone_string'),
			'date_format'                      => get_option('date_format'),
			'time_format'                      => get_option('time_format'),
			'use_webp_images'                  => false,
			'show_avatars'                     => 0,
			'host_gravatars_locally'           => 0,
			'auto_preload_purged_contents'     => true
		);

		return apply_filters('wpo_cache_defaults', $defaults);
	}

	/**
	 * Get advanced-cache.php file name with full path.
	 *
	 * @return string
	 */
	public function get_cache_config_filename() {
		$url = wp_parse_url(network_site_url());

		if (isset($url['port']) && '' != $url['port'] && 80 != $url['port']) {
			return 'config-'.strtolower($url['host']).'-port'.$url['port'].'.php';
		} else {
			return 'config-'.strtolower($url['host']).'.php';
		}
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  1.0
	 * @return WPO_Cache_Config
	 */
	public static function instance() {

		if (!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
endif;
