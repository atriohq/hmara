<?php

/*
Copyright (c) 2024, Till Brehm, ISPConfig UG
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

class user_cli extends cli {

    function __construct() {
        $cmd_opt = [];
        $cmd_opt['user'] = 'showHelp';
        $cmd_opt['user:set-password'] = 'setPassword';
        $cmd_opt['user:login'] = 'login';
        $this->addCmdOpt($cmd_opt);
    }

    public function setPassword($arg) {
        global $app, $conf;

        // Get username
        $username = $arg[0];

        // Check empty username
        if(empty($username)) {
          $this->swriteln();
          $this->swriteln('Error: Username may not be empty.');
          $this->swriteln();
          $this->showHelp($arg);
          die();
        }

        // Check for invalid chars
        if(!preg_match('/^[\w\.\-\_]{1,64}$/',$username)) {
          $this->swriteln();
          $this->swriteln('Error: Username contains invalid characters.');
          $this->swriteln();
          $this->showHelp($arg);
          die();
        }

        // Get user from ISPConfig database
        $user = $app->db->queryOneRecord("SELECT * FROM `sys_user` WHERE `username` = ?",$username);
        
        // Check if user exists
        if(empty($user)) {
          $this->swriteln();
          $this->swriteln('Error: Username does not exist.');
          $this->swriteln();
          $this->showHelp($arg);
          die();
        }

        // Include auth class from interface
        include_once '/usr/local/ispconfig/interface/lib/classes/auth.inc.php';
        $app->auth = new auth;

        $ok = false;

        while ($ok == false) {
          
          $ok = true;
          // Ask for new password
          $min_password_len = $app->auth->get_min_password_length();
          $new_password = $this->free_query('Enter new password for the user '.$username .' or quit', $app->auth->get_random_password($min_password_len));

          if(strlen($new_password) < $min_password_len) {
            $this->swriteln('The minimum password length is '. $min_password_len);
            $this->swriteln();
            $ok = false;
          }

          if($ok) {
            $new_password2 = $this->free_query('Repeat the password', '');
          }

          if($ok && $new_password != $new_password2) {
            $this->swriteln('Passwords do not match.');
            $this->swriteln();
            $ok = false;
          }

          if($ok) {
            $crypted_password = $app->auth->crypt_password($new_password);
            $app->db->query("UPDATE `sys_user` SET `passwort` = ? WHERE `username` = ?",$crypted_password,$username);
            $this->swriteln('Password for user '.$username.' has been changed.');
            $this->swriteln();
          }
      }
    }

    /**
     * Generate a one-time autologin token for a user.
     *
     * Usage:
     *   ispc user login <username> [ttl_minutes]
     *
     * ttl_minutes = 0 -> no expiry (not recommended). Default = 60.
     */
    public function login($arg) {
        global $app, $conf;

        $username = $arg[0] ?? '';
        $ttl = isset($arg[1]) ? intval($arg[1]) : 60;

        if (empty($username)) {
            $this->swriteln();
            $this->swriteln('Error: Username may not be empty.');
            $this->swriteln();
            $this->showHelp($arg);
            die();
        }

        // Validate username chars (same pattern as setPassword)
        if(!preg_match('/^[\w\.\-\_]{1,64}$/', $username)) {
          $this->swriteln();
          $this->swriteln('Error: Username contains invalid characters.');
          $this->swriteln();
          $this->showHelp($arg);
          die();
        }

        // fetch user
        $user = $app->db->queryOneRecord("SELECT * FROM `sys_user` WHERE `username` = ?", $username);
        if (empty($user)) {
            $this->swriteln();
            $this->swriteln('Error: Username does not exist.');
            $this->swriteln();
            $this->showHelp($arg);
            die();
        }

        if ($user['active'] != 1) {
            $this->swriteln();
            $this->swriteln('Error: User is not active.');
            $this->swriteln();
            die();
        }

        // generate token
        $token = bin2hex(random_bytes(32));

        $expires = null;
        if ($ttl > 0) {
            $expires = date('Y-m-d H:i:s', time() + $ttl * 60);
        }

        $created_by = 'CLI';

        // insert token
        $app->db->query(
            "INSERT INTO autologin_tokens (token, sys_userid, expires, created_by, ip, used) VALUES (?, ?, ?, ?, ?, 0)",
            $token,
            intval($user['userid']),
            $expires,
            $created_by,
            null
        );

        $this->swriteln();
        $this->swriteln('Autologin token created for user: '.$username);
        $this->swriteln('Token: '.$token);
        $this->swriteln('Expires: '. ($expires ?? 'never'));
        $this->swriteln();
        $server = $app->db->queryOneRecord("SELECT server_name FROM server WHERE server_id = ?", $conf['server_id']);
        $ispconfig_panel_url = "https://" . $server['server_name'];
        $this->swriteln($ispconfig_panel_url. '/login/index.php?authtoken=' . $token);
        $this->swriteln();
    }

    public function showHelp($arg) {
      global $conf;

      $this->swriteln("---------------------------------");
      $this->swriteln("- Available commandline options -");
      $this->swriteln("---------------------------------");
      $this->swriteln("ispc user set-password <username> - Set a new password for the ISPConfig user <username>.");
      $this->swriteln("ispc user login <username> [ttl_minutes] - Generate a one-time autologin token for the ISPConfig user <username>.");
      $this->swriteln("---------------------------------");
      $this->swriteln();
    }

}

