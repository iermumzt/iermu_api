<?php

!defined('IN_API') && exit('Access Denied');

class partnercontrol extends base {

    function __construct() {
        $this->partnercontrol();
    }

    function partnercontrol() {
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
    
    function ondevice_updatelist() {
        $this->init_input();
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_partner_sign($sign, $expire, '');
        if(!$check)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        $partner_id = $check['partner_id'];
        $support_type = split(',', $check['connect_support']);
        
        $ret = $_ENV['device']->partner_update_devicelist($partner_id, $support_type, $appid);
        if(!$ret)
            $this->error(API_HTTP_BAD_REQUEST, PARTNER_ERROR_UPDATE_DEVICELIST_FAILED);
        
        return array();
    }
    
    function ondevice_list() {
        $this->init_input();
        $page = intval($this->input['page']);
        $count = intval($this->input['count']);
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_partner_sign($sign, $expire, '');
        if(!$check)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        $partner_id = $check['partner_id'];
        $support_type = split(',', $check['connect_support']);
        
        if($page < 1) $page = 1;
        if($count < 1) $count = 10;

        $ret = $_ENV['device']->partner_listdevice($partner_id, $page, $count);
        if(!$ret) 
            $this->error(API_HTTP_BAD_REQUEST, PARTNER_ERROR_LISTDEVICE_FAILED);
        
        return $ret;
    }
    
    function ondevice_meta() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $param = stripcslashes(rawurldecode($this->input('param')));
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_partner_sign($sign, $expire, $deviceid.$param);
        if(!$check)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        $partner_id = $check['partner_id'];
        
        if($param) {
            $param = json_decode($param, true);
            if(!$param || !is_array($param) || !$param['list'])
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $list = $param['list'];
            if(!is_array($list))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $device_list = array();
            
            foreach($list as $did) {
                $check = $_ENV['device']->check_partner_device($partner_id, $did);
                if(!$check)
                    $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
                $device = $_ENV['device']->get_device_by_did($did);
                if(!$device) 
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
                $params = array();
                $params['auth_type'] = 'token';
                $params['uid'] = $device['uid'];
    
                $ret = $_ENV['device']->meta($device, $params);
                if(!$ret) 
                    $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_EXIST);
                
                $device_list[] = $ret;
            }
            
            $result = array();
            $result['count'] = count($device_list);
            $result['list'] = $device_list;
        } else {
            if(!$deviceid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
                
            $check = $_ENV['device']->check_partner_device($partner_id, $deviceid);
            if(!$check)
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
            $params = array();
            $params['auth_type'] = 'token';
            $params['uid'] = $device['uid'];
    
            $result = $_ENV['device']->meta($device, $params);
            if(!$result) 
                $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_EXIST);
        }
        
        return $result;
    }
    
    function ondevice_drop() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_partner_sign($sign, $expire, $deviceid);
        if(!$check)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        $partner_id = $check['partner_id'];
            
        $check = $_ENV['device']->check_partner_device($partner_id, $deviceid);
        if(!$check)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != 0 && !$_ENV['device']->drop($device)) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_FAILED);
        
        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }
    
    function ondevice_updatesetting() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $fileds = stripcslashes(rawurldecode($this->input('fileds')));
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$fileds || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_partner_sign($sign, $expire, $deviceid.$fileds);
        if(!$check)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
        if($fileds) {
            $fileds = json_decode($fileds, true);
        }

        if(!$fileds || !isset($fileds['power']))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $appid = $check['appid'];
        $partner_id = $check['partner_id'];
            
        $check = $_ENV['device']->check_partner_device($partner_id, $deviceid);
        if(!$check)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $result = $_ENV['device']->updatesetting($device, array('power' => $fileds['power']));
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        return $result;
    }
    
    function ondevice_liveview() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $controls = stripcslashes(rawurldecode($this->input('controls')));
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$sign || !$expire)
            $this->show_error(API_ERROR_PARAM);

        $cstr = $deviceid;
        if($controls) $cstr .= $controls;
        
        $check = $this->_check_partner_sign($sign, $expire, $cstr);
        if(!$check)
            $this->show_error(DEVICE_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        $partner_id = $check['partner_id'];

        $device = $_ENV['device']->get_partner_device_by_did($partner_id, $deviceid);
        if(!$device || !$device['uid']) 
            $this->show_error(DEVICE_ERROR_NOT_EXIST);
        
        $ret = $_ENV['device']->set_device_auth($device, $controls);
        if(!$ret)
            $this->show_error(DEVICE_ERROR_NO_AUTH);
        
        $url = API_DEFAULT_REDIRECT.'/profile/svideo/'.$deviceid;
        
        header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
        $this->redirect($url);
    }
    
    function ondevice_liveshare() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $share_type = intval($this->input['share']);
        $title = strval($this->input['title']);
        $intro = strval($this->input['intro']);
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$share_type || !$sign || !$expire)
            $this->show_error(API_ERROR_PARAM);
        
        if(!in_array($share_type, array(2,4)))
            $this->show_error(API_ERROR_PARAM);
        
        $check = $this->_check_partner_sign($sign, $expire, $deviceid.$share_type);
        if(!$check)
            $this->show_error(DEVICE_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        $client_id = $check['client_id'];
        $partner_id = $check['partner_id'];

        $device = $_ENV['device']->get_partner_device_by_did($partner_id, $deviceid);
        if(!$device || !$device['uid']) 
            $this->show_error(DEVICE_ERROR_NOT_EXIST);
        
        $share = $_ENV['device']->get_partner_share($device, $share_type, $title, $intro, $appid, $client_id);
        if (!$share)
            $this->show_error(API_ERROR_NETWORK);
        
        $shareid = $share['shareid'];
        $uk = $share['connect_uid'];
        
        $url = API_DEFAULT_REDIRECT.'/svideo/'.$shareid.'/'.$uk;
        
        header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
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
