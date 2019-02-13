<?php

!defined('IN_API') && exit('Access Denied');

class ai {
    var $db;
    var $redis;
    var $base;
    
    var $ai_id = 0;
    var $appid = 0;
    var $ai_type = '';
    var $ai_config = array();
    var $access_token = '';
    var $status = 0;
    
    var $expires = NULL;

    function __construct(&$base, $service) {
        $this->ai($base, $service);
    }

    function ai(&$base, $service) {
        $this->base = $base;
        $this->db = $base->db;
        $this->redis = $base->redis;
        
        $this->ai_id = $service['ai_id'];
        $this->appid = $service['appid'];
        $this->ai_type = $service['ai_type'];
        $this->ai_config = $service['ai_config'];
        $this->access_token = $service['access_token'];
        $this->expires = $service['expires'];
        $this->status = $service['status'];
    }
    
}
