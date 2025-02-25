<?php

/*
Copyright (c) 2007, Till Brehm, projektfarm Gmbh
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

class mysql_clientdb_plugin {

	var $plugin_name = 'mysql_clientdb_plugin';
	var $class_name = 'mysql_clientdb_plugin';

	var $denylist_user = ['root', 'debian-sys-maint', 'mysql.infoschema'];
	var $denylist_database = ['mysql', 'information_schema', 'performance_schema'];

	/** @var mysqli|null */
	var $link = null;
	/** @var string  */
	var $clientdb_host = '';
	/** @var string  */
	var $clientdb_user;
	/** @var string  */
	var $clientdb_password;

	//* This function is called during ISPConfig installation to determine
	//  if a symlink shall be created for this plugin.
	function onInstall() {
		global $conf;

		if($conf['services']['db']) {
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

		//* Databases
		$app->plugins->registerEvent('database_insert', $this->plugin_name, 'dbInsert');
		$app->plugins->registerEvent('database_update', $this->plugin_name, 'dbUpdate');
		$app->plugins->registerEvent('database_delete', $this->plugin_name, 'dbDelete');

		//* Database users
		// $app->plugins->registerEvent('database_user_insert', $this->plugin_name, 'dbUserInsert'); <- stale user accounts are useless ;)
		$app->plugins->registerEvent('database_user_update', $this->plugin_name, 'dbUserUpdate');
		$app->plugins->registerEvent('database_user_delete', $this->plugin_name, 'dbUserDelete');
	}

	function dbInsert($event_name, $data) {
		global $app;

		if($data['new']['type'] != 'mysql') {
			return;
		}

		if (!$this->connect()) {
			return;
		}

		$this->createDatabase($data['new']);

		// Create the database users if database is active
		if($data['new']['active'] == 'y') {
			$db_user = $app->db->queryOneRecord('SELECT * FROM `web_database_user` WHERE `database_user_id` = ?', $data['new']['database_user_id']);
			$db_ro_user = $app->db->queryOneRecord('SELECT * FROM `web_database_user` WHERE `database_user_id` = ?', $data['new']['database_ro_user_id']);
			$host_list = $this->getHostList($data['new']);
			$did_something = false;
			foreach($host_list as $host) {
				if($db_user) {
					$this->grant($data['new']['database_name'], $db_user, $host, ($data['new']['quota_exceeded'] == 'y' ? 'rd' : 'rw'));
					$did_something = true;
				}
				if($db_ro_user && $data['new']['database_user_id'] != $data['new']['database_ro_user_id']) {
					$this->grant($data['new']['database_name'], $db_ro_user, $host, 'r');
					$did_something = true;
				}
			}
			if($did_something) {
				$this->link->query('FLUSH PRIVILEGES');
			}
		}

		$this->disconnect();
	}

	function dbUpdate($event_name, $data) {
		global $app;

		if($data['new']['type'] != 'mysql') {
			return;
		}

		// skip processing if database was and is inactive
		if($data['new']['active'] == 'n' && $data['old']['active'] == 'n') {
			return;
		}

		if (!$this->connect()) {
			return;
		}

		// check if the database exists
		if($data['new']['database_name'] == $data['old']['database_name']) {
			$result = $this->link->query("SHOW DATABASES LIKE '" . $this->link->escape_string($data['new']['database_name']) . "'");
			if(!$result) {
				$app->log('Error getting info for database ' . $data['new']['database_name'] . ': ' . $this->link->error, LOGLEVEL_ERROR);
				$this->disconnect();
				return;
			}
			if($result->num_rows === 0) {
				$this->createDatabase($data['new']);
			}
			$result->free();
		}

		// get the users for this database (old&new)
		$db_user = $app->db->queryOneRecord('SELECT * FROM `web_database_user` WHERE `database_user_id` = ?', $data['new']['database_user_id']);
		$old_db_user = $app->db->queryOneRecord('SELECT * FROM `web_database_user` WHERE `database_user_id` = ?', $data['old']['database_user_id']);
		$db_ro_user = $app->db->queryOneRecord("SELECT * FROM `web_database_user` WHERE `database_user_id` = ?", $data['new']['database_ro_user_id']);
		$old_db_ro_user = $app->db->queryOneRecord("SELECT * FROM `web_database_user` WHERE `database_user_id` = ?", $data['old']['database_ro_user_id']);

		// get all lists of hosts we need upfront
		$host_list = $this->getHostList($data['new']);
		$old_host_list = $this->getHostList($data['old']);
		$combined_host_list = array_values(array_unique(array_merge($host_list, $old_host_list)));
		$other_host_lists = [];
		foreach([$data['old']['database_user_id'], $data['old']['database_ro_user_id'], $data['new']['database_user_id'], $data['new']['database_ro_user_id']] as $user_id) {
			if(!empty($user_id) && empty($other_host_lists[$user_id])) {
				$other_host_lists[$user_id] = $this->getOtherHostList($data['new']['database_id'], $user_id);
			}
		}

		//* rename database
		if($data['new']['database_name'] != $data['old']['database_name']) {
			$this->renameDatabase($data);
		}

		// revoke database user, if database just became inactive ($data['old']['active'] is 'y') at this point
		if($data['new']['active'] == 'n') {
			$did_something = false;
			foreach($old_host_list as $host) {
				if($old_db_user) {
					$this->revokeAndDrop($data['old']['database_name'], $old_db_user['database_user'], $host, !in_array($host, $other_host_lists[$old_db_user['database_user_id']]));
					$did_something = true;
				}
				if($old_db_ro_user && $data['old']['database_user_id'] != $data['old']['database_ro_user_id']) {
					$this->revokeAndDrop($data['old']['database_name'], $old_db_ro_user['database_user'], $host, !in_array($host, $other_host_lists[$old_db_ro_user['database_user_id']]));
					$did_something = true;
				}
			}
			if($did_something) {
				$this->link->query('FLUSH PRIVILEGES');
			}
			// Database is not active, so stop processing here
			$this->disconnect();
			return;
		}

		// go through all hosts (new&old) and figure out what to do for each host (GRANT or REVOKE users)
		$did_something = false;
		foreach($combined_host_list as $host) {
			$in_old = in_array($host, $old_host_list);
			$in_new = in_array($host, $host_list);
			$app->log('GRANT/REVOKE for ' . $data['new']['database_name'] . ' ' . $host . ' ' . ($in_old ? 'in_old' : '!in_old') . '/' . ($in_new ? 'in_new' : '!in_new'), LOGLEVEL_DEBUG);
			$revoked_users = [];
			if($in_new && $db_user) {
				$this->grant($data['new']['database_name'], $db_user, $host, ($data['new']['quota_exceeded'] == 'y' ? 'rd' : 'rw'));
				$did_something = true;
			}
			if($in_new && $db_ro_user && $data['new']['database_user_id'] != $data['new']['database_ro_user_id']) {
				$this->grant($data['new']['database_name'], $db_ro_user, $host, 'r');
				$did_something = true;
			}
			// user changed, revoke old one
			if($in_old && $data['new']['database_user_id'] != $data['old']['database_user_id'] && $old_db_user && $data['old']['database_user_id'] != $data['new']['database_ro_user_id']) {
				$this->revokeAndDrop($data['old']['database_name'], $old_db_user['database_user'], $host, !in_array($host, $other_host_lists[$old_db_user['database_user_id']]));
				$revoked_users[] = $old_db_user['database_user_id'];
				$did_something = true;
			}
			// ro user changed, revoke old one
			if($in_old && $data['new']['database_ro_user_id'] != $data['old']['database_ro_user_id'] && $old_db_ro_user && $data['old']['database_ro_user_id'] != $data['new']['database_user_id']) {
				$this->revokeAndDrop($data['old']['database_name'], $old_db_ro_user['database_user'], $host, !in_array($host, $other_host_lists[$old_db_ro_user['database_user_id']]));
				$revoked_users[] = $old_db_ro_user['database_user_id'];
				$did_something = true;
			}
			// host was removed, revoke both users for this host
			if($in_old && !$in_new) {
				if($old_db_user && !in_array($old_db_user['database_user_id'], $revoked_users)) {
					$this->revokeAndDrop($data['old']['database_name'], $old_db_user['database_user'], $host, !in_array($host, $other_host_lists[$old_db_user['database_user_id']]));
					$did_something = true;
				}
				if($old_db_ro_user && !in_array($old_db_ro_user['database_user_id'], $revoked_users)) {
					$this->revokeAndDrop($data['old']['database_name'], $old_db_ro_user['database_user'], $host, !in_array($host, $other_host_lists[$old_db_ro_user['database_user_id']]));
					$did_something = true;
				}
			}
		}

		if($did_something) {
			$this->link->query('FLUSH PRIVILEGES');
		}

		$this->disconnect();
	}

	function dbDelete($event_name, $data) {
		global $app;

		if($data['old']['type'] != 'mysql') {
			return;
		}

		if (!$this->connect()) {
			return;
		}

		$old_host_list = $this->getHostList($data['old']);

		$did_something = false;
		if($data['old']['database_user_id']) {
			$old_db_user = $app->db->queryOneRecord('SELECT * FROM `web_database_user` WHERE `database_user_id` = ?', $data['old']['database_user_id']);
			if($old_db_user) {
				$other_hosts = $this->getOtherHostList($data['old']['database_id'], $data['old']['database_user_id']);
				foreach($old_host_list as $host) {
					$this->revokeAndDrop($data['old']['database_name'], $old_db_user['database_user'], $host, !in_array($host, $other_hosts));
					$did_something = true;
				}
			}
		}
		if($data['old']['database_ro_user_id'] && $data['old']['database_ro_user_id'] != $data['old']['database_user_id']) {
			$old_db_user = $app->db->queryOneRecord('SELECT * FROM `web_database_user` WHERE `database_user_id` = ?', $data['old']['database_ro_user_id']);
			if($old_db_user) {
				$other_hosts = $this->getOtherHostList($data['old']['database_id'], $data['old']['database_ro_user_id']);
				foreach($old_host_list as $host) {
					$this->revokeAndDrop($data['old']['database_name'], $old_db_user['database_user'], $host, !in_array($host, $other_hosts));
					$did_something = true;
				}
			}
		}

		if($did_something) {
			$this->link->query('FLUSH PRIVILEGES');
		}

		$this->deleteDatabase($data['old']);

		$this->disconnect();
	}

	function dbUserUpdate($event_name, $data) {
		global $app;

		// nothing to do when username and password are the same. No need to check database_password_sha2 since it is always in sync with database_password
		if($data['old']['database_user'] == $data['new']['database_user'] && ($data['old']['database_password'] == $data['new']['database_password'] || $data['new']['database_password'] == '')) {
			return;
		}

		if (!$this->connect()) {
			return;
		}

		// get all databases this user was active for
		$user_id = intval($data['old']['database_user_id']);
		$db_list = $app->db->queryAllRecords('SELECT `remote_access`, `remote_ips` FROM `web_database` WHERE `database_user_id` = ? OR database_ro_user_id = ?', $user_id, $user_id);
		// nothing to do on this server for this db user
		if(empty($db_list)) {
			return;
		}

		$host_list = [];
		foreach($db_list as $database) {
			$host_list = array_merge($host_list, $this->getHostList($database));
		}
		$host_list = array_values(array_unique($host_list));

		$did_something = false;
		foreach($host_list as $db_host) {
			if($data['new']['database_user'] != $data['old']['database_user']) {
				$this->link->query("RENAME USER '" . $this->link->escape_string($data['old']['database_user']) . "'@'$db_host' TO '" . $this->link->escape_string($data['new']['database_user']) . "'@'$db_host'");
				$app->log('Renaming MySQL user: ' . $data['old']['database_user'] . ' to ' . $data['new']['database_user'], LOGLEVEL_DEBUG);
			}

			if($data['new']['database_password'] != $data['old']['database_password'] && $data['new']['database_password'] != '') {
				$this->setPassword( $data['new'], $db_host);
				$did_something = true;
			}
		}

		if($did_something) {
			$this->link->query("FLUSH PRIVILEGES");
		}

		$this->disconnect();
	}

	function dbUserDelete($event_name, $data) {
		global $app;

		if (in_array(strtolower($data['old']['database_user']), $this->denylist_user)) {
			$app->log('Refuse to drop user: ' . $data['old']['database_user'], LOGLEVEL_WARN);
		}

		if (!$this->connect()) {
			return;
		}

		$host_list = array();
		// read all mysql users with this username
		$result = $this->link->query("SELECT `User`, `Host` FROM `mysql`.`user` WHERE `User` = '" . $this->link->escape_string($data['old']['database_user']) . "' AND `Create_user_priv` = 'N'"); // basic protection against accidentally deleting system users like debian-sys-maint
		if($result) {
			while($row = $result->fetch_assoc()) {
				$host_list[] = $row['Host'];
			}
			$result->free();
		}

		$did_something = false;
		foreach($host_list as $db_host) {
			if($this->link->query("DROP USER '" . $this->link->escape_string($data['old']['database_user']) . "'@'$db_host';")) {
				$app->log('Dropping MySQL user: ' . $data['old']['database_user'], LOGLEVEL_DEBUG);
				$did_something = true;
			}
		}

		if($did_something) {
			$this->link->query('FLUSH PRIVILEGES');
		}

		$this->disconnect();
	}

	/**
	 * Connect to the client database.
	 *
	 * @return bool
	 */
	private function connect() {
		global $app;

		if ($this->link) {
			$this->disconnect();
		}

		$clientdb_host = '';
		$clientdb_user = '';
		$clientdb_password = '';

		if(!include ISPC_LIB_PATH . '/mysql_clientdb.conf') {
			$app->log('Unable to open' . ISPC_LIB_PATH . '/mysql_clientdb.conf', LOGLEVEL_ERROR);
			return false;
		}

		mysqli_report(MYSQLI_REPORT_OFF);
		//* Connect to the database
		$link = new mysqli($clientdb_host, $clientdb_user, $clientdb_password);
		if($link->connect_error) {
			$app->log('Unable to connect to mysql' . $link->connect_error, LOGLEVEL_ERROR);
			return false;
		}
		$this->link = $link;
		$this->clientdb_host = $clientdb_host;
		$this->clientdb_user = $clientdb_user;
		$this->clientdb_password = $clientdb_password;
		return true;
	}

	/**
	 * Close client database connection when it is open.
	 *
	 * @return void
	 */
	private function disconnect() {
		if ($this->link) {
			$this->link->close();
			$this->link = null;
		}
	}

	/**
	 * Gets the sub-type of the database server. Either `mysql` or `mariadb`.
	 *
	 * @return string
	 */
	private function getDatabaseType() {
		if(stristr($this->link->server_info, 'mariadb')) {
			return 'mariadb';
		} else {
			return 'mysql';
		}
	}

	/**
	 * Gets the version of the database server.
	 *
	 * @return string
	 */
	private function getDatabaseVersion() {
		// use SELECT VERSION() instead of link->server_info because for MariaDB there could be a strange compatibility version number prepended
		$result = $this->link->query('SELECT VERSION() as version');
		if($result) {
			$tmp = $result->fetch_assoc();
			$result->free();
			$version = explode('-', $tmp['version']);
			return $version[0] ?: '0.0.0-unknown';
		} else {
			return '0.0.0-unknown';
		}
	}


	/**
	 * Returns list of table names (filtered by $where).
	 *
	 * @param string $where
	 * @return array|false
	 */
	private function getTableList($where) {
		global $app;

		$tables = [];
		$query = $this->link->query('SELECT TABLE_NAME FROM information_schema.tables WHERE ' . $where);
		if($query) {
			while($row = $query->fetch_assoc()) {
				$tables[] = $row['TABLE_NAME'];
			}
			$query->free();
		} else {
			$app->log('Error while getting table list with WHERE ' . $where . ' :' . $this->link->error, LOGLEVEL_WARN);
			return false;
		}

		return $tables;
	}

	/**
	 * Returns list of trigger names (filtered by $from).
	 *
	 * @param string $from
	 * @return array|false
	 */
	private function getTriggerList($from) {
		global $app;

		$triggers = [];
		$query = $this->link->query('SHOW TRIGGERS FROM ' . $from);
		if($query) {
			while($row = $query->fetch_assoc()) {
				$triggers[] = $row['Trigger'];
			}
			$query->free();
		} else {
			$app->log('Error while getting trigger list with FROM ' . $from . ' :' . $this->link->error, LOGLEVEL_WARN);
			return false;
		}

		return $triggers;
	}


	/**
	 * Get list of hosts that this database is accessible from.
	 *
	 * @param array $db_record
	 * @return array
	 */
	private function getHostList($db_record) {
		$host_list = [];
		if($db_record['remote_access'] == 'y') {
			$host_list = array_filter(array_map(function($ip) {
				return filter_var(trim($ip), FILTER_VALIDATE_IP);
			}, explode(',', $db_record['remote_ips'] ?: '')));
			if(empty($host_list)) {
				$host_list[] = '%';
			}
		}
		$host_list[] = 'localhost';
		$host_list = array_values(array_unique($host_list));
		sort($host_list);

		return $host_list;
	}


	/**
	 * Get host list of all other databases of the user.
	 *
	 * @param int $database_id
	 * @param int $user_id
	 * @return array
	 */
	private function getOtherHostList($database_id, $user_id) {
		global $app;

		$db_user_host_list = [];
		$other_user_databases = $app->db->queryAllRecords("SELECT `remote_access`, `remote_ips` FROM web_database WHERE (database_user_id = ? OR database_ro_user_id = ?) AND active = 'y' AND database_id != ?",
			$user_id, $user_id, $database_id);
		foreach($other_user_databases as $record) {
			$db_user_host_list = array_merge($db_user_host_list, $this->getHostList($record));
		}

		return array_values(array_unique($db_user_host_list));
	}

	/**
	 * Creates a MySQL database
	 *
	 * @param array $record
	 * @return bool
	 */
	private function createDatabase($record) {
		global $app;

		if(in_array(strtolower($record['database_name']), $this->denylist_database)) {
			$app->log('Refuse to create database ' . $record['database_name'], LOGLEVEL_WARN);
			return false;
		}

		// Charset for the new table
		if($record['database_charset'] != '') {
			$query_charset_table = ' DEFAULT CHARACTER SET ' . $record['database_charset'];
		} else {
			$query_charset_table = '';
		}

		//* Create the new database
		if($this->link->query('CREATE DATABASE `' . $this->link->escape_string($record['database_name']) . '`' . $query_charset_table)) {
			$app->log('Created MySQL database: ' . $record['database_name'], LOGLEVEL_DEBUG);
			return true;
		} else {
			$app->log('Unable to create the database: ' . $this->link->error, LOGLEVEL_WARN);
			return false;
		}
	}

	/**
	 * Drops a MySQL database
	 *
	 * @param array $record
	 * @return bool
	 */
	private function deleteDatabase($record) {
		global $app;

		if(in_array(strtolower($record['database_name']), $this->denylist_database)) {
			$app->log('Refuse to delete database ' . $record['database_name'], LOGLEVEL_WARN);
			return false;
		}

		if($this->link->query('DROP DATABASE `' . $this->link->escape_string($record['database_name'] . '`'))) {
			$app->log('Dropping MySQL database: ' . $record['database_name'], LOGLEVEL_DEBUG);
			return true;
		} else {
			$app->log('Error while dropping MySQL database: ' . $record['database_name'] . ' ' . $this->link->error, LOGLEVEL_WARN);
			return false;
		}
	}

	/**
	 * Renames a database. Handles triggers&views.
	 *
	 * @param array $data
	 * @return bool
	 */
	private function renameDatabase($data) {
		global $app, $conf;

		if(in_array(strtolower($data['old']['database_name']), $this->denylist_database)
			|| in_array(strtolower($data['new']['database_name']), $this->denylist_database)) {
			$app->log('Refuse to rename database ' . $data['old']['database_name'] . ' to ' . $data['new']['database_name'], LOGLEVEL_WARN);
			return false;
		}

		if(strtolower($data['old']['database_name']) == strtolower($data['new']['database_name'])) {
			$app->log('Refuse to rename database ' . $data['old']['database_name'] . ' to ' . $data['new']['database_name'], LOGLEVEL_WARN);
			return false;
		}

		$old_name = $this->link->escape_string($data['old']['database_name']);
		$new_name = $this->link->escape_string($data['new']['database_name']);
		$dump_prefix = $conf['temppath'] . $conf['fs_div'] . time().$old_name;

		$tables = $this->getTableList("table_schema='" . $old_name . "' AND TABLE_TYPE='BASE TABLE'");
		$views = $this->getTableList( "table_schema='" . $old_name . "' AND TABLE_TYPE='VIEW'");
		$triggers = $this->getTriggerList($old_name);
		if($tables === false || $views === false || $triggers === false) {
			$app->log('Unable to rename database ' . $old_name . ' to ' . $new_name, LOGLEVEL_ERROR);
			return false;
		}

		//* Rename empty database by creating a new one and dropping the old database
		if(empty($tables) && empty($views) && empty($triggers)) {
			return $this->createDatabase($data['new']) && $this->deleteDatabase($data['old']);
		}

		//* save triggers, routines and events
		if(!empty($triggers)) {
			$app->log('Dumping triggers from ' . $old_name, LOGLEVEL_DEBUG);
			$command = 'mysqldump -h ? -u ? -p? ? -d -t -R -E > ?';
			$app->system->exec_safe($command, $this->clientdb_host, $this->clientdb_user, $this->clientdb_password, $old_name, $dump_prefix . '.triggers');
			$ret = $app->system->last_exec_retcode();
			$app->system->chmod($dump_prefix . '.triggers', 0600);
			if($ret != 0) {
				$triggers = [];
				$app->system->unlink($dump_prefix . '.triggers');
				$app->log('Unable to dump triggers from ' . $old_name, LOGLEVEL_ERROR);
			}
		}

		//* save views
		if(!empty($views)) {
			$app->log('Dumping views from ' . $old_name, LOGLEVEL_DEBUG);
			$command = 'mysqldump -h ? -u ? -p? ? ' . str_repeat("? ", count($views)) . " > ?";
			$args = array_merge([$this->clientdb_host, $this->clientdb_user, $this->clientdb_password, $old_name], $views, [$dump_prefix . '.views']);
			$app->system->exec_safe($command, ...$args);
			$ret = $app->system->last_exec_retcode();
			$app->system->chmod($dump_prefix . '.views', 0600);
			if($ret != 0) {
				$views = [];
				$app->system->unlink($dump_prefix . '.views');
				$app->log('Unable to dump views from ' . $old_name, LOGLEVEL_ERROR);
			}
		}

		//* create new database
		$this->createDatabase($data['new']);

		$res = $this->link->query("show databases like '" . $new_name . "'");
		if(!$res || $res->num_rows == 0) {
			$app->log('Connection to new database ' . $new_name . ' failed', LOGLEVEL_ERROR);
			if(!empty($triggers)) {
				$app->system->unlink($dump_prefix . '.triggers');
			}
			if(!empty($views)) {
				$app->system->unlink($dump_prefix . '.views');
			}
			return false;
		}
		$res->free();

		//* drop old triggers (be before rename tables otherwise the table rename would fail)
		if(!empty($triggers)) {
			foreach($triggers as $trigger) {
				$_trigger = $this->link->escape_string($trigger);
				$sql = 'DROP TRIGGER ' . $old_name . '.' . $_trigger;
				$res = $this->link->query($sql);
				$app->log($sql . ' success? ' . ($res ? 'yes' : 'no'), LOGLEVEL_DEBUG);
			}
		}

		//* rename tables
		foreach($tables as $table) {
			$table = $this->link->escape_string($table);
			$sql = 'RENAME TABLE ' . $old_name . '.' . $table . ' TO ' . $new_name . '.' . $table;
			$res = $this->link->query($sql);
			$app->log($sql, LOGLEVEL_DEBUG);
			if(!$res) {
				$app->log($sql . ' failed: '.$this->link->error, LOGLEVEL_ERROR);
			}
		}

		//* update triggers, routines and events
		if(!empty($triggers)) {
			$command = 'mysql -h ? -u ? -p? ? < ?';
			$app->system->exec_safe($command, $this->clientdb_host, $this->clientdb_user, $this->clientdb_password, $new_name, $dump_prefix . '.triggers');
			$ret = $app->system->last_exec_retcode();
			if($ret != 0) {
				$app->log('Unable to import triggers for ' . $new_name, LOGLEVEL_ERROR);
			} else {
				$app->system->unlink($dump_prefix . '.triggers');
			}
		}

		//* loading views
		if(!empty($views)) {
			$command = 'mysql -h ? -u ? -p? ? < ?';
			$app->system->exec_safe($command, $this->clientdb_host, $this->clientdb_user, $this->clientdb_password, $new_name, $dump_prefix . '.views');
			$ret = $app->system->last_exec_retcode();
			if($ret != 0) {
				$app->log('Unable to import views for ' . $new_name, LOGLEVEL_ERROR);
			} else {
				$app->system->unlink($dump_prefix . '.views');
			}
		}

		//* drop old database
		$this->deleteDatabase($data['old']);

		return true;
	}


	/**
	 * Set the password for a user/host. Selects the best available auth plugin (mysql_native_password, caching_sha2_password).
	 *
	 * @param array $db_user
	 * @param string $db_host_
	 * @return bool
	 */
	private function setPassword($db_user, $db_host_) {
		global $app;

		static $has_unwanted_plugins = null;
		if(is_null($has_unwanted_plugins)) {
			$unwanted_sql_plugins = array('validate_password'); // strict-password-validation
			$temp = "'" . implode("','", $unwanted_sql_plugins) . "'";
			$result = $this->link->query("SELECT plugin_name FROM information_schema.plugins WHERE plugin_status='ACTIVE' AND plugin_name IN (" . $temp . ')');
			if($result) {
				$sql_plugins = array();
				while($row = $result->fetch_assoc()) {
					$sql_plugins[] = $row['plugin_name'];
				}
				$result->free();
				if(count($sql_plugins) > 0) {
					foreach($sql_plugins as $plugin) {
						$app->log('MySQL-Plugin ' . $plugin . ' enabled - can not execute function set_password', LOGLEVEL_ERROR);
					}
					$has_unwanted_plugins = true;
				} else {
					$has_unwanted_plugins = false;
				}
			}
		}
		if($has_unwanted_plugins) {
			return false;
		}
		if(in_array(strtolower($db_user['database_user']), $this->denylist_user)) {
			$app->log('Refuse to set password for user ' . $db_user['database_user'], LOGLEVEL_WARN);
			return false;
		}

		$db_type = $this->getDatabaseType();
		$db_version = $this->getDatabaseVersion();
		$db_host = $this->link->escape_string($db_host_);
		$database_user = $this->link->escape_string($db_user['database_user']);
		$database_password = $this->link->escape_string($db_user['database_password'] ?: '*THISISNOTAVALIDPASSWORDTHATCANBEUSEDHERE');
		$database_password_sha2 = $this->link->escape_string(!empty($db_user['database_password_sha2']) ? $db_user['database_password_sha2'] : '');

		// mariadb or mysql < 5.7
		if($db_type == 'mariadb' || version_compare($db_version, '5.7', '<')) {
			$query = sprintf("SET PASSWORD FOR '%s'@'%s' = '%s'", $database_user, $db_host, $database_password);
			$auth_plugin = 'mysql_native_password';
		} else { // mysql >= 5.7
			if($database_password_sha2 && version_compare($db_version, '8.0', '>=')) {
				$auth_plugin = 'caching_sha2_password';
				$hash = $database_password_sha2;
			} else {
				$auth_plugin = 'mysql_native_password';
				$hash = $database_password;
			}
			$query = sprintf("ALTER USER IF EXISTS '%s'@'%s' IDENTIFIED WITH %s AS '%s'",
				$database_user,
				$db_host,
				$auth_plugin,
				$hash);
		}
		if($this->link->query($query)) {
			$app->log("Password set for '" . $database_user . "'@'" . $db_host . "' with " . $auth_plugin, LOGLEVEL_DEBUG);
			return true;
		} else {
			$app->log("Error setting password for '" . $database_user . "'@'" . $db_host . "' with " . $auth_plugin . ': ' . $this->link->error, LOGLEVEL_WARN);
			return false;
		}
	}

	/**
	 * Creates user@host, sets password and grant the user rights to a database.
	 *
	 * @param string $database_name
	 * @param array $db_user
	 * @param string $db_host_
	 * @param string $user_access_mode
	 * @return bool
	 */
	private function grant($database_name, $db_user, $db_host_, $user_access_mode) {
		global $app;

		if(in_array(strtolower($db_user['database_user']), $this->denylist_user)) {
			$app->log('Refuse to grant user ' . $db_user['database_user'], LOGLEVEL_WARN);
			return false;
		}

		$database_user = $this->link->escape_string($db_user['database_user']);
		$database_name = $this->link->escape_string($database_name);
		$db_host = $this->link->escape_string($db_host_);

		// revoke all privileges for read only user before granting restrictive permissions (ignore errors)
		if($user_access_mode == 'r' || $user_access_mode == 'rd') {
			$res = $this->link->query('REVOKE ALL PRIVILEGES ON `' . $database_name . "`.* FROM '" . $database_user . "'@'" . $db_host . "'");
			$app->log('REVOKE ALL PRIVILEGES ON `' . $database_name . "`.* FROM '" . $database_user . "'@'" . $db_host . "' success? " . ($res ? 'yes' : 'no'), LOGLEVEL_DEBUG);
		}

		// Create the user (ignore errors, since it might already be present)
		$res = $this->link->query("CREATE USER IF NOT EXISTS '" . $database_user . "'@'" . $db_host . "'");
		$app->log("CREATE USER '" . $database_user . "'@'" . $db_host . "' success? " . ($res ? 'yes' : 'no'), LOGLEVEL_DEBUG);

		// Set the password
		if (!$this->setPassword($db_user, $db_host_)) {
			return false;
		}

		// Set the grant
		$grants = 'ALL PRIVILEGES';
		if($user_access_mode == 'r') {
			$grants = 'SELECT';
		} elseif($user_access_mode == 'rd') {
			$grants = 'SELECT, DELETE, ALTER, DROP';
		}

		$res = $this->link->query('GRANT ' . $grants . ' ON `' . $database_name . "`.* TO '" . $database_user . "'@'" . $db_host . "'");
		if($res) {
			$app->log('Grant ' . $grants . ' on `' . $database_name . "`.* to '" . $database_user . "'@'" . $db_host , LOGLEVEL_DEBUG);
			return true;
		} else {
			$app->log('Error granting ' . $grants . ' on `' . $database_name . "`.* to '" . $database_user . "'@'" . $db_host . ': ' . $this->link->error, LOGLEVEL_WARN);
			return false;
		}
	}

	/**
	 * Revokes rights to a database to a user@host (and maybe drop the user afterward).
	 *
	 * @param string $database_name
	 * @param string $database_user
	 * @param string $host
	 * @param bool $drop
	 * @return bool
	 */
	private function revokeAndDrop($database_name, $database_user, $host, $drop) {
		global $app;
		if(in_array(strtolower($database_user), $this->denylist_user)) {
			$app->log('Refuse to revoke/drop user ' . $database_user, LOGLEVEL_WARN);
			return false;
		}
		$database_user = $this->link->escape_string($database_user);
		$database_name = $this->link->escape_string($database_name);
		$host = $this->link->escape_string($host);
		$res = $this->link->query('REVOKE ALL PRIVILEGES ON `' . $database_name . "`.* FROM '" . $database_user . "'@'" . $host . "'");
		$app->log('REVOKE ALL PRIVILEGES ON `' . $database_name . "`.* FROM '" . $database_user . "'@'" . $host . "' success? " . ($res ? 'yes' : 'no'), LOGLEVEL_DEBUG);
		if($drop) {
			$res = $this->link->query("DROP USER '" . $database_user . "'@'" . $host . "'");
			$app->log("DROP USER '" . $database_user . "'@'" . $host . "' success? " . ($res ? 'yes' : 'no'), LOGLEVEL_DEBUG);
		}
		return (bool)$res;
	}


} // end class
