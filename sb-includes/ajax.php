<?php
define ('SB_AJAX', true);

// Throughout this plugin, p stands for preacher, s stands for service and ss stands for series
if (isset($_POST['pname'])) { // preacher
	$pname = sanitize_text_field($_POST['pname']);
	if (isset($_POST['pid'])) {
		$pid = (int) $_POST['pid'];
		if (isset($_POST['del'])) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_preachers WHERE id = %d;", $pid));
		} else {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_preachers SET name = %s WHERE id = %d;", $pname, $pid));
		}
		echo 'done';
		die();
	} else {
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_preachers VALUES (null, %s, '', '');", $pname));
		echo $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['sname'])) { // service
	$sname = sanitize_text_field($_POST['sname']);
	list($sname, $stime) = explode('@', $sname);
	$sname = trim($sname);
	$stime = trim($stime);
	if (isset($_POST['sid'])) {
		$sid = (int) $_POST['sid'];
		if (isset($_POST['del'])) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_services WHERE id = %d;", $sid));
		} else {
			$old_time = $wpdb->get_var($wpdb->prepare("SELECT time FROM {$wpdb->prefix}sb_services WHERE id = %d;", $sid));
			if (!$old_time)
				$old_time = '00:00';
			$difference = (int) (strtotime($stime) - strtotime($old_time));
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_services SET name = %s, time = %s WHERE id = %d;", $sname, $stime, $sid));
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_sermons SET datetime = DATE_ADD(datetime, INTERVAL %d SECOND) WHERE override = 0 AND service_id = %d;", $difference, $sid));
		}
		echo 'done';
		die();
	} else {
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_services VALUES (null, %s, %s);", $sname, $stime));
		echo $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['ssname'])) { // series
	$ssname = sanitize_text_field($_POST['ssname']);
	if (isset($_POST['ssid'])) {
		$ssid = (int) $_POST['ssid'];
		if (isset($_POST['del'])) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_series WHERE id = %d;", $ssid));
		} else {
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_series SET name = %s WHERE id = %d;", $ssname, $ssid));
		}
		echo 'done';
		die();
	} else {
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}sb_series VALUES (null, %s, 0);", $ssname));
		echo $wpdb->insert_id;
		die();
	}
} elseif (isset($_POST['fname']) && validate_file (sb_get_option('upload_dir').$_POST['fname']) === 0) { // Files
	$fname = sanitize_file_name($_POST['fname']);
	if (isset($_POST['fid'])) {
		$fid = (int) $_POST['fid'];
		$oname = isset($_POST['oname']) ? sanitize_file_name($_POST['oname']) : '';
		if (isset($_POST['del'])) {
			if (!file_exists(SB_ABSPATH.sb_get_option('upload_dir').$fname) || unlink(SB_ABSPATH.sb_get_option('upload_dir').$fname)) {
				$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}sb_stuff WHERE id = %d;", $fid));
				echo 'deleted';
				die();
			} else {
				echo 'failed';
				die();
			}
		} else {
			if (defined('IS_MU') && IS_MU) {
				$file_allowed = FALSE;
				$allowed_extensions = explode(" ", get_site_option("upload_filetypes"));
				foreach ($allowed_extensions as $ext) {
					if (substr(strtolower($fname), -(strlen($ext)+1)) == ".".strtolower($ext))
						$file_allowed = TRUE;
				}
			} else {
				$file_allowed = TRUE;
			}
			if ($file_allowed) {
				if ((validate_file (sb_get_option('upload_dir').$oname) === 0) && !is_writable(SB_ABSPATH.sb_get_option('upload_dir').$fname) && rename(SB_ABSPATH.sb_get_option('upload_dir').$oname, SB_ABSPATH.sb_get_option('upload_dir').$fname)) {
					$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}sb_stuff SET name = %s WHERE id = %d;", $fname, $fid));
					echo 'renamed';
					die();
				} else {
					echo 'failed';
					die();
				}
			} else {
				echo 'forbidden';
				die();
			}
		}
	}
} elseif (isset($_POST['fetch'])) { // ajax pagination
    if (function_exists('wp_timezone_override_offset')) { wp_timezone_override_offset(); }
	$st = (int) $_POST['fetch'] - 1;
	if (!empty($_POST['title'])) {
		$cond = $wpdb->prepare("and m.title LIKE '%%%s%%' ", sanitize_text_field($_POST['title']));
	} else
		$cond = '';
	if (isset($_POST['preacher']) && $_POST['preacher'] != 0) {
		$cond .= $wpdb->prepare('and m.preacher_id = %d ', (int) $_POST['preacher']);
	}
	if (isset($_POST['series']) && $_POST['series'] != 0) {
		$cond .= $wpdb->prepare('and m.series_id = %d ', (int) $_POST['series']);
	}
	$limit = (int) sb_get_option('sermons_per_page');
	$m = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS m.id, m.title, m.datetime, p.name as pname, s.name as sname, ss.name as ssname
	FROM {$wpdb->prefix}sb_sermons as m
	LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id
	LEFT JOIN {$wpdb->prefix}sb_services as s ON m.service_id = s.id
	LEFT JOIN {$wpdb->prefix}sb_series as ss ON m.series_id = ss.id
	WHERE 1=1 {$cond}
	ORDER BY m.datetime desc, s.time desc LIMIT {$st}, {$limit}");

	$cnt = (int) $wpdb->get_var("SELECT FOUND_ROWS()");
	$i = 0;
	?>
	<?php foreach ($m as $sermon): ?>
		<tr class="<?php echo ++$i % 2 == 0 ? 'alternate' : '' ?>">
			<th style="text-align:center" scope="row"><?php echo (int) $sermon->id ?></th>
			<td><?php echo esc_html(stripslashes($sermon->title)) ?></td>
			<td><?php echo esc_html(stripslashes($sermon->pname)) ?></td>
			<td><?php echo ($sermon->datetime == '1970-01-01 00:00:00') ? __('Unknown', 'sermon-browser') : date_i18n('d %b %y', strtotime($sermon->datetime)); ?></td>
			<td><?php echo esc_html(stripslashes($sermon->sname)) ?></td>
			<td><?php echo esc_html(stripslashes($sermon->ssname)) ?></td>
			<td><?php echo sb_sermon_stats($sermon->id) ?></td>
			<td style="text-align:center">
				<?php //Security check
						if (current_user_can('edit_posts')) { ?>
						<a href="<?php echo admin_url("admin.php?page=sermon-browser/new_sermon.php&mid=" . (int)$sermon->id); ?>"><?php _e('Edit', 'sermon-browser') ?></a> | <a onclick="return confirm('Are you sure?')" href="<?php echo admin_url("admin.php?page=sermon-browser/sermon.php&mid=" . (int)$sermon->id); ?>"><?php _e('Delete', 'sermon-browser'); ?></a> |
				<?php } ?>
				<a href="<?php echo esc_url(sb_display_url().sb_query_char(true).'sermon_id='.(int)$sermon->id);?>">View</a>
			</td>
		</tr>
	<?php endforeach ?>
	<script type="text/javascript">
	<?php if($cnt < $limit || $cnt <= $st + $limit): ?>
		jQuery('#right').css('display','none');
	<?php elseif($cnt > $st + $limit): ?>
		jQuery('#right').css('display','');
	<?php endif ?>
	</script>
	<?php
} elseif (isset($_POST['fetchU']) || isset($_POST['fetchL']) || isset($_POST['search'])) { // ajax pagination (uploads)
	$limit = (int) sb_get_option('sermons_per_page');
	if (isset($_POST['fetchU'])) {
		$st = (int) $_POST['fetchU'] - 1;
		$abc = $wpdb->get_results($wpdb->prepare("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id = 0 AND f.type = 'file' ORDER BY f.name LIMIT %d, %d", $st, $limit));
	} elseif (isset($_POST['fetchL'])) {
		$st = (int) $_POST['fetchL'] - 1;
		$abc = $wpdb->get_results($wpdb->prepare("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.sermon_id <> 0 AND f.type = 'file' ORDER BY f.name LIMIT %d, %d", $st, $limit));
	} else {
		$s = sanitize_text_field($_POST['search']);
		$abc = $wpdb->get_results($wpdb->prepare("SELECT f.*, s.title FROM {$wpdb->prefix}sb_stuff AS f LEFT JOIN {$wpdb->prefix}sb_sermons AS s ON f.sermon_id = s.id WHERE f.name LIKE '%%%s%%' AND f.type = 'file' ORDER BY f.name;", $s));
	}
	$i = 0;
?>
<?php if (count((array)$abc) >= 1): ?>
	<?php foreach ($abc as $file): ?>
		<tr class="file <?php echo (++$i % 2 == 0) ? 'alternate' : '' ?>" id="<?php echo isset($_POST['fetchU']) ? '' : 's' ?>file<?php echo (int)$file->id ?>">
			<th style="text-align:center" scope="row"><?php echo (int)$file->id ?></th>
			<td id="<?php echo isset($_POST['fetchU']) ? '' : 's' ?><?php echo (int)$file->id ?>"><?php echo esc_html(substr($file->name, 0, strrpos($file->name, '.'))) ?></td>
			<td style="text-align:center"><?php 
				$ext = substr($file->name, strrpos($file->name, '.') + 1);
				echo esc_html(isset($filetypes[$ext]['name']) ? $filetypes[$ext]['name'] : strtoupper($ext)); 
			?></td>
			<?php if (!isset($_POST['fetchU'])) { ?><td><?php echo esc_html(stripslashes($file->title)) ?></td><?php } ?>
			<td style="text-align:center">
				<script type="text/javascript" language="javascript">
				function deletelinked_<?php echo (int)$file->id;?>(filename, filesermon) {
					if (confirm('Do you really want to delete '+filename+'?')) {
						if (filesermon != '') {
							return confirm('This file is linked to the sermon called ['+filesermon+']. Are you sure you want to delete it?');
						}
						return true;
					}
					return false;
				}
				</script>
				<?php if (isset($_POST['fetchU'])) { ?><a id="" href="<?php echo admin_url("admin.php?page=sermon-browser/new_sermon.php&amp;getid3=".(int)$file->id); ?>"><?php _e('Create sermon', 'sermon-browser') ?></a> | <?php } ?>
				<a id="link<?php echo (int)$file->id ?>" href="javascript:rename(<?php echo (int)$file->id ?>, '<?php echo esc_js($file->name) ?>')"><?php _e('Rename', 'sermon-browser') ?></a> | <a onclick="return deletelinked_<?php echo (int)$file->id;?>('<?php echo esc_js($file->name) ?>', '<?php echo esc_js($file->title) ?>');" href="javascript:kill(<?php echo (int)$file->id ?>, '<?php echo esc_js($file->name) ?>');"><?php _e('Delete', 'sermon-browser') ?></a>
			</td>
		</tr>
	<?php endforeach ?>
<?php else: ?>
	<tr>
		<td colspan="5"><?php _e('No results', 'sermon-browser') ?></td>
	</tr>
<?php endif ?>
<?php
}
die();
?>
