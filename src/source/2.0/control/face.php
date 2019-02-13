<?php

!defined('IN_API') && exit('Access Denied');

class facecontrol extends base {

    function __construct() {
        $this->facecontrol();
    }

    function facecontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('aiface');
        $this->load('device');
        $this->load('oauth2');
    }
    function onupload() {
        $this->init_user();
        $uid = $this->uid;
        return true;
        // $uid = 00001;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $groupid = $this->input('groupid') ? $this->input('groupid') : 1;
        $user = $_ENV['user']->get_user_by_uid($uid);
        if(!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
        if(empty($_FILES['file']))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['aiface']->face_upload($uid, $_FILES['file']['tmp_name']);
        unlink($_FILES['file']['tmp_name']);
        return $result;
    }
    //注册
    function onregister() {
        $this->init_input();
        $fname = $this->input('name');
        $remarks = $this->input('remark');
        $image_id = $this->input('image_id');

        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $user = $_ENV['user']->get_user_by_uid($uid);
        if(!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
        if(empty($_FILES['file']) || !$fname)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        if(!is_array($_FILES['file']['tmp_name']) || count($_FILES['file']['tmp_name']) != 2)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        // foreach($_FILES['file'] as $k=>$v){
        //     foreach($v as $kk=>$vv){
        //         if($k==$kk){
        //             $arr[$kk][$k] = $vv;
        //         };
        //     }
        // }
        
        //检测姓名与备注有效性
        $this->_check_name($fname);
        if($remarks)
            $this->_check_name($remarks);

        $imgid_black_withe = '';
        //图片处理
        if(!empty($_FILES['file'])){
            $img_result = $_ENV['aiface']->face_upload($uid, $_FILES['file']['tmp_name'][0]);
            $img_black_withe = $_ENV['aiface']->face_upload($uid, $_FILES['file']['tmp_name'][1], '_black');
            if(!$img_result)
                $this->error(API_HTTP_BAD_REQUEST, LOG_ERROR_UPLOAD_FILE_INTERNAL);
            
            $imgid = $img_result['image_id'];
            $imgid_black_withe = $img_black_withe['image_id'];
        }

        if(!$imgid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['aiface']->faceregister($imgid, $fname, $remarks, $uid, $imgid_black_withe, $img_result);
        if(!empty($_FILES['file'])){
            unlink($_FILES['file']['tmp_name'][0]);
            unlink($_FILES['file']['tmp_name'][1]);
        }
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_REGISTER_FAILED);
        }
        if(isset($result['error_code']) && $result['error_code']=='400291'){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_UPLOAD_FAILED);
        }
        //event处理
        // if($image_id && $result['face_id']){
        //     $_ENV['aiface']->event_update2($result['face_id'], $image_id);
        // }
        return $result;

    }

    //更新
    function onupdate() {
        $this->init_input();
        
        $fid = $this->input('face_id');
        $fname = $this->input('name');
        $remarks = $this->input('remark');
        $event_push = $this->input('event_push');
        $imgid = '';
        $access_token = $this->input('access_token');

        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        if(!$fid){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        if (isset($event_push) && $event_push != ''){
            $result = $_ENV['aiface']->event_push($uid , $event_push,$fid);
            if(!$result){
                $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_EVENT_PUSH_FAILED);
            }
            return $result;
        }
        if(!isset($fname) && !isset($remarks) && empty($_FILES['file']) )
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $user = $_ENV['user']->get_user_by_uid($uid);
        if(!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);

        //检测姓名与备注有效性
        if($fname)
            $this->_check_name($fname);
        if($remarks)
            $this->_check_name($remarks);

        $imgid_black_withe = '';
        //图片处理
        if(!empty($_FILES['file'])){
            if(!is_array($_FILES['file']['tmp_name']) || count($_FILES['file']['tmp_name']) != 2)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            $img_result = $_ENV['aiface']->face_upload($uid, $_FILES['file']['tmp_name'][0]);
            $img_black_withe = $_ENV['aiface']->face_upload($uid, $_FILES['file']['tmp_name'][1], '_black');
            if(!$img_result)
                $this->error(API_HTTP_BAD_REQUEST, LOG_ERROR_UPLOAD_FILE_INTERNAL);
            
            $imgid = $img_result['image_id'];
            $imgid_black_withe = $img_black_withe['image_id'];
        }

        $result = $_ENV['aiface']->faceupdate($fid, $imgid, $fname, $remarks, $uid, $imgid_black_withe, $img_result);
        if(!empty($_FILES['file'])){
            unlink($_FILES['file']['tmp_name'][0]);
            unlink($_FILES['file']['tmp_name'][1]);
        }
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
        if(!$_ENV['aiface']->check_name($name)) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_CHECK_USERNAME_FAILED);
        } 
        // elseif(!$_ENV['user']->check_usernamecensor($name)) {
        //     $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_USERNAME_BADWORD);
        // }

        return 1;
    }
    //用户获取已注册人脸列表
    function onlist() {
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $list_type = $this->input('list_type');
        $keyword = strval($this->input('keyword'));
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $event_push = intval($this->input('event_push'));
        $cluster = $this->input('cluster')?$this->input('cluster'):0;
        $deviceid = $this->input('deviceid');//优信设备id筛选
        $is_strange = $this->input('is_strange')?$this->input('is_strange'):0;
        if($is_strange){
            $cluster = $is_strange;
        }

        $st = $this->input('st');
        $et = $this->input('et');

        if (!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'page';
        }

        $ai_id = $this->ai_id();
        $result = $_ENV['aiface']->facelist($ai_id, $uid, $keyword, $page, $count, $list_type, $event_push, $cluster, $st, $et, $deviceid);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_LIST_FAILED);
        }
        return $result;
    }
    //单个人脸信息获取
    function oninfo(){
        $this->init_input();
        $face_id = $this->input('face_id');
        if(!$face_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['aiface']->faceinfo_by_id($face_id);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_INFO_FAILED);
        return $result;
    }
    //用户标签删除
    function ondrop() {
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $face_id = strval($this->input('face_id'));
        if(!$face_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['aiface']->face_del($face_id, $uid);
        if($result === NULL)
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_DEL_FAILED);
        }
        return $result;
    }

    //用户获取已注册人脸姓名列表
    function onnamelist() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $result = $_ENV['aiface']->facenamelist($uid);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_LIST_FAILED);
        }
        return $result;
    }

    //聚类统计
    function oncount(){
        $this->init_input('G');
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $cluster = intval($this->input('cluster'));
        $is_strange = $this->input('is_strange');
        if($is_strange){
            $cluster = $is_strange;
        }
        $day = intval($this->input('day'));
        $st = strval($this->input('st'));
        $et = strval($this->input('et'));
        $deviceid = strval($this->input('deviceid'));
        if((!$day && !$st && !$et) || $st>$et)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['aiface']->facecount($uid, $cluster, $day, $st, $et, $deviceid);
        if(!$result)
             $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_COUNT_FAILED);
         return $result;
    }
    //聚类合并
    function onlistmerge(){
        $this->init_input('G');
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $face_id = $this->input('face_id');
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $result = $_ENV['aiface']->mergelist($uid, $face_id, $page, $count, $list_type);
        // if($result == NULL)
        //     $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_MERGE_LIST_FAILED);
        }
        return $result;
    }
    function onmerge(){
        $this->init_input('P');
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $face_id = $this->input['face_id'];
        $face_arr = explode(',', $face_id);
        $face_arr = array_filter($face_arr);
        if(!$face_id || count($face_arr)<2)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        if(count($face_arr)>20)
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_BJECT_UPPER_LIMIT);
        $result = $_ENV['aiface']->mergeupdate($uid, $face_arr);
        if($result == NULL){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        }
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_MERGE_UPDATE_FAILED);
        }
        return $result;
    }
    function oncancelmerge(){
        $this->init_input('P');
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $face_id = $this->input['face_id'];
        if(!$face_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['aiface']->mergecancel($uid, $face_id);
        if($result == NULL){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        }
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_MERGE_CANCLE_FAILED);
        }
        return $result;
    }

    function ongetimgurl(){
        $this->init_input();
        $imgid = $this->input['imgid'];
        return $_ENV['aiface']->get_imagepath_by_imageid($imgid);
    }


    //shanchu
    // function onface_del_temp(){
    //     $result = $_ENV['aiface']->face_del_temp();
    //     return $result;
    // }

}