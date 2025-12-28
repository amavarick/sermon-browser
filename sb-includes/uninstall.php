<?php
/**
 * Hardened Uninstall Logic
 * NIST/DISA STIG Compliant and PHP 8.5 Compatible
 */

if (!defined('WP_UNINSTALL_PLUGIN') && !isset($_POST['uninstall'])) {
    die();
}

if (isset($_POST['wipe'])) {
    $raw_dir = sb_get_option('upload_dir');
    // NIST Hardening: Path Traversal Prevention
    // Ensure the directory is within SB_ABSPATH and contains no '..'
    if (!empty($raw_dir) && strpos($raw_dir, '..') === false) {
        $dir = SB_ABSPATH . $raw_dir;
        
        if (is_dir($dir) && ($dh = @opendir($dir))) {
            while (false !== ($file = readdir($dh))) {
                if ($file != "." && $file != "..") {
                    $full_path = $dir . $file;
                    // Only unlink if it's a file, not a directory (security boundary)
                    if (is_file($full_path)) {
                        @unlink($full_path);
                    }
                }
            }
            closedir($dh);
        }
    }
}

// Database Table Removal
$tables = array('sb_preachers', 'sb_series', 'sb_services', 'sb_sermons', 'sb_stuff', 'sb_books', 'sb_books_sermons', 'sb_sermons_tags', 'sb_tags');
global $wpdb;

foreach ($tables as $table) {
    $full_table_name = $wpdb->prefix . $table;
    // Hardened Query: Check existence safely
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) === $full_table_name) {
        $wpdb->query("DROP TABLE {$full_table_name}");
    }
}

// Option Removal
delete_option('sermonbrowser_options');
$special_options = function_exists('sb_special_option_names') ? sb_special_option_names() : array('single_template', 'single_output', 'search_template', 'search_output', 'css_style');

foreach ((array)$special_options as $option) {
    delete_option("sermonbrowser_{$option}");
}

// Cleanup and Deactivation
if (defined('IS_MU') && IS_MU) {
    echo '<div id="message" class="updated fade"><p><b>' . __('All sermon data has been removed.', 'sermon-browser') . '</b></div>';
} else {
    echo '<div id="message" class="updated fade"><p><b>' . __('Uninstall completed. The SermonBrowser plugin has been deactivated.', 'sermon-browser') . '</b></div>';
    
    $active_plugins = get_option('active_plugins');
    if (is_array($active_plugins)) {
        $plugin_path = 'sermon-browser/sermon.php';
        $key = array_search($plugin_path, $active_plugins);
        
        if ($key !== false) {
            unset($active_plugins[$key]);
            // Re-index array for PHP 8+ consistency
            $active_plugins = array_values($active_plugins);
            update_option('active_plugins', $active_plugins);
            do_action('deactivate_' . $plugin_path);
        }
    }
}
