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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
         $this->init_input();
         $udid = trim(strval($this->input('udid')));
         $pushid = $this->input('pushid');
         
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
         
         $cid = $_ENV['push']->client_register($uid, $udid, $pushid, $config, $_ENV['oauth2']->appid, $_ENV['oauth2']->client_id, $_ENV['oauth2']->access_token);
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
        
        if(!$deviceid || !$time || !$client_id || !$sign || !$param || !$param['storageid'])
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $ret = $_ENV['push']->device_alarm($device, $param, $time, $client_id, $check['appid']);
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, PUSH_ERROR_DEVICE_ALARM_FAILED);
        
        return $ret;
    }
}
