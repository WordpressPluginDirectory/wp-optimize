<?php

if (!defined('ABSPATH')) die('Access denied.');

if (!class_exists('Updraft_Task_Manager_1_4')) require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/common-libs/src/updraft-tasks/class-updraft-task-manager.php');

if (!class_exists('WPO_Webp_Task_Manager')) :

class WPO_Webp_Task_Manager extends Updraft_Task_Manager_1_4 {

	/**
	 * @var WPO_Webp_Task_Manager
	 */
	static protected $_instance = null;

	/**
	 * Logs a message using the WebP optimization instance.
	 *
	 * @param string $message    The message to log.
	 * @param string $error_type Optional. The type of error. Default 'info'.
	 */
	public function log($message, $error_type = 'info') {
		$webp_instance = WP_Optimize()->get_webp_instance();
		$webp_instance->log($message, $error_type);
	}

	/**
	 * Instance of WP_Optimize_Webp_Task_Manager.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if (empty(self::$_instance)) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Convert already compressed images to webp format
	 */
	public function webp_convert_compressed_images() {
		$task_type = 'webp-convert-compressed-images-task';

		$creating_tasks_semaphore = new Updraft_Semaphore_3_0('wpo_' . $task_type);
		$lock = $creating_tasks_semaphore->lock();
		if (!$lock) return;

		if (is_multisite()) {
			$sites = WP_Optimize()->get_sites();
			foreach ($sites as $site) {
				$blog_id = $site->blog_id;
				switch_to_blog($blog_id);
				$this->create_webp_convert_compressed_image_task($task_type, $blog_id);
				restore_current_blog();
			}
		} else {
			$this->create_webp_convert_compressed_image_task($task_type, 1);
		}

		$creating_tasks_semaphore->release();

		$this->process_queue($task_type);
	}

	/**
	 * Creates tasks for converting compressed images to WebP format
	 *
	 * @param string $task_type The type identifier for the conversion task
	 * @param int    $blog_id   The ID of the blog/site where the images should be converted
	 *
	 * @return void
	 */
	private function create_webp_convert_compressed_image_task(string $task_type, int $blog_id) {
		$this->clean_up_old_tasks($task_type);
		$images = $this->get_compressed_images_to_convert();
		foreach ($images as $image) {
			$blog_info = ', Blog ID : '. $blog_id;
			$description = 'Webp Conversion of Compressed Image with ID - ' . $image['post_id'] . $blog_info;
			$options = array(
				'attachment_id' => $image['post_id'],
				'blog_id' => $blog_id,
				'attachment_source' => $image['source'],
				'anonymous_user_allowed' => (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)
			);
			WPO_Webp_Convert_Image_Task::create_task($task_type, $description, $options);
		}
	}

	/**
	 * Get compressed images to convert
	 *
	 * @return array[]  Array of arrays containing:
	 *                  'post_id' (int) The ID of the attachment post
	 *                  'source' (string) The file path of the attachment
	 */
	private function get_compressed_images_to_convert(): array {
		$args =  array(
			'meta_query' => $this->get_compressed_images_meta_query(),
			'post_type' => 'attachment',
			'numberposts' => 50,
		);

		$page = 1;
		$webp_converter = new WPO_WebP_Convert();
		$posts = 0;
		$query_is_not_empty = true;
		$filtered_post_ids = array();
		while ($posts < 50 && $query_is_not_empty) {
			$args['paged'] = $page;
			$query = get_posts($args);
			if (empty($query)) $query_is_not_empty = false;

			foreach ($query as $post) {
				$source = get_attached_file($post->ID);
				if (false === $source) continue;
				$destination = $webp_converter->get_destination_path($source);
				if (file_exists($destination)) {
					// A way of backfilling already webp converted images
					update_post_meta($post->ID, 'wpo-webp-conversion-complete', true);
					continue;
				}

				$filtered_post_ids[] = array(
					'post_id' => $post->ID,
					'source'  => $source
				);
				$posts++;
			}
			$page++;
		};

		return $filtered_post_ids;
	}

	/**
	 * Meta query array for getting compressed images
	 *
	 * @return array
	 */
	private function get_compressed_images_meta_query(): array {
		return array(
			'relation' => 'AND',
			array(
				'key'	 => 'smush-complete',
				'compare' => '=',
				'value'   => '1',
			),
			// Check if the image is not already converted to webp
			array(
				'relation' => 'OR',
				array(
					'key'	 => 'wpo-webp-conversion-complete',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
				array(
					'key'	 => 'wpo-webp-conversion-complete',
					'compare' => '!=',
					'value'   => '1',
				)
			),
		);
	}
}
endif;
