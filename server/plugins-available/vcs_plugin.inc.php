<?php

/*
Creator: Óscar Marcos Silva, funcli.net
*/

class vcs_plugin {

    var $plugin_name = 'vcs_plugin';
    var $class_name = 'vcs_plugin';

    // private variables
    var $action = '';

    //* This function is called during ispconfig installation to determine
    //  if a symlink shall be created for this plugin.
    function onInstall() {
        global $conf;

        if($conf['services']['web'] == true) {
            return true;
        } else {
            return false;
        }

    }


    /*
        This function is called when the plugin is loaded
    */

    function onLoad() {
        global $app;

        /*
        Register for the events
        */

        $app->plugins->registerEvent('web_git_insert', $this->plugin_name, 'insert');
        $app->plugins->registerEvent('web_git_update', $this->plugin_name, 'update');
        $app->plugins->registerEvent('web_git_delete', $this->plugin_name, 'delete');

		//When a web is deleted, check if it has a web_git associated
		$app->plugins->registerEvent('web_domain_delete', $this->plugin_name, 'chekRemovedWeb');
    }

    function chekRemovedWeb($event_name, $data) {
		global $app, $conf;

		$domain_id = $data['old']['domain_id'];

		if($domain_id) {
			$array_logs = $app->db->query("DELETE FROM web_git WHERE parent_domain_id = ?", $domain_id);
		}
    }

    function delete($event_name, $data) {
		global $app, $conf;

		//Website
		$web_id = $data['old']['parent_domain_id'];
		$website = $app->db->queryOneRecord("SELECT * FROM web_domain WHERE domain_id = ?", $web_id);

		//document_root
	        $document_root = escapeshellcmd($website['document_root']);

		if($website) {
			//Document root is a git folder?
			$path = $document_root . '/web';
	                $dr_isGit = $this->pathIsGit($path);
			if($dr_isGit) {
				$this->_exec('rm -rf ' . $path . '/.git');
			}
		}
    }

    /* Pull request */
    function update($event_name, $data) {
		global $app, $conf;
	
		$this->insert($event_name, $data);
    }

    /* Check if clone the repo or make a pull */
    function insert($event_name, $data) {
        global $app, $conf;
        $this->action = 'insert';

		//Website
		$web_id = $data['new']['parent_domain_id'];
		$website = $app->db->queryOneRecord("SELECT * FROM web_domain WHERE domain_id = ?", $web_id);

		//user:grup
		$username = escapeshellcmd($website['system_user']);
        $groupname = escapeshellcmd($website['system_group']);

		//document_root
		$document_root = escapeshellcmd($website['document_root']);

		//Git URL
		$git_url = escapeshellcmd($data['new']['url']);
		//Git User
		$git_user = escapeshellcmd($data['new']['username']);
		//Git pass
		$git_pass = escapeshellcmd($data['new']['password']);

		if($website) {
			//Document root is a git folder?
			$dr_isGit = $this->pathIsGit($document_root . '/web');
			if($dr_isGit) {
				$this->pull_request($data['new'], $website);
			} else {
				$this->setup_git($data['new'], $website);
			}

		}


    }

    private function pull_request($data, $website) {
		global $app, $conf;

		$curr_path = $this->_exec('pwd'); // Current path

		//document_root
        $document_root = escapeshellcmd($website['document_root'])."/web";
        //cd to document_root path
        chdir($document_root);

		$gitBinary = $this->_exec("which git");
		
		$log = date('Y-m-d h:i:s') . " ==========================================================================".PHP_EOL;
		$log .= $this->_exec($gitBinary . ' pull');
		$log .= PHP_EOL."===========================================================================================".PHP_EOL;

		/* the id of the server as int */
		$server_id = intval($conf['server_id']);

		/** The type of the data */
		$type = 'log_web_git_' . $data['web_git_id'];

		/*
		 * actually this info has no state.
		 * maybe someone knows better...???...
		 */
		$state = 'no_state';

		/*
		 * Return the Result
		 */
		$res = array();
		$res['server_id'] = $server_id;
		$res['type'] = $type;
		$res['data'] = $log;
		$res['state'] = $state;

		/*
		 * Insert the log into the database
		 */
		$sql = 'REPLACE INTO monitor_data (server_id, type, created, data, state) ' .
				'VALUES (?, ?, UNIX_TIMESTAMP(), ?, ?)';
		$app->dbmaster->query($sql, $res['server_id'], $res['type'], serialize($res['data']), $res['state']);

		//Permissions
		//user:grup
        $username = escapeshellcmd($website['system_user']);
        $groupname = escapeshellcmd($website['system_group']);

		//Change permsis
        $this->_exec('chown -R  ' . $username . ':' . $groupname . ' ' . $document_root);

		chdir($curr_path);
    }

    /* Creates a new git repo */
    private function setup_git($data, $website) {
		global $app, $conf;

		//Create temp folder for the initial checkout
		$tmp_path = '/tmp/web_git';
	        $this->_exec('mkdir -p ' . $tmp_path);
		$tmp_path .= '/' . $website['domain_id'];

	        //Construir URL git con user y pass
		$url_parts = parse_url($data['url']);

		if($data['username'] != "") $url_parts['user'] = $data['username'];
		if($data['password'] != "") $url_parts['pass'] = $data['password'];

		$git_url = http_build_url($url_parts);

		$gitBinary = $this->_exec("which git");
		if($gitBinary) {
		        //Clonar repo
			$this->_exec($gitBinary . ' clone ' . $git_url . ' ' . $tmp_path);

			//user:grup
	        	$username = escapeshellcmd($website['system_user']);
		        $groupname = escapeshellcmd($website['system_group']);
			//document_root
		        $document_root = escapeshellcmd($website['document_root']);

		        //Cambiar permsis
			$this->_exec('chown -R  ' . $username . ':' . $groupname . ' ' . $tmp_path);

		        //mover .git a document_root
			$this->_exec('mv ' . $tmp_path . '/*' . ' ' . $document_root . '/web/'); /* nano comment */
			$this->_exec('mv ' . $tmp_path . '/.*' . ' ' . $document_root . '/web/');

			//Eliminar carpeta temporal
		        $this->_exec('rm -rf ' . $tmp_path);
		}
    }

    function pathIsGit($path) {
		global $app, $conf;
		$curr_path = $this->_exec('pwd'); // Current path
		//cd to document_root path
		chdir($path);

		$gitBinary = $this->_exec("which git");
		$check = '';
	        if($gitBinary) {
			$check = $this->_exec($gitBinary . ' rev-parse --is-inside-work-tree'); //Returns "true" if inside a git repo
		}

		chdir($curr_path); //back to previous path

		$app->log($check, LOGLEVEL_DEBUG);

		if($check == "true") {
			return true;
		} else {
			return false;
		}
    }

    private function _exec($command) {
		global $app;
		$app->log('exec: '. $command, LOGLEVEL_DEBUG);
		$output = shell_exec($command);
		return trim($output);
    }


} // end class


/**
 * URL constants as defined in the PHP Manual under "Constants usable with
 * http_build_url()".
 *
 * @see http://us2.php.net/manual/en/http.constants.php#http.constants.url
 */
if (!defined('HTTP_URL_REPLACE')) {
	define('HTTP_URL_REPLACE', 1);
}
if (!defined('HTTP_URL_JOIN_PATH')) {
	define('HTTP_URL_JOIN_PATH', 2);
}
if (!defined('HTTP_URL_JOIN_QUERY')) {
	define('HTTP_URL_JOIN_QUERY', 4);
}
if (!defined('HTTP_URL_STRIP_USER')) {
	define('HTTP_URL_STRIP_USER', 8);
}
if (!defined('HTTP_URL_STRIP_PASS')) {
	define('HTTP_URL_STRIP_PASS', 16);
}
if (!defined('HTTP_URL_STRIP_AUTH')) {
	define('HTTP_URL_STRIP_AUTH', 32);
}
if (!defined('HTTP_URL_STRIP_PORT')) {
	define('HTTP_URL_STRIP_PORT', 64);
}
if (!defined('HTTP_URL_STRIP_PATH')) {
	define('HTTP_URL_STRIP_PATH', 128);
}
if (!defined('HTTP_URL_STRIP_QUERY')) {
	define('HTTP_URL_STRIP_QUERY', 256);
}
if (!defined('HTTP_URL_STRIP_FRAGMENT')) {
	define('HTTP_URL_STRIP_FRAGMENT', 512);
}
if (!defined('HTTP_URL_STRIP_ALL')) {
	define('HTTP_URL_STRIP_ALL', 1024);
}
if (!function_exists('http_build_url')) {
	/**
	 * Build a URL.
	 *
	 * The parts of the second URL will be merged into the first according to
	 * the flags argument.
	 *
	 * @param mixed $url     (part(s) of) an URL in form of a string or
	 *                       associative array like parse_url() returns
	 * @param mixed $parts   same as the first argument
	 * @param int   $flags   a bitmask of binary or'ed HTTP_URL constants;
	 *                       HTTP_URL_REPLACE is the default
	 * @param array $new_url if set, it will be filled with the parts of the
	 *                       composed url like parse_url() would return
	 * @return string
	 */
	function http_build_url($url, $parts = array(), $flags = HTTP_URL_REPLACE, &$new_url = array())
	{
		is_array($url) || $url = parse_url($url);
		is_array($parts) || $parts = parse_url($parts);
		isset($url['query']) && is_string($url['query']) || $url['query'] = null;
		isset($parts['query']) && is_string($parts['query']) || $parts['query'] = null;
		$keys = array('user', 'pass', 'port', 'path', 'query', 'fragment');
		// HTTP_URL_STRIP_ALL and HTTP_URL_STRIP_AUTH cover several other flags.
		if ($flags & HTTP_URL_STRIP_ALL) {
			$flags |= HTTP_URL_STRIP_USER | HTTP_URL_STRIP_PASS
				| HTTP_URL_STRIP_PORT | HTTP_URL_STRIP_PATH
				| HTTP_URL_STRIP_QUERY | HTTP_URL_STRIP_FRAGMENT;
		} elseif ($flags & HTTP_URL_STRIP_AUTH) {
			$flags |= HTTP_URL_STRIP_USER | HTTP_URL_STRIP_PASS;
		}
		// Schema and host are alwasy replaced
		foreach (array('scheme', 'host') as $part) {
			if (isset($parts[$part])) {
				$url[$part] = $parts[$part];
			}
		}
		if ($flags & HTTP_URL_REPLACE) {
			foreach ($keys as $key) {
				if (isset($parts[$key])) {
					$url[$key] = $parts[$key];
				}
			}
		} else {
			if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH)) {
				if (isset($url['path']) && substr($parts['path'], 0, 1) !== '/') {
					// Workaround for trailing slashes
					$url['path'] .= 'a';
					$url['path'] = rtrim(
							str_replace(basename($url['path']), '', $url['path']),
							'/'
						) . '/' . ltrim($parts['path'], '/');
				} else {
					$url['path'] = $parts['path'];
				}
			}
			if (isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY)) {
				if (isset($url['query'])) {
					parse_str($url['query'], $url_query);
					parse_str($parts['query'], $parts_query);
					$url['query'] = http_build_query(
						array_replace_recursive(
							$url_query,
							$parts_query
						)
					);
				} else {
					$url['query'] = $parts['query'];
				}
			}
		}
		if (isset($url['path']) && $url['path'] !== '' && substr($url['path'], 0, 1) !== '/') {
			$url['path'] = '/' . $url['path'];
		}
		foreach ($keys as $key) {
			$strip = 'HTTP_URL_STRIP_' . strtoupper($key);
			if ($flags & constant($strip)) {
				unset($url[$key]);
			}
		}
		$parsed_string = '';
		if (!empty($url['scheme'])) {
			$parsed_string .= $url['scheme'] . '://';
		}
		if (!empty($url['user'])) {
			$parsed_string .= $url['user'];
			if (isset($url['pass'])) {
				$parsed_string .= ':' . $url['pass'];
			}
			$parsed_string .= '@';
		}
		if (!empty($url['host'])) {
			$parsed_string .= $url['host'];
		}
		if (!empty($url['port'])) {
			$parsed_string .= ':' . $url['port'];
		}
		if (!empty($url['path'])) {
			$parsed_string .= $url['path'];
		}
		if (!empty($url['query'])) {
			$parsed_string .= '?' . $url['query'];
		}
		if (!empty($url['fragment'])) {
			$parsed_string .= '#' . $url['fragment'];
		}
		$new_url = $url;
		return $parsed_string;
	}
}

?>
