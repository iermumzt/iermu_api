<?php

!defined('IN_API') && exit('Access Denied');

class logcontrol extends base {

    function __construct() {
        $this->logcontrol();
    }

    function logcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');  
    }

    function onplayinginfo() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $info = $this->input['info'];

        if (!$deviceid || !$info) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) {
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        }

        //$info_array = json_decode(stripcslashes(rawurldecode($info)), true);
        $info_array = stripcslashes(rawurldecode($info));
        
        SeasLog::setLogger('ops/playing');
        SeasLog::info($deviceid.' | playinginfo | '.$info_array);

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['request_id'] = request_id();
        return $result;
    }

    function onstartplay() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $info = $this->input['info'];

        if (!$deviceid || !$info) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) {
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        }

        $info_array = stripcslashes(rawurldecode($info));
        
        SeasLog::setLogger('ops/playing');
        SeasLog::info($deviceid.' | startplay | '.$info_array);

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['request_id'] = request_id();
        return $result;
    }

    function onadddevice() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $info = $this->input['info'];

        if (!$info) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $info_array = stripcslashes(rawurldecode($info));
        
        SeasLog::setLogger('ops/playing');
        SeasLog::info($deviceid.' | adddevice | '.$info_array);

        $result = array();
        $result['request_id'] = request_id();
        return $result;
    }

    function oncustomize() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $info = $this->input['info'];

        if (!$info) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $info_array = stripcslashes(rawurldecode($info));
        
        SeasLog::setLogger('ops/playing');
        SeasLog::info('customize | '.$info_array);

        $result = array();
        $result['request_id'] = request_id();
        return $result;
    }

    function onupload() {
        $this->error(API_HTTP_FORBIDDEN, LOG_ERROR_SYSTEM_BUSY);
        @header("Expires: 0");
        @header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
        @header("Pragma: no-cache");
        //header("Content-type: application/xml; charset=utf-8");
        /*
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        */
        if(empty($_FILES['file'])) {
            $this->error(API_HTTP_FORBIDDEN, LOG_ERROR_UPLOAD_FILE_NOT_EXIST);
        }

        if ($_FILES['file']['size'] >  1 * 1024 * 1204) {
            @unlink($_FILES['file']['tmp_name']);
            $this->error(API_HTTP_FORBIDDEN, LOG_ERROR_UPLOAD_FILE_TOO_LARGE);
        }

        $filepath = '/home/wwwlogs/ops/upload/'.$_FILES['file']['name'];
        file_exists($filepath) && @unlink($filepath);
        if(@copy($_FILES['file']['tmp_name'], $filepath) || @move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
            @unlink($_FILES['file']['tmp_name']);
        } else {
            @unlink($_FILES['file']['tmp_name']);
            $this->error(API_HTTP_FORBIDDEN, LOG_ERROR_UPLOAD_FILE_INTERNAL);
        }

        $result = array();
        $result['request_id'] = request_id();
        $result['filename'] = $_FILES['file']['name'];
        return $result;
    }

    function onplayerupload() {     
        $this->init_input();
        $info = $this->input['info'];

        if (!$info) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $info_array = stripcslashes(rawurldecode($info));
        
        SeasLog::setLogger('ops/playing');
        SeasLog::debug('playerupload | '.$info_array);

        $result = array();
        $result['request_id'] = request_id();
        return $result;
    }
}
