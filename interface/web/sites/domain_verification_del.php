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


$list_def_file = "list/domain_verification.list.php";
$tform_def_file = "form/domain_verification.tform.php";

/******************************************
 * End Form configuration
 ******************************************/

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

//* Check permissions for module
$app->auth->check_module_permissions('sites');

$app->uses('tpl,tform,tform_actions');
$app->load('tform_actions');


class page_action extends tform_actions {
    function onBeforeDelete() {
        if (!$app->tform->checkPerm($this->id, 'd')) {
            $app->error($app->lng('error_no_delete_permission'));
        }
    }
}


if (isset($_GET["deldomain_id"])) { //delete the domain from domain table

    global $app;
    global $conf;


    $domain_id = $_GET["deldomain_id"];
    if (!is_numeric($domain_id)) {
        die("Domain ID not numeric"); //should never happen...
    }

    $clientGroupId = $_SESSION["s"]["user"]["default_group"];
    $sql = "SELECT domain FROM domain WHERE domain_id = ? AND sys_groupid = ? AND (domain_type_flag = 'y' OR domain_type_flag = 's')";
    $res = $app->db->queryOneRecord($sql, $domain_id, $clientGroupId);
    if (is_array($res)) {
        $domain = $res['domain'];
    } else {
        die("Domain with ID " . $domain_id . " not found or no permission or not external"); //should never happen...
    }

    //* load language file for error msg
    $lngFile = 'lib/lang/' . $app->functions->check_language($_SESSION['s']['language']) . '_domain_verification_list.lng';
    include $lngFile;

    //Domain in use code from client/domain_del.php
    $sql = "SELECT id FROM dns_soa WHERE origin = ?";
    $res = $app->db->queryOneRecord($sql, $domain . ".");
    if (is_array($res)) {
        $app->error($wb['error_domain_in dnsuse']);
    }

    $sql = "SELECT id FROM dns_slave WHERE origin = ?";
    $res = $app->db->queryOneRecord($sql, $domain . ".");
    if (is_array($res)) {
        $app->error($wb['error_domain_in dnsslaveuse']);
    }

    $sql = "SELECT domain_id FROM mail_domain WHERE domain = ?";
    $res = $app->db->queryOneRecord($sql, $domain);
    if (is_array($res)) {
        $app->error($wb['error_domain_in mailuse']);
    }

    $sql = "SELECT domain_id FROM web_domain WHERE (domain = ? AND type IN ('alias', 'vhost', 'vhostalias')) OR (domain LIKE ? AND type IN ('subdomain', 'vhostsubdomain'))";
    $res = $app->db->queryOneRecord($sql, $domain, '%.' . $domain);
    if (is_array($res)) {
        $app->error($wb['error_domain_in webuse']);
    }

    //delete the domain - recheck permission and external flag
    $app->db->query("DELETE FROM domain WHERE  domain_id = ? AND sys_groupid = ? AND (domain_type_flag = 'y' OR domain_type_flag = 's')", $domain_id, $clientGroupId);

    header("Location: /sites/domain_verification_list.php"); //because onDelete don't get called we do it here

} else { //delete the open domain verification
    $app->tform_actions->onDelete();
}


