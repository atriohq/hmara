<?php

class extension_installer {

	private $extension_basedir = '/usr/local/ispconfig/extensions';
	private $ispconfig_dir = '/usr/local/ispconfig';
	private $download_url = 'https://repo.ispconfig.com/packages/';
	private $repo_list_url = 'https://repo.ispconfig.com/api/v1/list/';

	public $errors = [];

	// get functions for properties
	public function getExtensionBasedir() {
		return $this->extension_basedir;
	}
	public function getIspconfigDir() {
		return $this->ispconfig_dir;
	}
	public function getDownloadUrl() {
		return $this->download_url;
	}
	public function getRepoListUrl() {
		if(file_exists($this->extension_basedir.'/devkey')) {
			$devkey = trim(file_get_contents($this->extension_basedir.'/devkey'));
			return $this->repo_list_url.'?devkey='.urlencode($devkey);
		}
		
		return $this->repo_list_url;
	}

	// get errors
	public function getErrors() {
		return $this->errors;
	}

	// add Error message
	public function addError($error) {
		$this->errors[] = $error;
	}

	/**
     * Check if the extension name is valid
     * @param string $extension_name
     * @return bool
     */
    public function checkExtensionName($extension_name, $version = null) {
        global $app;
        
        // Check empty extension name
        if(empty($extension_name)) {
            $this->addError('Extension name may not be empty.');
            return false;
        }

        // Check for invalid chars
        if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/',$extension_name)) {
            $this->addError('Extension name contains invalid characters.');
            return false;
        }

        // check if extension exists in repository
		$response = file_get_contents($this->getRepoListUrl());
		$repo_extensions = json_decode($response, true);

		if(empty($repo_extensions)) {
			$this->addError('No extensions available in repository.');
            return false;
		}

		// Check if the extension exists in the repository
		$extension_found = false;
		foreach($repo_extensions as $extension) {
			if($extension['name'] == $extension_name) {
				if($version) {
					if($extension['version'] != $version) {
						$this->addError('Extension version not found in repository.');
						return false;
					}
				}
				$extension_found = true;
				break;
			}
		}

		if(!$extension_found) {
			$this->addError('Extension not found in repository.');
			return false;
		}

        return true;
    }

	/**
	 * Summary of enable_files
	 * @param mixed $name
	 * @return bool
	 */
	public function enable_files($name) {
		global $app, $conf;

		// check name validity
		if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/',$name)) {
			$app->log('Invalid extension name: '.$name, LOGLEVEL_WARN);
			$this->addError('Invalid extension name: '.$name);
			return false;
		}

		$ext_dir = $this->extension_basedir.'/'.$name;

		// check name against regex
		if(!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
			$app->log('Enabling extension'.$name.'failed. Invalid name.',LOGLEVEL_WARN);
			$this->addError('Enabling extension'.$name.'failed. Invalid name.');
			return false;
		}

		// Check if the extension has already been downloaded
		if(!is_dir($ext_dir)) {
			$app->log('Enabling extension'.$name.'failed. No such directory.',LOGLEVEL_WARN);
			$this->addError('Enabling extension'.$name.'failed. No such directory.');
			return false;
		}

		// Check if we have a file.list
		$file_list_path = $ext_dir.'/install/file.list';
		if(!file_exists($file_list_path)) {
			$app->log('The extension '.$name.' has no file list.',LOGLEVEL_WARN);
			$this->addError('The extension '.$name.' has no file list.');
			return false;
		}

		// Read file list
		$files = file($file_list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if(!empty($files) && is_array($files)) {
			foreach($files as $file) {
				// Skip comment lines
				if(substr(trim($file),0,1) == '#') continue;
				// skip empty lines
				if(empty(trim($file))) continue;

				// parse line
				list($action,$source,$target) = explode(':',trim($file));
				// echo 'Action: '.$action.' Source: '.$source.' Target: '.$target."\n";

				// Check action, must be c or s or d
				if($action != 'c' && $action != 's' && $action != 'd') {
					$app->log('Invalid file list action: '.$action, LOGLEVEL_WARN);
					$this->addError('Invalid file list action: '.$action);
					return false;
				}

				// Check source
				if(empty($source) || empty($action) || $source == '/' || $source == '.' || $source == '..') {
					$app->log('Invalid file list: '.$file_list_path, LOGLEVEL_WARN);
					$this->addError('Invalid file list: '.$file_list_path);
					return false;
				}
				// source file must be within /usr/local/ispconfig/extensions, check with realpath
				$source = realpath($ext_dir.'/'.$source);

				// check if empty after realpath
				if(empty($source)) {
					$app->log('Invalid file list: '.$file_list_path, LOGLEVEL_WARN);
					$this->addError('Invalid file list: '.$file_list_path);
					return false;
				}

				// Check target
				if(empty($target) || empty($action) || $target == '/' || $target == '.' || strpos($target, '..') !== false) {
					$app->log('Invalid file list: '.$file_list_path, LOGLEVEL_WARN);
					$this->addError('Invalid file list: '.$file_list_path);
					return false;
				}
				
				// Copy
				if($action == 'c') {
					if(!empty($source) && !empty($target) && is_file($source)) {
						if(is_link($this->ispconfig_dir.'/'.$target)) unlink($this->ispconfig_dir.'/'.$target);
						copy($source, $this->ispconfig_dir.'/'.$target);
						// if target starts with 'interface'
						if (substr($target, 0, 9) == 'interface') {
							exec('chown -h ispconfig:ispconfig '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						} else {
							exec('chown -h root:root '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						}
						exec('chmod 640 '.escapeshellarg($this->ispconfig_dir.'/'.$target));
					}
					if(!empty($source) && !empty($target) && is_dir($source)) {
						if(is_link($this->ispconfig_dir.'/'.$target)) unlink($this->ispconfig_dir.'/'.$target);
						exec('cp -prf '.$source.' '.$this->ispconfig_dir.'/'.$target);
						// if target starts with 'interface'
						if (substr($target, 0, 9) == 'interface') {
							exec('chown -R ispconfig:ispconfig '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						} else {
							exec('chown -R root:root '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						}
					}
				}

				// Symlink
				if($action == 's') {
					if(!empty($source) && !empty($target) && file_exists($source)) {
						if(is_link($this->ispconfig_dir.'/'.$target)) unlink($this->ispconfig_dir.'/'.$target);
						symlink($source, $this->ispconfig_dir.'/'.$target);
						// if target starts with 'interface'
						if (substr($target, 0, 9) == 'interface') {
							exec('chown ispconfig:ispconfig '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						} else {
							exec('chown root:root '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						}
						exec('chmod 640 '.escapeshellarg($this->ispconfig_dir.'/'.$target));
					}
				}

				// Directory "d"
				if($action == 'd') {
					if(!empty($target) && !is_dir($this->ispconfig_dir.'/'.$target)) {
						//exec('mkdir -p '.$this->ispconfig_dir.'/'.$target);
						$app->system->mkdir($this->ispconfig_dir.'/'.$target,false,0750);
						// if target starts with 'interface'
						if (substr($target, 0, 9) == 'interface') {
							exec('chown ispconfig:ispconfig '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						} else {
							exec('chown root:root '.escapeshellarg($this->ispconfig_dir.'/'.$target));
						}
						exec('chmod 640 '.escapeshellarg($this->ispconfig_dir.'/'.$target));
					}
				}
			}
		}

		// Create active file
		touch($ext_dir.'/active');

		return true;

	}

	/**
	 * Summary of disable_files
	 * @param mixed $name
	 * @return void
	 */
	public function disable_files($name) {
		global $app, $conf;

		// check name validity
		if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/',$name)) {
			$app->log('Invalid extension name: '.$name, LOGLEVEL_WARN);
			$this->errors[] = 'Invalid extension name: '.$name;
			return false;
		}

		$ext_dir = $this->extension_basedir.'/'.$name;

		// Check if the extension has already been downloaded
		if(!is_dir($ext_dir)) {
			$app->log('Disabling extension'.$name.'failed. No such directory.',LOGLEVEL_WARN);
			$this->addError('No such directory: '.$ext_dir);
			return false;
		}

		// Check if we have a file.list
		$file_list_path = $ext_dir.'/install/file.list';
		if(!file_exists($file_list_path)) {
			$app->log('The extension '.$name.' has no file list.',LOGLEVEL_WARN);
			$this->addError('No file list: '.$file_list_path);
			return false;
		}

		// Read file list
		$files = file($file_list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if(!empty($files) && is_array($files)) {
			foreach($files as $file) {
				// Skip comment lines
				if(substr(trim($file),0,1) == '#') continue;
				// skip empty lines
				if(empty(trim($file))) continue;

				// parse line
				list($action,$source,$target) = explode(':',$file);

				// Check action, must be c or s or d
				if($action != 'c' && $action != 's' && $action != 'd') {
					$app->log('Invalid file list action: '.$action, LOGLEVEL_WARN);
					$this->addError('Invalid file list action: '.$action);
					return false;
				}

				// Check target
				if(empty($target) || empty($action) || $target == '/' || $target == '.' || $target == '..') {
					$app->log('Invalid target: '.$target, LOGLEVEL_WARN);
					$this->addError('Invalid target: '.$target);
					return false;
				}

				// target file must be within /usr/local/ispconfig
				$target = $this->ispconfig_dir.'/'.$target;

				// check if empty after realpath
				if(empty($target)) {
					$app->log('Invalid target after realpath: '.$target, LOGLEVEL_WARN);
					$this->addError('Invalid target after realpath: '.$target);
					return false;
				}

				// check if target is within /usr/local/ispconfig and exists
				if(!str_starts_with($target, $this->ispconfig_dir)) {
					$app->log('Target not within /usr/local/ispconfig: '.$target, LOGLEVEL_WARN);
					$this->addError('Target not within /usr/local/ispconfig: '.$target);
					return false;
				}

				// Remove Copy
				if($action == 'c') {
					if(!empty($target) && is_file($target)) {
						unlink($target);
					}
					if(!empty($target) && $target != '/' && $target != '.' && $target != '..' && is_dir($target)) {
						exec('rm -rf '.escapeshellarg($target));
					}
				}

				

				// Remove Symlink
				if($action == 's') {
					if(!empty($target) && is_link($target)) {
						unlink($target);
					}
				}

			}
		}

		// Delete active file
		if(file_exists($ext_dir.'/active')) {
			unlink($ext_dir.'/active');
		}

		return true;
	}

	/**
	 * Summary of download_extension
	 * @param mixed $name
	 * @return void
	 */
	public function download_extension($name, $version = null, $force = false) {
		global $app, $conf;

		// check name validity
		if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/',$name)) {
			$app->log('Invalid extension name: '.$name, LOGLEVEL_WARN);
			$this->addError('Invalid extension name: '.$name);
			return false;
		}

		// check if extension exists in repository
		$response = file_get_contents($this->getRepoListUrl());
		$repo_extensions = json_decode($response, true);

		if(empty($repo_extensions)) {
			$app->log('No extensions available in repository', LOGLEVEL_WARN);
			$this->addError('No extensions available in repository');
			return false;
		}

		// Check if the extension exists in the repository
		$extension_found = false;
		foreach($repo_extensions as $extension) {
			if($extension['name'] == $name) {
				if($version) {
					if($extension['version'] != $version) {
						$app->log('Extension version not found in repository', LOGLEVEL_WARN);
						$this->addError('Extension version not found in repository');
						return false;
					}
				}
				$extension_found = true;
				break;
			}
		}

		if(!$extension_found) {
			$app->log('Extension not found in repository', LOGLEVEL_WARN);
			$this->addError('Extension not found in repository');
			return false;
		}

		$ext_dir = $this->extension_basedir.'/'.$name;

		// Check if the extension has already been downloaded
		if($force == false && is_dir($ext_dir)) {
			return true;
		}

		// Create the directory if it does not exist
		if(!is_dir($ext_dir)) {
			mkdir($ext_dir, 0750, true);
			// change to user and group ispconfig
			exec('chown -R ispconfig:ispconfig '.escapeshellarg($ext_dir));
		}

		// Download the extension using php curl
		$curl = curl_init();
		if($version) {
			$package_url = $this->download_url.$name.'-'.$version.'.pkg';
		} else {
			$package_url = $this->download_url.$name.'.pkg';
		}
		$app->log('Downloading extension from: '.$package_url, LOGLEVEL_DEBUG);
		curl_setopt($curl, CURLOPT_URL, $package_url);
		// Remove CURLOPT_RETURNTRANSFER as it conflicts with CURLOPT_FILE
		// curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		
		// Write the file with .pkg extension
		$package_file = $ext_dir.'/'.$name.'.pkg';
		$fp = fopen($package_file, 'w');
		curl_setopt($curl, CURLOPT_FILE, $fp);
		$result = curl_exec($curl);
		
		// Check for cURL errors
		if($result === false) {
			$error = curl_error($curl);
			$app->log('cURL error when downloading extension '.$name.': '.$error, LOGLEVEL_WARN);
			$this->addError('Failed to download extension: '.$name.' - '.$error);
			fclose($fp);
			curl_close($curl);
			return false;
		}
		
		// Get HTTP status code
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		fclose($fp);
		
		// Check if the file was downloaded successfully
		if($http_code != 200 || !file_exists($package_file) || filesize($package_file) == 0) {
			$app->log('Failed to download extension '.$name.'. HTTP status: '.$http_code, LOGLEVEL_WARN);
			$this->addError('Failed to download extension: '.$name.'. HTTP status: '.$http_code);
			// Remove empty file if it exists
			if(file_exists($package_file)) unlink($package_file);
			return false;
		}
		
		// unpack the zip archive
		// check if zip extension in PHP is enabled, if not, use unzip command on the shell
		if (!extension_loaded('zip')) {
			// Create a temporary directory for extraction to avoid nested folders
			$temp_dir = $ext_dir.'_temp';
			if(!is_dir($temp_dir)) {
				mkdir($temp_dir, 0750, true);
				exec('chown -R ispconfig:ispconfig '.escapeshellarg($temp_dir));
			}
			
			// Extract to temp directory
			exec('unzip -o '.escapeshellarg($package_file).' -d '.escapeshellarg($temp_dir));
			
			// Check if we have a nested directory with the same name
			if(is_dir($temp_dir.'/'.$name)) {
				// Move contents from nested directory to the extension directory
				exec('cp -rf '.escapeshellarg($temp_dir.'/'.$name.'/*').' '.escapeshellarg($ext_dir.'/'));
				// Remove temporary directory
				exec('rm -rf '.escapeshellarg($temp_dir));
			} else {
				// No nested directory, just move everything
				exec('cp -rf '.escapeshellarg($temp_dir.'/*').' '.escapeshellarg($ext_dir.'/'));
				// Remove temporary directory
				exec('rm -rf '.escapeshellarg($temp_dir));
			}
		} else {
			$zip = new ZipArchive();
			if ($zip->open($package_file) === TRUE) {
				// Create a temporary directory for extraction to avoid nested folders
				$temp_dir = $ext_dir.'_temp';
				if(!is_dir($temp_dir)) {
					mkdir($temp_dir, 0750, true);
					exec('chown -R ispconfig:ispconfig '.escapeshellarg($temp_dir));
				}
				
				// Extract to temp directory
				$zip->extractTo($temp_dir);
				$zip->close();
				
				// Check if we have a nested directory with the same name
				if(is_dir($temp_dir.'/'.$name)) {
					// Get all files and directories in the nested directory
					$files = scandir($temp_dir.'/'.$name);
					foreach($files as $file) {
						if($file != '.' && $file != '..') {
							$source = $temp_dir.'/'.$name.'/'.$file;
							$target = $ext_dir.'/'.$file;
							
							if(is_dir($source)) {
								// For directories, use recursive copy
								$app->system->exec_safe('cp -rf ? ?', $source, $ext_dir);
							} else {
								// For files, just copy
								copy($source, $target);
							}
						}
					}
				} else {
					// No nested directory, just move everything
					$files = scandir($temp_dir);
					foreach($files as $file) {
						if($file != '.' && $file != '..') {
							$source = $temp_dir.'/'.$file;
							$target = $ext_dir.'/'.$file;
							
							if(is_dir($source)) {
								// For directories, use recursive copy
								$app->system->exec_safe('cp -rf ? ?', $source, $ext_dir);
							} else {
								// For files, just copy
								copy($source, $target);
							}
						}
					}
				}
				
				// Remove temporary directory
				$app->system->exec_safe('rm -rf ?', $temp_dir);
			} else {
				$app->log('Failed to extract extension '.$name, LOGLEVEL_WARN);
				$this->addError('Failed to extract extension: '.$name);
				return false;
			}
			unlink($package_file);
			unset($zip);
		}

		// Change all extension files to ispconfig:ispconfig
        exec('chown -R ispconfig:ispconfig '.escapeshellarg($ext_dir.'/'));
        // chmod
        exec('chmod -R 750 '.escapeshellarg($ext_dir.'/'));
		
		return true;
	}

	/**
	 * Load install.sql dump into the database
	 */
	public function load_install_sql($name) {
		global $app, $conf;

		// check name validity
		if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/',$name)) {
			$app->log('Invalid extension name: '.$name, LOGLEVEL_WARN);
			$this->addError('Invalid extension name: '.$name);
			return false;
		}

		$ext_dir = $this->extension_basedir.'/'.$name;

		// Check if the extension has already been downloaded
		if(!is_dir($ext_dir)) {
			$app->log('Loading install.sql for extension'.$name.'failed. No such directory.',LOGLEVEL_WARN);
			$this->addError('No such directory: '.$ext_dir);
			return false;
		}

		// Check if we have a install.sql
		$install_sql_path = $ext_dir.'/install/install.sql';
		if(!file_exists($install_sql_path)) {
			$app->log('The extension '.$name.' has no install.sql.',LOGLEVEL_DEBUG);
			//$this->errors[] = 'No install.sql: '.$install_sql_path;
			return false;
		}
		
		// Load install.sql using mysql command with login details from $conf
		exec('mysql -u '.escapeshellarg($conf['mysql']['user']).' -p'.escapeshellarg($conf['mysql']['password']).' -D '.escapeshellarg($conf['mysql']['db_name']).' < '.escapeshellarg($install_sql_path), $output, $return_var);
		
		// check if execcommand was successful
		if($return_var != 0) {
			$app->log('Failed to load install.sql for extension '.$name, LOGLEVEL_WARN);
			$this->addError('Failed to load install.sql: '.$install_sql_path);
			return false;
		}
		

		// log success
		$app->log('Loaded install.sql for extension '.$name, LOGLEVEL_INFO);
		
		return true;
	}

	public function scan_extensions() {
        global $app, $conf;

        $app->log('Scanning extensions', LOGLEVEL_DEBUG);

		$extensions = [];

        // scan extensions directory
        $extension_directories = glob($this->extension_basedir.'/*', GLOB_ONLYDIR);
        if(!empty($extension_directories) && is_array($extension_directories)) {
            foreach($extension_directories as $extension_directory) {
                $app->log('Scanning extension '.$extension_directory, LOGLEVEL_DEBUG);
				
				// check if active
				if(file_exists($extension_directory.'/active')) {
					$active = true;
				} else {
					$active = false;
				}
				
				// check version
				if(file_exists($extension_directory.'/version')) {
					$version = file_get_contents($extension_directory.'/version');
				} else {
					$version = 'Unknown';
				}

				// check license
				if(file_exists($extension_directory.'/license')) {
					$license = file_get_contents($extension_directory.'/license');
				} else {
					$license = '';
				}
				
				$extensions[] = [
					'name' => basename($extension_directory),
					'active' => $active,
					'version' => $version,
					'license' => $license
				];
				$app->log('Found extension: '.basename($extension_directory), LOGLEVEL_DEBUG);
            }
        }

		if(!empty($extensions)) {
			//$app->log('Found extensions: '.print_r($extensions, true), LOGLEVEL_DEBUG);
			// store in monitor_data table

			// check if we have this type in monitor_data
			$sql = 'SELECT * FROM `monitor_data` WHERE `type` = ? and `server_id` = ?';
			$rec = $app->dbmaster->queryOneRecord($sql, 'extensions', $conf['server_id']);
			if(!empty($rec)) {
				// update
				$sql = 'UPDATE `monitor_data` SET `data` = ?, `created` = ? WHERE `type` = ? and `server_id` = ?';
				$app->dbmaster->query($sql, json_encode($extensions), time(), 'extensions', $conf['server_id']);
			} else {
				// insert
				$sql = 'INSERT INTO `monitor_data` (`server_id`, `type`, `created`, `data`, `state`) ' .
				'VALUES (' .
				$conf['server_id'] . ', ' .
				"'" . $app->dbmaster->quote('extensions') . "', " .
				'UNIX_TIMESTAMP(), ' .
				"'" . $app->dbmaster->quote(json_encode($extensions)) . "', " .
				"'" . 'ok' . "'" .
				')';
				$app->dbmaster->query($sql);
			}
		}

        return $extensions;
    }

	/**
	 * Install an extension
	 * @param string $name
	 * @param string $version
	 * @return bool
	 */

	 public function install_extension($name, $version = null) {
		global $app;

		// download extension if not already downloaded
        if(!is_dir($this->extension_basedir.'/'.$name)) {
            if(!$this->download_extension($name,$version)) {
                $this->addError('Failed to download extension '.$name);
                return false;
            }
        }

		// check if installer.php exists
        if(!file_exists($this->extension_basedir.'/'.$name.'/install/installer.php')) {
            $this->addError('Extension installer class not found.');
            return false;
        }
        
        // Include extension class from install directory
        require_once($this->extension_basedir.'/'.$name.'/install/installer.php');
        $classname = $name.'_installer';
        $installer = new $classname();

        if(!is_object($installer)) {
            $this->addError('Extension installer class not found or not an instance of installer.');
            return false;
        }

        // Install extension
        $installer->install($name);

        // Load install.sql
        $this->load_install_sql($name);

        // enable extension
        $installer->enable($name);

		return true;
	}

	public function update_extension($name, $version = null) {
		global $app;

		// download extension
        if(!$this->download_extension($name,$version,true)) {
            if(empty($version)) {
                $this->errors[] = 'Error: Failed to download extension '.$name;
            } else {
                $this->errors[] = 'Error: Failed to download extension '.$name.' version '.$version;
            }
            return false;
        }

        // check if installer.php exists
        if(!file_exists($this->extension_basedir.'/'.$name.'/install/installer.php')) {
            $this->addError('Extension installer class not found.');
            return false;
        }
        
        // Include extension class from install directory
        require_once($this->extension_basedir.'/'.$name.'/install/installer.php');
        $classname = $name.'_installer';
        $installer = new $classname();

        if(!is_object($installer)) {
            $this->addError('Extension installer class not found or not an instance of installer.');
            return false;
        }

        // Update extension
        $installer->update($name);

        // enable extension
        $installer->enable($name);
		
		return true;
	}

	/**
	 * Uninstall an extension
	 * @param string $name
	 * @return bool
	 */
	public function uninstall_extension($name) {
		global $app;

		// check if extension is installed
		if(!is_dir($this->extension_basedir.'/'.$name)) {
			$this->addError('Extension - '.$name.' - is not installed.');
			return false;
		}

		// check if installer.php exists
		if(!file_exists($this->extension_basedir.'/'.$name.'/install/installer.php')) {
			$this->addError('Extension installer class not found.');
			return false;
		}

		// include extension class from install directory
		require_once($this->extension_basedir.'/'.$name.'/install/installer.php');
		$classname = $name.'_installer';
		$installer = new $classname();

		if(!is_object($installer)) {
			$this->addError('Extension installer class not found or not an instance of installer.');
			return false;
		}

		// disable extension
		$installer->disable($name);

		// Uninstall extension
		$installer->uninstall($name);

		// Remove extension directory
        if(!empty($this->extension_basedir.'/'.$name) && is_dir($this->extension_basedir.'/'.$name)) {
            exec('rm -rf '.escapeshellarg($this->extension_basedir.'/'.$name));
        }

		return true;
	}

	public function enable_extension($name) {
		global $app;

		// check if extension is installed
		if(!is_dir($this->extension_basedir.'/'.$name)) {
			$this->addError('Extension - '.$name.' - is not installed.');
			return false;
		}

		// check if installer.php exists
		if(!file_exists($this->extension_basedir.'/'.$name.'/install/installer.php')) {
			$this->addError('Extension installer class not found.');
			return false;
		}

		// include extension class from install directory
		require_once($this->extension_basedir.'/'.$name.'/install/installer.php');
		$classname = $name.'_installer';
		$installer = new $classname();

		if(!is_object($installer)) {
			$this->addError('Extension installer class not found or not an instance of installer.');
			return false;
		}

		// enable extension
		$installer->enable($name);

		return true;
	}

	public function disable_extension($name) {
		global $app;

		// check if extension is installed
		if(!is_dir($this->extension_basedir.'/'.$name)) {
			$this->addError('Extension - '.$name.' - is not installed.');
			return false;
		}

		// check if installer.php exists
		if(!file_exists($this->extension_basedir.'/'.$name.'/install/installer.php')) {
			$this->addError('Extension installer class not found.');
			return false;
		}

		// include extension class from install directory
		require_once($this->extension_basedir.'/'.$name.'/install/installer.php');
		$classname = $name.'_installer';
		$installer = new $classname();

		if(!is_object($installer)) {
			$this->addError('Extension installer class not found or not an instance of installer.');
			return false;
		}

		// disable extension
		$installer->disable($name);

		return true;
	}

	/**
	 * Apply owner and permissions to a file or directory
	 * @param string $source
	 * @param string $target
	 * @throws \Exception
	 * @return bool
	 */
	private function applyOwnerAndPermissions($source, $target) {
		// Check if the source file exists
		if (!file_exists($source)) {
			throw new Exception("Source file does not exist: $source");
		}
	
		// Check if the target file exists
		if (!file_exists($target)) {
			throw new Exception("Target file does not exist: $target");
		}
	
		// Get the permissions of the source file
		$sourcePermissions = fileperms($source);
		if ($sourcePermissions === false) {
			throw new Exception("Failed to retrieve permissions for source file: $source");
		}
	
		// Get the owner and group of the source file
		$sourceOwner = fileowner($source);
		$sourceGroup = filegroup($source);
		if ($sourceOwner === false || $sourceGroup === false) {
			throw new Exception("Failed to retrieve owner or group for source file: $source");
		}
	
		// Apply the permissions to the target file
		if (!chmod($target, $sourcePermissions & 0777)) {
			throw new Exception("Failed to apply permissions to target file: $target");
		}
	
		// Apply the owner to the target file
		if (!chown($target, $sourceOwner)) {
			throw new Exception("Failed to change owner of target file: $target");
		}
	
		// Apply the group to the target file
		if (!chgrp($target, $sourceGroup)) {
			throw new Exception("Failed to change group of target file: $target");
		}
	
		return true;
	}

	/**
	 * Update the license
	 * @param string $name
	 * @param int $server_id
	 * @param string $license
	 * @return bool
	 */
	public function updateLicense($name, $server_id, $license) {
		global $app;

		// check name with regex
		if (!preg_match('/^[a-z0-9_]+$/', $name)) {
			$this->addError('Invalid extension name.');
			return false;
		}

		// check license
		if (!preg_match('/^[a-zA-Z0-9\-]+$/', $license)) {
			$this->addError('Invalid license.');
			return false;
		}

		// check if extension is installed
		if(!is_dir($this->extension_basedir.'/'.$name)) {
			$this->addError('Extension - '.$name.' - is not installed.');
			return false;
		}

		// write license file
		$license_file = $this->extension_basedir.'/'.$name.'/license';
		file_put_contents($license_file, $license);
		chmod($license_file, 0640);
		chown($license_file, 'ispconfig');
		chgrp($license_file, 'ispconfig');

		$app->log('License updated for extension - '.$name.' - on server - '.$server_id.' -', LOGLEVEL_DEBUG);

		// scan extension directory
		$this->scan_extensions();

		return true;
	}

	/**
	 * Get the license
	 * @param string $name
	 * @param int $server_id
	 * @return string
	 */
	public function getLicense($name, $server_id) {
		global $app;

		// check name with regex
		if (!preg_match('/^[a-z0-9_]+$/', $name)) {
			$this->addError('Invalid extension name.');
			return false;
		}

		// check if extension is installed
		if(!is_dir($this->extension_basedir.'/'.$name)) {
			$this->addError('Extension - '.$name.' - is not installed.');
			return false;
		}

		// read license file
		$license_file = $this->extension_basedir.'/'.$name.'/license';
		if (!file_exists($license_file)) {
			$this->addError('License file - '.$license_file.' - does not exist.');
			return false;
		}

		$app->log('License read for extension - '.$name.' - on server - '.$server_id.' -', LOGLEVEL_DEBUG);

		return file_get_contents($license_file);
	}

	/**
	 * Delete the license
	 * @param string $name
	 * @param int $server_id
	 * @return bool
	 */
	public function deleteLicense($name, $server_id) {
		global $app;

		// check name with regex
		if (!preg_match('/^[a-z0-9_]+$/', $name)) {
			$this->addError('Invalid extension name.');
			return false;
		}

		// check if extension is installed
		if(!is_dir($this->extension_basedir.'/'.$name)) {
			$this->addError('Extension - '.$name.' - is not installed.');
			return false;
		}

		// delete license file
		$license_file = $this->extension_basedir.'/'.$name.'/license';
		if (!file_exists($license_file)) {
			$this->addError('License file - '.$license_file.' - does not exist.');
			return false;
		}

		unlink($license_file);
		
		$app->log('License deleted for extension - '.$name.' - on server - '.$server_id.' -', LOGLEVEL_DEBUG);

		// scan extension directory
		$this->scan_extensions();

		return true;
	}

}