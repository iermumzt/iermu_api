<?php

!defined('IN_API') && exit('Access Denied');

class hackathoncontrol extends base {

    var $cookie_status = 1;

    function __construct() {
        $this->hackathoncontrol();
    }

    function hackathoncontrol() {
        parent::__construct();
        $this->load('hackathon');

    }
    //上传信息及其文档
    function onapply() {
        $params = array();
        $this->init_input();
        $params['name'] =  $this->input('name');
        $params['email'] = $this->input('email');
        $params['phone'] = $this->input('phone');
        $params['file'] = $_FILES['file'];
        //设置error
        if(!$params['name'] || !$params['email'] || !$params['phone'] || empty($_FILES['file']))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['hackathon']->upload($params);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, UPLOAD_ACTIVITY_ERROR);
        }
        unlink($_FILES['file']['tmp_name']);
        return $result;
    }

    //获取文档
    function onget() {
        //$this->init_input();
        ////$params['name'] =  $this->input('name');
        ////$params['email'] = $this->input('email');
        ////$params['phone'] = $this->input('phone');
        ////$params['file'] = $_FILES['file'];
        //////设置error
        ////if(!$params['name'] || !$params['email'] || !$params['phone'] || empty($_FILES['file']))
        ////    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        ////$result = $_ENV['hackathon']->getrealdoc(1);
        ////return $result;
        var_dump($_SESSION);
    }
    function ongetca() {
        $this->init_input();
        //设置error
        $result = $_ENV['hackathon']->getca();
        return $result;
    }
    /*
   * 检测验证码图片
   */
    function oncheckcode(){
        $this->init_input("G");
        $maxwidth = $this->input('maxwidth');
        $slidertime = $this->input('slidertime');
        $sliderwidth = $this->input('sliderwidth');
        if(!isset($maxwidth) || !isset($slidertime) || !isset($sliderwidth)){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['hackathon']->checkcode($maxwidth , $slidertime , $sliderwidth);
        if ($result){
            return array(
                'status' => 1
            );
        }else{
            return array(
                'status' => -1
            );
        }
    }

}
