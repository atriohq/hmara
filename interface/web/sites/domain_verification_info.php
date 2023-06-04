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

// Load the templating and form classes
$app->uses('tpl,tform,tools_sites');

//* Check permissions for module
$app->auth->check_module_permissions('sites');


if(isset($_GET['newid'])) {
	$app->tpl->newTemplate("templates/domain_verification_info.htm");
	$lngFile = 'lib/lang/'.$app->functions->check_language($_SESSION['s']['language']).'_domain_verification_info.lng';
	include $lngFile;
	$app->tpl->setVar($wb);

	if (!is_numeric($_GET['newid'])){
		die ('External Domain - ID not numeric');
	}
	$rec = $app->db->queryOneRecord("SELECT * FROM domain_verification WHERE domain_id = ?", $_GET['newid']);

	$app->tpl->setVar('dns_auth_record', $rec['dns_auth_record']);
	$app->tpl->setVar('domain', $rec['domain']);
	$app->tpl->setVar('domain_id', $rec['domain_id']);
	$app->tpl->setVar('task_done', 'no');
	
	$rrArray = dns_get_record($rec['domain'],DNS_NS); //get Auth nameserver from the domain
	foreach($rrArray as $rr ) { //Ask every Auth Nameserver 
		//$execStr = 'dig @'.$rr['target'].' '.$rec['domain'].' TXT +short';
		//exec($execStr, $arrTXT); //theres no way to do a dig @x.x.x.x with pure PHP
		$digAuthServerStr = '@'.$rr['target'];
		$app->system->exec_safe('dig ? ? ? ?', $digAuthServerStr, $rec['domain'], 'TXT', '+short');
		$arrTXT = [];
		if($app->system->last_exec_retcode() == 0) {
			$arrTXT = $app->system->last_exec_out();
		}
			
		foreach($arrTXT as $txtRecord){ //every TXT record
			$txtRecord = str_replace('"', '', $txtRecord); //Remove the " at begin and end of string
			if($txtRecord == $rec['dns_auth_record']){
				$tempRec =  $app->db->queryOneRecord("SELECT sys_userid FROM sys_user WHERE username = 'admin'"); //get sys_userid from admin. Should be 1 
				$sql = "INSERT INTO domain (sys_userid, sys_groupid, sys_perm_user, sys_perm_group, domain) VALUES (?, ?, 'riud', 'ru', ?)";
				$app->db->query($sql,$tempRec['sys_userid'], $rec['sys_groupid'], $rec['domain']); //Insert into domain table

				$sql = "DELETE FROM domain_verification WHERE domain_id = ?";
				$app->db->query($sql,$rec['domain_id']); //delete from domain_verification
				$app->tpl->setVar('task_done', 'yes');
				break 2;
			}
		}
	}

	if(isset($_GET['refreshbutton'])) {
		sleep(1); //simple delay to prevent refresh button abuse..
	}
	
	$app->tpl_defaults();
	$app->tpl->pparse();
}
?>
