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

class sympa_plugin {

	var $plugin_name = 'sympa_plugin';
	var $class_name = 'sympa_plugin';


	var $sympa_config_dir = '/etc/sympa/';
	var $sympa_expldir_dir = '/var/lib/sympa/list_data';

	//* This function is called during ispconfig installation to determine
	//  if a symlink shall be created for this plugin.
	function onInstall() {
		global $conf;

		if($conf['services']['mail'] == true) {
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

		$app->plugins->registerEvent('mail_mailinglist_insert', 'sympa_plugin', 'insert');
		$app->plugins->registerEvent('mail_mailinglist_update', 'sympa_plugin', 'update');
		$app->plugins->registerEvent('mail_mailinglist_delete', 'sympa_plugin', 'delete');

	}

	function insert($event_name, $data) {
		global $app, $conf;

		$this->update_config();

		// Generate a config File
		if(file_exists($conf["rootpath"]."/conf-custom/sympa_list_creation.xml.master")) {
			$content = file_get_contents($conf["rootpath"]."/conf-custom/sympa_list_creation.xml.master");
		} else {
			$content = file_get_contents($conf["rootpath"]."/conf/sympa_list_creation.xml.master");
		}

		$content = str_replace('{listname}', $data["new"]["listname"], $content);
		$content = str_replace('{domain}', $data["new"]["domain"], $content);
		$content = str_replace('{email}', $data["new"]["email"], $content);

		$filename = '/tmp/sympa_list_creation'.$data["new"]['mailinglist_id'].'.xml';

		file_put_contents($filename, $content);

		$pid = $app->system->exec_safe("nohup /usr/bin/sympa --create_list --robot ? --input_file ? >/dev/null 2>&1 & echo $!;", $data["new"]["domain"], $filename);
		// wait for /usr/lib/mailman/bin/newlist-call
		$running = true;
		do {
			exec('ps -p '.intval($pid), $out);
			if (count($out) ==1) $running=false; else sleep(1);
			unset($out);
		} while ($running);
		unset($out);
	/* 	if(is_file('/etc/mailman/virtual-mailman') && !is_link('/etc/sympa/virtual.sympa')) {
			symlink('/etc/mailman/virtual-mailman','/etc/sympa/virtual.sympa');
		} */
		// Still needed? if(is_file('/etc/sympa/virtual.sympa')) exec('postmap /etc/sympa/virtual.sympa');
		// Still needed? if(is_file('/etc/sympa/sympa_transport')) exec('postmap /etc/sympa/sympa_transport');
		
		exec('nohup '.$conf['init_scripts'] . '/' . 'sympa reload >/dev/null 2>&1 &');
		
		// Fix list URL
		//$app->system->exec_safe('/usr/sbin/withlist -l -r fix_url ?', $data["new"]["listname"]);

		$app->db->query("UPDATE mail_mailinglist SET password = '' WHERE mailinglist_id = ?", $data["new"]['mailinglist_id']);

	}

	// The purpose of this plugin is to rewrite the main.cf file
	function update($event_name, $data) {
		global $app, $conf;
		
		$this->update_config();

		// Still needed? 
		/* if($data["new"]["password"] != $data["old"]["password"] && $data["new"]["password"] != '') {
			// TODO: Change password reset tool
			$app->system->exec_safe("nohup /usr/lib/mailman/bin/change_pw -l ? -p ? >/dev/null 2>&1 &", $data["new"]["listname"], $data["new"]["password"]);
			exec('nohup '.$conf['init_scripts'] . '/' . 'sympa reload >/dev/null 2>&1 &');
			$app->db->query("UPDATE mail_mailinglist SET password = '' WHERE mailinglist_id = ?", $data["new"]['mailinglist_id']);
		}
		
		if(is_file('/etc/sympa/virtual.sympa')) exec('postmap /etc/sympa/virtual.sympa');
		if(is_file('/etc/sympa/sympa_transport')) exec('postmap /etc/sympa/sympa_transport'); */
	}

	function delete($event_name, $data) {
		global $app, $conf;

		$this->update_config();

		$app->system->exec_safe("nohup /usr/bin/sympa --close_list=? >/dev/null 2>&1 &", $data["old"]["listname"].'@'.$data["old"]["domain"]);

		exec('nohup '.$conf['init_scripts'] . '/' . 'sympa reload >/dev/null 2>&1 &');
		
		// Still needed? if(is_file('/etc/sympa/virtual.sympa')) exec('postmap /etc/sympa/virtual.sympa');
		// Still needed? if(is_file('/etc/sympa/sympa_transport')) exec('postmap /etc/sympa/sympa_transport');

	}

	function update_config() {
		global $app, $conf;
		
		/*
		//copy($this->sympa_config_dir.'/sympa/sympa.conf', $this->sympa_config_dir.'/sympa/sympa.conf~');

		// load the server configuration options
		$app->uses('getconf');
		$server_config = $app->getconf->get_server_config($conf['server_id'], 'server');

		// load files
		// TODO: Change file
		if(file_exists($conf["rootpath"]."/conf-custom/sympa_list_creation.xml.master")) {
			$content = file_get_contents($conf["rootpath"]."/conf-custom/sympa_list_creation.xml.master");
		} else {
			$content = file_get_contents($conf["rootpath"]."/conf/sympa_list_creation.xml.master");
		}
		$old_file = file_get_contents($this->sympa_config_dir."/mm_cfg.py");

		/* $old_options = array();
		$lines = explode("\n", $old_file);
		foreach ($lines as $line)
		{
			if (strlen($line) && substr($line, 0, 1) != '#')
			{
				list($key, $value) = explode("=", $line);
				if ($value && $value !== '')
				{
					$key = rtrim($key);
					$old_options[$key] = trim($value);
				}
			}
		}*/

		// create virtual_domains list
		$domainAll = $app->db->queryAllRecords("SELECT domain FROM mail_mailinglist GROUP BY domain");
		$virtual_domains = '';
		foreach($domainAll as $domain)
		{
			if ($domainAll[0]['domain'] == $domain['domain'])
				$virtual_domains .= "'".$domain['domain']."'";
			else
				$virtual_domains .= ", '".$domain['domain']."'";
			
			// create the domain https://github.com/sympa-community/sympa-community.github.io/blob/master/manual/install/configure-mail-server-postfix.md#adding-new-domain
			if(!is_dir($this->sympa_config_dir.'/'.$domain['domain'])) mkdir($this->sympa_config_dir.'/'.$domain['domain'], 0755);
			chown($this->sympa_config_dir.'/'.$domain['domain'], 'sympa');
			chgrp($this->sympa_config_dir.'/'.$domain['domain'], 'sympa');
			if(!is_file($this->sympa_config_dir.'/'.$domain['domain'].'/robot.conf')) touch($this->sympa_config_dir.'/'.$domain['domain'].'/robot.conf');
			chown($this->sympa_config_dir.'/'.$domain['domain'].'/robot.conf', 'sympa');
			chgrp($this->sympa_config_dir.'/'.$domain['domain'].'/robot.conf', 'sympa');
			
			/* TODO : add config to robot.conf
			listmaster adresse-email-admin@retzo.net
			create_list  listmaster
			wwsympa_url     http://lists.$line/sympa" > $SYSCONFDIR.'/'.$line/robot.conf 
			*/
						/* TODO : add config to transport.sympa
				echo "sympa@$line          sympa:sympa@$line
			listmaster@$line     sympa:listmaster@$line
			bounce@$line         sympabounce:sympa@$line
			abuse-feedback-report@$line  sympabounce:sympa@$line" >>  $SYSCONFDIR/transport.sympa
			*/

			/* TODO : add config to virtual.sympa
				echo "sympa-request@$line  postmaster@retzo.net
			sympa-owner@$line    postmaster@retzo.net" >>  $SYSCONFDIR/virtual.sympa
			*/

			if(!is_dir($this->sympa_expldir_dir.'/'.$domain['domain'])) mkdir($this->sympa_expldir_dir.'/'.$domain['domain'], 0750);
			chown($this->sympa_expldir_dir.'/'.$domain['domain'], 'sympa');
			chgrp($this->sympa_expldir_dir.'/'.$domain['domain'], 'sympa');
		}

		exec('nohup '.$conf['init_scripts'] . '/' . 'sympa reload >/dev/null 2>&1 &');

		// Still needed? $content = str_replace('{hostname}', $server_config['hostname'], $content);
		// $content = str_replace('{default_language}', $old_options['DEFAULT_SERVER_LANGUAGE'], $content);
		// $content = str_replace('{virtual_domains}', $virtual_domains, $content);

		//file_put_contents($this->sympa_config_dir.'/sympa/sympa.conf', $content);
	}

} // end class

?>
