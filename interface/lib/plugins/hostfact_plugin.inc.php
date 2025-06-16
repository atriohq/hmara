<?php
/**
 * HostFact Plugin for ISPConfig
 *
 * This plugin allows you to view HostFact information for domains in ISPConfig.
 *
 * Copyright (c) 2025, Herman van Rink, Initfour websolutions
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met:
 *
 *      * Redistributions of source code must retain the above copyright notice,
 *        this list of conditions and the following disclaimer.
 *      * Redistributions in binary form must reproduce the above copyright notice,
 *        this list of conditions and the following disclaimer in the documentation
 *        and/or other materials provided with the distribution.
 *      * Neither the name of ISPConfig nor the names of its contributors
 *        may be used to endorse or promote products derived from this software without
 *        specific prior written permission.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 *  ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 *  WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 *  IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 *  INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 *  BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 *  DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 *  OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 *  NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 *  EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

 /**
  * HostFact plugin for ISPConfig frontend
  *
  * This plugin allows you to view HostFact information for domains in ISPConfig.
  * It registers an event to display the HostFact information in the domain edit form.
  * The plugin uses the HostFact API to fetch domain and debtor information.
  * It caches the domain information for one hour to reduce API calls.
  * The plugin is designed to be used by admin users only.
  *
  * Setup:
  * 1. Place this file in the interface/lib/plugins directory of your ISPConfig installation.
  * 2. Add the HostFact API key and url to your ISPConfig configuration file (config.inc.php):
  *    $conf['hostfact_api_key'] = 'your_hostfact_api_key';
  *    $conf['hostfact_url'] = 'https://your_hostfact_url/';
  * 3. Ensure that the HostFact API is accessible from your ISPConfig server.
  * 4. Re-login as admin on the ISPConfig web interface to load the plugin.
  */
class hostfact_plugin
{

	var $plugin_name	= 'hostfact_plugin';
	var $class_name		= 'hostfact_plugin';

	private $url;
	private $api_key;

	function onLoad() {
		global $app;

		if (empty($conf['hostfact_api_key'])) {
			return;
		}

		// Register for the events
		$app->plugin->registerEvent('client:domain:client_domain_extra_info', $this->plugin_name, 'client_domain_form_print');
	}

	function client_domain_form_print($event, $data) {
		global $app, $conf;

		if($_SESSION["s"]["user"]["typ"] != 'admin') {
			return; // Only show this for admin users for now.
		}

		$listTpl = new tpl;
		$listTpl->newTemplate('templates/domain_edit_hostfact.htm');

		$this->url = $conf['hostfact_url'] . 'Pro/apiv2/api.php';
		$this->api_key = $conf['hostfact_api_key'];

		$hinfo = $this->get_domain($data->dataRecord['domain']);

		if (empty($hinfo)) {
			$listTpl->setVar('hostfact_error', 'Geen HostFact informatie gevonden voor dit domein.');
			return $listTpl->grab();
		}

		$hostfact_status = array(
			1 => 'Wachten op actie',
			4 => 'Actief',
			7 => 'Fout opgetreden',
			8 => 'Geannuleerd',
			9 => 'Verwijderd',
		);
		$listTpl->setVar('hostfact_url', $conf['hostfact_url']);
		$listTpl->setVar('hostfact_debtor', $hinfo['Debtor']);
		$listTpl->setVar('hostfact_debtorcode', $hinfo['DebtorCode']);
		$listTpl->setVar('hostfact_status_label', $hostfact_status[$hinfo['Status']]);

		return $listTpl->grab();
	}

	public function sendRequest($controller, $action, $params){
		if ($this->api_key == 'mock') {
			// Mock response for testing purposes
			return array(
				'controller' => $controller,
				'action' => $action,
				'status' => 'success',
				'date' => date('c'),
				'domain' => array(
					'DebtorCode' => 'C12345',
					'Debtor' => '12345',
					'Status' => 4,
				) + $params
			);
		}
		if(is_array($params)){
			$params['api_key']         = $this->api_key;
			$params['controller']     = $controller;
			$params['action']         = $action;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT,'10');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		$curlResp = curl_exec($ch);
		$curlError = curl_error($ch);

		if ($curlError != ''){
			$result = array(
				'controller' => 'invalid',
				'action' => 'invalid',
				'status' => 'error',
				'date' => date('c'),
				'errors' => array($curlError)
			);
		}else{
			$result = json_decode($curlResp, true);
		}

		return $result;
	}

	public function get_debtor_for_domain($domain_name) {

		$domain = $this->get_domain($domain_name);

		if ($domain) {
			return $this->get_debtor($domain['DebtorCode']);
		}
	}

	public function get_domain($domain_name) {

		if (!empty($_SESSION['hostfact_cache'][$domain_name]) && $_SESSION['hostfact_cache'][$domain_name]['timestamp'] > time() - 3600) {
			// Cache for an hour
			return $_SESSION['hostfact_cache'][$domain_name];
		}

		// Split the domain
		$matches = array();
		preg_match('/(.*?)\.([\.\w]+)$/', $domain_name, $matches);

		// Lookup domain
		$domainParams = array(
			'Domain'  => $matches[1],
			'Tld'   => $matches[2],
		);

		$response = $this->sendRequest('domain', 'show', $domainParams);

		if (!empty($response['errors'])) {
			return FALSE;
		}
		$_SESSION['hostfact_cache'][$domain_name] = $response['domain'];
		return $response['domain'];
	}

	public function get_debtor($debtorCode) {
		// Lookup debtor
		$debtorParams = array(
			'DebtorCode' => $debtorCode,
		);

		$response = $this->sendRequest('debtor', 'show', $debtorParams);
		if (!empty($response['errors'])) {
			return FALSE;
		}

		return $response['debtor'];
	}
}
