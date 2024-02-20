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

$app->tpl->newTemplate("templates/domain_verification_subdomain.htm");
$lngFile = 'lib/lang/' . $app->functions->check_language($_SESSION['s']['language']) . '_domain_verification_subdomain.lng';
include $lngFile;
$app->tpl->setVar($wb);

$domains = $app->tools_sites->getDomainModuleDomains(); //get Users Domains
$userDomains = array_column($domains,'domain','domain_id'); //remove the first array

if (isset($_POST['domain_id']) && isset($_POST['host'])) {
    $domain_id = $_POST['domain_id'];
    $host = $_POST['host'];

    if (! in_array($domain_id, array_column($domains, 'domain_id'))){
        die ("Domain ID ".$domain_id." not found or no permission");
    }

    $newDomain = $host.".".$userDomains[$domain_id];

    if (! filter_var($newDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $app->error($wb['subdomain_name_not_allowed_txt']);
    }
    if (in_array($newDomain, array_column($domains, 'domain'))){
        $app->error($wb['subdomain_exist_txt']);
    }
    $rec = $app->db->queryOneRecord("SELECT domain FROM web_domain WHERE domain = ?",$newDomain);
    if(!is_null($rec)) {
        $app->error($wb['subdomain_exist_as_web_txt']);
    }

    //Subdomain should be ok to add
    $sys_groupid = $_SESSION["s"]["user"]["default_group"];
    $tempRec = $app->db->queryOneRecord("SELECT sys_userid FROM sys_user WHERE username = 'admin'"); //get sys_userid from admin. Should be 1
    $sql = "INSERT INTO domain (sys_userid, sys_groupid, sys_perm_user, sys_perm_group, domain, domain_type_flag) VALUES (?, ?, 'riud', 'ru', ?, 's')";
    $app->db->query($sql, $tempRec['sys_userid'], $sys_groupid, $newDomain); //Insert into domain table

    header("Location: /sites/domain_verification_list.php");

} else {
    foreach( $domains as $domain) {
        $domain_select .= "<option value=" . $domain['domain_id'] . ">" . $app->functions->htmlentities($app->functions->idn_decode($domain['domain'])) . "</option>\r\n";
    }
    $app->tpl->setVar("domain_option", $domain_select);
    $app->tpl_defaults();
    $app->tpl->pparse();
}
