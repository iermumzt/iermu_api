<?php

!defined('IN_API') && exit('Access Denied');

class meetingcontrol extends base {

    function __construct() {
        $this->meetingcontrol();
    }

    function meetingcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('meeting');
        $this->load('device');
        $this->load('oauth2');
        $this->load('statistics');
    }
    //注册
    function onaddmember() {
        $this->init_input();
        $fname = $this->input('name');
        $remarks = $this->input('remark');
        $image_id = $this->input('image_id');
        $meeting_id = $this->input('meeting_id');
        $face_id = $this->input('face_id');

        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $user = $_ENV['user']->get_user_by_uid($uid);
        if(!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
        if(!$meeting_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        // 若存在则为app拉取到web
        if(isset($face_id) && !empty($face_id)){
            $face_arr = explode(',' , $face_id);
            $result = $_ENV['meeting']->addappmember($uid , $face_arr, $meeting_id);
            //if(!$result){
            //    $this->error(API_HTTP_BAD_REQUEST, ADD_MEMBER_ERROR);
            //}
            return $result;
        }
        if(empty($_FILES['file']) || !$fname)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        //检测姓名与备注有效性
        $this->_check_name($fname);
        if($remarks)
            $this->_check_name($remarks);

        //图片处理
        if(!empty($_FILES['file'])){
            //上传复用
            $img_result = $_ENV['meeting']->face_upload($uid, $_FILES['file']['tmp_name']);
            // if($img_result === 0)
            //     $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_NO_EXIST);
            // if($img_result == 2)
            //     $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_MANY);
            // if($img_result == 'not_probability')
            //     $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_BLUR);
            // if(!$img_result)
            //     $this->error(API_HTTP_BAD_REQUEST, LOG_ERROR_UPLOAD_FILE_INTERNAL);
            unlink($_FILES['file']['tmp_name']);
            $imgid = $img_result['image_id'];
        }
        if(!$imgid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['meeting']->faceregister($imgid, $fname, $remarks, $uid,$meeting_id);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_REGISTER_FAILED);
        }
        if(isset($result['error_code']) && $result['error_code']=='400291'){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_UPLOAD_FAILED);
        }
        return $result;
    }

    //更新
    function onupdatemember() {
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_member_id = $this->input('meeting_member_id');
        $name = $this->input('name');
        $remark = $this->input('remark');
        $imgid = '';
        $data = $this->input('data');
        if($data){
            $data = json_decode(stripslashes($data) , TRUE);
        }
        //if(!isset($data) || empty($data)){
        //    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        //}

        //图片处理
        if(!empty($_FILES['file'])){
            $img_result = $_ENV['meeting']->face_upload($uid, $_FILES['file']['tmp_name']);
            // if($img_result === 0)
            //     $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_NO_EXIST);
            // if($img_result == 2)
            //     $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_MANY);
            // if($img_result == 'not_probability')
            //     $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_BLUR);
            // if(!$img_result)
            //     $this->error(API_HTTP_BAD_REQUEST, LOG_ERROR_UPLOAD_FILE_INTERNAL);
            unlink($_FILES['file']['tmp_name']);
            $imgid = $img_result['image_id'];
        }
        $result = $_ENV['meeting']->faceupdate($uid , $data, $meeting_member_id, $name, $remark,$imgid);

        if($result === NULL)
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_UPDATE_FAILED);
        }
        if(isset($result['error_code']) && $result['error_code']=='400291'){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_UPLOAD_FAILED);
        }
        return $result;
    }

    function _check_name($name) {
        $name = addslashes(trim(stripslashes($name)));
        if(!$_ENV['meeting']->check_name($name)) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_CHECK_USERNAME_FAILED);
        } 
        // elseif(!$_ENV['user']->check_usernamecensor($name)) {
        //     $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_USERNAME_BADWORD);
        // }

        return 1;
    }
    //用户获取已注册人脸列表web
    function onlistmember() {
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $meeting_id = $this->input('meeting_id');

        if (!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'page';
        }
        $result = $_ENV['meeting']->facelist($uid, $page, $count, $list_type,$meeting_id);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_LIST_FAILED);
        }
        return $result;
    }
    //用户标签删除
    function ondropmember() {
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_member_id_str = $this->input('meeting_member_id');
        if(!$meeting_member_id_str)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $meeting_member_id_arr = explode(',' , $meeting_member_id_str);
        $result = $_ENV['meeting']->face_del($meeting_member_id_arr, $uid);
        //if($result === NULL)
        //    $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        //if(!$result){
        //    $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_DEL_FAILED);
        //}
        return $result;
    }
    //用户签到状态修改
    function onsigninmember() {
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_member_id = $this->input('meeting_member_id');
        $signinstatus = $this->input('signinstatus');
        if(!$meeting_member_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['meeting']->signinmember($uid , $meeting_member_id, $signinstatus);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, SIGNIN_MEMBER_ERROR);
        }
        return $result;
    }

   //会议列表
    function onlist(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $result = $_ENV['meeting']->meetinglist($uid);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, MEETING_LIST_ERROR);
        }
        return $result;
    }

    //添加新的会议
    function onadd(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $title = $this->input('title');
        $intro = $this->input('intro');
        if(!$title){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['meeting']->meetingadd($uid,$title,$intro);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, ADD_MEETING_ERROR);
        }
        return $result;
    }

    //修改会议信息
    function onupdate(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_id = $this->input('meeting_id');
        $title = $this->input('title');
        $intro = $this->input('intro');
        $device_ids = $this->input('deviceid');
        if(!$title){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['meeting']->meetingupdate($uid,$meeting_id,$title,$intro,$device_ids);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, UPDATE_MEETING_ERROR);
        }
        return $result;
    }

    //删除会议信息
    function ondrop(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_id = $this->input('meeting_id');
        if(!$meeting_id){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['meeting']->meetingdrop($uid,$meeting_id);
        return $result;
    }


    // 添加设备到本次会议

    function onadddevice(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $device_ids = $this->input('device_id');
        $meeting_id = $this->input('meeting_id');
        if(!$device_ids || !$meeting_id){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $device_ids_arr = explode(',' , $device_ids);
        $result = $_ENV['meeting']->adddevice($uid,$device_ids_arr,$meeting_id);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, DROP_MEETING_ERROR);
        }
        return $result;
    }

    // 删除设备

    function ondropdevice(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $device_ids = $this->input('device_id');
        $meeting_id = $this->input('meeting_id');
        if(!$device_ids || !$meeting_id){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['meeting']->dropdevice($uid,$device_ids,$meeting_id);
        return $result;
    }

    // 本次会议下使用的设备

    function onlistdevice(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_id = $this->input('meeting_id');
        if(!$meeting_id){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['meeting']->listdevice($uid,$meeting_id);

        foreach ($result['list'] as $k => $val){
            $device = $_ENV['device']->get_device_by_did($val['deviceid']);
            if (!$device)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            $params['auth_type'] = 'token';
            $params['uid'] = $uid;
            $meta_arr = $_ENV['statistics']->meta($device, $params);
            $meta_arr['in_use_device'] = strval($val['in_use_device']);
            $data[] = $meta_arr;
        }
        $result['list'] = $data;
        return $result;
    }

    // 获取本次会议签到人员信息Excle

    function ongetexcel(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_id = $this->input('meeting_id');
        if(!$meeting_id){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['meeting']->getexcel($uid,$meeting_id);
        return $result;
    }

    // 获取app拉取人数和本地上传人数

    function onmembercount(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $meeting_id = $this->input('meeting_id');
        if(!$meeting_id){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $result = $_ENV['meeting']->getmembercount($uid,$meeting_id);
        return $result;
    }
}