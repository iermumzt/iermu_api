<?php

!defined('IN_API') && exit('Access Denied');

class pushcontrol extends base {

    function __construct() {
        $this->pushcontrol();
    }

    function pushcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
        $this->load('server');
        $this->load('push');
    }
    
    function onindex() {
        $this->init_input();
        $model = $this->input('model');
        $method = $this->input('method');
        $action = 'on'.$model.'_'.$method;
        if($model && $method && method_exists($this, $action)) {
            unset($this->input['model']);
            unset($this->input['method']);
            return $this->$action();
        }
        $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    }
    
    function onclient_register() {
        $_ENV['oauth2']->verifyAccessToken('', FALSE, FALSE, FALSE, FALSE);

        $uid = $_ENV['oauth2']->uid;
        if(!$uid)
            $this->user_error();
        
         $this->init_input();
         $udid = trim(strval($this->input('udid')));
         $pushid = $this->input('pushid');
         $connect_type = $this->input('connect_type');
         if($connect_type === NULL) {
             $connect_type = -1;
         }
         $active = $this->input('active');
         if($active === NULL) {
             $active = 1;
         } else {
             $active = $active?1:0;
         }

         // 推送版本号
         $push_version = $this->input('push_version');
         if($push_version === NULL || !in_array($push_version, array(1,2))) {
             $push_version = 1;
         } else {
             $push_version = intval($push_version);
         }
         
         $config = stripcslashes(rawurldecode($this->input('config')));
         if($config) {
             $config = json_decode($config, true);
         }
        
         if(!$udid || !$pushid || !$config) 
             $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
         
         if(strlen($udid) > 30)
             $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
         
         // check push service
         $push_service = $_ENV['push']->get_push_service_by_pushid($pushid);
         if(!$push_service || $push_service['status'] <= 0)
             $this->error(API_HTTP_BAD_REQUEST, PUSH_ERROR_SERVICE_UNAVAILABLE);
         
         if($push_service['push_type'] == 'baidu' && (!$config['user_id'] || !$config['channel_id'])) {
            $this->error(API_HTTP_BAD_REQUEST, PUSH_ERROR_CLIENT_CONFIG);
         } else if($push_service['push_type'] == 'getui' && !$config['client_id']) {
            $this->error(API_HTTP_BAD_REQUEST, PUSH_ERROR_CLIENT_CONFIG);
         }
         
         $cid = $_ENV['push']->client_register($uid, $udid, $pushid, $config, $connect_type, $active, $push_version, $_ENV['oauth2']->appid, $_ENV['oauth2']->client_id, $_ENV['oauth2']->access_token);
         if(!$cid) 
             $this->error(API_HTTP_INTERNAL_SERVER_ERROR, PUSH_ERROR_CLIENT_REGISTER_FAILED);
         
         return array('cid' => $cid);
    }
    
    function ondevice_alarm() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $param = stripcslashes(rawurldecode($this->input('param')));
        if($param) {
            $param = json_decode($param, true);
        }
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if(!$deviceid || !$time || !$client_id || !$sign || !$param)
            $this->error(API_HTTP_OK, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_OK, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_OK, DEVICE_ERROR_NOT_EXIST);
        
        if(!$device['uid']) 
            $this->error(API_HTTP_OK, DEVICE_ERROR_NO_AUTH);
        
        // 时区处理
        $utc = intval($this->input('utc'));
        if(!$utc) {
            $tz_rule = $this->get_timezone_rule_from_timezone_id($device['timezone'], true);
            $time -= $tz_rule['offset'];
        }
        
        // 时间判断
        if($time > $this->time + 6*3600 || $time < $this->time - 6*3600)
            $this->error(API_HTTP_OK, API_ERROR_PARAM);

        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $ret = $_ENV['push']->device_alarm($device, $param, $time, $client_id, $check['appid']);
        if(!$ret)
            $this->error(API_HTTP_OK, PUSH_ERROR_DEVICE_ALARM_FAILED);
        
        if($ret['no_storage'])
            $this->error(API_HTTP_OK, PUSH_ERROR_DEVICE_ALARM_NO_STORAGE);
        
        if($ret['file_exist'])
            $this->error(API_HTTP_OK, PUSH_ERROR_DEVICE_ALARM_FILE_EXIST);
        
        return $ret;
    }

}
