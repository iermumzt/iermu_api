<?php

!defined('IN_API') && exit('Access Denied');

class pay {
    var $db;
    var $redis;
    var $base;
    
    var $payid = 0;
    var $appid = 0;
    var $pay_type = '';
    var $pay_config = array();
    var $status = 0;
    
    var $client = NULL;

    function __construct(&$base, $service) {
        $this->pay($base, $service);
    }

    function pay(&$base, $service) {
        $this->base = $base;
        $this->db = $base->db;
        $this->redis = $base->redis;
        
        $this->payid = $service['payid'];
        $this->appid = $service['appid'];
        $this->pay_type = $service['pay_type'];
        $this->pay_config = $service['pay_config'];
        $this->status = $service['status'];
    }

    function payment($out_trade_no, $subject, $total_fee, $note) {
        return '';
    }

    function verify_notify() {
        return false;
    }
}