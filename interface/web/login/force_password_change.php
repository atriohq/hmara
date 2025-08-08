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

// Check if we have an active user session
if($_SESSION['s']['user']['active'] == 1) {
	header('Location: /index.php');
	die();
}

// If we don't have a password change session, go back to login page
if(!isset($_SESSION['force_password_change'])) {
	header('Location: index.php');
	die();
}

// Variables and settings
$error = '';
$msg = '';

// CSRF Check if we got POST data
if(count($_POST) >= 1) {
	$app->auth->csrf_token_check();
}

require ISPC_ROOT_PATH.'/web/login/lib/lang/'.$app->functions->check_language($conf['language']).'.lng';

function finish_password_change_success() {
	global $app;
	$_SESSION['s'] = $_SESSION['s_pending'];
	unset($_SESSION['s_pending']);
	unset($_SESSION['force_password_change']);
	$username = $_SESSION['s']['user']['username'];
	$app->auth_log('Successful login for user \''. $username .'\' after forced password change from '. $_SERVER['REMOTE_ADDR'] .' at '. date('Y-m-d H:i:s') . ' with session ID ' .session_id());
	session_write_close();
	header('Location: ../index.php');
	die();
}

// Handle password change form submission
if(isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
	$new_password = $_POST['new_password'];
	$confirm_password = $_POST['confirm_password'];
	
	// Validate password requirements
	if(empty($new_password)) {
		$error = $wb['error_password_empty'];
	} elseif(strlen($new_password) < 8) {
		$error = $wb['error_password_too_short'];
	} elseif($new_password !== $confirm_password) {
		$error = $wb['error_passwords_do_not_match'];
	} else {
		// Check if new password is different from current password
		$current_user = $app->db->queryOneRecord('SELECT `passwort` FROM `sys_user` WHERE `userid` = ?', $_SESSION['s_pending']['user']['userid']);
		
		// Use ISPConfig's password verification method
		if(crypt($new_password, $current_user['passwort']) === $current_user['passwort']) {
			$error = $wb['error_password_same_as_current'];
		} else {
			// Hash the new password using ISPConfig's method and update the database
			$hashed_password = $app->auth->crypt_password($new_password);
			$app->db->query('UPDATE `sys_user` SET `passwort` = ?, `last_password_change` = CURDATE() WHERE `userid` = ?', 
				$hashed_password, $_SESSION['s_pending']['user']['userid']);
			
			if($app->db->errorMessage == '') {
				// Password changed successfully
				finish_password_change_success();
			} else {
				$error = $wb['error_password_change_failed'];
			}
		}
	}
}

$app->uses('tpl');
$app->tpl->newTemplate('main_login.tpl.htm');
$app->tpl->setInclude('content_tpl', 'templates/force_password_change.htm');

$app->load_language_file('web/login/lib/lang/'.$conf["language"].'.lng');

// Get system config for password requirements
$app->uses('getconf');
$system_config = $app->getconf->get_global_config('misc');
$force_days = isset($system_config['force_password_change_days']) ? (int)$system_config['force_password_change_days'] : 90;

// Calculate days since last password change
$user_data = $app->db->queryOneRecord('SELECT last_password_change FROM sys_user WHERE userid = ?', $_SESSION['s_pending']['user']['userid']);
$last_change = new DateTime($user_data['last_password_change']);
$today = new DateTime();
$days_since_change = $today->diff($last_change)->days;

if ($error != '') {
	$error = '<div class="box box_error">'.$error.'</div>';
}

$app->tpl->setVar('error', $error);
$app->tpl->setVar('error_txt', $wb['error_txt']);
$app->tpl->setVar('force_password_change_title', $wb['force_password_change_title']);
$app->tpl->setVar('force_password_change_message', sprintf($wb['force_password_change_message'], $days_since_change, $force_days));
$app->tpl->setVar('new_password_txt', $wb['new_password_txt']);
$app->tpl->setVar('confirm_password_txt', $wb['confirm_password_txt']);
$app->tpl->setVar('change_password_button_txt', $wb['change_password_button_txt']);
$app->tpl->setVar('generate_password_txt', $wb['generate_password_txt']);
$app->tpl->setVar('password_strength_txt', $wb['password_strength_txt']);
$app->tpl->setVar('password_mismatch_txt', $wb['password_mismatch_txt']);
$app->tpl->setVar('password_match_txt', $wb['password_match_txt']);
$app->tpl->setVar('current_theme', isset($_SESSION['s_pending']['theme']) ? $_SESSION['s_pending']['theme'] : 'default', true);

// Logo
$logo = $app->db->queryOneRecord("SELECT * FROM sys_ini WHERE sysini_id = 1");
if($logo['custom_logo'] != ''){
    $base64_logo_txt = $logo['custom_logo'];
} else {
    $base64_logo_txt = $logo['default_logo'];
}
$tmp_base64 = explode(',', $base64_logo_txt, 2);
$logo_dimensions = $app->functions->getimagesizefromstring(base64_decode($tmp_base64[1]));
$app->tpl->setVar('base64_logo_width', $logo_dimensions[0].'px');
$app->tpl->setVar('base64_logo_height', $logo_dimensions[1].'px');
$app->tpl->setVar('base64_logo_txt', $base64_logo_txt);

// SET csrf token
$csrf_token = $app->auth->csrf_token_get('force_password_change');
$app->tpl->setVar('_csrf_id',$csrf_token['csrf_id']);
$app->tpl->setVar('_csrf_key',$csrf_token['csrf_key']);

// Title
$sys_config = $app->getconf->get_global_config('misc');
if (!empty($sys_config['company_name'])) {
	$app->tpl->setVar('company_name', $sys_config['company_name'].' :: ');
}

$app->tpl_defaults();
$app->tpl->pparse();

?>
