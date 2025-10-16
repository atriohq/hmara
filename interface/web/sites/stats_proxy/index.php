<?php

/**
 * Proxy script for AWstats
 *
 * This allows users to access AWstats via the ISPConfig interface.
 * It checks if the user has permission to access the domain and then proxies the request.
 *
 * To enable this script, add the following line to your ISPConfig configuration:
 * $conf['stats_proxy_username'] = 'internal_username';
 * $conf['stats_proxy_password'] = 'internal_password';
 * These credentials will be used for Basic Authentication to the backend server. They should never be
 * exposed to the user.
 */

 /*
Copyright (c) 2025, Herman van Rink, Initfour websolutions
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/


require_once '../../../lib/config.inc.php';
require_once '../../../lib/app.inc.php';

$app->uses('tpl,tform,tform_actions');
// Check if we have an active user session and redirect to login if that's not the case.
if ($_SESSION['s']['user']['active'] != 1) {
	header('Location: /login/');
	die();
}

// Base URL
$base_url = "/sites/stats_proxy"; // Adjust this to match your script's base path


// Extract the path and query string from REQUEST_URI
$request_uri = $_SERVER['REQUEST_URI'];
$relative_path = str_replace($base_url, '', $request_uri);

// Get the first path component as the domain name
$relative_path_parts = explode('/', ltrim($relative_path, '/'));
$domain_name = $relative_path_parts[0];
$relative_path = '/' . implode('/', array_slice($relative_path_parts, 1));

// Check if the domain name is valid, based on the regex from validate_domain::_regex_validate()
$domain_pattern = '/^(\*\.)?[\w\.\-]{1,255}\.[a-zA-Z0-9\-]{2,63}$/';

if (empty($domain_name) || !preg_match($domain_pattern, $domain_name)) {
	header('HTTP/1.1 400 Bad Request');
	echo "Invalid domain name.";
	die();
}

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
curl_setopt($passthrough, CURLOPT_USERPWD, $conf['stats_proxy_username'] . ':' . $conf['stats_proxy_password']);

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
		// This could mean that the site has not been updated yet, to store the passwordt in web/stats/.htpasswd_stats
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
