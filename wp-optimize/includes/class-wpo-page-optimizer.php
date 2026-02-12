<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('WPO_Page_Optimizer')) :
class WPO_Page_Optimizer {

	/**
	 * Instance of this class
	 *
	 * @var null|WPO_Page_Optimizer
	 */
	private static $instance = null;

	/**
	 * Constructor
	 */
	private function __construct() {
		// Initialize WP_Optimize_Minify_Config to make available wp_optimize_minify_config()
		WP_Optimize_Minify_Config::get_instance();
		// Include page cache function to make available wpo_is_cacheable_sitemap_request() function
		$this->include_page_cache_functions();
	}

	/**
	 * Get the buffer and perform tasks related to page optimization
	 *
	 * @param  string $buffer Page HTML.
	 * @param  int    $flags  OB flags to be passed through.
	 *
	 * @return string
	 */
	private function optimize($buffer, $flags) {
	
		$buffer = apply_filters('wp_optimize_buffer', $buffer, $flags);
		$buffer = $this->maybe_host_google_fonts_locally($buffer);
		$buffer = $this->maybe_remove_unused_css($buffer);
		$buffer = $this->maybe_apply_capojs_rules($buffer);
		$buffer = $this->maybe_cache_page($buffer, $flags);

		return $buffer;
	}

	/**
	 * Cache the page if the page cache enabled
	 *
	 * @param  string $buffer Page HTML.
	 * @param  int    $flags  OB flags to be passed through.
	 *
	 * @return string
	 */
	private function maybe_cache_page($buffer, $flags) {

		if (!$this->is_wp_cli() && WP_Optimize()->get_page_cache()->should_cache_page()) {
			return wpo_cache($buffer, $flags);
		}

		return $buffer;
	}

	/**
	 * Optimize head tags sequence to make web page load optimally
	 *
	 * @param string $buffer source HTML page
	 * @return string
	 */
	private function maybe_apply_capojs_rules($buffer) {

		if (WP_Optimize::is_premium() && WP_Optimize_CapoJS_Rules::get_instance()->should_apply_capojs_rules($buffer)) {
			$buffer = WP_Optimize_CapoJS_Rules::get_instance()->optimize($buffer);
		}
		return $buffer;
	}

	/**
	 * Hosts Google Fonts locally from the provided HTML and the CSS files included in it, when the option is enabled.
	 *
	 * @param string $buffer
	 * @return string
	 */
	private function maybe_host_google_fonts_locally(string $buffer): string {
		if (WP_Optimize::is_premium() && wp_optimize_minify_config()->get('host_local_google_fonts')) {
			$buffer = WP_Optimize_Host_Google_Fonts::instance()->update_gfont_urls_in_html($buffer);
		}

		return $buffer;
	}
	
	/**
	 * Remove unused css
	 *
	 * @param String $buffer Page HTML.
	 *
	 * @return String
	 */
	private function maybe_remove_unused_css($buffer) {
		
		if (is_user_logged_in()) return $buffer;
		
		if (WP_Optimize::is_premium() && wp_optimize_minify_config()->get('enable_unused_css')) {
			$unused_css_class = WP_Optimize_Minify_Unused_Css::get_instance();
			return $unused_css_class->remove_unused_css($buffer);
		}
		return $buffer;
	}

	/**
	 * Check if we should initialise page optimizer.
	 *
	 * @return boolean
	 */
	private function should_initialise() {
		// Skip admin, AJAX, WP-CLI, cron, and page builder edit modes.
		if (is_admin() || $this->is_ajax() || $this->is_wp_cli() || $this->is_cron_job() || $this->is_static_asset_request() || WPO_Page_Builder_Compatibility::instance()->is_edit_mode()) {
			return false;
		}

		return true;
	}

	/**
	 * Initialise output buffer handler.
	 *
	 * @return void
	 */
	private function initialise() {
		ob_start(array(self::$instance, 'optimize'));
	}

	/**
	 * Maybe initialise page optimizer.
	 *
	 * @return void
	 */
	public function maybe_initialise() {
		if ($this->should_initialise()) {
			$this->initialise();
		}
	}

	/**
	 * Checks if the current execution context is WP-CLI.
	 *
	 * @return bool
	 */
	private function is_wp_cli() {
		return defined('WP_CLI') && WP_CLI;
	}

	/**
	 * Checks if the current execution context is ajax request.
	 *
	 * @return boolean
	 */
	private function is_ajax() {
		return defined('DOING_AJAX') && DOING_AJAX;
	}

	/**
	 * Checks if the current execution context is cron job request.
	 *
	 * @return boolean
	 */
	private function is_cron_job() {
		return defined('DOING_CRON') && DOING_CRON;
	}

	/**
	 * Checks if the current request is for a static asset.
	 *
	 * @return boolean
	 */
	private function is_static_asset_request() {
		if (wpo_is_cacheable_sitemap_request()) return false;

		// Skip non-HTML requests (like .css, .js, .map, .ico, images, fonts, .htaccess, etc.)
		$path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH) : false;
		if ($path) {
			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			$skip_exts = array(
				'css',
				'js',
				'json',
				'map',
				'ico',
				'png',
				'jpg',
				'jpeg',
				'gif',
				'webp',
				'svg',
				'woff',
				'woff2',
				'ttf',
				'otf',
				'eot',
				'xml',
				'txt',
				'pdf',
				'zip',
				'rar',
				'htaccess',
			);

			if (in_array($ext, $skip_exts, true)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns singleton instance of WPO_Page_Optimizer
	 *
	 * @return WPO_Page_Optimizer
	 */
	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Includes file-based-page-cache-functions.php
	 *
	 * @return void
	 */
	private function include_page_cache_functions() {
		require_once WPO_PLUGIN_MAIN_PATH . '/cache/file-based-page-cache-functions.php';
	}
}

endif;
