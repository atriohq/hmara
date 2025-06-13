<?php

class hostfact_plugin
{

  var $plugin_name        = 'hostfact_plugin';
  var $class_name         = 'hostfact_plugin';

  private $url;
  private $api_key;

  function onLoad() {
    global $app;

    //Register for the events
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
    if (isset($params['mock'] ) && $params['mock'] == true) {
      // Mock response for testing purposes
      return array(
          'controller' => $controller,
          'action' => $action,
          'status' => 'success',
          'date' => date('c'),
          'domain' => array(
              'Domain' => 'example',
              'Tld' => 'com',
              'DebtorCode' => 'C12345',
              'Debtor' => '12345',
              'Status' => 4,
              )
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
        // 'mock' => true, // Set to true for testing purposes
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
