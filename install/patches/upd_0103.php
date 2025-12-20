<?php

if(!defined('INSTALLER_RUN')) die('Patch update file access violation.');


// Update log file permissions after https://git.ispconfig.org/ispconfig/ispconfig3/-/issues/6940

class upd_0103 extends installer_patch_update {

	public function onAfterSQL() {
		global $inst, $conf;

		$sql = "SELECT domain_id, domain, type, document_root, web_folder, system_group, parent_domain_id, log_retention FROM web_domain WHERE (type = 'vhost' or type = 'vhostsubdomain' or type = 'vhostalias') AND server_id = ?";
		$records = $inst->db->queryAllRecords($sql, $conf['server_id']);
		foreach($records as $rec) {

			$log_folder = 'log';
			$site_logdir = $rec['document_root'].'/' . $log_folder;
			$this->exec_safe("chgrp -R ? ?", $rec['system_group'], $site_logdir);
			$this->exec_safe("chmod -R o-rwx ?", $site_logdir);
			ilog('Updated log file permissions for '. $rec['domain']);
		}
	}

	// Copy from server/lib/classes/system.inc.php, adapted to avoid $app
	public function exec_safe($cmd) {
		$args = func_get_args();
		$arg_count = func_num_args();
		if($arg_count != substr_count($cmd, '?') + 1) {
			trigger_error('Placeholder count not matching argument list.', E_USER_WARNING);
			return false;
		}
		if($arg_count > 1) {
			array_shift($args);

			$pos = 0;
			$a = 0;
			foreach($args as $value) {
				$a++;

				$pos = strpos($cmd, '?', $pos);
				if($pos === false) {
					break;
				}
				$value = escapeshellarg($value);
				$cmd = substr_replace($cmd, $value, $pos, 1);
				$pos += strlen($value);
			}
		}

		$last_exec_out = null;
		$last_exec_retcode = null;
		$ret = exec($cmd, $last_exec_out, $last_exec_retcode);

		ilog("safe_exec cmd: " . $cmd . " - return code: " . $last_exec_retcode, LOGLEVEL_DEBUG);

		return $ret;
	}
}
