<?php

if (!defined('BASEPATH'))exit('No direct script access allowed');

class Xero_authorize extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('xero/Xero');
        $this->load->model('xero_toval');
    }
    
    function index() {
        $authorization = $this->xero->oauth();
        if ($authorization) {
            redirect($authorization);
        }
    }
    
    /**
     * Callback from Xero
     */
    function callback_verify() {
        if (isset($_GET ['oauth_verifier'])) {
            $authorization = $this->xero->oauth_verifier();
            if ($authorization) {
                //success
            } else {
                redirect('xero_authorize/index');
            }
        }
    }
    
    /**
     * Disconnect from xero seesion
     */
    function disconnect() {

        $this->session->unset_userdata('access_token');
        $this->session->unset_userdata('oauth_token_secret');
        $this->session->unset_userdata('xero_session_handle');
    }

}
