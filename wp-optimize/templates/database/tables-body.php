<?php if (!defined('WPO_VERSION')) die('No direct access allowed'); ?>

<tbody id="the-list">
<?php
	
	foreach ($table_list_object_format as $index => $tablestatus) {
		echo '<tr 
			data-tablename="'.esc_attr($tablestatus->Name).'"
			data-type="'.esc_attr($tablestatus->Engine).'"
			data-optimizable="'.($tablestatus->is_optimizable ? 1 : 0).'"
			'.($is_multisite_mode ? 'data-blog_id="'.esc_attr($tablestatus->blog_id).'"' : '').'
		>'."\n";
		echo '<td data-colname="'.esc_attr__('No.', 'wp-optimize').'">'.esc_html(number_format_i18n($table_list[$index]['index'])).'</td>'."\n";
		echo '<td data-tablename="'.esc_attr($tablestatus->Name).'" data-colname="'.esc_attr__('Table', 'wp-optimize').'">'.esc_html($tablestatus->Name);

		if (!empty($tablestatus->plugin_status)) {
			if ($tablestatus->wp_core_table) {
				echo "<br><span style='font-size: 11px;'>".esc_html__('Belongs to:', 'wp-optimize')."</span> ";
				echo "<span style='font-size: 11px;'>".esc_html__('WordPress core', 'wp-optimize')."</span>";
			} elseif (false !== stripos($tablestatus->Name, 'actionscheduler_')) {
				$message = __('This table is used by many plugins for batch processing.', 'wp-optimize');
				$message .= ' ';
				echo "<br><span style='font-size: 11px;'>". esc_html($message) ."</span> ";
				echo "<span style='font-size: 11px;'>".esc_html__('Thus, it cannot be deleted.', 'wp-optimize')."</span>";
			} else {
				echo '<div class="table-plugins">';
				echo "<span style='font-size: 11px;'>".esc_html__('Known plugins that use this table name:', 'wp-optimize')."</span> ";
				foreach ($tablestatus->plugin_status as $plugins_status) {
					$plugin = $plugins_status['plugin'];
					$status = $plugins_status['status'];

					echo '<br>';
					
					if (in_array($plugin, $closed_plugins)) {
					  continue;
					}

					if ('sfwd-lms' === $plugin) {
						$wp_optimize->wp_optimize_url('https://www.learndash.com/', '', '<span style="font-size: 11px;">LearnDash</span>');
					} else {
						$wp_optimize->wp_optimize_url('https://wordpress.org/plugins/'.$plugin.'/', '', '<span style="font-size: 11px;">'.esc_html($plugin).'</span>');
					}

					if (false === $status['installed']) {
						echo ' <span style="font-size: 11px; color: #9B0000; font-weight: bold;">['.esc_html__('not installed', 'wp-optimize').']</span>';
					} elseif (false === $status['active']) {
						echo ' <span style="font-size: 11px; color: #9B0000; font-weight: bold;">['.esc_html__('inactive', 'wp-optimize').']</span>';
					}
				}
				echo '</div>';
			}
		}

		echo "</td>\n";

		echo '<td data-colname="'.esc_attr__('Records', 'wp-optimize').'" data-sort="'.esc_attr(intval($tablestatus->Rows)).'">'.esc_html(number_format_i18n($tablestatus->Rows)).'</td>'."\n";
		echo '<td data-colname="'.esc_attr__('Data Size', 'wp-optimize').'" data-sort="'.esc_attr(intval($tablestatus->Data_length)).'">'. esc_html($wp_optimize->format_size($tablestatus->Data_length)).'</td>'."\n";
		echo '<td data-colname="'.esc_attr__('Index Size', 'wp-optimize').'" data-sort="'.esc_attr(intval($tablestatus->Index_length)).'">'. esc_html($wp_optimize->format_size($tablestatus->Index_length)).'</td>'."\n";

		if ($tablestatus->is_optimizable) {
			echo '<td data-colname="'.esc_attr__('Type', 'wp-optimize').'" data-optimizable="1">'.esc_html($tablestatus->Engine).'</td>'."\n";

			echo '<td data-colname="'.esc_attr__('Overhead', 'wp-optimize').'" data-sort="'.esc_attr(intval($tablestatus->Data_free)).'">';
			$font_colour = ($optimize_db ? (($tablestatus->Data_free > $small_overhead_size) ? '#0000FF' : '#004600') : (($tablestatus->Data_free > $small_overhead_size) ? '#9B0000' : '#004600'));
			echo '<span style="color:'. esc_attr($font_colour) .';">';
			echo esc_html($wp_optimize->format_size($tablestatus->Data_free));
			echo '</span>';
			echo '</td>'."\n";
		} else {
			echo '<td data-colname="'.esc_attr__('Type', 'wp-optimize').'" data-optimizable="0">'.esc_html($tablestatus->Engine).'</td>'."\n";
			echo '<td data-colname="'.esc_attr__('Overhead', 'wp-optimize').'">';
			echo '<span style="color:#0000FF;">-</span>';
			echo '</td>'."\n";
		}

		echo '<td data-colname="'.esc_attr__('Actions', 'wp-optimize').'">'. wp_kses_post(apply_filters('wpo_tables_list_additional_column_data', '', $tablestatus)) .'</td>';

		echo '</tr>'."\n";
	}
?>
</tbody>
<tfoot>
<?php
	echo '<tr class="thead">'."\n";
	echo '<th>'.esc_html__('Total:', 'wp-optimize').'</th>'."\n";
	// translators: %s is the number of tables
	echo '<th>'.esc_html(sprintf(_n('%s Table', '%s Tables', $no, 'wp-optimize'), $no)).'</th>'."\n";
	echo '<th>'.esc_html($row_usage).'</th>'."\n";
	echo '<th>'.esc_html($data_usage).'</th>'."\n";
	echo '<th>'.esc_html($index_usage).'</th>'."\n";
	echo '<th>'.'-'.'</th>'."\n";
	echo '<th>';

	$font_colour = (($optimize_db) ? (($overhead_usage > $small_overhead_size) ? '#0000FF' : '#004600') : (($overhead_usage > $small_overhead_size) ? '#9B0000' : '#004600'));
	
	echo '<span style="color:'.esc_attr($font_colour).'">'.esc_html($overhead_usage_formatted).'</span>';
	
?>
	</th>
	<th><?php esc_html_e('Actions', 'wp-optimize'); ?></th>
	</tr>
</tfoot>
