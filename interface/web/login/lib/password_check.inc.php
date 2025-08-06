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

/**
 * Check if user password needs to be changed based on age
 * @param app $app
 * @param int $userid
 * @return bool
 */
function is_password_change_required($app, $userid) {
	// Get system configuration for password change days
	$app->uses('getconf');
	$system_config = $app->getconf->get_global_config('misc');
	
	// If force_password_change_days is not set or is 0, no password change is required
	if (!isset($system_config['force_password_change_days']) || (int)$system_config['force_password_change_days'] <= 0) {
		return false;
	}
	
	$force_days = (int)$system_config['force_password_change_days'];
	
	// Get user's last password change date
	$user_data = $app->db->queryOneRecord('SELECT `last_password_change` FROM `sys_user` WHERE `userid` = ?', $userid);
	
	if (!$user_data || !$user_data['last_password_change']) {
		// If no last_password_change date, assume password change is required
		return true;
	}
	
	// Calculate days since last password change
	$last_change = new DateTime($user_data['last_password_change']);
	$today = new DateTime();
	$days_since_change = $today->diff($last_change)->days;
	
	return $days_since_change >= $force_days;
}

/**
 * Redirect to forced password change page
 * @param app $app
 * @return void
 */
function redirect_to_password_change($app) {
	// Save current session in pending state
	$_SESSION['s_pending'] = $_SESSION['s'];
	unset($_SESSION['s']);
	
	// Create password change session
	$_SESSION['force_password_change'] = true;
	
	// Redirect to password change page
	header('Location: force_password_change.php');
	die();
}

?>
