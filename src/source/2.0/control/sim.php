<?php

!defined('IN_API') && exit('Access Denied');

class simcontrol extends base {

    function __construct() {
        $this->simcontrol();
    }

    function simcontrol() {
        parent::__construct();
        $this->load('sim');
        $this->load('device');
        $this->load('user');
    }
    
    function onregister() {
        $this->init_input('P');
        
        $simid = $this->input('simid');
        $deviceid = $this->input('deviceid');

        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        if(!$simid || !$deviceid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        $sim = $_ENV['sim']->get_sim_by_id($simid);
        
        // 已注册设备
        if($sim && $sim['uid'] != 0 && $sim['uid'] != $uid) {
            $extras = array();
            $user = $_ENV['user']->_format_user($uid);
            if($user && $user['username']) {
                $extras['username'] = $user['username'];
            }
            $this->error(API_HTTP_FORBIDDEN, SIM_ERROR_ALREADY_REG, NULL, NULL, NULL, $extras);
        }

        // 设备已绑定
        if(!$_ENV['sim']->check_device_bind($simid, $deviceid)) {
            $this->error(API_HTTP_BAD_REQUEST, SIM_ERROR_DEVICE_ALREADY_BIND);
        }  

        $sim = $_ENV['sim']->register($uid, $simid, $deviceid, $this->client_id, $this->appid);
        if(!$sim) {
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, SIM_ERROR_ADD_FAILED);
        }
        
        return $sim;
    }

    function onmeta() {
        $this->init_input('G');
        $simid = $this->input('simid');
        if(!$simid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $sim = $_ENV['sim']->get_sim_by_id($simid);
        if(!$sim || !$sim['uid'])
            $this->error(API_HTTP_BAD_REQUEST, SIM_ERROR_NOT_EXIST);

        if($sim['uid'] != $uid)
            $this->error(API_HTTP_BAD_REQUEST, SIM_ERROR_NO_AUTH);
        
        $result = $_ENV['sim']->meta($sim);
        if (!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, SIM_ERROR_NOT_EXIST);

        return $result;
    }

    function ondrop() {
        $this->init_input('P');
        $simid = $this->input('simid');
        if(!$simid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $sim = $_ENV['sim']->get_sim_by_id($simid);
        if(!$sim)
            $this->error(API_HTTP_BAD_REQUEST, SIM_ERROR_NOT_EXIST);

        if($sim['uid'] != $uid)
            $this->error(API_HTTP_BAD_REQUEST, SIM_ERROR_NO_AUTH);
        
        $ret = $_ENV['sim']->drop($sim);
        if (!$ret) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, SIM_ERROR_DROP_FAILED);

        $result = array(
            'simid' => strval($simid)
        );
        return $result;
    }

    function onlist() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input('G');
        $keyword = strval($this->input('keyword'));
        $orderby = $this->input('orderby');
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if (!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['sim']->list_sim($uid, $keyword, $orderby, $list_type, $page, $count, $this->appid);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, SIM_ERROR_NOT_EXIST);
        
        return $result;
    }
    
}