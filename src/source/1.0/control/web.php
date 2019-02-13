<?php

!defined('IN_API') && exit('Access Denied');

class webcontrol extends base {

    function __construct() {
        $this->webcontrol();
    }

    function webcontrol() {
        parent::__construct();
        $this->load('web');
    }

    // 获取首页图
    function ongetnewposter() {
        $this->init_input();
        $debug = intval($this->input('debug'));

        $status = $debug ? 0 : 1;

        $result = $_ENV['web']->get_new_poster($status);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, CLIENT_ERROR_POSTER_NOT_EXIST);
        
        return $result;
    }
}
