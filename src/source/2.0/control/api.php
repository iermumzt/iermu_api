<?php

!defined('IN_API') && exit('Access Denied');

class apicontrol extends base {

    function __construct() {
        $this->apicontrol();
    }

    function apicontrol() {
        parent::__construct();
        $this->load('api');
    }

    //海康摄像机抓拍图片
    function onfacesnap(){
        $var = file_get_contents('php://input');
        $var = json_decode($var, true);
        $result = $_ENV['api']->faceevent($var);
        if(!$result)
             $this->error(API_HTTP_BAD_REQUEST, SAVE_NOTIFY_ERROR);
        return $result;
    }

}
