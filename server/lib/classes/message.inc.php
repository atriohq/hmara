<?php

/*
Copyright (c) 2025, Falko Timme, Timme Hosting GmbH & Co. KG
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

/*
* This library allows it to display messages on the ISPConfig dashboard which the user
* has to actively acknowledge to hide them. The messages are stored in the sys_message table.
*/

class message {

    /*
    * This function is used to add a new message
    */
    
    public function add($message, $message_state = 'info', $sys_userid = 0, $sys_groupid = 0, $relation = '') {
        global $app, $conf;

        if(!in_array($message_state,['info','warning','error'])) $app->debug_log('Unknown message_state in message add function.');
        $message_date = date('Y-m-d H:i:s');
        $sql = "INSERT INTO `sys_message` (`sys_userid`,`sys_groupid`,`message_state`,`message_date`,`message`,`relation`) VALUES (?,?,?,?,?,?)";
        if(is_object($app->dbmaster)) {
            // server
            if(!$app->dbmaster->query($sql,(int)$sys_userid,(int)$sys_groupid,$message_state,$message_date,$message,$relation)) {
                $app->debug_log('Adding '.$message_state.' message to sys_message failed.');
            }
        } else {
            // interface
            if(!$app->db->query($sql,(int)$sys_userid,(int)$sys_groupid,$message_state,$message_date,$message,$relation)) {
                $app->debug_log('Adding '.$message_state.' message to sys_message failed.');
            }
        }
    }

    /*
    * This function is used to hide a message by message_id
    */

    public function hide_by_id($message_id) {
        global $app, $conf;
        $sql = "UPDATE `sys_message` SET `message_ack` = 'y' WHERE `message_id` = ?";
        if(is_object($app->dbmaster)) {
            // server
            if(!$app->dbmaster->query($sql,(int)$message_id)) {
                $app->debug_log('Hiding message '.$message_id.' in sys_message failed.');
            }
        } else {
            // interface
            if(!$app->db->query($sql,(int)$message_id)) {
                $app->debug_log('Hiding message '.$message_id.' in sys_message failed.');
            }
        }
    }

    /*
    * This function is used to hide a message by message_state
    */

    public function hide_by_message_state($message_state, $relation = '') {
        global $app, $conf;

        if(!in_array($message_state,['info','warning','error'])) $app->debug_log('Unknown message_state in message add function.');
        
        if(is_object($app->dbmaster)) {
            // server
            $sql = "UPDATE `sys_message` SET `message_ack` = 'y' WHERE `message_state` = ?";
            if(!empty($relation)) $sql .= " AND relation = ?";

            if(!$app->dbmaster->query($sql, $message_state, $relation)) {
                $app->debug_log('Hiding message by '.$message_state.' in sys_message failed.');
            }
        } else {
            // interface
            $app->uses('tform_base');
            $sql = "UPDATE `sys_message` SET `message_ack` = 'y' WHERE `message_state` = ? AND ".$app->tform_base->getAuthSQL('r');
            if(!empty($relation)) $sql .= " AND relation = ?";

            if(!$app->db->query($sql, $message_state, $relation)) {
                $app->debug_log('Hiding message by '.$message_state.' in sys_message failed.');
            }

            // Do some cleanup
            $this->cleanup();
        }
    }

    /*
    * This function is used to hide a message by relation
    */

    public function hide_by_message_relation($relation) {
        global $app, $conf;

        if(is_object($app->dbmaster)) {
            // server
            $sql = "UPDATE `sys_message` SET `message_ack` = 'y' WHERE `relation` = ?";

            if(!$app->dbmaster->query($sql, $relation)) {
                $app->debug_log('Hiding message by '.$relation.' in sys_message failed.');
            }
        } else {
            // interface
            $app->uses('tform_base');
            $sql = "UPDATE `sys_message` SET `message_ack` = 'y' WHERE `relation` = ? AND ".$app->tform_base->getAuthSQL('r');

            if(!$app->db->query($sql, $relation)) {
                $app->debug_log('Hiding message by '.$relation.' in sys_message failed.');
            }

            // Do some cleanup
            $this->cleanup();
        }
    }

    /*
    * This function is used to delete a message by message_id
    */

    public function delete($message_id) {
        global $app, $conf;
        $sql = "DELETE FROM `sys_message` WHERE `message_id` = ?";

        if(is_object($app->dbmaster)) {
            // server
            if(!$app->dbmaster->query($sql,(int)$message_id)) {
                $app->debug_log('Deleting message '.$message_id.' from sys_message failed.');
            }
        } else {
            // interface
            if(!$app->db->query($sql,(int)$message_id)) {
                $app->debug_log('Deleting message '.$message_id.' from sys_message failed.');
            }
        }
    }

    /*
    * This function is used to clean up old messages
    */

    private function cleanup() {
        global $app, $conf;
        $message_cleanup_date = date('Y-m-d H:i:s',strtotime('- 1 month'));
        $sql = "DELETE FROM `sys_message` WHERE `message_ack` = 'y' and `message_date` < ?";
        if(!$app->db->query($sql,$message_cleanup_date)) {
            $app->debug_log('Cleaning up messages from sys_message failed.');
        }
    }

    /*
    * This function returns an array with all not acknowledged messages
    * for the currently logged-in user (use in interface only)
    */

    public function get_current_messages($relation = '') {
        global $app, $conf;

        $app->uses('tform_base');
        if(!is_object($app->tform_base)) {
            $app->debug_log('No tform_base object. Do not use this function in server part.');
            return false;
        }

        if($relation != '') {
            $sql = "SELECT * FROM `sys_message` WHERE `message_ack` = 'n' AND `relation` = ? AND ".$app->tform_base->getAuthSQL('r');
        } else {
            $sql = "SELECT * FROM `sys_message` WHERE `message_ack` = 'n' AND ".$app->tform_base->getAuthSQL('r');
        }
        $messages = $app->db->queryAllRecords($sql,$relation);

        // Translate messages
        $app->load_language_file('web/dashboard/lib/lang/'.$_SESSION['s']['language'].'_message.lng');
        foreach($messages as $key => $msg) {
            $messages[$key]['message'] = $app->lng($msg['message']);
        }

        return $messages;
    }
}