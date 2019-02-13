<?php

!defined('IN_API') && exit('Access Denied');

class connectcontrol extends base {

    function __construct() {
        $this->connectcontrol();
    }

    function connectcontrol() {
        parent::__construct();
        $this->load('device');
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
    
    function onchinacache_notify() {
        $this->init_input();
        
        if(empty($this->input))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $data = json_encode($this->input);
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."connect_notify SET connect_type='".API_CHINACACHE_CONNECT_TYPE."', data='$data', dateline='".$this->time."'");
        return array();
    }
    
    function onlingyang_notify() {
        $this->log('lingyang_notify', 'start notify.');
        $raw = file_get_contents("php://input");
        if(!$raw)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
        
        $this->log('lingyang_notify', 'post raw='.$raw);
        
        $input = json_decode($raw, true);
        if(!$input || !$input['event'])
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $client = $this->load_connect(API_LINGYANG_CONNECT_TYPE);
        if(!$client) {
            $this->log('lingyang_notify', 'load connect client failed.');
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        if(!$client->msg_notify($input)) {
            $this->log('lingyang_notify', 'connect client notify failed.');
            $this->error(API_HTTP_BAD_REQUEST, CONNECT_ERROR_MSG_NOTIFY_FAILED);
        }
        
        $this->log('lingyang_notify', 'notify success.');
        
        return array();
    }

    function onqiniu_notify() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $param = stripcslashes(rawurldecode($this->input('param')));
        if($param) {
            $param = json_decode($param, true);
        }
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');

        if(!$deviceid || !$type || !$param || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_OK, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_OK, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_OK, DEVICE_ERROR_NOT_EXIST);

        switch ($type) {
            case 'device_preset':
                $preset = $param['preset'];
                $ret = $_ENV['device']->preset_qiniu_notify($device, $preset);
                if(!$ret) {
                    $this->error(API_HTTP_OK, DEVICE_ERROR_UPLOAD_PRESET_THUMBNAIL_FAILED);
                }
                break;
            
            default:
                $this->error(API_HTTP_OK, API_ERROR_PARAM);
                break;
        }

        return $ret;
    }
    
    function onpartner_liveplay() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $client_id = $this->input('client_id');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$client_id || !$sign || !$expire)
            $this->show_error(API_ERROR_PARAM);
        
        $check = $this->_check_client_sign($sign, $client_id, $expire, $deviceid);
        if(!$check || !$check['partner_type'] || !$check['partner_id'])
            $this->show_error(DEVICE_ERROR_NO_AUTH);
        
        $partner_id = $check['partner_id'];

        $device = $_ENV['device']->get_partner_device_by_did($partner_id, $deviceid);
        if(!$device || !$device['uid']) 
            $this->show_error(DEVICE_ERROR_NOT_EXIST);
        
        $ret = $_ENV['device']->set_device_auth($device);
        if(!$ret)
            $this->show_error(DEVICE_ERROR_NO_AUTH);
        
        $url = API_DEFAULT_REDIRECT.'/profile/svideo/'.$deviceid;
        $this->redirect($url);
    }
    
    function show_error($error, $description="") {
    	//error code处理
    	if($error && $p = strpos($error, ':')) {
    		$error_code = intval(substr($error, 0, $p));
    		$error = substr($error, $p+1);
    	}
        $this->showmessage('error', 'oauth2_error', array('error'=>$error));
    }
    
}
