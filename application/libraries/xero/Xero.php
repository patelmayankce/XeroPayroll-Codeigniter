<?php

if (!defined('BASEPATH'))exit('No direct script access allowed');

require('OAuthSimple.php');
require('XeroOAuth.php');

define("XRO_APP_TYPE", "Public");
define('BASE_PATH', realpath('.'));

class Xero {

    protected $ci;
    protected $config;
    private $xro_defaults = array('xero_url' => 'https://api.xero.com/api.xro/2.0',
        'site' => 'https://api.xero.com',
        'authorize_url' => 'https://api.xero.com/oauth/Authorize',
        'signature_method' => 'HMAC-SHA1');
    private $xro_private_defaults = array('xero_url' => 'https://api.xero.com/api.xro/2.0',
        'site' => 'https://api.xero.com',
        'authorize_url' => 'https://api.xero.com/oauth/Authorize',
        'signature_method' => 'RSA-SHA1');
    private $xro_partner_defaults = array('xero_url' => 'https://api-partner.network.xero.com/api.xro/2.0',
        'site' => 'https://api-partner.network.xero.com',
        'authorize_url' => 'https://api.xero.com/oauth/Authorize',
        'accesstoken_url' => 'https://api-partner.xero.com/oauth/AccessToken',
        'signature_method' => 'RSA-SHA1');
    private $xro_partner_mac_defaults = array('xero_url' => 'https://api-partner2.network.xero.com/api.xro/2.0',
        'site' => 'https://api-partner2.network.xero.com',
        'authorize_url' => 'https://api.xero.com/oauth/Authorize',
        'accesstoken_url' => 'https://api-partner2.xero.com/oauth/AccessToken',
        'signature_method' => 'RSA-SHA1');
    private $xro_consumer_options = array('request_token_path' => '/oauth/RequestToken',
        'access_token_path' => '/oauth/AccessToken',
        'authorize_path' => '/oauth/Authorize');
    private $oauth_callback;
    private $useragent = "Xero-OAuth-PHP Public";
    private $signatures;

    public function __construct() {
        $this->ci = & get_instance();
        //get the config part
        $this->ci->config->load('xero');
        $this->config = $this->ci->config->item('config');


        $this->signatures = array(
            'consumer_key' => $this->config['consumer']['key'],
            'shared_secret' => $this->config['consumer']['secret'],
            'core_version' => '2.0',
            'payroll_version' => '1.0'
        );
        $this->oauth_callback = $this->config['callback'];

        if (XRO_APP_TYPE == "Private" || XRO_APP_TYPE == "Partner") {
            $this->signatures ['rsa_private_key'] = BASE_PATH . '/certs/privatekey.pem';
            $this->signatures ['rsa_public_key'] = BASE_PATH . '/certs/publickey.cer';
        }
        if (XRO_APP_TYPE == "Partner") {
            $this->signatures ['curl_ssl_cert'] = BASE_PATH . '/certs/entrust-cert-RQ3.pem';
            $this->signatures ['curl_ssl_password'] = '12345';
            $this->signatures ['curl_ssl_key'] = BASE_PATH . '/certs/entrust-private-RQ3.pem';
        }
    }

    function oauth_verifier() {
        $this->_reset_obj();
        if ($check = $this->check_errors() === TRUE) {
            if (isset($_GET ['oauth_verifier'])) {
                $this->XeroOAuth->config ['access_token'] = $this->ci->session->userdata('oauth')['oauth_token'];
                $this->XeroOAuth->config ['access_token_secret'] = $this->ci->session->userdata('oauth')['oauth_token_secret'];
                $code = $this->XeroOAuth->request('GET', $this->XeroOAuth->url('AccessToken', ''), array(
                    'oauth_verifier' => $_GET ['oauth_verifier'],
                    'oauth_token' => $_GET ['oauth_token']
                        ));
                if ($this->XeroOAuth->response ['code'] == 200) {
                    $response = $this->XeroOAuth->extract_params($this->XeroOAuth->response ['response']);
                    $session = $this->persistSession($response);
                    $this->ci->session->unset_userdata('oauth');
                    return TRUE;
                } else {
                    return FALSE;
                }
            }
        } else {
            return $check;
        }
    }

    function oauth() {
        $this->_reset_obj();
        if ($check = $this->check_errors() === TRUE) {
            $params = array(
                'oauth_callback' => $this->oauth_callback
            );
            $response = $this->XeroOAuth->request('GET', $this->XeroOAuth->url('RequestToken', ''), $params);
            if ($this->XeroOAuth->response ['code'] == 200) {
                $scope = 'payroll.employees,payroll.leaveapplications,payroll.payitems,payroll.payrollcalendars,payroll.payruns,payroll.payslip,payroll.superfunds,payroll.settings,payroll.timesheets ';
                $this->ci->session->set_userdata(array('oauth' => $this->XeroOAuth->extract_params($this->XeroOAuth->response ['response'])));
                $authurl = $this->XeroOAuth->url("Authorize", '') . "?oauth_token={$this->ci->session->userdata('oauth')['oauth_token']}&scope=" . $scope;
                return $authurl;
            } else {
//                $this->outputError($this->XeroOAuth);
                return FALSE;
            }
        } else {
            return $check;
        }
    }

    /**
     * 
     * @param type $method_type (GET/POST/PUT)
     * @param type $method_name (Methods like(Employee/Invoices))
     * @param type $type (core/payroll)
     * @param type $search_data search data
     * @param type $post_data XML
     * @return boolean
     */
    public function call($method_type = 'GET', $method_name, $type = 'payroll', $search_data = array(), $post_data = NULL) {
        $this->_reset_obj();
        $oauthSession = $this->retrieveSession();
        $this->XeroOAuth->config['access_token'] = $oauthSession['oauth_token'];
        $this->XeroOAuth->config['access_token_secret'] = $oauthSession['oauth_token_secret'];
        $this->XeroOAuth->config['session_handle'] = $oauthSession['oauth_session_handle'];
        if ($method_type == 'GET') {
            $response = $this->XeroOAuth->request($method_type, $this->XeroOAuth->url($method_name, $type) . $post_data, $search_data);
        } else {
            $response = $this->XeroOAuth->request($method_type, $this->XeroOAuth->url($method_name, $type), $search_data, $post_data);
        }
        if ($this->XeroOAuth->response['code'] == 200) {
            $data = $this->XeroOAuth->parseResponse($this->XeroOAuth->response['response'], $this->XeroOAuth->response['format']);
            return $data;
        } else {
//            $this->outputError($this->XeroOAuth);
            return FALSE;
        }
    }

    protected function _reset_obj() {
        $this->XeroOAuth = new XeroOAuth(array_merge(array(
                            'application_type' => XRO_APP_TYPE,
                            'oauth_callback' => $this->oauth_callback,
                            'user_agent' => $this->useragent
                                ), $this->signatures));
    }

    private function retrieveSession() {
        if ($this->ci->session->userdata('access_token') != '') {
            $response['oauth_token'] = $this->ci->session->userdata('access_token');
            $response['oauth_token_secret'] = $this->ci->session->userdata('oauth_token_secret');
            $response['oauth_session_handle'] = $this->ci->session->userdata('session_handle');
            return $response;
        } else {
            return false;
        }
    }

    private function persistSession($response) {
        if (isset($response)) {
            $this->ci->session->set_userdata(array('access_token' => $response['oauth_token']));
            $this->ci->session->set_userdata(array('oauth_token_secret' => $response['oauth_token_secret']));
            if (isset($response['oauth_session_handle']))
                $this->ci->session->set_userdata(array('session_handle' => $response['oauth_session_handle']));
        } else {
            return false;
        }
    }

    private function outputError($XeroOAuth) {
        echo 'Error: ' . $XeroOAuth->response['response'] . PHP_EOL;
        $this->pr($XeroOAuth);
    }

    private function pr($obj) {

        if (!$this->is_cli())
            echo '<pre style="word-wrap: break-word">';
        if (is_object($obj))
            print_r($obj);
        elseif (is_array($obj))
            print_r($obj);
        else
            echo $obj;
        if (!$this->is_cli())
            echo '</pre>';
    }

    private function is_cli() {
        return (PHP_SAPI == 'cli' && empty($_SERVER['REMOTE_ADDR']));
    }

    private function check_errors() {
        $initialCheck = $this->XeroOAuth->diagnostics();
        $checkErrors = count($initialCheck);
        if ($checkErrors > 0) {
            foreach ($initialCheck as $check) {
                $return = 'Error: ' . $check . PHP_EOL;
            }
            return $return;
        } else {
            return TRUE;
        }
    }

}
