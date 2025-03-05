<?php

/*
Copyright (c) 2025, Till Brehm - ISPConfig UG
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

class cronjob_sysbackup extends cronjob {

	// job schedule
	protected $_schedule = '0 3 * * *';
    private $_tools = null;

	/* this function is optional if it contains no custom code */
	public function onPrepare() {
		global $app;

		parent::onPrepare();
	}

	/* this function is optional if it contains no custom code */
	public function onBeforeRun() {
		global $app;

		return parent::onBeforeRun();
	}

	public function onRunJob() {
		global $app, $conf;

        /* used for all monitor cronjobs */
		$app->load('monitor_tools,system');
		$this->_tools = new monitor_tools();
		/* end global section for monitor cronjobs */

		/* the id of the server as int */
		$server_id = intval($conf['server_id']);

		$app->load("sysbackup");
		
        // Get server config
        $rec = $app->db->queryOneRecord("SELECT * FROM `server` WHERE `server_id` = ?", $server_id);
        if(!empty($rec)) {
            $server_config = $app->ini_parser->parse_ini_string(stripslashes($rec['config']),'server');
            if(isset($server_config['server']['sysbackup_copies']) && $server_config['server']['sysbackup_copies'] > 0) {
                $backup_path = $server_config['server']['backup_dir'].DIRECTORY_SEPARATOR.'ispconfig';
                $backup_copies = $server_config['server']['sysbackup_copies'];

                if ($server_config['server']['backup_dir_is_mount'] == 'y') {
                    $app->system->mount_backup_dir($server_config['server']['backup_dir']);
                }

                try {
                    // do backup
                    $backup = new sysbackup(100);
                    $dbBackupFile = $backup->backupDatabase($backup_path,$conf['db_host'],$conf['db_port'],$conf['db_database'],$conf['db_user'],$conf['db_password'],$backup_copies);
                    $etcBackupFile = $backup->backupDirectory($backup_path,'/etc',$backup_copies);
                    $ispconfigBackupFile = $backup->backupDirectory($backup_path,'/usr/local/ispconfig',$backup_copies);
                    
                    // get backup stats
                    $stats = $backup->backupStats($backup_path);

                    /*
                    * Insert the data into the database
                    */
                    if(!empty($stats)) {
                        $sql = 'INSERT INTO `monitor_data` (`server_id`, `type`, `created`, `data`, `state`) ' .
                            'VALUES (' .
                            $server_id . ', ' .
                            "'" . $app->dbmaster->quote('sysbackup') . "', " .
                            'UNIX_TIMESTAMP(), ' .
                            "'" . $app->dbmaster->quote(serialize($stats)) . "', " .
                            "'ok'" .
                            ')';
                        $app->dbmaster->query($sql);
                    }

                    /* The new data is written, now we can delete the old one */
                    $this->_tools->delOldRecords('sysbackup', $server_id);

                } catch (Exception $e) {
                    $app->log($e->getMessage(), LOGLEVEL_WARN);
                }

                if ($server_config['server']['backup_dir_is_mount'] == 'y') {
                    $app->system->umount_backup_dir($server_config['server']['backup_dir']);
                }
            }
        }

		parent::onRunJob();
	}

	/* this function is optional if it contains no custom code */
	public function onAfterRun() {
		global $app;

		parent::onAfterRun();
	}

}
