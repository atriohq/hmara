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

class resync_cli extends cli {

    private $app;
    private $server_id = 0;

    function __construct() {
        global $app;
        $this->app = $app;
        
        $cmd_opt = [];
        $cmd_opt['resync'] = 'showHelp';
        $cmd_opt['resync:all'] = 'all';
        $cmd_opt['resync:web'] = 'web';
        $cmd_opt['resync:db'] = 'db';
        $cmd_opt['resync:mail'] = 'mail';
        $cmd_opt['resync:mailfilter'] = 'mailfilter';
        $cmd_opt['resync:client'] = 'client';
        $cmd_opt['resync:dns'] = 'dns';
        $this->addCmdOpt($cmd_opt);
    }

    /**
     * Show help for resync command
     */
    public function showHelp($arg) {
        global $conf;

        $this->swriteln("---------------------------------");
        $this->swriteln("- Available commandline option -");
        $this->swriteln("---------------------------------");
        $this->swriteln("ispc resync - Show the ISPConfig resync options.");
        $this->swriteln("ispc resync all - Resync all services.");
        $this->swriteln("ispc resync web - Resync web services.");
        $this->swriteln("ispc resync db - Resync database services.");
        $this->swriteln("ispc resync mail - Resync mail services.");
        $this->swriteln("ispc resync mailfilter - Resync mailfilter services.");
        $this->swriteln("ispc resync client - Resync client services.");
        $this->swriteln("ispc resync dns - Resync DNS zones.");
        $this->swriteln("---------------------------------");
        $this->swriteln();
    }
    
    /**
     * Resync all services
     */
    public function all($arg) {
        global $app;
        
        $this->swriteln("Resyncing all services...");
        
        // Call all individual resync methods
        $this->web($arg);
        $this->db($arg);
        $this->mail($arg);
        $this->mailfilter($arg);
        $this->dns($arg);
        $this->client($arg);
        
        $this->swriteln("All services resynced successfully.");
    }
    
    /**
     * Resync web services
     */
    public function web($arg) {
        global $app, $conf;

        // if server_id is 1 and we have multiple servers, use provided server_id
        if ($conf['server_id'] == 1 && $this->is_multiserver_primary()) {
            $server_id = (isset($arg[0])) ? intval($arg[0]) : $conf['mirror_server_id'];
        } else {
            $server_id = $conf['server_id'];
        }
        
        $this->swriteln("Resyncing web services...");
        
        // Resync websites
        $this->do_resync('web_domain', 'domain_id', 'web', $server_id, 'domain', 'Websites');
        
        // Resync FTP
        $this->do_resync('ftp_user', 'ftp_user_id', 'web', $server_id, 'username', 'FTP Users');
        
        // Resync WebDAV
        $this->do_resync('webdav_user', 'webdav_user_id', 'file', $server_id, 'username', 'WebDAV Users');
        
        // Resync Shell
        $this->do_resync('shell_user', 'shell_user_id', 'web', $server_id, 'username', 'Shell Users');
        
        // Resync Cron
        $this->do_resync('cron', 'id', 'web', $server_id, 'command', 'Cron Jobs');
        
        $this->swriteln("Web services resynced successfully.");
    }
    
    /**
     * Resync database services
     */
    public function db($arg) {
        global $app, $conf;

        // if server_id is 1 and we have multiple servers, use provided server_id
        if ($conf['server_id'] == 1 && $this->is_multiserver_primary()) {
            $server_id = (isset($arg[0])) ? intval($arg[0]) : $conf['mirror_server_id'];
        } else {
            $server_id = $conf['server_id'];
        }
        
        $this->swriteln("Resyncing database services...");
        
        // Resync database users
        $this->do_resync('web_database_user', 'database_user_id', 'db', $server_id, 'database_user', 'Database Users', false);
        
        // Resync databases
        $this->do_resync('web_database', 'database_id', 'db', $server_id, 'database_name', 'Databases');
        
        $this->swriteln("Database services resynced successfully.");
    }
    
    /**
     * Resync mail services
     */
    public function mail($arg) {
        global $app, $conf;

        // if server_id is 1 and we have multiple servers, use provided server_id
        if ($conf['server_id'] == 1 && $this->is_multiserver_primary()) {
            $server_id = (isset($arg[0])) ? intval($arg[0]) : $conf['mirror_server_id'];
        } else {
            $server_id = $conf['server_id'];
        }
        
        $this->swriteln("Resyncing mail services...");
        
        // Resync mail domains
        $this->do_resync('mail_domain', 'domain_id', 'mail', $server_id, 'domain', 'Mail Domains');
        
        // Resync spam filter policies
        $this->do_resync('spamfilter_policy', 'id', 'mail', $server_id, '', 'Spam Filter Policies', false);
        
        // Resync mail get
        $this->do_resync('mail_get', 'mailget_id', 'mail', $server_id, 'source_username', 'Mail Fetcher');
        
        // Resync mailboxes
        $this->do_resync('mail_user', 'mailuser_id', 'mail', $server_id, 'email', 'Mailboxes', false);
        
        // Resync mail forwarding
        $this->do_resync('mail_forwarding', 'forwarding_id', 'mail', $server_id, '', 'Mail Forwarding');
        
        // Resync mailing lists
        $this->do_resync('mail_mailinglist', 'mailinglist_id', 'mail', $server_id, 'listname', 'Mailing Lists', false);
        
        // Resync mail transport
        $this->do_resync('mail_transport', 'transport_id', 'mail', $server_id, 'domain', 'Mail Transport', false);
        
        // Resync mail relay
        $this->do_resync('mail_relay_recipient', 'relay_recipient_id', 'mail', $server_id, 'source', 'Mail Relay', false);
        
        $this->swriteln("Mail services resynced successfully.");
    }
    
    /**
     * Resync mail filter services
     */
    public function mailfilter($arg) {
        global $app, $conf;

        // if server_id is 1 and we have multiple servers, use provided server_id
        if ($conf['server_id'] == 1 && $this->is_multiserver_primary()) {
            $server_id = (isset($arg[0])) ? intval($arg[0]) : $conf['mirror_server_id'];
        } else {
            $server_id = $conf['server_id'];
        }
        
        $this->swriteln("Resyncing mail filter services...");
        
        // Resync mail access
        $this->do_resync('mail_access', 'access_id', 'mail', $server_id, '', 'Mail Access');
        
        // Resync mail content filter
        $this->do_resync('mail_content_filter', 'content_filter_id', 'mail', $server_id, '', 'Mail Content Filter');
        
        // Resync mail user filter
        $this->do_resync('mail_user_filter', 'filter_id', 'mail', $server_id, '', 'Mail User Filter', false);
        
        // Resync spam filter users
        $this->do_resync('spamfilter_users', 'id', 'mail', $server_id, '', 'Spam Filter Users', false);
        
        // Resync spam filter whitelist/blacklist
        $this->do_resync('spamfilter_wblist', 'wblist_id', 'mail', $server_id, '', 'Spam Filter Whitelist/Blacklist');
        
        $this->swriteln("Mail filter services resynced successfully.");
    }
    
    /**
     * Resync client services
     */
    public function client($arg) {
        global $app, $conf;

        // if server_id is 1 and we have multiple servers, use provided server_id
        if ($conf['server_id'] == 1 && $this->is_multiserver_primary()) {
            $server_id = (isset($arg[0])) ? intval($arg[0]) : $conf['mirror_server_id'];
        } else {
            $server_id = $conf['server_id'];
        }
        
        $this->swriteln("Resyncing client services...");
        
        $this->do_resync('client', 'client_id', 'client', $server_id, '', 'Clients');
        
        $this->swriteln("Client services resynced successfully.");
    }
    
    /**
     * Resync DNS zones
     */
    public function dns($arg) {
        global $app, $conf;

        // if server_id is 1 and we have multiple servers, use provided server_id
        if ($conf['server_id'] == 1 && $this->is_multiserver_primary()) {
            $server_id = (isset($arg[0])) ? intval($arg[0]) : $conf['mirror_server_id'];
        } else {
            $server_id = $conf['server_id'];
        }
        
        $this->swriteln("Resyncing DNS zones...");
        
        $rec = $this->query_server('dns_soa', $server_id, 'dns');
        $soa_records = $rec[0];
        $server_name = $rec[1];
        
        if(is_array($soa_records) && !empty($soa_records)) {
            foreach($soa_records as $soa_rec) {
                // Get DNS resource records for this zone
                $rr_records = $app->db->queryAllRecords("SELECT * FROM dns_rr WHERE zone = ? AND active = 'y'", $soa_rec['id']);
                
                if(!empty($rr_records)) {
                    foreach($rr_records as $rr_rec) {
                        $new_serial = $app->validate_dns->increase_serial($rr_rec['serial']);
                        $app->db->datalogUpdate('dns_rr', array("serial" => $new_serial), 'id', $rr_rec['id']);
                    }
                }
                
                // Update SOA record with new serial
                $new_serial = $app->validate_dns->increase_serial($soa_rec['serial']);
                $app->db->datalogUpdate('dns_soa', array("serial" => $new_serial), 'id', $soa_rec['id']);
                
                $rr_count = is_array($rr_records) ? count($rr_records) : 0;
                $this->swriteln("  [" . $server_name[$soa_rec['server_id']] . "] " . $soa_rec['origin'] . " (" . $rr_count . " RR)");
            }
            $this->swriteln("  " . count($soa_records) . " DNS zones resynced.");
        } else {
            $this->swriteln("  No DNS zones found to resync.");
        }
        
        $this->swriteln("DNS zones resynced successfully.");
    }
    
    /**
     * Query server for records to resync
     * 
     * @param string $db_table Database table name
     * @param int $server_id Server ID
     * @param string $server_type Server type
     * @param bool $active Only active records
     * @param string $opt Additional SQL options
     * @return array Array with records and server names
     */
    private function query_server($db_table, $server_id, $server_type, $active=true, $opt='') {
        global $app, $conf;

        $server_name = array();
        if ($server_id == 0) { // resync multiple server
            $temp = $app->db->queryAllRecords("SELECT server_id, server_name FROM server WHERE ?? = 1 AND active = 1 AND mirror_server_id = 0", $server_type."_server");
            foreach ($temp as $server) {
                $temp_id .= $server['server_id'].',';
                $server_name[$server['server_id']] = $server['server_name'];
            }
            if (isset($temp_id)) $server_id = rtrim($temp_id,',');
        } else {
            $temp = $app->db->queryOneRecord("SELECT `server_name` FROM `server` WHERE `server_id` = ?", $server_id);
            $server_name[$server_id] = $temp['server_name'];
        }
        unset($temp);

        $sql = "SELECT * FROM ??";
        if ($db_table != "mail_user_filter" && $db_table != "spamfilter_policy") $sql .= " WHERE server_id IN (".$server_id.") ";
        $sql .= $opt;
        if ($active) $sql .= " AND `active` = 'y'"; 
        $records = $app->db->queryAllRecords($sql, $db_table);

        return array($records, $server_name);
    }
    
    /**
     * Perform the resync operation
     * 
     * @param string $db_table Database table name
     * @param string $index_field Index field name
     * @param string $server_type Server type
     * @param int $server_id Server ID
     * @param string $msg_field Message field name
     * @param string $title Title for output
     * @param bool $active Only active records
     */
    private function do_resync($db_table, $index_field, $server_type, $server_id, $msg_field, $title, $active=true) {
        global $app, $conf;

        $server_id = intval($server_id);
        $rec = $this->query_server($db_table, $server_id, $server_type, $active);
        $records = $rec[0];
        $server_name = $rec[1];
        
        $this->swriteln("Resyncing " . $title . "...");
        
        if(!empty($records)) {
            foreach($records as $rec) {
                $app->db->datalogUpdate($db_table, $rec, $index_field, $rec[$index_field], true);
                if(!empty($msg_field) && !empty($rec[$msg_field])) {
                    $this->swriteln("  [" . $server_name[$rec['server_id']] . "] " . $rec[$msg_field]);
                }
            }
            $this->swriteln("  " . count($records) . " " . $title . " resynced.");
        } else {
            $this->swriteln("  No " . $title . " found to resync.");
        }
    }

    /**
     * Check if we are the primary server of a multi-server setup
     * 
     * @return bool True if we are the primary server, false otherwise
     */
    private function is_multiserver_primary() {
        global $conf, $app;

        if($conf['server_id'] == 1) {
            // Count records in server table
            $rec = $app->db->queryOneRecord("SELECT COUNT(*) FROM server");
            if($rec['COUNT(*)'] > 1) return true;
        }
        return false;
    }
}
