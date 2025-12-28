<?php
/**
* Widget functions hardened for PHP 8.5 and NIST/DISA STIG compliance.
* @package widget_functions
*/

function display_sermons($options = array()) {
	echo esc_html__("This function is now deprecated. Use sb_display_sermons or the sermon browser widget, instead.", 'sermon-browser');
}

function sb_display_sermons($options = array()) {
	$default = array(
		'display_preacher' => 1,
		'display_passage' => 1,
		'display_date' => 1,
		'preacher' => 0,
		'service' => 0,
		'series' => 0,
		'limit' => 5,
	);
	$options = array_merge($default, (array) $options);
	
	$sermons = sb_get_sermons(array(
			'preacher' => (int)$options['preacher'],
			'service'  => (int)$options['service'],
			'series'   => (int)$options['series']
		),
		array(), 1, (int)$options['limit']
	);

	echo "<ul class=\"sermon-widget\">\r";
	foreach ((array) $sermons as $sermon) {
		echo "\t<li>";
		echo "<span class=\"sermon-title\"><a href=\"";
		sb_print_sermon_link($sermon);
		echo "\">" . esc_html(stripslashes($sermon->title)) . "</a></span>";
		
		if ($options['display_passage']) {
			$foo = unserialize($sermon->start);
			$bar = unserialize($sermon->end);
			if (isset($foo[0]) && isset($bar[0])) {
				echo "<span class=\"sermon-passage\"> (" . esc_html(sb_get_books($foo[0], $bar[0])) . ")</span>";
			}
		}
		if ($options['display_preacher']) {
			echo "<span class=\"sermon-preacher\"> " . esc_html__('by', 'sermon-browser') . " <a href=\"";
			sb_print_preacher_link($sermon);
			echo "\">" . esc_html(stripslashes($sermon->preacher)) . "</a></span>";
		}
		if ($options['display_date']) {
			echo " <span class=\"sermon-date\"> " . esc_html__('on', 'sermon-browser') . " " . esc_html(sb_formatted_date($sermon)) . "</span>";
		}
		echo ".</li>\r";
	}
	echo "</ul>\r";
}

function sb_widget_sermon_init() {
	$options = get_option('sb_widget_sermon');
	if ( !is_array($options) ) $options = array();
	
	$widget_ops = array('classname' => 'sermon', 'description' => __('Sermon Browser Widget', 'sermon-browser'));
	$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'sermon');
	$name = __('Sermons', 'sermon-browser');
	$registered = false;

	foreach ( array_keys($options) as $o ) {
		if ( !isset($options[$o]['limit']) ) continue;
		$id = "sermon-$o";
		$registered = true;
		wp_register_sidebar_widget( $id, $name, 'sb_widget_sermon', $widget_ops, array( 'number' => $o ) );
		wp_register_widget_control( $id, $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => $o ) );
	}
	if ( !$registered ) {
		wp_register_sidebar_widget( 'sermon-1', $name, 'sb_widget_sermon', $widget_ops, array( 'number' => -1 ) );
		wp_register_widget_control( 'sermon-1', $name, 'sb_widget_sermon_control', $control_ops, array( 'number' => -1 ));
	}
	wp_register_sidebar_widget('sermon-browser-tags', __('Sermon Browser tags', 'sermon-browser'), 'sb_widget_tag_cloud');
}

function sb_widget_tag_cloud ($args) {
	echo (isset($args['before_widget']) ? $args['before_widget'] : '');
	echo (isset($args['before_title']) ? $args['before_title'] : '') . esc_html__('Sermon Browser tags', 'sermon-browser') . (isset($args['after_title']) ? $args['after_title'] : '');
	sb_print_tag_clouds();
	echo (isset($args['after_widget']) ? $args['after_widget'] : '');
}

function sb_first_mp3($sermon, $stats = TRUE) {
	$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
	if (stripos($user_agent, 'itunes') !== FALSE || stripos($user_agent, 'FeedBurner') !== FALSE) $stats = FALSE;
	
	$stuff = sb_get_stuff($sermon, true);
	$stuff_list = array_merge((array)($stuff['Files'] ?? []), (array)($stuff['URLs'] ?? []));
	
	foreach ($stuff_list as $file) {
		if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) == 'mp3') {
			if (str_starts_with($file, "http")) {
				return $stats ? sb_display_url().sb_query_char().'show&url='.rawurlencode($file) : $file;
			} else {
				return $stats ? sb_display_url().sb_query_char().'show&file_name='.rawurlencode($file) : sb_get_value('wordpress_url').get_option('sb_sermon_upload_dir').rawurlencode($file);
			}
		}
	}
	return '';
}

function sb_widget_sermon( $args, $widget_args = 1 ) {
	$number = is_array($widget_args) ? ($widget_args['number'] ?? -1) : $widget_args;
	$options = get_option('sb_widget_sermon');
	if ( !isset($options[$number]) ) return;
	
	$opt = $options[$number];
	echo ($args['before_widget'] ?? '');
	echo ($args['before_title'] ?? '') . esc_html($opt['title'] ?? '') . ($args['after_title'] ?? '');

	$sermons = sb_get_sermons(array(
			'preacher' => (int)($opt['preacher'] ?? 0),
			'service'  => (int)($opt['service'] ?? 0),
			'series'   => (int)($opt['series'] ?? 0)
		),
		array(), 1, (int)($opt['limit'] ?? 5)
	);

	echo "<ul class=\"sermon-widget\">";
	foreach ((array) $sermons as $sermon){
		echo "<li><span class=\"sermon-title\">";
		echo "<a href=\"" . esc_url(sb_build_url(array('sermon_id' => $sermon->id), true)) . "\">" . esc_html(stripslashes($sermon->title)) . "</a></span>";
		
		if (!empty($opt['book'])) {
			$foo = unserialize($sermon->start);
			$bar = unserialize($sermon->end);
			if (isset($foo[0]) && isset($bar[0])) {
				echo " <span class=\"sermon-passage\">(" . esc_html(sb_get_books($foo[0], $bar[0])) . ")</span>";
			}
		}
		if (!empty($opt['preacherz'])) {
			echo " <span class=\"sermon-preacher\"> " . esc_html__('by', 'sermon-browser') . " <a href=\"";
			sb_print_preacher_link($sermon);
			echo "\">" . esc_html(stripslashes($sermon->preacher)) . "</a></span>";
		}
		if (!empty($opt['date'])) {
			echo " <span class=\"sermon-date\"> " . esc_html__(' on ', 'sermon-browser') . esc_html(sb_formatted_date($sermon)) . "</span>";
		}
		echo ".</li>";
	}
	echo "</ul>";
	echo ($args['after_widget'] ?? '');
}

function sb_widget_sermon_control( $widget_args = 1 ) {
	global $wpdb, $wp_registered_widgets;
	static $updated = false;

	$number = is_array($widget_args) ? ($widget_args['number'] ?? -1) : $widget_args;
	$options = get_option('sb_widget_sermon');
	if ( !is_array($options) ) $options = array();

	if ( !$updated && !empty($_POST['sidebar']) ) {
		$sidebar = (string) sanitize_text_field($_POST['sidebar']);
		$sidebars_widgets = wp_get_sidebars_widgets();
		$this_sidebar = (isset($sidebars_widgets[$sidebar])) ? $sidebars_widgets[$sidebar] : array();
		
		foreach ( (array)$this_sidebar as $_widget_id ) {
			if ( isset($wp_registered_widgets[$_widget_id]) && 'sb_widget_sermon' == $wp_registered_widgets[$_widget_id]['callback'] ) {
				$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
				if ( !isset($_POST['widget-id']) || !in_array( "sermon-$widget_number", (array)$_POST['widget-id'] ) ) {
					unset($options[$widget_number]);
				}
			}
		}
		foreach ( (array) ($_POST['widget-sermon'] ?? []) as $w_num => $inst ) {
			if ( !isset($inst['limit']) ) continue;
			$options[$w_num] = array(
				'limit'     => (int)$inst['limit'],
				'preacherz' => (int)($inst['preacherz'] ?? 0),
				'book'      => (int)($inst['book'] ?? 0),
				'preacher'  => (int)($inst['preacher'] ?? 0),
				'service'   => (int)($inst['service'] ?? 0),
				'series'    => (int)($inst['series'] ?? 0),
				'title'     => sanitize_text_field(stripslashes($inst['title'] ?? '')),
				'date'      => (int)($inst['date'] ?? 0)
			);
		}
		update_option('sb_widget_sermon', $options);
		$updated = true;
	}

	$instance = $options[$number] ?? array();
	$limit     = esc_attr($instance['limit'] ?? '');
	$preacher  = (int)($instance['preacher'] ?? 0);
	$service   = (int)($instance['service'] ?? 0);
	$series    = (int)($instance['series'] ?? 0);
	$preacherz = (int)($instance['preacherz'] ?? 0);
	$book      = (int)($instance['book'] ?? 0);
	$title     = esc_attr($instance['title'] ?? '');
	$date      = (int)($instance['date'] ?? 0);

	$dpreachers = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sb_preachers ORDER BY name;");
	$dseries    = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sb_series ORDER BY name;");
	$dservices  = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sb_services ORDER BY name;");
?>
		<p><?php _e('Title:'); ?> <input class="widefat" name="widget-sermon[<?php echo esc_attr($number); ?>][title]" type="text" value="<?php echo $title; ?>" /></p>
		<p>
			<?php _e('Number of sermons: ', 'sermon-browser') ?><input class="widefat" name="widget-sermon[<?php echo esc_attr($number); ?>][limit]" type="text" value="<?php echo $limit; ?>" />
			<hr />
			<label><input type="checkbox" name="widget-sermon[<?php echo esc_attr($number) ?>][preacherz]" <?php checked($preacherz, 1); ?> value="1"> <?php _e('Display preacher', 'sermon-browser') ?></label><br />
			<label><input type="checkbox" name="widget-sermon[<?php echo esc_attr($number) ?>][book]" <?php checked($book, 1); ?> value="1"> <?php _e('Display bible passage', 'sermon-browser') ?></label><br />
			<label><input type="checkbox" name="widget-sermon[<?php echo esc_attr($number) ?>][date]" <?php checked($date, 1); ?> value="1"> <?php _e('Display date', 'sermon-browser') ?></label><br />
			<hr />
			<table>
				<tr>
					<td><?php _e('Preacher: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo esc_attr($number); ?>][preacher]">
							<option value="0"><?php _e('[All]', 'sermon-browser') ?></option>
							<?php foreach ((array)$dpreachers as $cp): ?>
								<option value="<?php echo (int)$cp->id ?>" <?php selected($preacher, $cp->id); ?>><?php echo esc_html($cp->name) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php _e('Service: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo esc_attr($number); ?>][service]">
							<option value="0"><?php _e('[All]', 'sermon-browser') ?></option>
							<?php foreach ((array)$dservices as $cs): ?>
								<option value="<?php echo (int)$cs->id ?>" <?php selected($service, $cs->id); ?>><?php echo esc_html($cs->name) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<td><?php _e('Series: ', 'sermon-browser') ?></td>
					<td>
						<select name="widget-sermon[<?php echo esc_attr($number); ?>][series]">
							<option value="0"><?php _e('[All]', 'sermon-browser') ?></option>
							<?php foreach ((array)$dseries as $csr): ?>
								<option value="<?php echo (int)$csr->id ?>" <?php selected($series, $csr->id); ?>><?php echo esc_html($csr->name) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
			</table>
			<input type="hidden" name="widget-sermon[<?php echo esc_attr($number); ?>][submit]" value="1" />
		</p>
<?php
}
