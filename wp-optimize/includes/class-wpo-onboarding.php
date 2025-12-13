<?php

use Updraftplus\Wp_Optimize\Wizard\Onboarding\Onboarding;

if (!defined('ABSPATH')) die('No direct access allowed');

require_once(WPO_PLUGIN_MAIN_PATH . 'vendor/team-updraft/lib-onboarding-wizard/autoload.php');

/**
 * Class WPO_Onboarding
 */
class WPO_Onboarding {

	const MAILING_LIST_FREE_ID    = 132;

	const MAILING_LIST_PREMIUM_ID = 133;

	const MAILING_LIST_ENDPOINT   = 'https://teamupdraft.com/?fluentcrm=1&route=contact&hash=69902751-58c5-460b-bd9f-456d62033c2b';

	private $is_premium = false;

	private $prefix = 'wp-optimize';

	private $caller_slug = 'wp-optimize';

	private $upgrade_url = '';

	private $is_multisite;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->is_premium = WP_Optimize::is_premium();
		$this->is_multisite = is_multisite();

		if ($this->is_multisite) {
			$this->prefix = 'wp-optimize-mu';
		}
		$this->upgrade_url = WP_Optimize()->premium_version_link;
	}

	/**
	 * Get a singleton instance
	 *
	 * @return WPO_Onboarding
	 */
	public static function instance(): WPO_Onboarding {
		static $instance = null;
		if (null === $instance) {
			$instance = new self();
		}
		return $instance;
	}

	/**
	 * Prevent cloning of the instance
	 */
	private function __clone() {
	}

	/**
	 * Prevent unserializing of the instance
	 */
	public function __wakeup() {
		throw new Exception("Cannot unserialize singleton");
	}

	/**
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function init(): void {

		add_action( $this->prefix.'_onboarding_update_options', array($this, 'update_step_settings'), 10, 2 );
		add_filter( $this->prefix.'_onboarding_steps', array($this, 'load_steps'));

		$this->setup_onboarding();
	}

	/**
	 * Get Steps Ids from a step fields array
	 *
	 * @param array $step_fields Step Fields data
	 *
	 * @return array
	 */
	private function get_step_ids(array $step_fields): array {
		$step_ids = array();
		if (!empty($step_fields)) {
			foreach ($step_fields as $step) {
				$step_ids[] = $step['id'];
			}
		}

		return $step_ids;
	}

	/**
	 * Get Features Status
	 *
	 * @param array $settings    Settings data
	 * @param array $step_fields Step Fields data
	 *
	 * @return void
	 */
	public function update_step_settings(array $settings, array $step_fields): void {
		$steps = $this->get_step_ids($step_fields);

		if (empty($settings)) {
			return;
		}
		foreach ($settings as $setting) {
			if (empty($setting['id'])) continue;

			if (!in_array($setting['id'], $steps)) continue;

			$is_lock = isset($setting['is_lock']) ? (bool) $setting['is_lock'] : false;
			if ($is_lock) {
				continue;
			}

			$id = (string) $setting['id'];
			$value = isset($setting['value']) ? (bool) $setting['value'] : false;

			if ('enable_caching_onboarding' === $id) {
				$wpo_page_cache = WP_Optimize()->get_page_cache();

				if (true === $value) {
					$cache_operation_success = $wpo_page_cache->enable(true);
				} else {
					$cache_operation_success = $wpo_page_cache->disable();
				}

				if (true === $cache_operation_success) {
					$wpo_cache_config                    = WPO_Cache_Config::instance();
					$cache_config                        = $wpo_cache_config->get();
					$cache_config['enable_page_caching'] = $value;
					$wpo_cache_config->update($cache_config);
				}
			}

			if ('enable_minify_onboarding' === $id) {
				wp_optimize_minify_config()->update(array('enabled' => $value));
			}

			if ('enable_image_compression_onboarding' === $id) {
				Updraft_Smush_Manager()->update_smush_options(array('autosmush' => $value));
			}

			if ('enable_webp_conversion_onboarding' === $id) {
				$smush_settings = array();
				$smush_settings['webp_conversion'] = $value;
				WP_Optimize()->get_task_manager()->commands->update_webp_options($smush_settings);
			}

			if ('enable_lazy_load_onboarding' === $id) {
				$lazy_settings = array();
				$lazy_settings['lazyload'] = array(
					'images'          =>  $value,
					'iframes'         =>  $value,
					'backgrounds'     =>  $value,
					'youtube_preview' =>  $value,
					'skip_classes'    => '',
				);
				WP_Optimize()->get_options()->save_lazy_load_settings($lazy_settings);
			}

			if ('enable_image_dimensions_onboarding' === $id) {
				WP_Optimize()->get_options()->update_option('image_dimensions', (int) $value);
			}
		}
	}

	/**
	 * Load the steps for the onboarding process.
	 *
	 * Builds and returns the steps depending on the user's
	 * premium status and license connection.
	 *
	 * @param array $steps Unused parameter for compatibility.
	 *
	 * @return array Ordered an array of steps for the onboarding wizard.
	 */
	public function load_steps($steps = array()): array {
		// Build steps
		$steps = array(); // To override all previous steps.

		// Step 1: Intro
		$steps[] = $this->intro_step();

		// Step 2: License step (only for premium users without a connected license)
		if ($this->should_add_license_step()) {
			$steps[] = $this->license_step();
		}

		// Step 3: Feature settings
		$steps[] = $this->features_step();

		// Step 4: Newsletter signup
		$steps[] = $this->newsletter_step();

		// Step 5: Recommended plugins
		$steps[] = $this->plugins_install_step();

		// Step 6: Go Premium step (only for non-premium users)
		if (!$this->is_premium) {
			$steps[] = $this->go_premium_step();
		}

		// Step 7: Final step
		$steps[] = $this->last_step();

		return $steps;
	}

	/**
	 * Determine if the license step should be added.
	 *
	 * @return bool True if the plugin is premium but the license is not connected.
	 */
	private function should_add_license_step(): bool {
		return $this->is_premium && !$this->is_license_connected();
	}

	/**
	 * Build the intro step for the onboarding wizard.
	 *
	 * This step introduces the plugin to the user, displays key
	 * benefits as bullet points, and includes a start button.
	 *
	 * @return array Step configuration including ID, type, title, subtitle, intro bullets, button info, and note.
	 */
	private function intro_step(): array {
		$intro_bullets = $this->get_intro_bullets();

		$note_1 = $this->is_premium ? __( "Premium plugin", 'wp-optimize' ) : __( "Free plugin", 'wp-optimize' );
		$note_2 = __( "Quick setup", 'wp-optimize' );
		$note_3 = __( "No tech skills needed", 'wp-optimize' );
		$bottom_note = $note_1 . '   •   ' . $note_2 . '   •   ' . $note_3;
		return array(
			'id'            => 'intro',
			'type'          => 'intro',
			'title'         => __( 'Let\'s get started!', 'wp-optimize' ),
			'subtitle'      => __( "Speed up and optimize your WordPress site with ease, trusted by over 1 million sites.", 'wp-optimize' ),
			'intro_bullets' => $intro_bullets,
			'button' => array(
				'id'    => 'start',
				'label' => __( 'Start', 'wp-optimize' ),
				'icon'  => 'magic-wand',
			),
			'note' => $bottom_note,
		);
	}

	/**
	 * Build the license activation step for the onboarding wizard.
	 *
	 * This step includes fields for email and password, conditional
	 * titles/subtitles based on license status, and a button to
	 * activate the license.
	 *
	 * @return array Step configuration including ID, type, icon, titles, subtitles, fields, and button info.
	 */
	private function license_step(): array {
		return array(
			'id'       => 'license',
			'type'     => 'license',
			'icon'     => 'user-lock',
			'title'    => __( 'Connect and activate your license', 'wp-optimize' ),
			'title_conditional' => array(
				'licenseActivated' => __( 'License activated!', 'wp-optimize' ),
				'isUpdating' => __( 'Activating your Premium license...', 'wp-optimize' ),
			),
			'subtitle' => __( 'Please enter your TeamUpdraft credentials to start using Premium features.', 'wp-optimize' ),
			'subtitle_conditional' => array(
				'licenseActivated' => '',
				'isUpdating' => '',
			),
			'fields'   => array(
				array(
					'id'    => 'registration_email',
					'type'  => 'email',
					'label' => __( 'Email', 'wp-optimize' ),
				),
				array(
					'id'    => 'registration_password',
					'type'  => 'password',
					'label' => __( 'Password', 'wp-optimize' ),
				),
			),
			'button'   => array(
				'id'   => 'activate',
				'label'=> __( 'Confirm and activate', 'wp-optimize' ),
				'icon' => 'EastRoundedIcon',
			)
		);
	}

	/**
	 * Build the step for the best-practice features in the onboarding wizard.
	 *
	 * @return array Step data including title, subtitle, feature fields, button info, and skip step info.
	 */
	private function features_step(): array {
		$features = $this->get_feature_settings();
		return array(
			'id'       => 'page_features',
			'type'     => 'settings',
			'icon'     => 'settings',
			'title'    => __( 'Enable best-practice settings', 'wp-optimize' ),
			'subtitle' => __( 'We\'ve pre-selected core settings to speed up and optimize your site.', 'wp-optimize' ).' '.__( 'You can tweak them anytime.', 'wp-optimize' ),
			'fields'   => $features,
			'button'   => array(
				'id'   => 'save',
				'label'=> __( 'Save and continue', 'wp-optimize' ),
				'icon' => 'EastRoundedIcon',
			),
			'skip_step' => array(
				'icon' => 'info',
				'tooltip' => array(
					'text' => __( 'All above features will be disabled if you skip.', 'wp-optimize' ),
				),
			)
		);
	}

	/**
	 * Build the step for a newsletter in the onboarding wizard.
	 *
	 * @return array Step data including title, subtitle, email fields, and button info.
	 */
	private function newsletter_step(): array {
		$email_fields = $this->get_email_fields();
		return array(
			'id'       => 'email',
			'type'     => 'email',
			'icon'     => 'mail',
			'title'    => __( 'Get lightning-fast insights!', 'wp-optimize' ),
			'subtitle' => __( 'Join our newsletter for speed-optimization tips and best practices.', 'wp-optimize' ).' '.__( 'Delivered straight to your inbox.', 'wp-optimize' ),
			'fields'   => $email_fields,
			'button'   => array(
				'id'   => 'save',
				'label'=> __( 'Save and continue', 'wp-optimize' ),
				'icon' => 'EastRoundedIcon',
			),
		);
	}

	/**
	 * Build the step for the recommended plugins installation in the onboarding wizard.
	 *
	 * @return array Step data including title, subtitle, plugin fields, and button info.
	 */
	private function plugins_install_step(): array {
		return array(
			'id'             => 'plugins',
			'type'           => 'plugins',
			'icon'           => 'plugin',
			'first_run_only' => false,
			'title'          => __( 'Recommended for your setup', 'wp-optimize' ),
			'title_conditional' => array(
				'all_installed' => __( 'Best-practice plugins enabled', 'wp-optimize' ),
			),
			'subtitle'       => __( 'We\'ve carefully handpicked these plugins to match your website\'s setup, so everything works just the way it should.', 'wp-optimize' ),
			'subtitle_conditional' => array(
				'all_installed' => __( 'Wow, your site already meets all our plugin recommendations, let\'s move on!', 'wp-optimize' ),
			),
			'fields'         => array(
				array(
					'id'    => 'plugins',
					'type'  => 'plugins',
				),
			),
			'button'         => array(
				'id'    => 'save',
				'label' => __( 'Install and continue', 'wp-optimize' ),
				'icon' => 'EastRoundedIcon',
			),
		);
	}

	/**
	 * Build the "Go Premium" onboarding step array.
	 *
	 * @return array Step data including title, subtitle, bullets, and button info.
	 */
	private function go_premium_step(): array {
		$go_premium_step_bullets = $this->get_go_premium_bullets();
		return array(
			'id'        => 'go_premium',
			'type'      => 'go_premium',
			'icon'      => 'bolt',
			'title'     => __( 'Upgrade to Premium', 'wp-optimize' ),
			'subtitle'  => __( 'The complete optimization suite with safe defaults and expert help.', 'wp-optimize' ),
			'bullets'   => $go_premium_step_bullets,
			'enable_premium_btn' => true,
			'premium_btn_text' => __( 'Upgrade to Premium', 'wp-optimize' ),
		);
	}

	/**
	 * Build the final "Completed" onboarding step array.
	 *
	 * @return array Step data including title, subtitle, bullets, and finish button info.
	 */
	private function last_step(): array {
		list($last_step_subtitle, $last_step_subtitle_is_installing) = $this->get_last_step_subtitles();

		$last_step_bullets = $this->get_last_step_bullets();

		return array(
			'id'        => 'completed',
			'type'      => 'completed',
			'icon'      => 'CheckRoundedIcon',
			'title'     => __( 'You\'re all set!', 'wp-optimize' ),
			'title_conditional' => array(
				'isInstalling' => __( 'Almost done, finalizing...', 'wp-optimize' ),
			),
			'subtitle'  => $last_step_subtitle,
			'subtitle_conditional' => array(
				'isInstalling' => $last_step_subtitle_is_installing
			),
			'bullets'   => $last_step_bullets,
			'button'  => array(
				'id'    => 'finish',
				'label' => __( 'Go to settings', 'wp-optimize' ),
			),
		);
	}

	/**
	 * Check if the plugin license is connected via the Updraft updater instance.
	 *
	 * @return bool True if the license is connected, false otherwise.
	 */
	private function is_license_connected(): bool {
		global $updraft_updater_instance;

		if (!isset($updraft_updater_instance)) {
			return false;
		}

		try {
			/*
			 * The is_connected() method is protected in class Updraft_Manager_Updater_1_9.
			 * So we cannot call it directly from outside the class without using ReflectionClass.
			 */
			$reflection = new ReflectionClass($updraft_updater_instance);
			if (!$reflection->hasMethod('is_connected')) {
				return false;
			}
			$method = $reflection->getMethod('is_connected');
			$method->setAccessible(true);

			return (bool) $method->invoke($updraft_updater_instance);
		} catch (\Throwable $e) {
			// Any exception results in returning false
			return false;
		}
	}

	/**
	 * Get bullet points for the "Go Premium" step.
	 *
	 * Each bullet highlights a benefit of upgrading to the Premium version.
	 *
	 * @return array Array of bullet strings, each wrapped in its own array.
	 */
	private function get_go_premium_bullets(): array {
		return array(
			array(
				__('Ranking high', 'wp-optimize')
			),
			array(
				__('Loading at top speed', 'wp-optimize')
			),
			array(
				__('Turning more visits into sales', 'wp-optimize'),
			),
			array(
				__('Serving visitors & customers better', 'wp-optimize'),
			),
		);
	}

	/**
	 * Get subtitles for the last onboarding step.
	 *
	 * Provides two strings:
	 * 1. Standard subtitle for the completed step.
	 * 2. Subtitle showed when redirecting to settings (with a link to the plugin page).
	 *
	 * @return array Array containing two strings: [standard_subtitle, installing_subtitle].
	 */
	private function get_last_step_subtitles(): array {

		$subtitle = __('WP-Optimize is ready to help your site run faster.', 'wp-optimize');
		$subtitle .= ' ';
		$subtitle .= __('You can review or customize settings whenever you like.', 'wp-optimize');

		$installing = __('Setting things up in the background...', 'wp-optimize') . '<br>';
		$installing .= __('This will only take a moment.', 'wp-optimize');

		return array($subtitle, $installing);
	}

	/**
	 * Get the list of feature bullets shown in the final onboarding step.
	 *
	 * Returns premium-only feature bullet points.
	 * If the user is not premium, an empty array is returned.
	 *
	 * @return array List of bullet point groups.
	 */
	private function get_last_step_bullets(): array {
		if (!$this->is_premium) {
			return array();
		}

		return array(
			array(
				__('Lazy loading', 'wp-optimize'),
				__('Remove unused images', 'wp-optimize'),
			),
			array(
				__('Multisite optimization', 'wp-optimize'),
				__('Preload key requests', 'wp-optimize'),
			),
			array(
				__('User and role-based cache', 'wp-optimize'),
				__('Premium support and  more', 'wp-optimize'),
			),
		);
	}

	/**
	 * Get email-related form fields for the onboarding wizard.
	 *
	 * Returns configuration for email input and consent checkbox fields.
	 *
	 * @return array List of email field definitions.
	 */
	private function get_email_fields(): array {
		return array(
			array(
				'id'      => 'email_reports_mailinglist',
				'key'     => 'email_reports_mailinglist',
				'type'    => 'email',
				'label'   => __( 'Email', 'wp-optimize'),
				'default' => '',
			),
			array(
				'id'      => 'tips_tricks_mailinglist',
				'key'     => 'tips_tricks_mailinglist',
				'type'    => 'checkbox',
				'label'   => __( 'I agree to receive emails with tips, updates and marketing content.',  'wp-optimize').' '.__( 'I understand I can unsubscribe at any time.', 'wp-optimize' ),
				'default' => false,
				'show_privacy_link' => true,
			),
		);
	}

	/**
	 * Get the list of feature settings for the onboarding wizard.
	 *
	 * Generates an array of feature configuration options, including
	 * labels, types, defaults, and premium-locked items.
	 *
	 * @return array List of feature setting definitions.
	 */
	private function get_feature_settings(): array {
		$premium_heading = '';
		$premium_text    = '';
		$is_lock = !$this->is_premium;
		if ($is_lock) {
			list($premium_heading, $premium_text) = $this->get_premium_tooltip();
		}

		$webp_instance = WP_Optimize()->get_webp_instance();
		$webp_tooltip  = __( 'Serve modern WebP images for smaller downloads.', 'wp-optimize' );
		$is_lock_webp  = false;

		if (!$webp_instance->shell_functions_available()) {
			$is_lock_webp = true;
			$webp_tooltip = __( 'Required WebP shell functions are not available on your server.', 'wp-optimize' );
		} elseif ($webp_instance->should_run_webp_conversion_test()) {
			WPO_WebP_Test_Run::get_converter_status();
			if (!$webp_instance->is_webp_conversion_successful()) {
				$is_lock_webp = true;
				$webp_tooltip = __( 'No working WebP converter was found on your server.', 'wp-optimize' );
			}
		}

		return array(
			array(
				'id'      => 'enable_caching_onboarding',
				'key'     => 'enable_caching_onboarding',
				'type'    => 'checkbox',
				'subtype' => 'switch',
				'label'   => __( 'Page caching', 'wp-optimize' ),
				'tooltip' => array(
					'text' => __( 'Cache full pages for faster repeat visits.', 'wp-optimize' ),
				),
				'default' => true,
			),
			array(
				'id'      => 'enable_minify_onboarding',
				'key'     => 'enable_minify_onboarding',
				'type'    => 'checkbox',
				'subtype' => 'switch',
				'label'   => __( 'Minify static assets', 'wp-optimize' ),
				'tooltip' => array(
					'text' => __( 'Shrink HTML, CSS and JavaScript files for quicker loads.', 'wp-optimize' ),
				),
				'default' => true,
			),
			array(
				'id'      => 'enable_image_compression_onboarding',
				'key'     => 'enable_image_compression_onboarding',
				'type'    => 'checkbox',
				'subtype' => 'switch',
				'label'   => __( 'Image compression', 'wp-optimize' ),
				'tooltip' => array(
					'text' => __( 'Automatically reduce image file sizes on upload to improve page load speed.', 'wp-optimize' ).' '.__( 'In settings, you can adjust compression quality as well as manually compress existing images.', 'wp-optimize' ),
				),
				'default' => true,
			),
			array(
				'id'      => 'enable_webp_conversion_onboarding',
				'key'     => 'enable_webp_conversion_onboarding',
				'type'    => 'checkbox',
				'subtype' => 'switch',
				'is_lock' => $is_lock_webp,
				'label'   => __( 'WebP conversion', 'wp-optimize' ),
				'tooltip' => array(
					'text' => $webp_tooltip,
				),
				'default' => !$is_lock_webp,
			),
			array(
				'id'      => 'enable_lazy_load_onboarding',
				'key'     => 'enable_lazy_load_onboarding',
				'type'    => 'checkbox',
				'subtype' => 'switch',
				'is_lock' => $is_lock,
				'label'   => __('Lazy Loading', 'wp-optimize'),
				'tooltip' => array(
					'heading' => array(
						'text' => $is_lock ? $premium_heading : ''
					),
					'text' => $is_lock ? $premium_text : __( 'Load images and videos only when they enter the viewport.', 'wp-optimize' ),
				),
				'default' => !$is_lock,
			),
			array(
				'id'      => 'enable_image_dimensions_onboarding',
				'key'     => 'enable_image_dimensions_onboarding',
				'type'    => 'checkbox',
				'subtype' => 'switch',
				'is_lock' => $is_lock,
				'label'   => __('Image Dimensions', 'wp-optimize'),
				'tooltip' => array(
					'heading' => array(
						'text' => $is_lock ? $premium_heading : ''
					),
					'text' => $is_lock ? $premium_text : __( 'Auto-add missing width and height to improve load speed and reduce layout shifts.', 'wp-optimize' ),
				),
				'default' => !$is_lock,
			),
		);
	}

	/**
	 * Get the tooltip heading and text for premium-only features.
	 *
	 * @return array An array with two values: heading and tooltip text.
	 */
	private function get_premium_tooltip(): array {
		$heading = __('Premium feature ⚡', 'wp-optimize');

		$upgrade_url_tooltip = WP_Optimize_Utils::add_utm_params($this->upgrade_url, $this->get_utm_params_to_override('upgrade-to-premium', 'tooltip'), true);
		$text = sprintf(
			// translators: %s: Text with Link
			__( '%s to unlock this and other advanced options.', 'wp-optimize' ), '<a href="'.$upgrade_url_tooltip.'" class="underline" target="_blank">' . __( 'Upgrade to Premium', 'wp-optimize' ) . '</a>');

		return array($heading, $text);
	}

	/**
	 * Get the introductory feature bullet points for 1st Step of Onboarding wizard.
	 *
	 * @return array List of bullets with icon, title, and description.
	 */
	private function get_intro_bullets(): array {
		return array(
			array(
				'icon'  => 'database',
				'title' => __( 'Clean database', 'wp-optimize' ),
				'desc'  => __( 'Remove unnecessary data to keep your site fast.', 'wp-optimize' ),
			),
			array(
				'icon'  => 'compress',
				'title' => __( 'Compress images', 'wp-optimize' ),
				'desc'  => __( 'Reduce image sizes for quicker page loads.', 'wp-optimize' ),
			),
			array(
				'icon'  => 'cache',
				'title' => __( 'Cache pages', 'wp-optimize' ),
				'desc'  => __( 'Store pages for instant loading.', 'wp-optimize' ),
			),
			array(
				'icon'  => 'minify',
				'title' => __( 'Minify code', 'wp-optimize' ),
				'desc'  => __( 'Shrink CSS, JavaScript, and HTML for better performance.', 'wp-optimize' ),
			),
		);
	}

	/**
	 * Array of UTM parameters for the onboarding wizard.
	 *
	 * @param string $content UTM content.
	 * @param string $format  UTM creative_format.
	 *
	 * @return array
	 */
	private function get_utm_params_to_override($content = 'onboarding', $format = 'text'): array {
		$type = $this->is_premium ? 'prem' : 'free';
		return array(
			'utm_content'  => $content,
			'utm_campaign' => sprintf('paac-%s-onboarding-wizard', $type),
			'utm_creative_format'  => $format,
		);
	}

	/**
	 * Initialize the onboarding
	 *
	 * @return void
	 */
	public function setup_onboarding(): void {
		$onboarding = new Onboarding();
		if ($onboarding::is_onboarding_active($this->prefix, $this->caller_slug)) {

			$support_link = $this->is_premium ? WP_Optimize_Utils::add_utm_params('https://teamupdraft.com/support/premium-support/', $this->get_utm_params_to_override('premium-support')) : esc_url('https://wordpress.org/support/plugin/wp-optimize/');

			$onboarding->is_pro                         = $this->is_premium;
			$onboarding->logo_path                      = esc_url(trailingslashit(WPO_PLUGIN_URL) . 'images/notices/wp_optimize_logo.png');
			$onboarding->prefix                         = $this->prefix;
			$onboarding->plugin_name                    = $this->is_premium ? 'WP-Optimize Premium' : 'WP-Optimize';
			$onboarding->mailing_list                   = array($this->is_premium ? self::MAILING_LIST_PREMIUM_ID : self::MAILING_LIST_FREE_ID);
			$onboarding->mailing_list_endpoint          = self::MAILING_LIST_ENDPOINT;
			$onboarding->caller_slug                    = $this->caller_slug;
			$onboarding->capability                     = 'manage_options';
			$onboarding->support_url                    = $support_link;
			$onboarding->privacy_url_label              = __( 'Privacy Policy.', 'wp-optimize' );
			$onboarding->privacy_statement_url          = WP_Optimize_Utils::add_utm_params('https://teamupdraft.com/privacy/', $this->get_utm_params_to_override('privacy-statement'));
			$onboarding->forgot_password_url            = WP_Optimize_Utils::add_utm_params('https://teamupdraft.com/my-account/lost-password/', $this->get_utm_params_to_override('forgot-password'));
			$onboarding->documentation_url              = WP_Optimize_Utils::add_utm_params('https://teamupdraft.com/documentation/wp-optimize/', $this->get_utm_params_to_override('documentation'));
			$onboarding->upgrade_url                    = WP_Optimize_Utils::add_utm_params($this->upgrade_url, $this->get_utm_params_to_override('upgrade-to-premium', 'button'), true);
			$onboarding->page_prefix                    = $this->caller_slug;
			$onboarding->version                        = WPO_VERSION;
			$onboarding->languages_dir                  = WPO_PLUGIN_MAIN_PATH . 'languages';
			$onboarding->text_domain                    = 'wp-optimize';
			$onboarding->exit_wizard_text               = __( 'Exit setup', 'wp-optimize' );
			$onboarding->reload_settings_page_on_finish = true;
			$onboarding->udmupdater_muid = 2;
			$onboarding->udmupdater_slug = 'wp-optimize-premium';

			$onboarding->init();

		}
	}

	/**
	 * Enable the onboarding wizard for the plugin.
	 *
	 * This method performs two actions:
	 *
	 * 1. Sets an option using site option functions, so it works for both single and multisite to trigger the onboarding
	 *    wizard the next time a WP-Optimize page loads.
	 * 2. Removes the installation source flag, ensuring that the plugin does not
	 *    treat the current activation as originating from the onboarding wizard.
	 *
	 * WordPress Internally, uses the site wrappers for update_option() and delete_option()
	 * to ensure compatibility with both single-site and multisite environments.
	 *
	 * @return void
	 */
	public function activate_onboarding_wizard(): void {
		// to enable onboarding wizard on WP-Optimize pages
		update_site_option($this->prefix.'_start_onboarding', true);

		// to reset that this plugin was installed from the onboarding wizard
		delete_site_option('teamupdraft_installation_source_'.$this->caller_slug);
	}
}
