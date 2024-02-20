<?php
/*
 * Copyright (c) 2023, Johannes Koschier <hannes@cheat.at>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */


// include the core configuration and application classes
require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

//* Check permissions for module
$app->auth->check_module_permissions('sites');

$app->load('listform_actions');

// Path to the list definition file
$list_def_file = 'list/domain_verification.list.php';

class list_action extends listform_actions {



	function onShow() {

		global $app;
		$globalDomainConfig = $app->getconf->get_global_config('domains');

		$clientGroupId = $_SESSION["s"]["user"]["default_group"];
		//Internal Domain List
		$sql = "SELECT * FROM domain WHERE sys_groupid = ? AND domain_type_flag = 'n'";
		$records = $app->db->queryAllRecords($sql, $clientGroupId);
        $app->tpl->setLoop('domain_records', $records);

		if($globalDomainConfig['use_domain_verification'] == 'y') {
			//External Domain List
			$sql = "SELECT * FROM domain WHERE sys_groupid = ? AND domain_type_flag = 'y'";
			$records = $app->db->queryAllRecords($sql, $clientGroupId);
			$app->tpl->setLoop('domain_records_ex', $records);
			$app->tpl->setVar('use_domain_verification','yes');
		}
		if($globalDomainConfig['use_domain_subdomain'] == 'y') {
			//Subdomain (as Maindomain) List
			$sql = "SELECT * FROM domain WHERE sys_groupid = ? AND domain_type_flag = 's'";
			$records = $app->db->queryAllRecords($sql, $clientGroupId);
			$app->tpl->setLoop('domain_records_subdomain', $records);
			$app->tpl->setVar('use_domain_subdomain','yes');
		}
		//* SET csrf token
		$csrf_token = $app->auth->csrf_token_get('domain_verification');
		$app->tpl->setVar('_csrf_id',$csrf_token['csrf_id']);
		$app->tpl->setVar('_csrf_key',$csrf_token['csrf_key']);
		parent::onShow();
	}


}
$list = new list_action;
$list->SQLOrderBy = 'ORDER BY domain';
$list->onLoad();
?>
