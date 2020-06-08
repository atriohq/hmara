<?php

/*
Copyright (c) 2018, Óscar Marcos (funcli.net)
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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* check if the user is logged in */
if(!isset($_SESSION['s']['user'])) {
	die ("You have to be logged in to permform that action!");
}

/* get the id of the user (must be int!) */
if (!isset($_GET['id']) && !isset($_GET['cid'])){
	die ("No repository selected!");
}


//Logued user id
$userid = $_SESSION["s"]["user"]["userid"];
$web_git_id = $app->functions->intval($_GET['id']);

if($userid && $web_git_id) {
	/*
	 * Get the data of the repository
	 */
	$dbgit = $app->db->queryOneRecord(
        	"SELECT * FROM web_git WHERE web_git_id = ?", $web_git_id);

	if($dbgit && $dbgit["sys_userid"] == $userid) {
		$data = [];
		$data['old'] = $data['new'] = $dbgit;
		$insert_data = [
			"server_id" => 1,
			"dbtable" => "web_git",
			"dbidx" => "web_git_id:".$web_git_id,
			"action" => 'p',
			"tstamp" => time(),
			"user" => "admin",
			"data" => serialize($data),
			"status" => "ok"
		];

		$mysql_db_user_id = $app->db->insertFromArray('sys_datalog', $insert_data);

		/* Sending this headers prevents the page to load the new content */
		header('HTTP/1.1 500 Internal Server Error');
	} else {
		echo "You don't have permission to perform that action.";
	}
} else {
	echo "You must be logued in and provide an id.";
	die();
}

die();
?>