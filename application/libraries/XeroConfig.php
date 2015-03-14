<?php

if (!defined('BASEPATH'))exit('No direct script access allowed');

class XeroConfig {
   var $ci;
   var $config;   
   public function __construct() {
     $this->ci =& get_instance();
     $this->ci->config->load('xero');
     $this->config = $this->ci->config->item('config');
   }
   public function get() {
     return $this->config;
   }
}
?>
