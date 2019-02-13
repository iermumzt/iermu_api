<?php

!defined('IN_API') && exit('Access Denied');

class baiduapi {

    var $db;
    var $redis;
    var $base;
    
    var $connect_type = 0;
    var $config = array();
    
    var $domain = '';
    
    var $client = NULL;

    function __construct(&$base, $_config) {
        $this->baiduapi($base, $_config);
    }

    function baiduapi(&$base, $_config) {
        $this->base = $base;
        $this->db = $base->db;
        $this->redis = $base->redis;
        $this->config = $_config;
        $this->connect_type = $_config['connect_type'];
        
        $this->domain = $base->connect_domain;
    }
    
}
