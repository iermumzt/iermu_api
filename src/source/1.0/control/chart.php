<?php

!defined('IN_API') && exit('Access Denied');

class chartcontrol extends base {

    function __construct() {
        $this->chartcontrol();
    }

    function chartcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');  
    }

    // 获取用户排行榜
    function onusershare() {
        $this->init_input();
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['user']->sharecharts($page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_GET_CHARTS_FAILED);
        
        return $result;
    }

}
