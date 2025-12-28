<?php
// Error message for people using the old function
function display_sermons($options = array()) {
	echo "This function is now deprecated. Use sb_display_sermons or the sermon browser widget, instead.";
}
// Function to display sermons for users to add to their template
function sb_display_sermons($options = array()) {
	$default = array(
		'display_preacher' => 1,
		'display_passage' => 1,
		'display_date' => 1,
		'preacher' => 0,
		'service' => 0,
		'series' => 0,
		'limit' => 5,
		'url_only' => 0,
	);
	$options = array_merge($default, (array) $options);
	extract($options);

	// PHP 8.5 Fix: Ensure filter variables are initialized for immediate rendering
	$preacher = (int)($preacher ?? 0);
	$service = (int)($service ?? 0);
	$series = (int)($series ?? 0);

	if ($url_only == 1)
		$limit = 1;
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit
	);
	if ($url_only == 1)
		sb_print_sermon_link($sermons[0] ?? null, true);
	else {
		echo "<ul class=\"sermon-widget\">\r";
		foreach ((array) $sermons as $sermon) {
			echo "\t<li>";
			echo "<span class=\"sermon-title\"><a href=\"";
			sb_print_sermon_link($sermon, true);
			echo "\">".esc_html(stripslashes($sermon->title))."</a></span>";
			if ($display_passage) {
				$foo = unserialize($sermon->start);
				$bar = unserialize($sermon->end);
				echo "<span class=\"sermon-passage\"> (".sb_get_books($foo[0] ?? null, $bar[0] ?? null).")</span>";
			}
			if ($display_preacher) {
				echo "<span class=\"sermon-preacher\">".__('by', 'sermon-browser')." <a href=\"";
				sb_print_preacher_link($sermon);
				echo "\">".esc_html(stripslashes($sermon->preacher))."</a></span>";
			}
			if ($display_date)
				echo " <span class=\"sermon-date\">".__('on', 'sermon-browser')." ".sb_formatted_date ($sermon)."</span>";
			echo ".</li>\r";
		}
		echo "</ul>\r";
	}
}

// Displays the widget
function sb_widget_sermon($args, $widget_args=1) {
	extract( (array)$args, EXTR_SKIP );
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( (array)$widget_args, EXTR_SKIP );
	$options = sb_get_option('sermons_widget_options');
	if ( !isset($options[$number]) )
		return;

	// PHP 8.5 Fix: Prevent initialization failure by ensuring keys exist
	$instance = $options[$number] ?? array();
	$preacher = (int)($instance['preacher'] ?? 0);
	$service  = (int)($instance['service'] ?? 0);
	$series   = (int)($instance['series'] ?? 0);
	$limit    = (int)($instance['limit'] ?? 5);
	$title    = $instance['title'] ?? '';
	$book     = $instance['book'] ?? 0;
	$preacherz = $instance['preacherz'] ?? 0;
	$date     = $instance['date'] ?? 0;

	echo $before_widget;
	echo $before_title . esc_html($title) . $after_title;
	$sermons = sb_get_sermons(array(
			'preacher' => $preacher,
			'service' => $service,
			'series' => $series
		),
		array(), 1, $limit
	);
	$i=0;
	echo "<ul class=\"sermon-widget\">";
	foreach ((array) $sermons as $sermon){
		$i++;
		echo "<li><span class=\"sermon-title\">";
		echo '<a href="'.esc_url(sb_build_url(array('sermon_id' => (int)$sermon->id), true)).'">'.esc_html(stripslashes($sermon->title)).'</a></span>';
		if ($book) {
			$foo = unserialize($sermon->start);
			$bar = unserialize($sermon->end);
			if (isset ($foo[0]) && isset($bar[0]))
				echo " <span class=\"sermon-passage\">(".sb_get_books($foo[0], $bar[0]).")</span>";
		}
		if ($preacherz) {
			echo " <span class=\"sermon-preacher\">".__('by', 'sermon-browser')." <a href=\"";
			sb_print_preacher_link($sermon);
			echo "\">".esc_html(stripslashes($sermon->preacher))."</a></span>";
		}
		if ($date)
			echo " <span class=\"sermon-date\">".__(' on ', 'sermon-browser').sb_formatted_date ($sermon)."</span>";
		echo ".</li>";
	}
	echo "</ul>";
	echo $after_widget;
}

// Displays the tag cloud in the sidebar
function sb_widget_tag_cloud ($args) {
	extract((array)$args);
	echo $before_widget;
	echo $before_title.__('Sermon Browser tags', 'sermon-browser').$after_title;
	sb_print_tag_clouds();
	echo $after_widget;
}

function sb_admin_bar_menu () {
	global $wp_admin_bar;
	if (!current_user_can('edit_posts') || !class_exists('WP_Admin_Bar'))
		return;
	if (isset($_GET['sermon_id']) && (int)$_GET['sermon_id'] != 0 && current_user_can('publish_pages')) {
		$wp_admin_bar->add_menu(array('id' => 'sermon-browser-menu', 'title' => __('Edit Sermon', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php&mid='.(int)$_GET['sermon_id'])));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-sermons', 'title' => __('List Sermons', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/sermon.php')));
	} else {
		$wp_admin_bar->add_menu(array('id' => 'sermon-browser-menu', 'title' => __('Sermons', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/sermon.php')));
		if (current_user_can('publish_pages'))
			$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-add', 'title' => __('Add Sermon', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php')));
	}
	if (current_user_can('upload_files'))
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-files', 'title' => __('Files', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/files.php')));
	if (current_user_can('manage_categories')) {
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-preachers', 'title' => __('Preachers', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/preachers.php')));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-series', 'title' => __('Series &amp; Services', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/manage.php')));
	}
	if (current_user_can('manage_options')) {
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-options', 'title' => __('Options', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/options.php')));
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-series-templates', 'title' => __('Templates', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/templates.php')));
	}
	if (current_user_can('edit_plugins'))
		$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-uninstall', 'title' => __('Uninstall', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/uninstall.php')));
	$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-help', 'title' => __('Help', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/help.php')));
	$wp_admin_bar->add_menu(array('parent' => 'sermon-browser-menu', 'id' => 'sermon-browser-japan', 'title' => __('Pray for Japan', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/japan.php')));
	$wp_admin_bar->add_menu(array('parent' => 'new-content', 'id' => 'sermon-browser-add2', 'title' => __('Sermon', 'sermon-browser'), 'href' => admin_url('admin.php?page=sermon-browser/new_sermon.php')));
}

// Sorts an object by rank
function sb_sort_object($a,$b) {
	if(  $a->rank ==  $b->rank )
		return 0;
	return ($a->rank < $b->rank) ? -1 : 1;
}

// Displays the most popular sermons in the sidebar
function sb_widget_popular ($args) {
	global $wpdb;
	extract((array)$args);
	if (!isset($suffix))
		$suffix = '_w';
	if (!isset($options))
		$options = sb_get_option('popular_widget_options');
	echo $before_widget;
	if (isset($options['title']) && $options['title'] != '')
		echo $before_title.esc_html($options['title']).$after_title;
	$jscript = '';
	$trigger = array();
	$output = array();
	$series_final = array();
	$preachers_final = array();
	if (isset($options['display_sermons']) && $options['display_sermons']) {
		$lim = (int)($options['limit'] ?? 5);
		$sermons = $wpdb->get_results("SELECT sermons.id, sermons.title, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   GROUP BY sermons.id ORDER BY total DESC LIMIT 0, {$lim}");
		if ($sermons) {
			$output['sermons'] = '<div class="popular-sermons'.$suffix.'"><ul>';
			foreach ($sermons as $sermon)
				$output['sermons'] .= '<li><a href="'.esc_url(sb_build_url(array('sermon_id' => (int)$sermon->id), true)).'">'.esc_html($sermon->title).'</a></li>';
			$output['sermons'] .= '</ul></div>';
			$trigger[] = '<a id="popular_sermons_trigger'.$suffix.'" href="#">Sermons</a>';
			$jscript .=  'jQuery("#popular_sermons_trigger'.$suffix.'").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_series_trigger'.$suffix.'").removeAttr("style");
							jQuery("#popular_preachers_trigger'.$suffix.'").removeAttr("style");
							jQuery.setSbCookie ("sermons");
							jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['sermons'] ?? '').'").fadeIn("slow");
							});
							return false;
						});';
		}
	}

	if (isset($options['display_series']) && $options['display_series']) {
		$series1 = $wpdb->get_results("SELECT series.id, series.name, avg(stuff.count) AS average
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   LEFT JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY average DESC");
		$series2 = $wpdb->get_results("SELECT series.id, sum(stuff.count) AS total
									   FROM {$wpdb->prefix}sb_stuff AS stuff
									   LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
									   LEFT JOIN {$wpdb->prefix}sb_series AS series ON sermons.series_id = series.id
									   GROUP BY series.id ORDER BY total DESC");
		if ($series1) {
			$i=1;
			foreach ($series1 as $series) {
				if (!isset($series_final[$series->id])) { $series_final[$series->id] = new stdClass(); }
				$series_final[$series->id]->name = $series->name;
				$series_final[$series->id]->rank = $i;
				$series_final[$series->id]->id = $series->id;
				$i++;
			}
			$i=1;
			foreach ($series2 as $series) {
				if (isset($series_final[$series->id])) { $series_final[$series->id]->rank += $i; }
				$i++;
			}
			usort($series_final,'sb_sort_object');
			$lim = (int)($options['limit'] ?? 5);
			$series_final = array_slice($series_final, 0, $lim);
			$output['series'] = '<div class="popular-series'.$suffix.'"><ul>';
			foreach ($series_final as $series)
				$output['series'] .= '<li><a href="'.esc_url(sb_build_url(array('series' => (int)$series->id), true)).'">'.esc_html($series->name).'</a></li>';
			$output['series'] .= '</ul></div>';
		}
		$trigger[] = '<a id="popular_series_trigger'.$suffix.'" href="#">Series</a>';
		$jscript .=	 'jQuery("#popular_series_trigger'.$suffix.'").click(function() {
							jQuery(this).attr("style", "font-weight:bold");
							jQuery("#popular_sermons_trigger'.$suffix.'").removeAttr("style");
							jQuery("#popular_preachers_trigger'.$suffix.'").removeAttr("style");
							jQuery.setSbCookie ("series");
							jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
								jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['series'] ?? '').'").fadeIn("slow");
							});
							return false;
						});';
	}

	if (isset($options['display_preachers']) && $options['display_preachers']) {
		$preachers1 = $wpdb->get_results("SELECT preachers.id, preachers.name, avg(stuff.count) AS average
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  LEFT JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY average DESC");
		$preachers2 = $wpdb->get_results("SELECT preachers.id, sum(stuff.count) AS total
										  FROM {$wpdb->prefix}sb_stuff AS stuff
										  LEFT JOIN {$wpdb->prefix}sb_sermons AS sermons ON stuff.sermon_id = sermons.id
										  LEFT JOIN {$wpdb->prefix}sb_preachers AS preachers ON sermons.preacher_id = preachers.id
										  GROUP BY preachers.id
										  ORDER BY total DESC");
		if ($preachers1) {
			$i=1;
			foreach ($preachers1 as $preacher) {
				if (!isset($preachers_final[$preacher->id])) { $preachers_final[$preacher->id] = new stdClass(); }
				$preachers_final[$preacher->id]->name = $preacher->name;
				$preachers_final[$preacher->id]->rank = $i;
				$preachers_final[$preacher->id]->id = $preacher->id;
				$i++;
			}
			$i=1;
			foreach ($preachers2 as $preacher) {
				if (isset($preachers_final[$preacher->id])) { $preachers_final[$preacher->id]->rank += $i; }
				$i++;
			}
			usort($preachers_final,'sb_sort_object');
			$lim = (int)($options['limit'] ?? 5);
			$preachers_final = array_slice($preachers_final, 0, $lim);
			$output['preachers'] = '<div class="popular-preachers'.$suffix.'"><ul>';
			foreach ($preachers_final as $preacher)
				$output['preachers'] .= '<li><a href="'.esc_url(sb_build_url(array('preacher' => (int)$preacher->id), true)).'">'.esc_html($preacher->name).'</a></li>';
			$output['preachers'] .= '</ul></div>';
			$trigger[] = '<a id="popular_preachers_trigger'.$suffix.'" href="#">Preachers</a>';
			$jscript .=	 'jQuery("#popular_preachers_trigger'.$suffix.'").click(function() {
								jQuery(this).attr("style", "font-weight:bold");
								jQuery("#popular_series_trigger'.$suffix.'").removeAttr("style");
								jQuery("#popular_sermons_trigger'.$suffix.'").removeAttr("style");
								jQuery.setSbCookie("preachers");
								jQuery("#sb_popular_wrapper'.$suffix.'").fadeOut("slow", function() {
									jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['preachers'] ?? '').'").fadeIn("slow");
								});
								return false;
							 });';
		}
	}

$jscript .= 'if (jQuery.getSbCookie() == "preachers") { 
                jQuery("#popular_preachers_trigger'.$suffix.'").attr("style", "font-weight:bold"); 
                jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['preachers'] ?? '').'")
            };';
$jscript .= 'if (jQuery.getSbCookie() == "series") { 
                jQuery("#popular_series_trigger'.$suffix.'").attr("style", "font-weight:bold"); 
                jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['series'] ?? '').'")
            };';
$jscript .= 'if (jQuery.getSbCookie() == "sermons") { 
                jQuery("#popular_sermons_trigger'.$suffix.'").attr("style", "font-weight:bold"); 
                jQuery("#sb_popular_wrapper'.$suffix.'").html("'.addslashes($output['sermons'] ?? '').'")
            };';
	echo '<p>'.implode(' | ', $trigger).'</p>';
	echo '<div id="sb_popular_wrapper'.$suffix.'">'.current($output).'</div>';
	echo '<script type="text/javascript">jQuery.setSbCookie = function (value) {
											document.cookie = "sb_popular="+encodeURIComponent(value);
										 };</script>';
	echo '<script type="text/javascript">jQuery.getSbCookie = function () {
											var cookieValue = null;
											if (document.cookie && document.cookie != "") {
												var cookies = document.cookie.split(";");
												for (var i = 0; i < cookies.length; i++) {
													var cookie = jQuery.trim(cookies[i]);
													var name = "sb_popular";
													if (cookie.substring(0, name.length + 1) == (name + "=")) {
														cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
														break;
													}
												}
											}
										 return cookieValue;
										 }</script>';
	echo '<script type="text/javascript">jQuery(document).ready(function() {'.$jscript.'});</script>';
	echo $after_widget;
}

function sb_print_most_popular() {
	$args['before_widget'] = '<div id="sermon_most_popular" style="border: 1px solid ; margin: 0pt 0pt 1em 2em; padding: 5px; float: right; font-size: 75%; line-height: 1em">';
	$args['after_widget'] = '</div>';
	$args['before_title'] = '<span class="popular_title">';
	$args['after_title'] = '</span>';
	$args['suffix'] = '_f';
	sb_widget_popular ($args);
}

//Modify page title
function sb_page_title($title) {
	global $wpdb;
	if (isset($_GET['sermon_id'])) {
		$id = (int)$_GET['sermon_id'];
		$sermon = $wpdb->get_row($wpdb->prepare("SELECT m.title, p.name FROM {$wpdb->prefix}sb_sermons as m LEFT JOIN {$wpdb->prefix}sb_preachers as p ON m.preacher_id = p.id where m.id = %d", $id));
		if ($sermon)
			return $title.' ('.esc_html(stripslashes($sermon->title)).' - '.esc_html(stripslashes($sermon->name)).')';
		else
			return $title.' ('.__('No sermons found.', 'sermon-browser').')';
	}
	else
		return $title;
}

//Downloads external webpage. Used to add Bible passages to sermon page.
function sb_download_page ($page_url, $headers = array()) {
	$response = wp_remote_get($page_url, array ('headers' => $headers));
	if (is_array ($response)) {
		return $response['body'] ?? '';
	}
    return '';
}

// Returns human friendly Bible reference (e.g. John 3:1-16, not John 3:1-John 3:16)
function sb_tidy_reference ($start, $end, $add_link = FALSE) {
	if (!isset($start['book']) || !trim($start['book'])) {
		return "";
	}
	$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));
	$start_book = $translated_books[trim($start['book'])] ?? $start['book'];
	$end_book = $translated_books[trim($end['book'] ?? '')] ?? ($end['book'] ?? '');
	$start_chapter = trim($start['chapter'] ?? '');
	$end_chapter = trim($end['chapter'] ?? '');
	$start_verse = trim($start['verse'] ?? '');
	$end_verse = trim($end['verse'] ?? '');
	if ($add_link) {
		$start_book = "<a href=\"".esc_url(sb_get_book_link(trim($start['book'])))."\">".esc_html($start_book)."</a>";
		$end_book = "<a href=\"".esc_url(sb_get_book_link(trim($end['book'] ?? '')))."\">".esc_html($end_book)."</a>";
	}
	if ($start_book == $end_book) {
		if ($start_chapter == $end_chapter) {
			if ($start_verse == $end_verse) {
				$reference = "$start_book $start_chapter:$start_verse";
			} else {
				$reference = "$start_book $start_chapter:$start_verse-$end_verse";
			}
		} else {
			 $reference = "$start_book $start_chapter:$start_verse-$end_chapter:$end_verse";
		}
	} else {
		$reference =  "$start_book $start_chapter:$start_verse - $end_book $end_chapter:$end_verse";
	}
	return $reference;
}

//Print unstyled bible passage
function sb_print_bible_passage ($start, $end) {
	echo "<p class='bible-passage'>".sb_tidy_reference($start, $end)."</p>";
}

// Returns human friendly Bible reference with link to filter
function sb_get_books($start, $end) {
	return sb_tidy_reference ($start, $end, TRUE);
}

//Add Bible text to single sermon page
function sb_add_bible_text ($start, $end, $version) {
	if ($version == 'esv')
		return sb_add_esv_text ($start, $end);
	elseif ($version == 'net')
		return sb_add_net_text ($start, $end);
	else
		return sb_add_other_bibles ($start, $end, $version);
}

//Returns ESV text
function sb_add_esv_text ($start, $end) {
	$api_key = sb_get_option('esv_api_key');
	if ($api_key) {
		$header = array('Authorization' => 'Token '.$api_key);
		$esv_url = 'https://api.esv.org/v3/passage/html?q='.rawurlencode(sb_tidy_reference ($start, $end)).'&include-headings=false&include-footnotes=false';
		$api_body = sb_download_page ($esv_url, $header);
		$decode = json_decode ($api_body);
		if (is_object($decode) && isset($decode->passages) && is_array($decode->passages)) {
			return implode ('', $decode->passages);
}
	} else {
		return sb_add_bible_text($start, $end, 'kjv');
	}
    return '';
}

// Converts XML string to object
function sb_get_xml ($content) {
    if (empty($content)) return null;
    try {
        return new SimpleXMLElement($content);
    } catch (Exception $e) {
        return null; 
    }
}

//Returns NET Bible text
function sb_add_net_text ($start, $end) {
	$reference = str_replace(' ', '+', sb_tidy_reference ($start, $end));
	$old_chapter = $start['chapter'] ?? '';
	$net_url = "http://labs.bible.org/api/xml/verse.php?passage={$reference}";
	$xml = sb_get_xml(sb_download_page($net_url.'&formatting=para'));
	$output='';
	if ($xml && isset($xml->item)) {
        foreach ($xml->item as $item) {
            if ($item->text != '[[EMPTY]]') {
                $text = (string)$item->text;
                if (substr($text, 0, 8) == '<p class') {
                    $paraend = stripos($text, '>', 8)+1;
                    $output .= "\n".substr($text, 0, $paraend);
                    $text = substr ($text, $paraend);
                }
                if ($old_chapter == (string)$item->chapter) {
                    $output .= " <span class=\"verse-num\">".(string)$item->verse."</span>";
                } else {
                    $output .= " <span class=\"chapter-num\">".(string)$item->chapter.":".(string)$item->verse."</span> ";
                    $old_chapter = (string)$item->chapter;
                }
                $output .= 	$text;
            }
        }
    }
	return "<div class=\"net\">\r<h2>".sb_tidy_reference ($start, $end)."</h2><p>{$output} (<a href=\"http://net.bible.org/?{$reference}\">NET Bible</a>)</p></div>";
}

//Returns Bible text using SermonBrowser's own API
function sb_add_other_bibles ($start, $end, $version) {
	if ($version == 'hnv')
		return '<div class="'.$version.'"><p>Sorry, the Hebrew Names Version is no longer available.</p></div>';
	$reference = str_replace(' ', '+', sb_tidy_reference ($start, $end));
	$reference = str_replace('Psalm+', 'Psalms+', $reference);		//Fix for API "Psalms" vs "Psalm"
	$old_chapter = $start['chapter'] ?? '';
	$url = "http://api.preachingcentral.com/bible.php?passage={$reference}&version={$version}";
	$xml = sb_get_xml(sb_download_page($url));
	$output='';
	if ($xml && isset($xml->range->item)) {
		foreach ($xml->range->item as $item) {
			if ($item->text != '[[EMPTY]]') {
				if ($old_chapter == (string)$item->chapter) {
					$output .= " <span class=\"verse-num\">".(string)$item->verse."</span>";
				} else {
					$output .= " <span class=\"chapter-num\">".(string)$item->chapter.":".(string)$item->verse."</span> ";
					$old_chapter = (string)$item->chapter;
				}
				$output .=	 (string)$item->text;
			}
		}
	}
	return '<div class="'.esc_attr($version).'"><h2>'.sb_tidy_reference ($start, $end). '</h2><p>'.$output.' (<a href="http://biblepro.bibleocean.com/dox/default.aspx">'. strtoupper(esc_html($version)). '</a>)</p></div>';
}

//Adds edit sermon link if current user has edit rights
function sb_edit_link ($id) {
	if (current_user_can('publish_posts')) {
		$id = (int)$id;
		echo '<div class="sb_edit_link"><a href="'.esc_url(admin_url('admin.php?page=sermon-browser/new_sermon.php&mid='.$id)).'">Edit Sermon</a></div>';
	}
}

// Returns URL for search links
// Relative links now deprecated
function sb_build_url($arr, $clear = false) {
	global $wpdb;
	// Word list for URL building purpose
	$wl = array('preacher', 'title', 'date', 'enddate', 'series', 'service', 'sortby', 'dir', 'book', 'stag', 'podcast');
	$foo = array_merge((array) $_GET, (array) $_POST, (array)$arr);
	$bar = array(); // MUST initialize this variable explicitly
    foreach ($foo as $k => $v) {
		if (in_array($k, array_keys((array)$arr)) || (in_array($k, $wl) && !$clear)) {
			$bar[] = rawurlencode($k).'='.rawurlencode($v ?? '');
		}
	}
	if (!empty($bar)) {
			return esc_url(sb_display_url().sb_query_char().implode('&', $bar));
	}
	else {
		return sb_display_url();
	}
}

// Adds javascript and CSS where required
function sb_add_headers() {
	global $post, $wpdb, $wp_scripts;
	if (isset($post->ID) && $post->ID != '') {
		echo "\r";
		echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".esc_attr(__('Sermon podcast', 'sermon-browser'))."\" href=\"".esc_url(sb_get_option('podcast_url'))."\" />\r";
		wp_enqueue_style('sb_style');
		$pageid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND (post_status = 'publish' OR post_status = 'private') AND ID=%d AND post_date < NOW();", '%[sermons%', (int)$post->ID));
		if ($pageid !== NULL) {
			if (isset($_REQUEST['title']) || isset($_REQUEST['preacher']) || isset($_REQUEST['date']) || isset($_REQUEST['enddate']) || isset($_REQUEST['series']) || isset($_REQUEST['service']) || isset($_REQUEST['book']) || isset($_REQUEST['stag']))
				echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"".esc_attr(__('Custom sermon podcast', 'sermon-browser'))."\" href=\"".esc_url(sb_podcast_url())."\" />\r";
			wp_enqueue_script('jquery');
		} else {
			$sidebars_widgets = wp_get_sidebars_widgets();
			if (isset($sidebars_widgets['wp_inactive_widgets']))
				unset($sidebars_widgets['wp_inactive_widgets']);
			if (is_array($sidebars_widgets))
				foreach ($sidebars_widgets as $sb_w)
					if (is_array($sb_w) && in_array('sermon-browser-popular', $sb_w))
						wp_enqueue_script('jquery');
		}
	}
}

// Formats date into words
function sb_formatted_date ($sermon) {
	if (isset($sermon->time) && $sermon->time != '')
		$sermon_time = $sermon->time;
	else
		$sermon_time = sb_default_time ($sermon->sid ?? 0);
	if ($sermon->datetime == '1970-01-01 00:00:00')
		return __('Unknown Date', 'sermon-browser');
	else
		return date_i18n(get_option('date_format'), strtotime($sermon->datetime));
}

// Returns podcast URL
function sb_podcast_url() {
	return str_replace(' ', '%20', sb_build_url(array('podcast' => 1, 'dir'=>'desc', 'sortby'=>'m.datetime')));
}

// Prints sermon search URL
function sb_print_sermon_link($sermon, $echo = true) {
    if (!$sermon) return '';
	if ($echo)
		echo sb_build_url(array('sermon_id' => (int)$sermon->id), true);
	else
		return sb_build_url(array('sermon_id' => (int)$sermon->id), true);
}

// Prints preacher search URL
function sb_print_preacher_link($sermon) {
	echo sb_build_url(array('preacher' => (int)$sermon->pid), false);
}

// Prints series search URL
function sb_print_series_link($sermon) {
	echo sb_build_url(array('series' => (int)$sermon->ssid), false);
}

// Prints service search URL
function sb_print_service_link($sermon) {
	echo sb_build_url(array('service' => (int)$sermon->sid), false);
}

// Prints bible book search URL
function sb_get_book_link($book_name) {
	return sb_build_url(array('book' => $book_name), false);
}

// Prints tag search URL
function sb_get_tag_link($tag) {
	return sb_build_url(array('stag' => $tag), false);
}

// Prints tags
function sb_print_tags($tags) {
    $out = array();
    foreach ((array) $tags as $tag) {
        $tag = stripslashes($tag);
        $out[] = '<a href="'.esc_url(sb_get_tag_link($tag)).'">'.esc_html($tag).'</a>';
    }
    // Fixed: glue string first, array second
    $tags_output = implode(', ', $out);
    echo $tags_output;
}

//Prints tag cloud
function sb_print_tag_clouds($minfont=80, $maxfont=150) {
	global $wpdb;
	$rawtags = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sb_tags as t RIGHT JOIN {$wpdb->prefix}sb_sermons_tags as st ON t.id = st.tag_id");
	$cnt = array();
    foreach ($rawtags as $tag) {
		if (isset($cnt[$tag->name]))
			$cnt[$tag->name]++;
		else
			$cnt[$tag->name] = 1;
	}
	unset($cnt['']);
	$fontrange = $maxfont - $minfont;
	$maxcnt = 0;
	$mincnt = 1000000;
	foreach ($cnt as $cur) {
		if ($cur > $maxcnt) $maxcnt = $cur;
		if ($cur < $mincnt) $mincnt = $cur;
	}
	$minlog = log($mincnt > 0 ? $mincnt : 1);
	$maxlog = log($maxcnt > 0 ? $maxcnt : 1);
	$logrange = $maxlog == $minlog ? 1 : $maxlog - $minlog;
	arsort($cnt);
    $out = array();
	foreach ($cnt as $tag => $count) {
		$size = $minfont + $fontrange * (log($count) - $minlog) / $logrange;
		$out[] = '<a style="font-size:'.(int) $size.'%" href="'.esc_url(sb_get_tag_link($tag)).'">'.esc_html($tag).'</a>';
	}
	echo implode(' ', $out);
}

/**
* Displays the 'Next page' link
*/
function sb_print_next_page_link() {
	global $record_count;
	
	// DYNAMIC: Respects your Admin "Sermons per page" setting
	$per_page = (int)sb_get_option('sermons_per_page');
	if ($per_page <= 0) $per_page = 10;
	
	$page = isset($_REQUEST['pagenum']) ? max(1, (int)$_REQUEST['pagenum']) : 1;

	// Only show "Next" if total sermons found is greater than current display
	if ($record_count > ($page * $per_page)) {
		$next_page = $page + 1;
		echo '<a href="' . esc_url(add_query_arg('pagenum', $next_page)) . '">' . __('Next page', 'sermon-browser') . ' &raquo;</a>';
	}
}

/**
* Displays the 'Previous page' link
*/
function sb_print_prev_page_link() {
	$page = isset($_REQUEST['pagenum']) ? (int)$_REQUEST['pagenum'] : 1;

	if ($page > 1) {
		$prev_page = $page - 1;
		// If going back to page 1, we remove the pagenum arg for a cleaner URL
		if ($prev_page == 1) {
			echo '<a href="' . esc_url(remove_query_arg('pagenum')) . '">&laquo; ' . __('Previous page', 'sermon-browser') . '</a>';
		} else {
			echo '<a href="' . esc_url(add_query_arg('pagenum', $prev_page)) . '">&laquo; ' . __('Previous page', 'sermon-browser') . '</a>';
		}
	}
}

// Print link to attached files
function sb_print_url($url) {
	require (SB_INCLUDES_DIR.'/filetypes.php');
	$pathinfo = pathinfo($url);
	$ext = $pathinfo['extension'] ?? '';
	if ((substr($url,0,7) == "http://") or (substr($url,0,8) == 'https://'))
		$url=sb_display_url().sb_query_char(FALSE).'show&url='.rawurlencode($url);
	else
		if (strtolower($ext) == 'mp3')
			$url=sb_display_url().sb_query_char(FALSE).'show&file_name='.rawurlencode($url);
		else
			$url=sb_display_url().sb_query_char(FALSE).'download&file_name='.rawurlencode($url);
	$uicon = $default_site_icon;
	foreach ($siteicons as $site => $icon) {
		if (strpos($url, $site) !== false) {
			$uicon = $icon;
			break;
		}
	}
	$uicon = isset($filetypes[$ext]['icon']) ? $filetypes[$ext]['icon'] : $uicon;
	if (strtolower($ext) == 'mp3') {
		if (do_shortcode(sb_get_option('mp3_shortcode')) != sb_get_option('mp3_shortcode')) {
			echo do_shortcode(str_ireplace('%SERMONURL%', $url, sb_get_option('mp3_shortcode')));
			return;
		}
	}
	$uicon = SB_PLUGIN_URL.'/sb-includes/icons/'.$uicon;
	if (!isset($filetypes[$ext]['name']))
		$ft_name = sprintf(__('%s file', 'sermon-browser'), addslashes($ext));
	else
		$ft_name = addslashes($filetypes[$ext]['name']);
	echo "<a href=\"".esc_url($url)."\"><img class=\"site-icon\" alt=\"".esc_attr($ft_name)."\" title=\"".esc_attr($ft_name)."\" src=\"".esc_url($uicon)."\"></a>";
}

// Print link to attached external URLs
function sb_print_url_link($url) {
	echo '<div class="sermon_file">';
	sb_print_url ($url);
	if (strtolower(substr($url, -4)) == ".mp3") {
		if ((substr($url,0,7) == "http://") or (substr($url,0,8) == 'https://')) {
			$param="url"; }
		else {
			$param="file_name"; }
		$raw_url = rawurlencode($url);
        echo ' <a href="'.esc_url(sb_display_url().sb_query_char().'download&amp;'.$param.'='.$raw_url).'">'.__('Download', 'sermon-browser').'</a>';
	}
	echo '</div>';
}

//Decode base64 encoded data
function sb_print_code($code) {
	echo do_shortcode(base64_decode($code));
}

//Prints preacher description
function sb_print_preacher_description($sermon) {
	if (strlen($sermon->preacher_description)>0) {
		echo "<div class='preacher-description'><span class='about'>" . __('About', 'sermon-browser').' '.esc_html(stripslashes($sermon->preacher)).': </span>';
		echo "<span class='description'>".wp_kses_post(stripslashes($sermon->preacher_description))."</span></div>";
	}
}

//Prints preacher image
function sb_print_preacher_image($sermon) {
	if ($sermon->image)
		echo "<img alt='".esc_attr(stripslashes($sermon->preacher))."' class='preacher' src='".esc_url(trailingslashit(site_url()).sb_get_option('upload_dir').'images/'.$sermon->image)."'>";
}

//Prints link to sermon preached next (but not today)
function sb_print_next_sermon_link($sermon) {
	global $wpdb;
	$next = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) > DATE(%s) AND id <> %d ORDER BY datetime asc LIMIT 1", $sermon->datetime, (int)$sermon->id));
	if (!$next) return;
	echo '<a href="';
	sb_print_sermon_link($next);
	echo '">'.esc_html(stripslashes($next->title)).' &raquo;</a>';
}

//Prints link to sermon preached on previous days
function sb_print_prev_sermon_link($sermon) {
	global $wpdb;
	$prev = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) < DATE(%s) AND id <> %d ORDER BY datetime desc LIMIT 1", $sermon->datetime, (int)$sermon->id));
	if (!$prev) return;
	echo '<a href="';
	sb_print_sermon_link($prev);
	echo '">&laquo; '.esc_html(stripslashes($prev->title)).'</a>';
}

//Prints links to other sermons preached on the same day
function sb_print_sameday_sermon_link($sermon) {
	global $wpdb;
	$same = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$wpdb->prefix}sb_sermons WHERE DATE(datetime) = DATE(%s) AND id <> %d", $sermon->datetime, (int)$sermon->id));
	if (!$same) {
		_e('None', 'sermon-browser');
		return;
	}
	$output = array();
	foreach ($same as $cur)
		$output[] = '<a href="'.esc_url(sb_print_sermon_link($cur, false)).'">'.esc_html(stripslashes($cur->title)).'</a>';
	echo implode(', ', $output);
}

//Gets single sermon from the database
function sb_get_single_sermon($id) {
	global $wpdb;
	$id = (int) $id;
	$sermon = $wpdb->get_row($wpdb->prepare("SELECT m.id, m.title, m.datetime, m.start, m.end, m.description, p.id as pid, p.name as preacher, p.image as image, p.description as preacher_description, s.id as sid, s.name as service FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_preachers as p, {$wpdb->prefix}sb_services as s where m.preacher_id = p.id and m.service_id = s.id and m.id = %d", $id));
	$series = $wpdb->get_row($wpdb->prepare("SELECT ss.id as ssid, ss.name as series FROM {$wpdb->prefix}sb_sermons as m, {$wpdb->prefix}sb_series as ss WHERE m.series_id = ss.id and m.id = %d", $id));
	if ($sermon) {
		if ($series) {
			$sermon->series = $series->series;
			$sermon->ssid = $series->ssid;
		}
		else {
			$sermon->series = '';
			$sermon->ssid = 0;
		}
		$file = $code = $tags = array();
		$stuff = $wpdb->get_results($wpdb->prepare("SELECT f.id, f.type, f.name FROM {$wpdb->prefix}sb_stuff as f WHERE sermon_id = %d ORDER BY id desc", $id));
		$rawtags = $wpdb->get_results($wpdb->prepare("SELECT t.name FROM {$wpdb->prefix}sb_sermons_tags as st LEFT JOIN {$wpdb->prefix}sb_tags as t ON st.tag_id = t.id WHERE st.sermon_id = %d ORDER BY t.name asc", (int)$sermon->id));
		foreach ($rawtags as $tag) {
			$tags[] = $tag->name;
		}
		foreach ($stuff as $cur) {
			if ($cur->type == 'file') { $file[] = $cur->name; }
			elseif ($cur->type == 'code') { $code[] = $cur->name; }
		}
		$sermon->start = unserialize($sermon->start);
		$sermon->end = unserialize($sermon->end);
		return array(
			'Sermon' => $sermon,
			'Files' => $file,
			'Code' => $code,
			'Tags' => $tags,
		);
	} else
		return false;
}

//Prints the filter line for a given parameter
function sb_print_filter_line ($id, $results, $filter, $display, $max_num = 7) {
	global $more_applied;
	$translate_book = FALSE;
	if ($id == 'book') {
		$translate_book = TRUE;
		$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));
	}
	echo "<div id = \"".esc_attr($id)."\" class=\"filter\">\r<span class=\"filter-heading\">".__(ucwords($id), 'sermon-browser').":</span> \r";
	$i = 1;
	$more = FALSE;
	foreach ($results as $result) {
		if ($i == $max_num) {
			echo "<span id=\"".esc_attr($id)."-more\">";
			$more = TRUE;
			$more_applied[] = $id;
		}
		if ($i != 1)
			echo ", \r";
			if ($translate_book) {
				echo '<a href="'.esc_url(sb_build_url(array($id => $result->$filter), false)).'">'.esc_html($translated_books[stripslashes($result->$display)] ?? $result->$display).'</a>&nbsp;('.(int)$result->count.')';
			}
			else {
				echo '<a href="'.esc_url(sb_build_url(array($id => $result->$filter), false)).'">'.esc_html(stripslashes($result->$display)).'</a>&nbsp;('.(int)$result->count.')';
			}
		$i++;
	}
	echo ".";
	if ($more)
		echo "</span>\r<span id=\"".esc_attr($id)."-more-link\" style=\"display:none\">&hellip; (<a  id=\"".esc_attr($id)."-toggle\" href=\"#\"><strong>".($i-$max_num).' '.__('more', 'sermon-browser').'</strong></a>)</span>';
	echo '</div>';
}

//Prints the filter line for the date parameter
function sb_print_date_filter_line ($dates) {
	global $more_applied;
	if (empty($dates)) return;
    $date_output = "<div id = \"dates\" class=\"filter\">\r<span class=\"filter-heading\">".__('Date', 'sermon-browser').":</span> \r";
	$first = $dates[0];
	$last = end($dates);
	$count = 0;
	if ($first->year == $last->year) {
		if ($first->month == $last->month) {
			$date_output = '';
		} else {
			$previous_month = -1;
			foreach ($dates as $date) {
				if ($date->month != $previous_month) {
					if ($count != 0)
						$date_output .= '('.$count.'), ';
					$date_output .= '<a href="'.esc_url(sb_build_url(Array ('date' => $date->year.'-'.$date->month.'-01', 'enddate' => $date->year.'-'.$date->month.'-31'), false)).'">'.date_i18n('F', strtotime("{$date->year}-{$date->month}-{$date->day}")).'</a> ';
					$previous_month = $date->month;
					$count = 1;
				} else
					$count++;
			}
			$date_output .= '('.$count.'), ';
		}
	} else {
		$previous_year = 0;
		foreach ($dates as $date) {
			if ($date->year !== $previous_year) {
				if ($count !== 0)
					$date_output .= '('.$count.'), ';
				$date_output .= '<a href="'.esc_url(sb_build_url(Array ('date' => $date->year.'-01-01', 'enddate' => $date->year.'-12-31'), false)).'">'.(int)$date->year.'</a> ';
				$previous_year = $date->year;
				$count = 1;
			} else
				$count++;
		}
		$date_output .= '('.$count.'), ';
	}
	if ($date_output != '')
		echo rtrim($date_output, ', ')."</div>\r";
}

//Returns the filter URL minus a given parameter
function sb_url_minus_parameter ($param1, $param2='') {
	global $filter_options;
	$existing_params = array_merge((array) $_GET, (array) $_POST);
	$returned_query = array();
	foreach (array_keys($existing_params) as $query) {
		if (in_array($query, (array)$filter_options) && $query != $param1 && $query != $param2) {
			$returned_query[] = rawurlencode($query)."=".rawurlencode($existing_params[$query]);
		}
	}
	if (count($returned_query) > 0)
		return esc_url(sb_display_url().sb_query_char().implode('&', $returned_query));
	else
		return sb_display_url();
}

//Displays the filter on sermon search page
function sb_print_filters($filter) {
	global $wpdb, $more_applied, $filter_options, $sermons, $record_count; // Added $sermons and $record_count

	// --- START OF SAFETY NET: Load sermons if none exist (First Load) ---
	if (empty($sermons)) {
		$page = isset($_REQUEST['pagenum']) ? max(1, (int)$_REQUEST['pagenum']) : 1;
		$per_page = (int)sb_get_option('sermons_per_page');
		if ($per_page <= 0) $per_page = 20;
		
		$sort_order = array(
			'by' => isset($_REQUEST['sortby']) ? sanitize_key($_REQUEST['sortby']) : 'm.datetime', 
			'dir' => isset($_REQUEST['dir']) ? sanitize_key($_REQUEST['dir']) : 'desc'
		);
		
		// Run the fetch for the first time
		$sermons = sb_get_sermons((array)$filter, $sort_order, $page, $per_page, (bool)sb_get_option('hide_no_attachments'));
	}
	// --- END OF SAFETY NET ---
	$hide_filter = FALSE;
	if (isset($filter['filterhide']) && $filter['filterhide'] == 'hide') {
		$hide_filter = TRUE;
		$js_hide = "
		var filter_visible = false;
		jQuery('#mainfilter').hide();
		jQuery('#show_hide_filter').text('[ SHOW ]');
		jQuery('#show_hide_filter').click(function() {
			jQuery('#mainfilter:visible').slideUp('slow');
			jQuery('#mainfilter:hidden').slideDown('slow');
			if (filter_visible) {
				jQuery('#show_hide_filter').text('[ SHOW ]');
				filter_visible = false;
			} else {
				jQuery('#show_hide_filter').text('[ HIDE ]');
				filter_visible = true;
			}
			return false;
		});";
		$js_hide = str_replace ('SHOW', __('Show filter', 'sermon-browser'), $js_hide);
		$js_hide = str_replace ('HIDE', __('Hide filter', 'sermon-browser'), $js_hide);
	}
	if (isset($filter['filter']) && $filter['filter'] == 'oneclick') {
		// One click filter
		$hide_custom_podcast = true;
		$filter_options = array ('preacher', 'book', 'service', 'series', 'date', 'enddate', 'title');
		$output = '';
		foreach ($filter_options AS $filter_option)
			if (isset($_REQUEST[$filter_option])) {
				if ($filter_option != 'enddate') {
					if ($output != '')
						$output .= "\r, ";
					if ($filter_option == 'date') {
						$output .= '<strong>'.__('Date', 'sermon-browser').'</strong>:&nbsp;';
						if (substr($_REQUEST['date'],0,4) == substr($_REQUEST['enddate'],0,4))
							$output .= (int)substr($_REQUEST['date'],0,4).'&nbsp;(<a href="'.esc_url(sb_url_minus_parameter('date', 'enddate')).'">x</a>)';
						if (substr($_REQUEST['date'],5,2) == substr($_REQUEST['enddate'],5,2))
							$output .= ', '.date_i18n('F', strtotime($_REQUEST['date'])).' (<a href="'.esc_url(sb_build_url(Array ('date' => (int)substr($_REQUEST['date'],0,4).'-01-01', 'enddate' => (int)substr($_REQUEST['date'],0,4).'-12-31'), false)).'">x</a>)';
					} else {
						$output .= '<strong>'.__(ucwords($filter_option), 'sermon-browser').'</strong>:&nbsp;*'.$filter_option.'*';
						$output .= '&nbsp;(<a href="'.esc_url(sb_url_minus_parameter($filter_option)).'">x</a>)';
					}
				}
				$hide_custom_podcast = FALSE;
			}
		$hide_empty = sb_get_option('hide_no_attachments');
		$sermons=sb_get_sermons($filter, array(), 1, 99999, $hide_empty);
		$ids = array();
		foreach ($sermons as $sermon)
			$ids[] = (int)$sermon->id;
        
        if (empty($ids)) { $ids = array(0); }
		$ids_in = "(".implode (",", $ids).")";

		$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS sermons ON p.id = sermons.preacher_id WHERE sermons.id IN {$ids_in} GROUP BY p.id ORDER BY count DESC, sermons.datetime DESC");
		$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id  WHERE sermons.id IN {$ids_in} GROUP BY ss.id ORDER BY sermons.datetime DESC");
		$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id  WHERE sermons.id IN {$ids_in} GROUP BY s.id ORDER BY count DESC");
		$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books as b ON bs.book_name=b.name AND bs.sermon_id IN {$ids_in} GROUP BY b.id");
		$dates = $wpdb->get_results("SELECT YEAR(datetime) as year, MONTH (datetime) as month, DAY(datetime) as day FROM {$wpdb->prefix}sb_sermons WHERE id IN {$ids_in} ORDER BY datetime ASC");

		$more_applied = array();
		$output = str_replace ('*preacher*', isset($preachers[0]->name) ? esc_html($preachers[0]->name) : '', $output);
		$output = str_replace ('*book*', isset($_REQUEST['book']) ? esc_html($_REQUEST['book']) : '', $output);
		$output = str_replace ('*service*', isset($services[0]->name) ? esc_html($services[0]->name) : '', $output);
		$output = str_replace ('*series*', isset($series[0]->name) ? esc_html($series[0]->name) : '', $output);

		echo "<span class=\"inline_controls\"><a href=\"#\" id=\"show_hide_filter\"></a></span>";
		if ($output != '')
			echo '<div class="filtered">'.__('Active filter', 'sermon-browser').': '.$output."</div>\r";
		echo '<div id="mainfilter">';
		if (count((array)$preachers) > 1)
			sb_print_filter_line ('preacher', $preachers, 'id', 'name', 7);
		if (count((array)$book_count) > 1)
			sb_print_filter_line ('book', $book_count, 'name', 'name', 10);
		if (count((array)$series) > 1)
			sb_print_filter_line ('series', $series, 'id', 'name', 10);
		if (count((array)$services) > 1)
			sb_print_filter_line ('service', $services, 'id', 'name', 10);
		sb_print_date_filter_line ($dates);
		echo "</div>\r";
		if (count($more_applied) > 0 || $output != '' || $hide_custom_podcast === TRUE || $hide_filter === TRUE) {
			echo "<script type=\"text/javascript\">\r";
			echo "\tjQuery(document).ready(function() {\r";
			if ($hide_filter === TRUE && isset($js_hide))
				echo $js_hide."\r";
			if ($hide_custom_podcast === TRUE)
				echo "\t\tjQuery('.podcastcustom').hide();\r";
			if (count($more_applied) > 0) {
				foreach ($more_applied as $element_id) {
					echo "\t\tjQuery('#{$element_id}-more').hide();\r";
					echo "\t\tjQuery('#{$element_id}-more-link').show();\r";
					echo "\t\tjQuery('a#{$element_id}-toggle').click(function() {\r";
					echo "\t\t\tjQuery('#{$element_id}-more').show();\r";
					echo "\t\t\tjQuery('#{$element_id}-more-link').hide();\r";
					echo "\t\t\treturn false;\r";
					echo "\t\t});\r";
				}
			}
			echo "\t});\r";
			echo "</script>\r";
		}
	} elseif (isset($filter['filter']) && $filter['filter'] == 'dropdown') {
		// Drop-down filter
		$translated_books = array_combine(sb_get_default('eng_bible_books'), sb_get_default('bible_books'));
		$preachers = $wpdb->get_results("SELECT p.*, count(p.id) AS count FROM {$wpdb->prefix}sb_preachers AS p JOIN {$wpdb->prefix}sb_sermons AS s ON p.id = s.preacher_id GROUP BY p.id ORDER BY count DESC, s.datetime DESC");
		$series = $wpdb->get_results("SELECT ss.*, count(ss.id) AS count FROM {$wpdb->prefix}sb_series AS ss JOIN {$wpdb->prefix}sb_sermons AS sermons ON ss.id = sermons.series_id GROUP BY ss.id ORDER BY sermons.datetime DESC");
		$services = $wpdb->get_results("SELECT s.*, count(s.id) AS count FROM {$wpdb->prefix}sb_services AS s JOIN {$wpdb->prefix}sb_sermons AS sermons ON s.id = sermons.service_id GROUP BY s.id ORDER BY count DESC");
		$book_count = $wpdb->get_results("SELECT bs.book_name AS name, count(distinct bs.sermon_id) AS count FROM {$wpdb->prefix}sb_books_sermons AS bs JOIN {$wpdb->prefix}sb_books AS b ON bs.book_name = b.name GROUP BY b.id");
		$sb = array(
			'Title' => 'm.title',
			'Preacher' => 'preacher',
			'Date' => 'm.datetime',
			'Passage' => 'b.id',
		);
		$di = array(
			__('Ascending', 'sermon-browser') => 'asc',
			__('Descending', 'sermon-browser') => 'desc',
		);
		$csb = isset($_REQUEST['sortby']) ? sanitize_text_field($_REQUEST['sortby']) : 'm.datetime';
		$cd = (isset($_REQUEST['dir']) && (strtolower($_REQUEST['dir']) == 'asc' || strtolower($_REQUEST['dir']) == 'desc')) ? $_REQUEST['dir'] : 'desc';
		?>
		<span class="inline_controls"><a href="#" id="show_hide_filter"></a></span>
		<div id="mainfilter">
			<form method="get" id="sermon-filter" action="<?php echo esc_url(sb_display_url()); ?>">
				<div style="clear:both">
					<table class="sermonbrowser">
						<tr>
							<td class="fieldname"><?php _e('Preacher', 'sermon-browser') ?></td>
							<td class="field"><select name="preacher" id="preacher">
									<option value="0" <?php echo (isset($_REQUEST['preacher']) && $_REQUEST['preacher'] != 0) ? '' : 'selected="selected"' ?>><?php _e('[All]', 'sermon-browser') ?></option>
									<?php foreach ((array)$preachers as $preacher): ?>
									<option value="<?php echo (int)$preacher->id ?>" <?php echo isset($_REQUEST['preacher']) && $_REQUEST['preacher'] == $preacher->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($preacher->name)).' ('.(int)$preacher->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Services', 'sermon-browser') ?></td>
							<td class="field"><select name="service" id="service">
									<option value="0" <?php echo isset($_REQUEST['service']) && $_REQUEST['service'] != 0 ? '' : 'selected="selected"' ?>><?php _e('[All]', 'sermon-browser') ?></option>
									<?php foreach ((array)$services as $service): ?>
									<option value="<?php echo (int)$service->id ?>" <?php echo isset($_REQUEST['service']) && $_REQUEST['service'] == $service->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($service->name)).' ('.(int)$service->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Book', 'sermon-browser') ?></td>
							<td class="field"><select name="book">
									<option value=""><?php _e('[All]', 'sermon-browser') ?></option>
									<?php foreach ((array)$book_count as $book): ?>
									<option value="<?php echo esc_attr($book->name) ?>" <?php echo isset($_REQUEST['book']) && $_REQUEST['book'] == $book->name ? 'selected="selected"' : '' ?>><?php echo esc_html($translated_books[stripslashes($book->name)] ?? $book->name). ' ('.(int)$book->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Series', 'sermon-browser') ?></td>
							<td class="field"><select name="series" id="series">
									<option value="0" <?php echo (isset($_REQUEST['series']) && $_REQUEST['series'] != 0) ? '' : 'selected="selected"' ?>><?php _e('[All]', 'sermon-browser') ?></option>
									<?php foreach ((array)$series as $item): ?>
									<option value="<?php echo (int)$item->id ?>" <?php echo isset($_REQUEST['series']) && $_REQUEST['series'] == $item->id ? 'selected="selected"' : '' ?>><?php echo esc_html(stripslashes($item->name)).' ('.(int)$item->count.')' ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Start date', 'sermon-browser') ?></td>
							<td class="field"><input type="date" name="date" id="date" value="<?php echo isset($_REQUEST['date']) ? esc_attr($_REQUEST['date']) : '' ?>" placeholder="YYYY-MM-DD" /></td>
							<td class="fieldname rightcolumn"><?php _e('End date', 'sermon-browser') ?></td>
							<td class="field"><input type="date" name="enddate" id="enddate" value="<?php echo isset($_REQUEST['enddate']) ? esc_attr($_REQUEST['enddate']) : '' ?>" placeholder="YYYY-MM-DD" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Keywords', 'sermon-browser') ?></td>
							<td class="field" colspan="3"><input style="width: 98.5%" type="text" id="title" name="title" value="<?php echo (isset($_REQUEST['title']) ? esc_attr($_REQUEST['title']) : '') ?>" /></td>
						</tr>
						<tr>
							<td class="fieldname"><?php _e('Sort by', 'sermon-browser') ?></td>
							<td class="field"><select name="sortby" id="sortby">
									<?php foreach ($sb as $k => $v): ?>
									<option value="<?php echo esc_attr($v) ?>" <?php echo $csb == $v ? 'selected="selected"' : '' ?>><?php _e($k, 'sermon-browser') ?></option>
									<?php endforeach ?>
								</select>
							</td>
							<td class="fieldname rightcolumn"><?php _e('Direction', 'sermon-browser') ?></td>
							<td class="field"><select name="dir" id="dir">
									<?php foreach ($di as $k => $v): ?>
									<option value="<?php echo esc_attr($v) ?>" <?php echo $cd == $v ? 'selected="selected"' : '' ?>><?php _e($k, 'sermon-browser') ?></option>
									<?php endforeach ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
							<td class="field"><input type="submit" class="filter" value="<?php _e('Filter &raquo;', 'sermon-browser') ?>">			</td>
						</tr>
					</table>
					<input type="hidden" name="pagenum" value="1">
				</div>
			</form>
		</div>
	<?php
	}
}
// Returns the first MP3 file attached to a sermon
// Stats have to be turned off for iTunes compatibility
function sb_first_mp3($sermon, $stats= TRUE) {
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if (stripos($user_agent, 'itunes') !== FALSE || stripos($user_agent, 'FeedBurner') !== FALSE)
		$stats = FALSE;
	$stuff_all = sb_get_stuff($sermon, true);
	$stuff = array_merge((array)($stuff_all['Files'] ?? array()), (array)($stuff_all['URLs'] ?? array()));
	foreach ((array) $stuff as $file) {
		if (strtolower(substr($file, strrpos($file, '.') + 1)) == 'mp3') {
			if ((substr($file,0,7) == "http://") or (substr($file,0,8) == 'https://')) {
				if ($stats)
					$file=sb_display_url().sb_query_char().'show&amp;url='.rawurlencode($file);
			} else {
				if (!$stats)
					$file=trailingslashit(site_url()).sb_get_option('upload_dir').rawurlencode($file);
				else
					$file=sb_display_url().sb_query_char().'show&amp;file_name='.rawurlencode($file);
			}
			return $file;
		}
	}
    return '';
}
