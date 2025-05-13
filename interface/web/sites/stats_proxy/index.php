<?php

require_once '../../../lib/config.inc.php';
require_once '../../../lib/app.inc.php';

$app->uses('tpl,tform,tform_actions');
// Check if we have an active user session and redirect to login if that's not the case.
if ($_SESSION['s']['user']['active'] != 1) {
    header('Location: /login/');
    die();
}

// todo move to config?
$basic_auth_username = $conf['stats_proxy_username'];
$basic_auth_password = $conf['stats_proxy_passsword'];
// When changing the password, be sure to re-sync all the servers with the new password.

// Base URL
$base_url = "/sites/stats_proxy"; // Adjust this to match your script's base path


// Extract the path and query string from REQUEST_URI
$request_uri = $_SERVER['REQUEST_URI'];
$relative_path = str_replace($base_url, '', $request_uri);

// Get the first path component as the domain name
$relative_path_parts = explode('/', ltrim($relative_path, '/'));
$domain_name = $relative_path_parts[0];
$relative_path = '/' . implode('/', array_slice($relative_path_parts, 1));

// Check if the current user is allowed to access the domain
$domain_access = $app->db->queryOneRecord("SELECT `domain`, `ssl` FROM web_domain WHERE domain = ? AND " . $app->tform->getAuthSQL('r'), $domain_name);

if (!$domain_access) {
    header('HTTP/1.1 403 Forbidden');
    echo "You are not authorized to access this domain.";
    die();
}

// Construct the full URL for the backend
$backend_url = ($domain_access['ssl'] == 'y' ? 'https' : 'http') . "://$domain_name/stats";
$url = $backend_url . $relative_path;

$passthrough = curl_init();
curl_setopt($passthrough, CURLOPT_RETURNTRANSFER, true);
curl_setopt($passthrough, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($passthrough, CURLOPT_USERAGENT, "ISPconfig panel");
curl_setopt($passthrough, CURLOPT_URL, $url);

// Apply Basic Authentication
curl_setopt($passthrough, CURLOPT_USERPWD, "$basic_auth_username:$basic_auth_password");

$passthroughdata = curl_exec($passthrough);

if ($passthroughdata === false) {
    echo 'Curl error: ' . curl_error($passthrough);
} else {
    // Get the content type from the backend response
    $content_type = curl_getinfo($passthrough, CURLINFO_CONTENT_TYPE);
    $status_code = curl_getinfo($passthrough, CURLINFO_HTTP_CODE);
    if ($status_code != 200) {
        header("HTTP/1.1 $status_code");
        echo "Error: Sorry the backend site returned HTTP status code $status_code";
        die();
    }

    if ($content_type) {
        header("Content-Type: " . $content_type);
    } else {
        header("Content-Type: text/html; charset=utf-8"); // Default fallback
    }
}

curl_close($passthrough);

// Output the response data
print $passthroughdata;
