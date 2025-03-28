<?php

/*
Copyright (c) 2025, Till Brehm, ISPConfig UG
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

class extension_installer
{

	/**
	 * The repository list URL
	 * @var string
	 */
	private $repo_list_url = 'https://repo.ispconfig.com/api/v1/list/';
	private $extension_basedir = '/usr/local/ispconfig/extensions';
	private $error = [];

	private $repo_cache = [];

	/**
	 * Get the repository list URL
	 * @return string
	 */
	public function getRepoListUrl() {
		if(file_exists($this->extension_basedir.'/devkey')) {
			$devkey = trim(file_get_contents($this->extension_basedir.'/devkey'));
			return $this->repo_list_url.'?devkey='.urlencode($devkey);
		}
		
		return $this->repo_list_url;
	}

	// add error
	public function addError($error) {
		$this->error[] = $error;
	}

	// get errors
	public function getErrors() {
		return $this->error;
	}

	// has errors
	public function hasErrors() {
		return count($this->error) > 0;
	}

	/**
	 * Get the list of available extensions from the repository
	 * @return array
	 */
	public function getRepoExtensions() {
		if(empty($this->repo_cache)) {
			$response = file_get_contents($this->getRepoListUrl());
			if (empty($response)) {
				return [];
			} else {
				$this->repo_cache = json_decode($response, true);
			}
		}
		
		return $this->repo_cache;
	}

	public function getRepoExtension($name) {
		$repo_extensions = $this->getRepoExtensions();
		
		$repo_extension = array_filter($repo_extensions, fn($ext) => $ext['name'] === $name);
		
		if(!empty($repo_extension)) {
			return reset($repo_extension);
		} else {
			return null;
		}
	}

	public function getInstalledExtensions($server_id = null) {
		global $app, $conf;
		
		// get extensions from monitor_data table
		if($server_id === null) {
			$sql = 'SELECT * FROM `monitor_data` WHERE `type` = ?';
			$records = $app->db->queryAllRecords($sql, 'extensions');
		} else {
			$sql = 'SELECT * FROM `monitor_data` WHERE `type` = ? and `server_id` = ?';
			$records = $app->db->queryAllRecords($sql, 'extensions', $server_id);
		}

		// get repo extensions
		$repo_extensions = $this->getRepoExtensions();
		
		if(empty($records)) {
			return [];
		} else {
			$extensions = [];
			foreach($records as $record) {
				$data_records = json_decode($record['data'], true);
				if(!empty($data_records) && is_array($data_records)) {
					foreach($data_records as $data) {
						// get title by searching in repo extensions
						$repo_extension = array_filter($repo_extensions, fn($ext) => $ext['name'] === $data['name']);
						if(!empty($repo_extension)) {
							$repo_extension = reset($repo_extension);
							$extensions[] = [
								'name' => $data['name'],
								'version' => $data['version'],
								'license' => $data['license'],
								'title' => $repo_extension['title'],
								'active' => $data['active'],
								'server_id' => $record['server_id']
							];
						}
					}
				}
			}
			return $extensions;
		}
	}

	public function getInstalledExtension($name, $server_id) {
		global $app, $conf;

		// getInstalledExtensions
		$extensions = $this->getInstalledExtensions($server_id);
		
		// search in extensions
		$extension = array_filter($extensions, fn($ext) => $ext['name'] === $name);
		if(!empty($extension)) {
			return reset($extension);
		} else {
			return null;
		}
	}

	public function installExtension($name, $server_id) {
		global $app, $conf;

		// check if extension exists in repository
		$repo_extension = $this->getRepoExtension($name);
		if($repo_extension === null) {
			$this->addError('Extension not found in repository');
			return false;
		}

		// check if extension is already installed
		$installed_extensions = $this->getInstalledExtensions($server_id);
		$extension = array_filter($installed_extensions, fn($ext) => $ext['name'] === $name);
		if(!empty($extension)) {
			$this->addError('Extension already installed');
			return false;
		}

		// check server_id in server table
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			$this->addError('Server not found');
			return false;
		}
		
		// Install the extension
		$sql = "INSERT INTO sys_remoteaction (server_id, tstamp, action_type, action_param, action_state, response) " .
				"VALUES (?, UNIX_TIMESTAMP(), 'extension_install', ?, 'pending', '')";
		$app->db->query($sql, $server_id, $name);
		
		return true;
	}

	public function updateExtension($name, $server_id) {
		global $app, $conf;
		
		// check if extension exists in repository
		$repo_extension = $this->getRepoExtension($name);
		if($repo_extension === null) {
			$this->addError('Extension not found in repository');
			return false;
		}
		
		// check if extension is already installed
		$installed_extensions = $this->getInstalledExtensions($server_id);
		$extension = array_filter($installed_extensions, fn($ext) => $ext['name'] === $name);
		if(empty($extension)) {
			$this->addError('Extension not installed');
			return false;
		}
		
		// check server_id in server table
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			$this->addError('Server not found');
			return false;
		}
		
		// Update the extension
		$sql = "INSERT INTO sys_remoteaction (server_id, tstamp, action_type, action_param, action_state, response) " .
				"VALUES (?, UNIX_TIMESTAMP(), 'extension_update', ?, 'pending', '')";
		$app->db->query($sql, $server_id, $name);
		
		return true;
	}

	public function deleteExtension($name, $server_id) {
		global $app, $conf;
		
		// check if extension is already installed
		$installed_extensions = $this->getInstalledExtensions($server_id);
		$extension = array_filter($installed_extensions, fn($ext) => $ext['name'] === $name);
		if(empty($extension)) {
			$this->addError('Extension not installed');
			return false;
		}
		
		// check server_id in server table
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			$this->addError('Server not found');
			return false;
		}
		
		// Delete the extension
		$sql = "INSERT INTO sys_remoteaction (server_id, tstamp, action_type, action_param, action_state, response) " .
				"VALUES (?, UNIX_TIMESTAMP(), 'extension_uninstall', ?, 'pending', '')";
		$app->db->query($sql, $server_id, $name);
		
		return true;
		}

	public function enableExtension($name, $server_id) {
		global $app, $conf;
		
		// check if extension is already installed
		$installed_extensions = $this->getInstalledExtensions($server_id);
		$extension = array_filter($installed_extensions, fn($ext) => $ext['name'] === $name);
		if(empty($extension)) {
			$this->addError('Extension not installed');
			return false;
		}
		
		// check server_id in server table
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			$this->addError('Server not found');
			return false;
		}
		
		// Enable the extension
		$sql = "INSERT INTO sys_remoteaction (server_id, tstamp, action_type, action_param, action_state, response) " .
				"VALUES (?, UNIX_TIMESTAMP(), 'extension_enable', ?, 'pending', '')";
		$app->db->query($sql, $server_id, $name);
		
		return true;
	}

	public function disableExtension($name, $server_id) {
		global $app, $conf;
		
		// check if extension is already installed
		$installed_extensions = $this->getInstalledExtensions($server_id);
		$extension = array_filter($installed_extensions, fn($ext) => $ext['name'] === $name);
		if(empty($extension)) {
			$this->addError('Extension not installed');
			return false;
		}
		
		// check server_id in server table
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			$this->addError('Server not found');
			return false;
		}
		
		// Disable the extension
		$sql = "INSERT INTO sys_remoteaction (server_id, tstamp, action_type, action_param, action_state, response) " .
				"VALUES (?, UNIX_TIMESTAMP(), 'extension_disable', ?, 'pending', '')";
		$app->db->query($sql, $server_id, $name);
		
		return true;
	}

	public function updateLicense($name, $server_id, $license) {
		global $app, $conf;
		
		// check if extension is already installed
		$installed_extensions = $this->getInstalledExtensions($server_id);
		$extension = array_filter($installed_extensions, fn($ext) => $ext['name'] === $name);
		if(empty($extension)) {
			$this->addError('Extension not installed');
			return false;
		}
		
		// check server_id in server table
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			$this->addError('Server not found');
			return false;
		}
		
		// Update the license
		$data = ['name' => $name, 'license' => $license];
		$sql = "INSERT INTO sys_remoteaction (server_id, tstamp, action_type, action_param, action_state, response) " .
				"VALUES (?, UNIX_TIMESTAMP(), 'extension_license_update', ?, 'pending', '')";
		$app->db->query($sql, $server_id, json_encode($data));
		
		return true;
	}

	public function getServerName($server_id) {
		global $app;
		$sql = 'SELECT `server_name` FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			return null;
		}
		return $record['server_name'];
	}

}
