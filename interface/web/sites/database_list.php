<?php

/*
Copyright (c) 2008, Till Brehm, projektfarm Gmbh
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

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

/******************************************
* Begin Form configuration
******************************************/

$list_def_file = "list/database.list.php";

/******************************************
* End Form configuration
******************************************/

//* Check permissions for module
$app->auth->check_module_permissions('sites');

$app->load('listform_actions');


class list_action extends listform_actions {

	private $global_config;

	function onLoad() {
		global $app;

		$app->uses('getconf');
		$this->global_config = $app->getconf->get_global_config('sites');

		parent::onLoad();
	}

	function prepareDataRow($rec) {
		global $app;

		$rec = parent::prepareDataRow($rec);

		//* Set flags for showing phpMyAdmin or phpPgAdmin links based on database type
		$db_type = isset($rec['type']) ? $rec['type'] : 'mysql';

		//* Show phpMyAdmin link only for MySQL/MariaDB databases
		if(strtolower($db_type) == 'mysql' && $this->global_config['dblist_phpmyadmin_link'] == 'y') {
			$rec['show_phpmyadmin_link'] = 1;
		} else {
			$rec['show_phpmyadmin_link'] = 0;
		}

		//* Show phpPgAdmin link only for PostgreSQL databases and only if URL is configured
		if(strtolower($db_type) == 'pgsql' && !empty($this->global_config['phppgadmin_url'])) {
			$rec['show_phppgadmin_link'] = 1;
		} else {
			$rec['show_phppgadmin_link'] = 0;
		}

		return $rec;
	}

}

$list = new list_action;
$list->SQLOrderBy = 'ORDER BY web_database.database_name';
$list->onLoad();


?>
