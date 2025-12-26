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

$app->uses('tpl,tform');
// Check if we have an active user session and redirect to login if that's not the case.
if ($_SESSION['s']['user']['active'] != 1) {
	header('Location: /login/');
	die();
}

// Base URL
$base_url = "/sites/stats_proxy"; // Adjust this to match your script's base path


// Extract the path and query string from REQUEST_URI and normalize
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

// Ensure the request starts with the expected base URL
if (strpos($request_uri, $base_url) !== 0) {
	header('HTTP/1.1 400 Bad Request');
	echo "Invalid request path.";
	die();
}

// Remove the base URL prefix and split path/query
$relative_uri = substr($request_uri, strlen($base_url));
$path = parse_url($relative_uri, PHP_URL_PATH) ?: '/';
$query = parse_url($relative_uri, PHP_URL_QUERY) ?: '';

// Decode once and reject null bytes
$decoded_path = rawurldecode($path);
if (strpos($decoded_path, "\0") !== false) {
	header('HTTP/1.1 400 Bad Request');
	echo "Invalid path.";
	die();
}

// Normalize path segments to remove '.' and '..' and prevent traversal
$parts = explode('/', ltrim($decoded_path, '/'));
$normalized = [];
foreach ($parts as $seg) {
	if ($seg === '' || $seg === '.') {
		continue;
	}
	if ($seg === '..') {
		// Trying to go above the root => reject
		if (empty($normalized)) {
			header('HTTP/1.1 400 Bad Request');
			echo "Path traversal attempt detected.";
			die();
		}
		array_pop($normalized);
		continue;
	}
	// reject control characters or other suspicious characters in segments
	if (preg_match('/[\x00-\x1F\x7F]/', $seg)) {
		header('HTTP/1.1 400 Bad Request');
		echo "Invalid path segment.";
		die();
	}
	$normalized[] = $seg;
}

if (count($normalized) === 0) {
	header('HTTP/1.1 400 Bad Request');
	echo "Missing domain name in path.";
	die();
}

// Extract domain name (first segment) and rebuild relative path (rest)
$domain_name = rawurldecode($normalized[0]);
$remaining = array_slice($normalized, 1);
$relative_path = '/' . implode('/', $remaining);
if ($relative_path === '/') {
	// keep as single slash
	$relative_path = '/';
}

// Re-add sanitized query if present
if ($query !== '') {
	parse_str($query, $query_params);
	$relative_path .= (strpos($relative_path, '?') === false ? '?' : '&') . http_build_query($query_params, '', '&', PHP_QUERY_RFC3986);
}

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
// Follow redirects but only for http/https and limit redirects
curl_setopt($passthrough, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($passthrough, CURLOPT_MAXREDIRS, 5);
curl_setopt($passthrough, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
curl_setopt($passthrough, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
// Do not allow credentials to be sent to other hosts during redirects
curl_setopt($passthrough, CURLOPT_UNRESTRICTED_AUTH, false);
curl_setopt($passthrough, CURLOPT_USERAGENT, "ISPconfig panel");
curl_setopt($passthrough, CURLOPT_URL, $url);
// Timeouts and SSL checks
curl_setopt($passthrough, CURLOPT_TIMEOUT, 15);
curl_setopt($passthrough, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($passthrough, CURLOPT_SSL_VERIFYHOST, 2);

// Apply Basic Authentication
curl_setopt($passthrough, CURLOPT_USERPWD, $conf['stats_proxy_username'] . ':' . $conf['stats_proxy_password']);

$passthroughdata = curl_exec($passthrough);

if ($passthroughdata === false) {
	echo 'Curl error: ' . curl_error($passthrough);
}
elseif ($passthroughdata !== false) {
	// Ensure redirects did not lead us to a different host
	$effective_url = curl_getinfo($passthrough, CURLINFO_EFFECTIVE_URL);
	if ($effective_url) {
		$effective_host = parse_url($effective_url, PHP_URL_HOST);
		if ($effective_host && strtolower($effective_host) !== strtolower($domain_name)) {
			header('HTTP/1.1 502 Bad Gateway');
			echo "Backend redirected to unexpected host.";
			curl_close($passthrough);
			die();
		}
	}

	$status_code = curl_getinfo($passthrough, CURLINFO_HTTP_CODE);
	if ($status_code != 200) {
		header("HTTP/1.1 $status_code");
		echo "Error: Sorry the backend site returned HTTP status code $status_code";
		// This could mean that the site has not been updated yet, to store the passwordt in web/stats/.htpasswd_stats
		die();
	}

	// Get the content type from the backend response
	$content_type = curl_getinfo($passthrough, CURLINFO_CONTENT_TYPE);
	if ($content_type) {
		header("Content-Type: " . $content_type);
	} else {
		header("Content-Type: text/html; charset=utf-8"); // Default fallback
	}
}

curl_close($passthrough);

// Output the response data
print $passthroughdata;
