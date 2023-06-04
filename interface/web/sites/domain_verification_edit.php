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

// Set the path to the form definition file.
$tform_def_file = 'form/domain_verification.tform.php';

// include the core configuration and application classes
require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

// Load the templating and form classes
$app->uses('tpl,tform,tform_actions,tools_sites');
$app->load('tform_actions');

//* Check permissions for module
$app->auth->check_module_permissions('sites');

// Create a class page_action that extends the tform_actions base class
class page_action extends tform_actions {
	function onBeforeInsert() {
		global $app;

		//If domain already in system than exit with error message
		$rec = $app->db->queryOneRecord("SELECT domain FROM domain WHERE domain = ?",$this->dataRecord['domain']);
		if(!is_null($rec)) {
			$app->tform->errorMessage .= $app->lng('Domain not external');
		}
		unset($rec);
		
		//If domain validation already in progress than exit with error message
		$rec = $app->db->queryOneRecord("SELECT domain FROM domain_verification WHERE domain = ?",$this->dataRecord['domain']);
		if(!is_null($rec)) {
			$app->tform->errorMessage .= $app->lng('Domain already added - delete if first if you want restart the process');
		}
		$app->uses('getconf');
		$global_domain_config = $app->getconf->get_global_config('domains');
		$prefix = $global_domain_config['domain_verification_prefix'];
		if(strlen($prefix) < 2) {
			$prefix = 'ISP';
		}
		$randStr = $prefix.'-'.rand(100000,999999);  //Create Validations string
		$this->dataRecord['dns_auth_record']=$randStr;
	}
	public function onAfterInsert() {
		global $app;
		$newurl = 'domain_verification_info.php?newid='.$app->db->insertID();
		$_SESSION['s']['form']['return_to_url'] = $newurl;
		parent::onAfterInsert();
	}
}

// Create the new page object
$page = new page_action();

// Start the page rendering and action handling
$page->onLoad();
?>
