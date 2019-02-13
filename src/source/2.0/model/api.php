<?php

!defined('IN_API') && exit('Access Denied');

class apimodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->apimodel($base);
    }

    function apimodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function faceevent($var){
        $this->base->log('haikang log', json_encode($var));

        //人脸比对
        // $identify = $this->faceidentify();

        //图片存储入库
        // $imagedata = $this->savepic();

        //比对结果入库
        // $event = $this->saveevent();

        $res = array('code' => 200, 'message'=>'成功', 'data'=>'');
        return $res;
    }


}
