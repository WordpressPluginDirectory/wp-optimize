<?php

if (!defined('ABSPATH')) die('No direct access allowed');

/**
 * All cache commands that are intended to be available for calling from any sort of control interface (e.g. wp-admin, UpdraftCentral) go in here. All public methods should either return the data to be returned, or a WP_Error with associated error code, message and error data.
 */
class WP_Optimize_Cache_Commands {

	private $optimizer;

	private $options;

	/**
	 * WP_Optimize_Cache_Commands constructor.
	 */
	public function __construct() {
		$this->optimizer = WP_Optimize()->get_optimizer();
		$this->options = WP_Optimize()->get_options();
	}

	/**
	 * Save cache settings
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function save_cache_settings($data) {

		if (!class_exists('WPO_Cache_Config')) return array(
			'result' => false,
			'message' => "WPO_Cache_Config class doesn't exist",
		);

		// filter for validate cache settings before save it.
		$validation = apply_filters('wpo_save_cache_settings_validation', $data['cache-settings']);

		if (!empty($validation) && isset($validation['result']) && false === $validation['result']) {
			return $validation;
		}

		$enabled = false;
		$disabled = false;
		$return = empty($validation) ? array() : $validation;
		$previous_settings = WPO_Cache_Config::instance()->get();

		// Attempt to change current status if required
		if (isset($previous_settings['enable_page_caching']) && $previous_settings['enable_page_caching'] != $data['cache-settings']['enable_page_caching']) {
			// Disable cache.
			if (empty($data['cache-settings']['enable_page_caching'])) {
				$disabled = WPO_Page_Cache::instance()->disable();
				// Disabling failed
				if ($disabled && is_wp_error($disabled)) {
					// If disabling failed, we re-enable whatever was disabled, to make sure nothing breaks.
					if ($previous_settings['enable_page_caching']) WPO_Page_Cache::instance()->enable(true);
					$return['error'] = array(
						'code' => $disabled->get_error_code(),
						'message' => $disabled->get_error_message()
					);
				} elseif (WPO_Page_Cache::instance()->has_warnings()) {
					$return['warnings_label'] = __('Page caching was disabled, but with some warnings:', 'wp-optimize');
					$return['warnings'] = WPO_Page_Cache::instance()->get_errors('warning');
				}
			} else {
				// we need to rebuild advanced-cache.php and add WP_CACHE to wp-config.
				$enabled = WPO_Page_Cache::instance()->enable(true);
				// Enabling failed
				if (is_wp_error($enabled)) {
					// disable everything, to avoid half enabled things
					WPO_Page_Cache::instance()->disable();
					$return['error'] = array(
						'code' => $enabled->get_error_code(),
						'message' => $enabled->get_error_message()
					);

					if (WPO_Page_Cache::instance()->advanced_cache_file_writing_error) {
						$return['advanced_cache_file_writing_error'] = true;
						$return['advanced_cache_file_content'] = WPO_Page_Cache::instance()->advanced_cache_file_content;
					}
				} elseif (WPO_Page_Cache::instance()->has_warnings()) {
					$return['warnings_label'] = __('Page caching was enabled, but with some warnings:', 'wp-optimize');
					$return['warnings'] = WPO_Page_Cache::instance()->get_errors('warning');
				}
			}
			// Override enabled setting value
			$data['cache-settings']['enable_page_caching'] = ($enabled && !is_wp_error($enabled)) || ($previous_settings['enable_page_caching'] && is_wp_error($disabled));
		} else {
			$data['cache-settings']['enable_page_caching'] = $previous_settings['enable_page_caching'];
			$enabled = $previous_settings['enable_page_caching'];
		}

		$data['cache-settings']['use_webp_images'] = (bool) WP_Optimize()->get_options()->get_option('webp_conversion');

		$skip_if_no_file_yet = !$enabled || is_wp_error($enabled);
		$save_settings_result = WPO_Cache_Config::instance()->update($data['cache-settings'], $skip_if_no_file_yet);

		if ($save_settings_result && !is_wp_error($save_settings_result)) {
			WP_Optimize_Page_Cache_Preloader::instance()->cache_settings_updated($data['cache-settings'], $previous_settings);
			$return['result'] = $save_settings_result;
		} else {
			// Saving the settings returned an error
			if (is_wp_error($save_settings_result)) {
				if (isset($return['error'])) {
					$return['error']['message'] .= "\n\n".$save_settings_result->get_error_message();
				} else {
					$return['error'] = array(
						'code' => $save_settings_result->get_error_code(),
						'message' => $save_settings_result->get_error_message()
					);
				}
			}
			$return['result'] = false;
		}

		$return['enabled'] = ($enabled && !is_wp_error($enabled)) || ($previous_settings['enable_page_caching'] && is_wp_error($disabled));

		if ($enabled && !is_wp_error($enabled)) {
			$options_needs_purge = array_flip(array(
				'enable_mobile_caching',
				'enable_user_caching',
				'enable_per_role_cache',
				'enable_user_specific_cache',
				'enable_rest_caching',
				'show_avatars',
				'host_gravatars_locally',
				'cache_exception_urls',
				'cache_ignore_query_variables',
				'cache_exception_cookies',
				'cache_exception_conditional_tags',
				'cache_exception_browser_agents',
			));
			$previous_settings = array_intersect_key($previous_settings, $options_needs_purge);
			$current_settings = wp_parse_args($data['cache-settings'], WPO_Cache_Config::instance()->defaults);
			$current_settings = array_intersect_key($current_settings, $options_needs_purge);
			ksort($previous_settings);
			ksort($current_settings);
			if ($current_settings !== $previous_settings) {
				$cache = WPO_Page_Cache::instance();
				$cache->file_log('Setting changed, purging cache');
				$cache->purge();
			}
		}
		// $disable can either be boolean or WP_Error
		if (!is_wp_error($disabled) && $disabled) {
			WPO_Page_Cache::instance()->prune_cache_logs();
			wp_clear_scheduled_hook('wpo_prune_cache_logs');
		}
		
		return $return;
	}

	/**
	 * Get information about current cache status. Used in cli commands.
	 *
	 * @return array
	 */
	public function get_status_info() {
		$status = array();

		$status[] = WPO_Page_Cache::instance()->is_enabled() ? __('Caching is enabled', 'wp-optimize') : __('Caching is disabled', 'wp-optimize');

		$preloader_status = WP_Optimize_Page_Cache_Preloader::instance()->get_status_info();
		// translators: %s: The current cache size
		$status[] = sprintf(__('Current cache size: %s', 'wp-optimize'), $preloader_status['size']);
		// translators: %s: The number of files in the cache
		$status[] = sprintf(__('Number of files: %s', 'wp-optimize'), $preloader_status['file_count']);

		if (array_key_exists('message', $preloader_status)) $status[] = $preloader_status['message'];

		$status['message'] = join(PHP_EOL, $status);

		return $status;
	}

	/**
	 * Enable cache.
	 */
	public function enable() {
		$settings = WPO_Cache_Config::instance()->get();
		$settings['enable_page_caching'] = true;
		return $this->format_save_cache_settings_response($this->save_cache_settings(array('cache-settings' => $settings)));
	}

	/**
	 * Disable cache.
	 */
	public function disable() {
		$settings = WPO_Cache_Config::instance()->get();
		$settings['enable_page_caching'] = false;
		return $this->format_save_cache_settings_response($this->save_cache_settings(array('cache-settings' => $settings)));
	}

	/**
	 * Purge WP-Optimize page cache.
	 *
	 * @return array
	 */
	public function purge_page_cache() {

		$wpo_page_cache = WP_Optimize()->get_page_cache();
		
		if (!$wpo_page_cache->is_enabled()) {
			return array(
				'success' => false,
				'error' => __('Cache is not enabled.', 'wp-optimize'),
			);
		}
		
		if (!$wpo_page_cache->can_purge_cache() && !(defined('WP_CLI') && WP_CLI)) {
			return array(
				'success' => false,
				'message' => __('You do not have permission to purge the cache', 'wp-optimize'),
			);
		}

		$purged = WP_Optimize()->get_page_cache()->purge();
		if ($purged) WP_Optimize()->get_page_cache()->file_log("Full Cache Purge triggered by: " . __METHOD__);
		$cache_size = WP_Optimize()->get_page_cache()->get_cache_size();
		$wpo_page_cache_preloader = WP_Optimize_Page_Cache_Preloader::instance();

		$response = array(
			'success' => $purged,
			'size' => WP_Optimize()->format_size($cache_size['size']),
			'file_count' => $cache_size['file_count'],
		);

		// if scheduled preload enabled then reschedule and run preloader.
		if ($wpo_page_cache_preloader->is_scheduled_preload_enabled()) {
			// cancel preload and reschedule preload action.
			$wpo_page_cache_preloader->cancel_preload();
			$wpo_page_cache_preloader->reschedule_preload();

			// run preloader.
			$wpo_page_cache_preloader->run('scheduled', $response);
		} elseif ($wpo_page_cache->should_auto_preload_purged_contents()) {
			// When auto preload is enabled but scheduled preload is disabled
			$wpo_page_cache_preloader->run('manual', $response);
		}

		if ($response['success']) {
			$response['message'] = __('Page cache purged successfully', 'wp-optimize');
		}

		return $response;
	}

	/**
	 * Run cache preload (for wp-cli).
	 *
	 * @return array|bool
	 */
	public function run_cache_preload_cli() {
		if (!(defined('WP_CLI') && WP_CLI)) return false;

		// define WPO_ADVANCED_CACHE constant as WP-CLI doesn't load advanced-cache.php file
		// but we check this constant value wen detecting status of cache
		if (!defined('WPO_ADVANCED_CACHE')) define('WPO_ADVANCED_CACHE', true);
		// don't interrupt queue processing
		add_filter('updraft_interrupt_tasks_queue_load-url-task', '__return_false', 99);

		// if preloading is running then exit.
		if (WP_Optimize_Page_Cache_Preloader::instance()->is_busy()) {
			return array(
				'success' => false,
				'error' => __('Preloading is currently running in another process.', 'wp-optimize'),
			);
		}

		// set default response.
		$response = array(
			'success' => true,
			'message' => __('All URLs were preloaded into cache successfully', 'wp-optimize'),
		);

		WP_CLI::log(__('Preloading URLs into cache...', 'wp-optimize'));

		return WP_Optimize_Page_Cache_Preloader::instance()->run('manual', $response);
	}

	/**
	 * Run cache preload action.
	 *
	 * @return void|array - Doesn't return anything if run() is successful (Run() prints a JSON object and closed browser connection) or an array if failed.
	 */
	public function run_cache_preload() {
		return WP_Optimize_Page_Cache_Preloader::instance()->run('manual');
	}

	/**
	 * Cancel cache preload action.
	 *
	 * @return array
	 */
	public function cancel_cache_preload() {
		WP_Optimize_Page_Cache_Preloader::instance()->cancel_preload();
		return WP_Optimize_Page_Cache_Preloader::instance()->get_status_info();
	}

	/**
	 * Get status of cache preload.
	 *
	 * @return array
	 */
	public function get_cache_preload_status() {
		return WP_Optimize_Page_Cache_Preloader::instance()->get_status_info();
	}

	/**
	 * Enable or disable browser cache.
	 *
	 * @param array $params - ['browser_cache_expire' => '1 month 15 days 2 hours' || '' - for disable cache]
	 * @return array
	 */
	public function enable_browser_cache($params) {
		return WP_Optimize()->get_browser_cache()->enable_browser_cache_command_handler($params);
	}

	/**
	 * Format save_cache_settings() result for displaying in WP-CLI console
	 *
	 * @param array $response
	 * @return array
	 */
	private function format_save_cache_settings_response($response) {
		$result = array(
			'success' => $response['result'],
		);

		if (isset($response['error'])) {
			$result['success'] = false;
			$result['error'] = $response['error']['message'];
		}

		if ($result['success']) {
			$result['message'] = __('Page cache settings updated successfully.', 'wp-optimize');
		}

		return $result;
	}

	/**
	 * Enable or disable auto preloading after cache purge
	 *
	 * @param array $params Option value sent via AJAX
	 * @return array
	 */
	public function save_cache_auto_preload_option($params) {
		$wpo_cache = WP_Optimize()->get_page_cache();
		$wpo_cache_options = $wpo_cache->config->get();
		$wpo_cache_options['auto_preload_purged_contents'] = 'true' === $params['enabled'] ? 1 : 0;
		$update_status = $wpo_cache->config->update($wpo_cache_options);

		if (is_wp_error($update_status)) {
			$result = array(
				'success' => false,
				'message' => $update_status->get_error_message(),
			);
		} else {
			$result = array(
				'success' => true,
				'message' => __('Auto preload settings have been updated successfully', 'wp-optimize'),
			);
		}

		return $result;
	}
}
