<?php

class HostFactAPI
{

  private $url;
  private $api_key;

  function __construct($url = '', $api_key = '') {
    $this->url             = $url . 'Pro/apiv2/api.php';
    $this->api_key         = $api_key;
  }

  public function sendRequest($controller, $action, $params){

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
    global $debug;

    $domain = $this->get_domain($domain_name);

    if ($domain) {
      return $this->get_debtor($domain['DebtorCode']);
    }
  }

  public function get_domain($domain_name) {
    global $debug;
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
