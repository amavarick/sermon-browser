<?php

/**
* Checks for old-style sermonbrowser options (prior to 0.43)
*/
function sb_upgrade_options () {
	$standard_options = array (
		array ('old_option' => 'sb_sermon_style_date_modified', 'new_option' => 'style_date_modified'),
		array ('old_option' => 'sb_sermon_db_version', 'new_option' => 'db_version'),
		array ('old_option' => 'sb_sermon_version', 'new_option' => 'code_version'),
		array ('old_option' => 'sb_podcast', 'new_option' => 'podcast_url'),
		array ('old_option' => 'sb_filtertype', 'new_option' => 'filter_type'),
		array ('old_option' => 'sb_filterhide', 'new_option' => 'filter_hide'),
		array ('old_option' => 'sb_widget_sermon', 'new_option' => 'sermons_widget_options'),
		array ('old_option' => 'sb_sermon_upload_dir', 'new_option' => 'upload_dir'),
		array ('old_option' => 'sb_sermon_upload_url', 'new_option' => 'upload_url'),
		array ('old_option' => 'sb_display_method', 'new_option' => 'display_method'),
		array ('old_option' => 'sb_sermons_per_page', 'new_option' => 'sermons_per_page'),
		array ('old_option' => 'sb_show_donate_reminder', 'new_option' => 'show_donate_reminder'),
	);
	foreach ($standard_options as $option) {
		$old = get_option($option['old_option']);
		if ($old !== false) {
			sb_update_option($option['new_option'], $old);
			delete_option ($option['old_option']);
		}
	}
	$base64_options = array (
		array ('old_option' => 'sb_sermon_single_form', 'new_option' => 'single_template'),
		array ('old_option' => 'sb_sermon_single_output', 'new_option' => 'single_output'),
		array ('old_option' => 'sb_sermon_multi_form', 'new_option' => 'search_template'),
		array ('old_option' => 'sb_sermon_multi_output', 'new_option' => 'search_output'),
		array ('old_option' => 'sb_sermon_style', 'new_option' => 'css_style'),
	);
	foreach ($base64_options as $option) {
		$old = get_option($option['old_option']);
		if ($old !== false) {
			$old = stripslashes(base64_decode((string)$old));
			sb_update_option($option['new_option'], $old);
			delete_option ($option['old_option']);
		}
	}
	delete_option('sb_sermon_style_output');
}

/**
* Runs the version upgrade procedures (PHP 8.5 Safe)
*/
function sb_version_upgrade ($old_version, $new_version) {
	require_once(SB_INCLUDES_DIR.'/dictionary.php');

	$sbmf = sb_get_option('search_template');
	if ($sbmf) {
		$fixed = strtr((string)$sbmf, sb_search_results_dictionary());
		$fixed = str_replace('implode($ref_output, ", ")', 'implode(", ", $ref_output)', $fixed);
		sb_update_option('search_output', $fixed);
	}

	$sbsf = sb_get_option('single_template');
	if ($sbsf) {
		$fixed = strtr((string)$sbsf, sb_sermon_page_dictionary());
		$fixed = str_replace('implode($ref_output, ", ")', 'implode(", ", $ref_output)', $fixed);
		sb_update_option('single_output', $fixed);
	}

	sb_update_option('code_version', (string)$new_version);
	if (sb_get_option('filter_type') == '') {
		sb_update_option('filter_type', 'dropdown');
	}
}

/**
* Runs the database upgrade procedures (NIST/DISA STIG Hardened)
*/
function sb_database_upgrade ($old_version) {
	require_once(SB_INCLUDES_DIR.'/dictionary.php');
	require_once(SB_INCLUDES_DIR.'/admin.php');
	global $wpdb;
	$sermonUploadDir = sb_get_default('sermon_path');

	switch ($old_version) {
		case '1.0':
			$oldSermonPath = dirname(__FILE__)."/files/";
			$files = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_stuff WHERE type = 'file' ORDER BY name ASC");
			foreach ((array)$files as $file) {
				@chmod(SB_ABSPATH.$oldSermonPath.$file->name, 0777);
				@rename(SB_ABSPATH.$oldSermonPath.$file->name, SB_ABSPATH.$sermonUploadDir.$file->name);
			}
			$table_name = $wpdb->prefix . "sb_preachers";
			if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
				$wpdb->query("ALTER TABLE {$table_name} ADD description TEXT NOT NULL, ADD image VARCHAR(255) NOT NULL");
			}
			update_option('sb_sermon_db_version', '1.1');
			// Fall-through intended

		case '1.1':
			$defaultStyle = isset($defaultStyle) ? $defaultStyle : '';
			add_option('sb_sermon_style', base64_encode($defaultStyle));
			if(!is_dir(SB_ABSPATH.$sermonUploadDir.'images') && sb_mkdir(SB_ABSPATH.$sermonUploadDir.'images')){
				@chmod(SB_ABSPATH.$sermonUploadDir.'images', 0777);
			}
			update_option('sb_sermon_db_version', '1.2');
			// Fall-through intended

		case '1.2':
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_stuff ADD count INT(10) NOT NULL DEFAULT 0");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_books_sermons ADD INDEX (sermon_id)");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_sermons_tags ADD INDEX (sermon_id)");
			update_option('sb_sermon_db_version', '1.3');
			// Fall-through intended

		case '1.3':
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_series ADD page_id INT(10) NOT NULL DEFAULT 0");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_sermons ADD page_id INT(10) NOT NULL DEFAULT 0");
			add_option('sb_display_method', 'dynamic');
			add_option('sb_sermons_per_page', '10');
			
			$multi_form = get_option('sb_sermon_multi_form');
			if ($multi_form) {
				add_option('sb_sermon_multi_output', base64_encode(strtr(base64_decode($multi_form), sb_search_results_dictionary())));
			}
			update_option('sb_sermon_db_version', '1.4');
			// Fall-through intended

		case '1.4' :
			$extra_indexes = $wpdb->get_results($wpdb->prepare("SELECT index_name, table_name FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = %s AND index_name LIKE 'sermon_id_%%'", DB_NAME));
			if (is_array($extra_indexes)) {
				foreach ($extra_indexes as $extra_index) {
					$wpdb->query("ALTER TABLE {$extra_index->table_name} DROP INDEX {$extra_index->index_name}");
				}
			}
			
			$unique_tags = $wpdb->get_results("SELECT DISTINCT name FROM {$wpdb->prefix}sb_tags");
			if (is_array($unique_tags)) {
				foreach ($unique_tags as $tag) {
					$tag_ids = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sb_tags WHERE name=%s", $tag->name));
					if (is_array($tag_ids) && count($tag_ids) > 1) {
						$first_id = $tag_ids[0]->id;
						foreach ($tag_ids as $tag_id) {
							$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_sermons_tags SET tag_id=%d WHERE tag_id=%d", (int)$first_id, (int)$tag_id->id));
							if ($first_id != $tag_id->id) {
								$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_tags WHERE id=%d", (int)$tag_id->id));
							}
						}
					}
				}
			}
			sb_delete_unused_tags();
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_tags CHANGE name name VARCHAR(255)");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_tags ADD UNIQUE (name)");
			update_option('sb_sermon_db_version', '1.5');
			// Fall-through intended

		case '1.5' :
			sb_upgrade_options ();
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_stuff ADD duration VARCHAR (6) NOT NULL DEFAULT '0'");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}sb_sermons CHANGE date `datetime` DATETIME NOT NULL");
			
			$sermon_dates = $wpdb->get_results("SELECT id, datetime, service_id, time, override FROM {$wpdb->prefix}sb_sermons");
			if ($sermon_dates) {
				$services = $wpdb->get_results("SELECT id, time FROM {$wpdb->prefix}sb_services ORDER BY id asc");
				$service_time = array();
				foreach ((array)$services as $service) {
					$service_time[$service->id] = $service->time;
				}
				foreach ($sermon_dates as $sermon_date) {
					$base_time = strtotime($sermon_date->datetime);
					if ($sermon_date->override) {
						$time_offset = strtotime((string)$sermon_date->time) - strtotime('00:00');
					} else {
						$time_offset = strtotime((string)($service_time[$sermon_date->service_id] ?? '00:00')) - strtotime('00:00');
					}
					$new_dt = date("Y-m-d H:i:s", $base_time + $time_offset);
					$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_sermons SET datetime = %s WHERE id=%d", $new_dt, (int)$sermon_date->id));
				}
			}
			sb_update_option('db_version', '1.6');
			// Fall-through intended

		case '1.6' :
			sb_update_option('mp3_shortcode', '[audio mp3="%SERMONURL%"]');
			sb_update_option('db_version', '1.7');
			return;

		default :
			update_option('sb_sermon_db_version', '1.0');
	}
}
