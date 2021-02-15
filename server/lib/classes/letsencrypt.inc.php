<?php

/*
Copyright (c) 2017, Marius Burkard, projektfarm Gmbh
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

class letsencrypt {

	/**
	 * Construct for this class
	 *
	 * @return system
	 */
	private $base_path = '/etc/letsencrypt';
	private $renew_config_path = '/etc/letsencrypt/renewal';
	private $COMMAND_TYPE_CHECK = "CHECK";
	private $COMMAND_TYPE_REQUEST = "REQUEST";
	private $COMMAND_TYPE_INSTALL = "INSTALL";

	public function __construct(){

	}

	/**
	 * acme.sh
	 * Searches for the acme.sh client scripts in known locations
	 * Returns false if no acme.sh executable is found
	 * Returns the path the the acme.sh script if found
	 *
	 * @return false|string
	 */
	public function get_acme_script() {
		$acme = explode("\n", shell_exec('which /usr/local/ispconfig/server/scripts/acme.sh /root/.acme.sh/acme.sh'));
		$acme = reset($acme);
		if(is_executable($acme)) {
			return $acme;
		} else {
			return false;
		}
	}

	/**
	 * acme.sh
	 * Generates the shell commands to be used when acme.sh is the LetsEncrypt client
	 *
	 * @param COMMAND_TYPE $command_type One of the ENUMs telling which type of command should be generated
	 * @param array $domains Array of domains relevant for the certification
	 * @param string $key_file Path to the certificate key file
	 * @param string $bundle_file Path to the certificate bundle file
	 * @param string $cert_file Path to the certificate file
	 * @param string $server_type apache|nginx
	 *
	 * @return false|string
	 */
	public function get_acme_command($command_type, $domains, $key_file, $bundle_file, $cert_file, $server_type = 'apache') {
		global $app, $conf;

		$letsencrypt = $this->get_acme_script();

		$domains_arg = '';
		// generate cli format
		foreach($domains as $domain) {
			$domains_arg .= (string) " -d " . $domain;
		}

		if($domains_arg == '') {
			return false;
		}

		$log_arg = "--log " . escapeshellarg($conf['ispconfig_log_dir'].'/acme.log'). "";
		$reload_arg = "--reloadcmd " . escapeshellarg($this->get_reload_command()) . "";

		if($server_type != 'apache' || version_compare($app->system->getapacheversion(true), '2.4.8', '>=')) {
			$cert_arg = '--fullchain-file ' . escapeshellarg($cert_file);
		} else {
			$cert_arg = '--fullchain-file ' . escapeshellarg($bundle_file) . ' --cert-file ' . escapeshellarg($cert_file);
		}

		if ( $this->COMMAND_TYPE_REQUEST == $command_type) {
			return "{$letsencrypt} --issue {$domains_arg} -w /usr/local/ispconfig/interface/acme --always-force-new-domain-key --keylength 4096 {$log_arg}";
		} else if ( $this->COMMAND_TYPE_INSTALL == $command_type) {
			return "{$letsencrypt} --install-cert {$domains_arg} --key-file ". escapeshellarg($key_file) ." {$cert_arg} {$reload_arg} {$log_arg}";
		} else {
			return "";
		}

	}

	/**
	 * acme.sh
	 *
	 * Installs the acme.sh script locally
	 *
	 * @return bool true for successful install, false if failed
	 */
	private function install_acme() {
		$install_cmd = 'wget -O -  https://get.acme.sh | sh';
		$ret = null;
		$val = 0;
		exec($install_cmd . ' 2>&1', $ret, $val);

		return ($val == 0 ? true : false);
	}

	/**
	 * certbot
	 *
	 * Searches for the certbot client script in known locations
	 * Returns false if no certbot executable is found
	 * Returns the path the the certbot script if found
	 *
	 * @return false|string
	 */
	public function get_certbot_script() {
		$letsencrypt = explode("\n", shell_exec('which letsencrypt certbot /root/.local/share/letsencrypt/bin/letsencrypt /opt/eff.org/certbot/venv/bin/certbot'));
		$letsencrypt = reset($letsencrypt);
		if(is_executable($letsencrypt)) {
			return $letsencrypt;
		} else {
			return false;
		}
	}

	/**
	 * certbot | acme.sh
	 *
	 * Returns the reload command for the used http server
	 *
	 * @return string the reload command
	 */
	private function get_reload_command() {
		global $app, $conf;

		$web_config = $app->getconf->get_server_config($conf['server_id'], 'web');

		switch ($web_config['server_type']) {
			case 'nginx':
				$daemon = $web_config['server_type'];
				break;
			default:
				if(is_file($conf['init_scripts'] . '/' . 'httpd24-httpd') || is_dir('/opt/rh/httpd24/root/etc/httpd')) {
					$daemon = 'httpd24-httpd';
				} elseif(is_file($conf['init_scripts'] . '/' . 'httpd') || is_dir('/etc/httpd')) {
					$daemon = 'httpd';
				} else {
					$daemon = 'apache2';
				}
		}

		$cmd = $app->system->getinitcommand($daemon, 'force-reload');
		return $cmd;
	}

	/**
	 * certbot
	 *
	 * Generates the shell commands to be used when certbot is the LetsEncrypt client
	 *
	 * @param COMMAND_TYPE $command_type One of the ENUMs telling which type of command should be generated
	 * @param array $domains Array of domains relevant for the certification
	 *
	 * @return string the shell command (empty in case of errors or missing parameters)
	 */
	public function get_certbot_command($command_type, $domains) {
		global $app;

		$letsencrypt = $this->get_certbot_script();

		// Map the domain array to a string containing the cli args
		$domain_arg = '';
		foreach($domains as $domain) {
			$domain_arg .= (string) " --domains " . $domain;
		}

		// Domains are required
		if($domain_arg == '') {
			return '';
		}

		$primary_domain = $domains[0];
		$certbot_can_use_certcommand = false;

		$letsencrypt_version = $this->get_certbot_version();
		$app->log("LE version is " . $letsencrypt_version, LOGLEVEL_DEBUG);
		if (version_compare($letsencrypt_version, '0.22', '>=')) {
			$acme_version = 'https://acme-v02.api.letsencrypt.org/directory';
		} else {
			$app->log("You are using an outdated Let's Encrypt client which is not able to use the acme V02 protocol. Please update! The old acme V01 protocol will be end of life in mid 2021. See https://community.letsencrypt.org/t/end-of-life-plan-for-acmev1/88430", LOGLEVEL_ERROR);
			$acme_version = 'https://acme-v01.api.letsencrypt.org/directory';
		}
		// Modern versions of certbot allow us for some more fancy options
		if (version_compare($letsencrypt_version, '0.30', '>=')) {
			$app->log("using certificates command and --webroot-map", LOGLEVEL_DEBUG);
			$certbot_can_use_certcommand = true;
			$webroot_map = array();
			for($i = 0; $i < count($domains); $i++) {
				$webroot_map[$domains[$i]] = '/usr/local/ispconfig/interface/acme';
			}
			$webroot_args = "--webroot-map " . escapeshellarg(str_replace(array("\r", "\n"), '', json_encode($webroot_map)));
			// Domain list is not required with json webroot map, the domains will be implicitly used from the json
			$domain_arg = "";
			$cert_selection_command = "--cert-name $primary_domain";
		} else {
			$webroot_args = "--webroot-path /usr/local/ispconfig/interface/acme";
			$cert_selection_command = "--expand";
		}

		// Generate the required command based on the $command_type passed in
		if ( $this->COMMAND_TYPE_REQUEST == $command_type) {
			return $letsencrypt . " certonly -n --text --agree-tos {$cert_selection_command} --authenticator webroot --server {$acme_version} --rsa-key-size 4096 --email postmaster@{$primary_domain} {$webroot_args} {$domain_arg}";
		} else if ( $this->COMMAND_TYPE_CHECK == $command_type && $certbot_can_use_certcommand) {
			return $letsencrypt . " certificates {$domain_arg}";
		} else {
			return '';
		}

	}

	/**
	 * certbot
	 *
	 * Returns the certbot version
	 *
	 * @return string The certbot version
	 */
	public function get_certbot_version() {
		$letsencrypt = $this->get_certbot_script();

		$matches = array();
		$ret = null;
		$val = 0;

		$letsencrypt_version = exec($letsencrypt . ' --version  2>&1', $ret, $val);
		if(preg_match('/^(\S+|\w+)\s+(\d+(\.\d+)+)$/', $letsencrypt_version, $matches)) {
			return $matches[2];
		}
		return $letsencrypt_version;
	}

	/**
	 * certbot
	 *
	 * Searches the letsencrypt directory for the best matching existing certificates for all given domains.
	 * This is done searching and scoring the renewal config file and the containing domains based on a given domain list
	 * Returns false if none is found
	 * Returns an array of certificate paths if a matching cert is found
	 *
	 * @param array $domains The target domains to find a matching certificate for
	 *
	 * @return false|array False if none found. Array of the certificate paths if a matching cert is found
	 */
	public function find_matching_certificate_on_filesystem($domains = array()) {
		global $app;

		if($this->get_acme_script()) {
			return false;
		}

		if(empty($domains)) return false;
		if(!is_dir($this->renew_config_path)) return false;

		$dir = opendir($this->renew_config_path);
		if(!$dir) return false;

		$path_scores = array();

		$main_domain = reset($domains);
		sort($domains);
		$min_diff = false;

		// Iterate over all renewal config files and create a score for each file
		while($file = readdir($dir)) {
			if($file === '.' || $file === '..' || substr($file, -5) !== '.conf')  continue;
			$file_path = $this->renew_config_path . '/' . $file;
			if(!is_file($file_path) || !is_readable($file_path)) continue;

			$fp = fopen($file_path, 'r');
			if(!$fp) continue;

			$path_scores[$file_path] = array(
				'domains' => array(),
				'diff' => 0,
				'has_main_domain' => false,
				'cert_paths' => array(
					'cert' => '',
					'privkey' => '',
					'chain' => '',
					'fullchain' => ''
				)
			);
			$in_list = false;
			while(!feof($fp) && $line = fgets($fp)) {
				$line = trim($line);
				if($line === '') continue;
				elseif(!$in_list) {
					if($line == '[[webroot_map]]') $in_list = true;

					$tmp = explode('=', $line, 2);
					if(count($tmp) != 2) continue;
					$key = trim($tmp[0]);
					if($key == 'cert' || $key == 'privkey' || $key == 'chain' || $key == 'fullchain') {
						$path_scores[$file_path]['cert_paths'][$key] = trim($tmp[1]);
					}

					continue;
				}

				$tmp = explode('=', $line, 2);
				if(count($tmp) != 2) continue;

				$domain = trim($tmp[0]);
				if($domain == $main_domain) $path_scores[$file_path]['has_main_domain'] = true;
				$path_scores[$file_path]['domains'][] = $domain;
			}
			fclose($fp);

			sort($path_scores[$file_path]['domains']);
			if(count(array_intersect($domains, $path_scores[$file_path]['domains'])) < 1) {
				$path_scores[$file_path]['diff'] = false;
			} else {
				// give higher diff value to missing domains than to those that are too much in there
				$path_scores[$file_path]['diff'] = (count(array_diff($domains, $path_scores[$file_path]['domains'])) * 1.5) + count(array_diff($path_scores[$file_path]['domains'], $domains));
			}

			if($min_diff === false || $path_scores[$file_path]['diff'] < $min_diff) $min_diff = $path_scores[$file_path]['diff'];
		}
		closedir($dir);

		// We didn't find any matching certificate
		if($min_diff === false) return false;

		$cert_paths = false;
		$used_path = false;
		// Select the config with the best matching score
		foreach($path_scores as $path => $data) {
			if($data['diff'] === $min_diff) {
				$used_path = $path;
				$cert_paths = $data['cert_paths'];
				if($data['has_main_domain'] == true) break;
			}
		}

		$app->log("Let's Encrypt Cert config path is: " . ($used_path ? $used_path : "not found") . ".", LOGLEVEL_DEBUG);

		return $cert_paths;
	}

	/**
	 * certbot | acme.sh
	 * Returns the cleaned SSL Domain. Removes invalid parts and maps empty ssl_domain configs
	 *
	 * @param $data
	 *
	 * @return string
	 */
	private function get_ssl_domain($data) {
		global $app;

		$domain = $data['new']['ssl_domain'];
		if(!$domain) {
			$domain = $data['new']['domain'];
		}

		if($data['new']['ssl'] == 'y' && $data['new']['ssl_letsencrypt'] == 'y') {
			$domain = $data['new']['domain'];
			if(substr($domain, 0, 2) === '*.') {
				// wildcard domain not yet supported by letsencrypt!
				$app->log('Wildcard domains not yet supported by letsencrypt, so changing ' . $domain . ' to ' . substr($domain, 2), LOGLEVEL_WARN);
				$domain = substr($domain, 2);
			}
		}

		return $domain;
	}

	/**
	 * certbot | acme.sh
	 *
	 * Calculates the Paths, where the certificate files should be found within the webroot
	 *
	 * @param $data The website config array
	 *
	 * @return array The path array
	 */
	public function get_website_certificate_paths($data) {
		$ssl_dir = $data['new']['document_root'].'/ssl';
		$domain = $this->get_ssl_domain($data);

		$cert_paths = array(
			'domain' => $domain,
			'key' => $ssl_dir.'/'.$domain.'.key',
			'key2' => $ssl_dir.'/'.$domain.'.key.org',
			'csr' => $ssl_dir.'/'.$domain.'.csr',
			'crt' => $ssl_dir.'/'.$domain.'.crt',
			'bundle' => $ssl_dir.'/'.$domain.'.bundle'
		);

		if($data['new']['ssl'] == 'y' && $data['new']['ssl_letsencrypt'] == 'y') {
			$cert_paths = array(
				'domain' => $domain,
				'key' => $ssl_dir.'/'.$domain.'-le.key',
				'key2' => $ssl_dir.'/'.$domain.'-le.key.org',
				'crt' => $ssl_dir.'/'.$domain.'-le.crt',
				'bundle' => $ssl_dir.'/'.$domain.'-le.bundle'
			);
		}

		return $cert_paths;
	}

	/**
	 * acme.sh
	 *
	 * Check if acme.sh is installed and use it prefered
	 * If neither acme.sh nor certbot are there, install acme.sh
	 *
	 * @return bool
	 */
	public function can_use_acmesh() {
		global $app;

		if($this->get_acme_script()) {
			return true;
		} elseif(!$this->get_certbot_script()) {
			$app->log("Unable to find Let's Encrypt client, installing acme.sh.", LOGLEVEL_DEBUG);
			// acme and le missing
			$this->install_acme();
			if($this->get_acme_script()) {
				return true;
			}
			$app->log("Unable to install acme.sh. Cannot proceed, no Let's Encrypt client found.", LOGLEVEL_WARN);
		}
		return false;
	}

	/**
	 * certbot | acme.sh
	 *
	 * Returns an array of all domains required for the vhost including subdomains and alias domains
	 *
	 * @param $data The website config array
	 *
	 * @return array The domain list for the given website
	 */
	public function get_domains_for_certificate($data) {
		global $app;

		$domain = $this->get_ssl_domain($data);
		// default values
		$temp_domains = array($domain);
		$subdomains = null;
		$aliasdomains = null;

		//* be sure to have good domain
		if(substr($domain,0,4) != 'www.' && ($data['new']['subdomain'] == "www" || $data['new']['subdomain'] == "*")) {
			$temp_domains[] = "www." . $domain;
		}

		//* then, add subdomain if we have
		$subdomains = $app->db->queryAllRecords('SELECT domain FROM web_domain WHERE parent_domain_id = '.intval($data['new']['domain_id'])." AND active = 'y' AND type = 'subdomain' AND ssl_letsencrypt_exclude != 'y'");
		if(is_array($subdomains)) {
			foreach($subdomains as $subdomain) {
				$temp_domains[] = $subdomain['domain'];
			}
		}

		//* then, add alias domain if we have
		$aliasdomains = $app->db->queryAllRecords('SELECT domain,subdomain FROM web_domain WHERE parent_domain_id = '.intval($data['new']['domain_id'])." AND active = 'y' AND type = 'alias' AND ssl_letsencrypt_exclude != 'y'");
		if(is_array($aliasdomains)) {
			foreach($aliasdomains as $aliasdomain) {
				$temp_domains[] = $aliasdomain['domain'];
				if(isset($aliasdomain['subdomain']) && substr($aliasdomain['domain'],0,4) != 'www.' && ($aliasdomain['subdomain'] == "www" OR $aliasdomain['subdomain'] == "*")) {
					$temp_domains[] = "www." . $aliasdomain['domain'];
				}
			}
		}

		// prevent duplicate
		return array_unique($temp_domains);
	}

	/**
	 * certbot
	 *
	 * Checks whether a certificate already exists and returns it using certbot certificate command
	 * Returns false if none is found
	 * Returns an array of the paths to the certificates if found
	 *
	 * @param $domains the domain list
	 *
	 * @return false|array False if none found. An array of the paths if a certificate is found
	 */
	public function get_existing_certificate_from_certbot($domains) {
		global $app;

		// There is no way to check this using acme.sh so always return false
		if (!$this->can_use_acmesh()) {

			$app->log("LE Certbot - Checking for existing certificates using the 'certificate' command", LOGLEVEL_DEBUG);

			$output = explode("\n", shell_exec($this->get_certbot_command($this->COMMAND_TYPE_CHECK, $domains) . " 2>/dev/null | grep -v '^\$'"));
			$le_path = '';
			$le_valid_until = 0;
			$skip_to_next = true;
			$matches = null;
			foreach($output as $outline) {
				$outline = trim($outline);
				$app->log("LE CERT OUTPUT: " . $outline, LOGLEVEL_DEBUG);

				if($skip_to_next === true && !preg_match('/^\s*Certificate Name/', $outline)) {
					continue;
				}
				$skip_to_next = false;

				// Check if the certificate is expired ("VALID: EXPIRED").
				// Skip all other checks
				if(preg_match('/^\s*Expiry.*?VALID:\s+\D/', $outline)) {
					$app->log("Found LE path is expired or invalid: " . $matches[1], LOGLEVEL_DEBUG);
					$skip_to_next = true;
					continue;
				}

				// Get validity information
				if(preg_match('/^\s*Expiry Date:\s?(.*)\s?\(VALID:\s+\d+/', $outline)) {
					$app->log("Certificate valid until: " . $matches[1], LOGLEVEL_DEBUG);
					$le_valid_until = strtotime(trim($matches[1]));
					continue;
				}

				if(preg_match('/^\s*Certificate Path:\s*(\/.*?)\s*$/', $outline, $matches)) {
					$app->log("Found LE path: " . $matches[1], LOGLEVEL_DEBUG);
					$le_path = dirname($matches[1]);
					if(is_dir($le_path)) {
						break;
					} else {
						$le_path = false;
					}
				}
			}

			if($le_path) {
				return array(
					'privkey' => $le_path . '/privkey.pem',
					'chain' => $le_path . '/chain.pem',
					'cert' => $le_path . '/cert.pem',
					'fullchain' => $le_path . '/fullchain.pem',
					'valid_until' => $le_valid_until
				);
			}
		}
		return false;
	}

	/**
	 * certbot
	 *
	 * Searches for an existing certbot certificate
	 *
	 * @param $domains the domains list for certification
	 * @return false|array false if none found, else an array with the paths to the certificate
	 */
	public function get_existing_certificate($domain) {

		$domains = $this->get_domains_for_certificate($domain);

		if($this->can_use_acmesh()) {
			// TODO: Use the --install-cert Command of ACME and check the return code (1 = error | 0 = found)

			return false;
		} else {
			$letsencrypt_version = $this->get_certbot_version();

			// Use Certbot certificate command
			if (version_compare($letsencrypt_version, '0.30', '>=')) {
				return $this->get_existing_certificate_from_certbot($domains);
			} else {
				// On older certbot versions, search on filesystem (legacy fallback)
				return $this->find_matching_certificate_on_filesystem($domains);
			}
		}
	}

	/**
	 * certbot | acme.sh
	 *
	 * Request a new certificate using one of the available LE backends.
	 * Returns the domains of the certificate or false in case of failure
	 *
	 * @param $data The website config
	 * @param string $server_type The http server type (apache | nginx)
	 *
	 * @return array|false False on failure. Else the array of domains the cert was requested for
	 */
	public function fetch_certificate_from_le($data, $server_type = 'apache') {

		global $app, $conf;

		$app->log("Trying to fetch new certificate from Letsencrypt", LOGLEVEL_DEBUG);

		$app->uses('getconf');
		$web_config = $app->getconf->get_server_config($conf['server_id'], 'web');
		$server_config = $app->getconf->get_server_config($conf['server_id'], 'server');

		$use_acme = $this->can_use_acmesh();

		$tmp = $this->get_website_certificate_paths($data);
		$domain = $tmp['domain'];
		$key_file = $tmp['key'];
		$crt_file = $tmp['crt'];
		$bundle_file = $tmp['bundle'];
		// Create the list of all wanted domains
		$temp_domains = $this->get_domains_for_certificate($data);
		// Validate all the domains and only return the validated ones
		$temp_domains = $this->validate_certificate_domains($temp_domains, $web_config, $server_config);

		// LE only accepts 100 domains per single certificate, so cut overflow off and war about it
		$le_domain_count = count($temp_domains);
		if($le_domain_count > 100) {
			$temp_domains = array_slice($temp_domains, 0, 100);
			$app->log("There were " . $le_domain_count . " domains in the domain list. LE only supports 100, so we strip the rest.", LOGLEVEL_WARN);
		}

		// Prepare the LE backend to use, get the command
		$allow_return_codes = null;
		$old_umask = umask(0022);  # work around acme.sh permission bug, see #6015
		if($use_acme) {
			$letsencrypt_cmd = $this->get_acme_command($this->COMMAND_TYPE_REQUEST, $temp_domains, $key_file, $bundle_file, $crt_file, $server_type);
			$allow_return_codes = array(2);
		} else {
			$letsencrypt_cmd = $this->get_certbot_command($this->COMMAND_TYPE_REQUEST, $temp_domains);
			umask($old_umask);
		}

		// Execute the LE Backend call and obtain the certificate
		$success = false;
		if($letsencrypt_cmd) {
			if(!isset($server_config['migration_mode']) || $server_config['migration_mode'] != 'y') {
				$app->log("Create Let's Encrypt SSL Cert for: $domain", LOGLEVEL_DEBUG);
				$app->log("Let's Encrypt SSL Cert domains: ". implode(" ", $temp_domains), LOGLEVEL_DEBUG);

				$success = $app->system->_exec($letsencrypt_cmd, $allow_return_codes);
			} else {
				$app->log("Migration mode active, skipping Let's Encrypt SSL Cert creation for: $domain", LOGLEVEL_DEBUG);
				$success = true;
			}
		}

		if(!$success) {
			$app->log('Let\'s Encrypt SSL Cert for: ' . $domain . ' could not be issued.', LOGLEVEL_WARN);
			$app->log($letsencrypt_cmd, LOGLEVEL_WARN);
			return false;
		} else {
			$app->log('Let\'s Encrypt SSL Cert for: ' . $domain . ' successfully issued.', LOGLEVEL_DEBUG);
			return $temp_domains;
		}

	}

	/**
	 * certbot | acme.sh
	 *
	 * Checks whether a list of domains can be reached from the outside to prevalidate the Let's Encrypt requests
	 *
	 * @param $domains_to_validate List of domains to validate
	 *
	 * @return array The list of validated domains which can be accessed from the outside
	 */
	private function validate_certificate_domains($domains_to_validate, $web_config, $server_config) {
		global $app;
		// check if domains are reachable to avoid letsencrypt verification errors
		$le_rnd_file = uniqid('le-') . '.txt';
		$le_rnd_hash = md5(uniqid('le-', true));
		if(!is_dir('/usr/local/ispconfig/interface/acme/.well-known/acme-challenge/')) {
			$app->system->mkdir('/usr/local/ispconfig/interface/acme/.well-known/acme-challenge/', false, 0755, true);
		}
		file_put_contents('/usr/local/ispconfig/interface/acme/.well-known/acme-challenge/' . $le_rnd_file, $le_rnd_hash);

		$le_domains = array();
		foreach($domains_to_validate as $temp_domain) {
			if((isset($web_config['skip_le_check']) && $web_config['skip_le_check'] == 'y') || (isset($server_config['migration_mode']) && $server_config['migration_mode'] == 'y')) {
				$le_domains[] = $temp_domain;
			} else {
				$le_hash_check = trim(@file_get_contents('http://' . $temp_domain . '/.well-known/acme-challenge/' . $le_rnd_file));
				if($le_hash_check == $le_rnd_hash) {
					$le_domains[] = $temp_domain;
					$app->log("Verified domain " . $temp_domain . " should be reachable for letsencrypt.", LOGLEVEL_DEBUG);
				} else {
					$app->log("Could not verify domain " . $temp_domain . ", so excluding it from letsencrypt request.", LOGLEVEL_WARN);
				}
			}
		}
		@unlink('/usr/local/ispconfig/interface/acme/.well-known/acme-challenge/' . $le_rnd_file);

		return $le_domains;
	}

	/**
	 * certbot | acme.sh
	 *
	 * Setup all required links, files and stuff on the server
	 *
	 * @param $data
	 * @param $domains
	 * @param string $server_type
	 *
	 * @return false | array False on setup failure. Else an array with the validity of the new certificate
	 */
	public function setup_certificate($data, $domains, $server_type = 'apache') {
		global $app;

		// Target paths
		$tmp = $this->get_website_certificate_paths($data);
		$domain = $tmp['domain'];
		$key_file = $tmp['key'];
		$crt_file = $tmp['crt'];
		$bundle_file = $tmp['bundle'];

		if ($this->can_use_acmesh()) {
			// Install the certificate using acme.sh client
			$cmd = $this->get_acme_command($this->COMMAND_TYPE_INSTALL, $domains, $key_file, $bundle_file, $crt_file, $server_type );
			$app->system->exec_safe($cmd);
			// Return the validity of the setup certificate
			return $app->openssl->get_cert_validity($crt_file);
		} else {
			// Certbot requires manually created symlinks

			// Get the existing certificates
			$le_files = $this->get_existing_certificate($domains);
			if (!$le_files) {
				return false;
			}

			// Chose the required files from let's encrypt to setup
			// Apache requires different certificate types (cert only VS full chain) based on the Apache version
			if($server_type != 'apache' || version_compare($app->system->getapacheversion(true), '2.4.8', '>=')) {
				$crt_tmp_file = $le_files['fullchain'];
			} else {
				$crt_tmp_file = $le_files['cert'];
			}
			$key_tmp_file = $le_files['privkey'];
			$bundle_tmp_file = $le_files['chain'];

			//* check is been correctly created
			if(file_exists($crt_tmp_file)) {
				$app->log("Let's Encrypt Cert file: $crt_tmp_file exists.", LOGLEVEL_DEBUG);

				// TODO: check if is a symlink, if target same keep it, either remove it
				if(is_file($key_file)) {
					$app->system->copy($key_file, $key_file.'.old');
					$app->system->chmod($key_file.'.old', 0400);
					$app->system->unlink($key_file);
				}

				if(@is_link($key_file)) $app->system->unlink($key_file);
				if(@file_exists($key_tmp_file)) $app->system->exec_safe("ln -s ? ?", $key_tmp_file, $key_file);

				if(is_file($crt_file)) {
					$app->system->copy($crt_file, $crt_file.'.old');
					$app->system->chmod($crt_file.'.old', 0400);
					$app->system->unlink($crt_file);
				}

				if(@is_link($crt_file)) $app->system->unlink($crt_file);
				if(@file_exists($crt_tmp_file))$app->system->exec_safe("ln -s ? ?", $crt_tmp_file, $crt_file);

				if(is_file($bundle_file)) {
					$app->system->copy($bundle_file, $bundle_file.'.old');
					$app->system->chmod($bundle_file.'.old', 0400);
					$app->system->unlink($bundle_file);
				}

				if(@is_link($bundle_file)) $app->system->unlink($bundle_file);
				if(@file_exists($bundle_tmp_file)) $app->system->exec_safe("ln -s ? ?", $bundle_tmp_file, $bundle_file);

				// All done, return validity of the setup cert file
				return $app->openssl->get_cert_validity($crt_file);
			} else {
				$app->log("Let's Encrypt Cert file: $crt_tmp_file does not exist.", LOGLEVEL_DEBUG);
				return false;
			}
		}
	}

}
