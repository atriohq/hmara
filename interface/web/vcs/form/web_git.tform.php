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

//* Title of the form
$form["title"]    = "Git repository";

//* Description of the form (optional)
$form["description"]  = "";

//* Name of the form. The name shall not contain spaces or foreign characters
$form["name"]    = "web_git";

//* The file that is used to call the form in the browser
$form["action"]   = "web_git_edit.php";

//* The name of the database table that shall be used to store the data
$form["db_table"]  = "web_git";

//* The name of the database table index field, this field must be a numeric auto increment column
$form["db_table_idx"] = "web_git_id";

//* Shall changes to this table be stored in the database history (sys_datalog) table.
//* This should be set to "yes" for all tables that store configuration information.
$form["db_history"]  = "yes"; // yes / no

//* The name of the tab that is shown when the form is opened
$form["tab_default"] = "info";

//* The name of the default list file of this form
$form["list_default"] = "web_git_list.php";

//* Use the internal authentication system for this table. This should
//* be set to yes in most cases
$form["auth"]   = 'yes'; // yes / no

//* Authentication presets. The defaults below does not need to be changed in most cases.
$form["auth_preset"]["userid"]  = 0; // 0 = id of the user, > 0 id must match with id of current user
$form["auth_preset"]["groupid"] = 0; // 0 = default groupid of the user, > 0 id must match with groupid of current user
$form["auth_preset"]["perm_user"] = 'riud'; //r = read, i = insert, u = update, d = delete
$form["auth_preset"]["perm_group"] = 'riud'; //r = read, i = insert, u = update, d = delete
$form["auth_preset"]["perm_other"] = ''; //r = read, i = insert, u = update, d = delete


//* Maybe we're writing in a response to another message
$sm_default_recipient_id = '';
$sm_default_subject = '';
if(isset($_GET['reply']))
{
	$sm_msg_id = preg_replace("/[^0-9]/", "", $_GET['reply']);
	$res = $app->db->queryOneRecord("SELECT sender_id, subject FROM support_message WHERE support_message_id=?", $sm_msg_id);
	if($res['sender_id'])
	{
		$sm_default_recipient_id = $res['sender_id'];
		$sm_default_subject = (preg_match("/^Re:/", $res['subject'])?"":"Re: ") . $res['subject'];
	}
}

$authsql = $app->tform->getAuthSQL('r', 'client');

//* Begin of the form definition of the first tab. The name of the tab is called "message". We refer
//* to this name in the $form["tab_default"] setting above.
$form["tabs"]['info'] = array (
	'title'  => "Website", // Title of the Tab
	'width'  => 100, // Tab width
	'template'  => "templates/web_git_edit.htm", // Template file name
	'fields'  => array (
		//#################################
		// Begin Datatable fields
		//#################################

		//Website
        'parent_domain_id' => array (
            'datatype' => 'INTEGER',
            'formtype' => 'SELECT',
            'default' => '',
            'datasource' => array ( 'type' => 'SQL',
				                    'querystring' => "SELECT web_domain.domain_id, CONCAT(web_domain.domain, ' :: ', server.server_name) AS parent_domain FROM web_domain, server WHERE web_domain.type = 'vhost' AND web_domain.server_id = server.server_id AND {AUTHSQL::web_domain} ORDER BY web_domain.domain",
                                    'keyfield'=> 'domain_id',
                                    'valuefield'=> 'parent_domain'
            ),
            'value'  => ''
        ),
		'url' => array (
            'datatype' => 'VARCHAR',
			'formtype' => 'TEXT',
			'validators' => array (  0 => array ( 'type' => 'NOTEMPTY',
			     'errmsg'=> 'subject_is_empty'),
			),
			'filters'   => array(
				0 => array( 'event' => 'SAVE',
			     	'type' => 'STRIPTAGS'),
				1 => array( 'event' => 'SAVE',
					'type' => 'STRIPNL')
			),
			'default' => $sm_default_subject,
			'value'  => '',
			'width'  => '30',
			'maxlength' => '255'
		),
		'username' => array (
                        'datatype' => 'VARCHAR',
                        'formtype' => 'TEXT',
                        'default' => '',
                        'value'  => '',
                        'separator' => '',
                        'width'  => '30',
                        'maxlength' => '255',
                        'rows'  => '',
                        'cols'  => '',
                        'searchable' => 2
                ),
		'password' => array (
                        'datatype' => 'VARCHAR',
                        'formtype' => 'TEXT',
                        'default' => '',
                        'value'  => '',
                        'separator' => '',
                        'width'  => '30',
                        'maxlength' => '255',
                        'rows'  => '',
                        'cols'  => '',
                        'searchable' => 0
                ),
		/*'password' => array (
                        'datatype' => 'VARCHAR',
                        'formtype' => 'PASSWORD',
                        'encryption'=> 'CRYPT',
                        'default' => '',
                        'value'  => '',
                        'separator' => '',
                        'width'  => '30',
                        'maxlength' => '255',
                        'rows'  => '',
                        'cols'  => ''
                ),*/
		'tstamp' => array (
			'datatype' => 'INTEGER',
			'formtype' => 'TEXT',
			'default' => time(),
			'value'  => '',
			'width'  => '30',
			'maxlength' => '30'
		),
		//#################################
		// ENDE Datatable fields
		//#################################
	)
);



?>