<?php

/*
Copyright (c) 2025, Till Brehm, ISPConfig UG
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

//* Check permissions for module
$app->auth->check_module_permissions('admin');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* load language file
$lng_file = 'lib/lang/'.$app->functions->check_language($_SESSION['s']['language']).'_extension_install_list.lng';
include $lng_file;

//* This is only allowed for administrators
if(!$app->auth->is_admin()) die('Only allowed for administrators.');

$action = $_REQUEST['action'];
$name = $_REQUEST['name'];
$server_id = (isset($_REQUEST['server_id']) ? intval($_REQUEST['server_id']) : 0);

// check name with regex
if(!preg_match('/^[a-z0-9_]+$/', $name)) show_message('invalid_extension_name_txt', 'admin/extension_repo_list.php');

// check action
if(!in_array($action, array('edit', 'install', 'delete', 'enable', 'disable', 'update'))) {
	show_message('unknown_action_txt', 'admin/extension_repo_list.php');
}

$app->uses('extension_installer');

// handle form post
if(count($_POST) > 0) {
    if($action == 'install') {
        
		// check if server exists
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			show_message('server_not_found_txt', 'admin/extension_repo_list.php');
		}
		
		// check if extension is already installed
		$extension = $app->extension_installer->getInstalledExtension($name, $server_id);
		if(!empty($extension)) {
			show_message('extension_already_installed', 'admin/extension_repo_list.php');
		}
		
		// install extension
		if($app->extension_installer->installExtension($name, $server_id) === false ||$app->extension_installer->hasErrors()) {
			$next_link = 'admin/extension_repo_list.php';
			show_message('install_failed', $next_link, true);
		} else {
			$next_link = 'admin/extension_install_list.php';
			$repo_extension = $app->extension_installer->getRepoExtension($name);
			show_message('install_success', $next_link,false,$repo_extension['postinstall_info']);
		}
		
    }
    if($action == 'edit') {
		// check if server exists
		$sql = 'SELECT * FROM `server` WHERE `server_id` = ?';
		$record = $app->db->queryOneRecord($sql, $server_id);
		if($record === null) {
			show_message('server_not_found_txt', 'admin/extension_repo_list.php');
		}
		
		// check if extension is already installed
		$extension = $app->extension_installer->getInstalledExtension($name, $server_id);
		if(empty($extension)) {
			show_message('extension_not_installed', 'admin/extension_repo_list.php');
		}

		$success = true;

		// if active state has changed
		if(isset($_POST['active'])) {
			if($extension['active'] != 1) {
				if($app->extension_installer->enableExtension($name, $server_id) === false) {
					$success = false;
				}
			}
		} else {
			if($extension['active'] == 1) {
				if($app->extension_installer->disableExtension($name, $server_id) === false) {
					$success = false;
				}
			}
		}
		

		// if license has changed
		if($extension['license'] != $_POST['license']) {
			// check license with regex
			if(!empty($_POST['license']) && !preg_match('/^[a-zA-Z0-9\-]+$/', $_POST['license'])) {
				show_message('invalid_license_txt', 'admin/extension_repo_list.php');
				$success = false;
			}
			if($app->extension_installer->updateLicense($name, $server_id, $_POST['license']) === false) {
				$success = false;
			}
		}

		if($success) {
			show_message('update_success', 'admin/extension_repo_list.php', false);
		} else {
			show_message('update_failed', 'admin/extension_repo_list.php', true);
		}
		
		// update extension
		/*
		if($app->extension_installer->updateExtension($name, $server_id) === false) {
			$next_link = 'admin/extension_install_list.php';
			show_message('update_failed', $next_link, true);
		} else {
			$next_link = 'admin/extension_install_list.php';
			show_message('update_success', $next_link, false);
		}
		*/	
    }
}

$app->tpl->newTemplate('form.tpl.htm');
$app->tpl->setVar('name', $name);

switch($action) {
    case 'edit':
        $app->tpl->setInclude('content_tpl', 'templates/extension_edit.htm');
		$repo_extension = $app->extension_installer->getRepoExtension($name);
        if($repo_extension === null) {
            show_message('extension_not_found_txt', 'admin/extension_repo_list.php');
        }
		$repo_extension['repo_license'] = $repo_extension['license'];
		unset($repo_extension['license']);
		$repo_extension['repo_version'] = $repo_extension['version'];
		unset($repo_extension['version']);
        $app->tpl->setVar($repo_extension);
        $extension = $app->extension_installer->getInstalledExtension($name, $server_id);
        if(empty($extension)) {
            show_message('extension_not_installed', 'admin/extension_repo_list.php');
        }
        $app->tpl->setVar($extension);
		// get server name
		$server_name = $app->extension_installer->getServerName($server_id);
		$app->tpl->setVar('server_name', $server_name);
		// show update button if versions differ
		if($extension['version'] !== $repo_extension['repo_version']) {
			$app->tpl->setVar('show_update', true);
		} else {
			$app->tpl->setVar('show_update', false);
		}
		// show postinstall_info
		if(!empty($extension['postinstall_info'])) {
			$app->tpl->setVar('postinstall_info', $extension['postinstall_info']);
		}
		// show free limits
		if(!empty($extension['free_limits'])) {
			$app->tpl->setVar('free_limits', $extension['free_limits']);
		}
        break;
    case 'install':
        $app->tpl->setInclude('content_tpl', 'templates/extension_install.htm');
        $repo_extension = $app->extension_installer->getRepoExtension($name);
        if($repo_extension === null) {
            show_message('extension_not_found_txt', 'admin/extension_repo_list.php');
        }
        $app->tpl->setVar($repo_extension);
        // get servers
        $servers = $app->db->queryAllRecords('SELECT * FROM `server` WHERE `active` = 1');
        if(!empty($servers)) {
          $server_list = '';
          foreach($servers as $server) {
            $server_list .= '<option value="' . $server['server_id'] . '">' . $server['server_name'] . '</option>';
          }
          $app->tpl->setVar('server_id', $server_list);
        }
        break;
    case 'delete':
        if($app->extension_installer->deleteExtension($name, $server_id) === false) {
            show_message('delete_failed', 'admin/extension_install_list.php', true);
        } else {
            show_message('delete_success', 'admin/extension_install_list.php', false);
        }
        break;
	case 'enable':
		if($app->extension_installer->enableExtension($name, $server_id) === false) {
            show_message('enable_failed', 'admin/extension_install_list.php', true);
        } else {
            show_message('enable_success', 'admin/extension_install_list.php', false);
        }
		break;
	case 'disable':
		if($app->extension_installer->disableExtension($name, $server_id) === false) {
            show_message('disable_failed', 'admin/extension_install_list.php', true);
        } else {
            show_message('disable_success', 'admin/extension_install_list.php', false);
        }
		break;
	case 'update':
		if($app->extension_installer->updateExtension($name, $server_id) === false) {
            show_message('update_failed', 'admin/extension_install_list.php', true);
        } else {
            show_message('update_success', 'admin/extension_install_list.php', false);
        }
		break;
    default:
        show_message('unknown_action_txt', 'admin/extension_repo_list.php');
        break;
}

// set language file variables
$app->tpl->setVar($wb);

$app->tpl_defaults();
$app->tpl->pparse();

// ----------------------------------------------------------------------------------------
function show_message($message, $next_link, $show_errors = false, $info_text = '') {
	global $app;

	$app->tpl->newTemplate('form.tpl.htm');
	$app->tpl->setInclude('content_tpl', 'templates/extension_msg.htm');

	//* load language file 
	$lng_file = 'lib/lang/'.$app->functions->check_language($_SESSION['s']['language']).'_extension_install_list.lng';
	include $lng_file;
	$app->tpl->setVar($wb);

	$message_txt = $wb[$message];
	if($show_errors && $app->extension_installer->hasErrors()) {
		$message_txt .= '<br>' . implode('<br>', $app->extension_installer->getErrors());
	}
	
	$app->tpl->setVar('message_txt', $message_txt);
	$app->tpl->setVar('next_link', $next_link);
	$app->tpl->setVar('info_text', $info_text);

	$app->tpl_defaults();
	$app->tpl->pparse();

	die();
}

// ----------------------------------------------------------------------------------------

function show_form($template, $vars = array(), $error_message = '') {
	global $app;

	$app->tpl->newTemplate('form.tpl.htm');
	$app->tpl->setInclude('content_tpl', 'templates/'.$template);

	//* load language file 
	$lng_file = 'lib/lang/'.$app->functions->check_language($_SESSION['s']['language']).'_wp_action.lng';
	include $lng_file;
	$app->tpl->setVar($wb);
	
	$app->tpl->setVar('error', $wb[$error_message]);

	$app->tpl->setVar($vars);
	$app->tpl_defaults();
	$app->tpl->pparse();

	die();
}

// ----------------------------------------------------------------------------------------