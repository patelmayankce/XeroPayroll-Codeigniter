<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Xero extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('xero/Xero');
        $this->load->library('XeroConfig');
    }

    /**
     * checks session for oauth token. if not found then we are not
     * logged onto xero. 
     * 
     * bool @authorized: passed to view to either render 'connect to xero' button or xero commands.
     * 
     */
    public function index() {
        $config = $this->xeroconfig->get();
        if (!isset($this->session->userdata['oauth_token'])) {
            $authorized = FALSE;
        } else {
            $authorized = TRUE;
        }
        $this->load->view('xero', array('authorized' => $authorized));
    }

    // used in functions below to allow or disallow access to API
    public function authorize() {

        /**
         * checks for Xero authorization
         * 
         * if not authorized, Connet to Xero link is rendered, else, sets $this->authorized to true;
         */
        if (!isset($this->session->userdata['oauth_token'])) {
            // using link instead of Xero button image to simplify sample code
            // button images are obtained from Xero
            echo '<a href="' . site_url() . 'xero_authorize">Connect to Xero</a>';
            return FALSE;
        } else {
            $this->authorized = TRUE;
            return TRUE;
        }
    }

    public function get_contacts() {
        if ($this->authorize()) {
            $contactsData = $xml = simplexml_load_string($this->xero->api_call('contacts'));
            if (!isset($contactsData->Contacts)) {
                die('no contacts');
            }

            $this->load->view('contacts', array('contacts' => $contactsData->Contacts->Contact));
        }
    }

    // renders add contact view & sets form post uri segment to 'put_contact' in view 
    public function add_contact() {
        $this->load->view('edit_contact', array(
            'method' => 'post_contact',
            'label' => 'Add'
        ));
    }

    // creates new contact
    // receives POST from edit contact view
    // renders contact list after add
    public function put_contact() {
        $this->xero->api_put($_POST);
        $this->get_contacts();
    }

    // updates contact
    // receives POST from add contact view
    // renders contact list after update
    public function post_contact() {
        $this->xero->api_post($_POST);
        $this->index();
    }

    // renders edit contact view with contact details in form & sets form post uri segment to 'post_contact' in view
    public function edit_contact($id) {
        // get contact from api
        $contactData = $xml = simplexml_load_string($this->xero->api_call('contacts', $id));

        // map dynamic address and phone fields into into $contactData object
        $contactData = $this->xero->map_fields($contactData);
        //ds($contactData,1);
        if (count($contactData) < 1) {
            echo "No Contacts";
            exit;
        }

        $this->load->view('edit_contact', array(
            'contact' => $contactData->Contacts->Contact,
            'method' => 'post_contact',
            'label' => 'Edit'
        ));
    }

    // used to refresh the 1/2hr expired access token
    // make sure access token used to make this call is the expired access token and not a new one
    public function renew_access_token() {
        $this->xero->renew_access_token();
        redirect(site_url() . 'xero');
    }

    // simple termination of the session
    public function logoff_xero() {
        $this->session->sess_destroy();
        redirect(site_url() . 'xero');
    }

    //get all session data
    public function session() {
        echo "oauth_token:- " . $this->session->userdata['oauth_token'] . "<br>";
        echo "oauth_secret:- " . $this->session->userdata['oauth_secret'];
    }

}

/* End of file xero.php */
/* Location: ./application/controllers/xero.php */
