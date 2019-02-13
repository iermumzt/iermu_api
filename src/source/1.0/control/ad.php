<?php

!defined('IN_API') && exit('Access Denied');

class adcontrol extends base {

    function __construct() {
        $this->adcontrol();
    }

    function adcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');  
    }

    // 获取首页图
    function onindex() {
        $this->init_input();
        $type = $this->input('type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['device']->list_ad($type, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_AD_FAILED);
        
        return $result;
    }
}
