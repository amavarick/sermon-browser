<?php
/**
 * Hardened CSS Output Logic
 * NIST/DISA STIG Compliant and PHP 8.5 Compatible
 */

// Block direct access if not via WordPress
if (!function_exists('sb_get_option')) {
	header("HTTP/1.0 403 Forbidden");
	die('Access Denied');
}

// Set Content-Type early
header('Content-Type: text/css; charset=utf-8');

// PHP 8.5: Explicit type casting for timestamp
$lastModifiedDate = (int)sb_get_option('style_date_modified');

// Check for valid cache
$if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
if (!empty($if_modified_since)) {
	$header_time = strtotime($if_modified_since);
	// Ensure $header_time is a valid integer before comparison
	if ($header_time !== false && $header_time >= $lastModifiedDate) {
		if (php_sapi_name() === 'cgi' || php_sapi_name() === 'fast-cgi') {
			header("Status: 304 Not Modified");
		} else {
			header("HTTP/1.0 304 Not Modified");
		}
		exit;
	}
}

// Set cache headers safely
$gmtDate = gmdate("D, d M Y H:i:s", $lastModifiedDate) . " GMT";
header('Last-Modified: ' . $gmtDate);

$expires_seconds = 604800; // 7 days
header("Cache-Control: public, max-age=" . $expires_seconds);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires_seconds) . ' GMT');

// Output content with stripslashes to ensure CSS isn't corrupted by DB escapes
$css = (string)sb_get_option('css_style');
echo stripslashes($css);
// Add styling for the Reset Button
echo "
.sb_reset_button {
    color: #666 !important;
    background-color: #eee !important;
    padding: 4px 10px !important;
    border-radius: 4px !important;
    border: 1px solid #ccc !important;
    font-size: 12px !important;
    text-decoration: none !important;
    display: inline-block !important;
    margin-right: 10px !important;
}
.sb_reset_button:hover {
    background-color: #ddd !important;
    color: #cc0000 !important;
}";
exit;
