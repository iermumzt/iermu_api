<?php

!defined('IN_API') && exit('Access Denied');

class recommendcontrol extends base {

    function __construct() {
        $this->recommendcontrol();
    }

    function recommendcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
        $this->load('server');
    }

    // 猜你喜欢-设备
    function onshare() {
        $this->init_input();

        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->user_error();
        } else {
            $uid = 0;
        }

        $client_id = $this->input('client_id');
        if(!$client_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $support_type = $_ENV['oauth2']->get_client_connect_support($client_id);
        
        $result = $_ENV['device']->guess_share($uid, $support_type, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_GET_CHARTS_FAILED);
        
        return $result;
    }

    // 猜你喜欢-用户
    function onuser() {
        $this->init_input();

        $client_id = $this->input('client_id');
        if(!$client_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $sharenum = intval($this->input('sharenum'));
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        $sharenum = $sharenum > 0 ? $sharenum : 0;
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $support_type = $_ENV['oauth2']->get_client_connect_support($client_id);

        $result = $_ENV['user']->guess_user($support_type, $sharenum, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_GET_CHARTS_FAILED);
        
        return $result;
    }
}
