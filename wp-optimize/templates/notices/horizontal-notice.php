<?php if (!defined('WPO_PLUGIN_MAIN_PATH')) die('No direct access allowed'); ?>

<?php if (!empty($button_meta) && 'review' == $button_meta) : ?>

	<div class="updraft-ad-container updated below-h2">
	<div class="updraft_notice_container updraft_review_notice_container">
		<div class="updraft_advert_content_left_extra">
			<img src="<?php echo esc_url(WPO_PLUGIN_URL.'images/'.$image); // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- N/A ?>" width="85" alt="<?php esc_attr_e('notice image', 'wp-optimize');?>">
		</div>
		<div class="updraft_advert_content_right">
			<p>
				<?php echo wp_kses_post($text); ?>
			</p>
					
			<?php if (!empty($button_link)) { ?>
				<div class="updraft_advert_button_container">
					<a class="button button-primary" href="<?php echo esc_url($button_link);?>" target="_blank" onclick="jQuery('.updraft-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'wp_optimize_ajax', subaction: '<?php echo esc_js($dismiss_time);?>', nonce: '<?php echo esc_js(wp_create_nonce('wp-optimize-ajax-nonce'));?>', dismiss_forever: '1' });">
						<?php esc_html_e('Ok, you deserve it', 'wp-optimize'); ?>
					</a>
					<div class="dashicons dashicons-calendar"></div>
					<a class="updraft_notice_link" href="#" onclick="jQuery('.updraft-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'wp_optimize_ajax', subaction: '<?php echo esc_js($dismiss_time);?>', nonce: '<?php echo esc_js(wp_create_nonce('wp-optimize-ajax-nonce'));?>', dismiss_forever: '0' });">
						<?php esc_html_e('Maybe later', 'wp-optimize'); ?>
					</a>
					<div class="dashicons dashicons-no-alt"></div>
					<a class="updraft_notice_link" href="#" onclick="jQuery('.updraft-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'wp_optimize_ajax', subaction: '<?php echo esc_js($dismiss_time);?>', nonce: '<?php echo esc_js(wp_create_nonce('wp-optimize-ajax-nonce'));?>', dismiss_forever: '1' });"><?php esc_html_e('Never', 'wp-optimize'); ?></a>
				</div>
			<?php } ?>
		</div>
	</div>
	<div class="clear"></div>
</div>

<?php else : ?>

<div class="updraft-ad-container updated below-h2">
	<div class="updraft_notice_container">
		<div class="updraft_advert_content_left">
			<img src="<?php echo esc_url(WPO_PLUGIN_URL.'images/'.$image); // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- N/A ?>" width="60" height="60" alt="<?php esc_attr_e('notice image', 'wp-optimize'); ?>">
		</div>
		<div class="updraft_advert_content_right">
			<h3 class="updraft_advert_heading">
				<?php
				if (!empty($prefix)) echo esc_html($prefix).' ';
					echo esc_html($title);
				?>
				<div class="updraft-advert-dismiss">
				<?php if (!empty($dismiss_time)) { ?>
					<a href="#" onclick="jQuery('.updraft-ad-container').slideUp(); jQuery.post(ajaxurl, {action: 'wp_optimize_ajax', subaction: '<?php echo esc_js($dismiss_time); ?>', nonce: '<?php echo esc_js(wp_create_nonce('wp-optimize-ajax-nonce')); ?>' });"><?php esc_html_e('Dismiss', 'wp-optimize'); ?></a>
				<?php } else { ?>
					<a href="#" onclick="jQuery('.updraft-ad-container').slideUp();"><?php esc_html_e('Dismiss', 'wp-optimize'); ?></a>
				<?php } ?>
				</div>
			</h3>
			<p>
				<?php
					echo wp_kses_post($text);
					$button_text = '';
					if (isset($discount_code)) echo ' <b>' . esc_html($discount_code) . '</b>';
				
				if (!empty($button_link) && !empty($button_meta) && 'no-button' !== $button_meta) {
					// Check which Message is going to be used.
					if ('updraftcentral' == $button_meta) {
						$button_text = __('Get UpdraftCentral', 'wp-optimize');
					} elseif ('updraftplus' == $button_meta) {
						$button_text = __('Get UpdraftPlus', 'wp-optimize');
					} elseif ('aios' == $button_meta) {
						$button_text = __('Get AIOS', 'wp-optimize');
					} elseif ('signup' == $button_meta) {
						$button_text = __('Sign up', 'wp-optimize');
					} elseif ('go_there' == $button_meta) {
						$button_text = __('Go there', 'wp-optimize');
					} elseif ('wpo-premium' == $button_meta) {
						$button_text = __('Find out more.', 'wp-optimize');
					} elseif ('wp-optimize' == $button_meta) {
						$button_text = __('Find out more.', 'wp-optimize');
					} elseif ('collection' == $button_meta) {
						$button_text = __('Read more.', 'wp-optimize');
					} elseif ('translate' == $button_meta) {
						$button_text = __('Translate', 'wp-optimize');
					}
					$wp_optimize->wp_optimize_url($button_link, $button_text, null, 'class="updraft_notice_link"');
				}
				?>
			</p>
		</div>
	</div>
	<div class="clear"></div>
</div>

<?php

endif;