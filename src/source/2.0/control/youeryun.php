<?php

!defined('IN_API') && exit('Access Denied');

class youeryuncontrol extends base {

    function __construct() {
        $this->youeryuncontrol();
    }

    function youeryuncontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
        $this->load('server');
        $this->load('search');
    }

    // 获取公开摄像头列表
    function onlistshare() {
        $this->init_input();
        $page = intval($this->input['page']);
        $count = intval($this->input['count']);
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        $dids = strval($this->input('deviceid'));
        
        if(!$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if($dids) {
            $dlist = split(",", $dids);
            $dids = "";
            if($dlist && is_array($dlist)) {
                $dids = "'";
                $dids .= join("', '", $dlist);
                $dids .= "'";
            }
        }
        
        if($page < 1) $page = 1;
        if($count < 1) $count = 10;
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $support_type = $_ENV['oauth2']->get_client_connect_support($check['client_id']);
                
        return $_ENV['device']->list_share(0, 0, $support_type, 0, 0, 0, $page, $count, $check['appid'], 1, $dids);
    }

    // 添加幼儿云设备
    function onadddevice() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->addyoueryundevice($deviceid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_PARTNER_ADD_FAILED);
        
        return $result;
    }

    // 删除幼儿云设备
    function ondropdevice() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->dropyoueryundevice($deviceid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_PARTNER_DROP_FAILED);
        
        return $result;
    }

    // 获取设备设置信息
    function onsetting() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !($type === 'cvr') || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->checkyoueryundevice($deviceid)) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->setting($device, $type, '');
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);
        
        return $result;
    }

    // 设置接口
    function onupdatesetting() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$deviceid || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $fileds = stripcslashes(rawurldecode($this->input('fileds')));
        if($fileds) {
            $fileds = json_decode($fileds, true);
        }

        if (!$fileds || !isset($fileds['cvr']))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->checkyoueryundevice($deviceid)) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->updatesetting($device, array('cvr' => $fileds['cvr']));
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        return $result;
    }

    function onplaylist() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);

        if(!$deviceid || !$sign || !$expire || !$starttime || !$endtime || $starttime >= $endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
    
        if(!$_ENV['device']->checkyoueryundevice($deviceid)) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->playlist($device, '', $starttime, $endtime);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_PLAYLIST);
        
        return $result;
    }

    // 开始剪辑
    function onclip() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        
        if(!$deviceid || !$sign || !$expire || !$starttime || !$endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->checkyoueryundevice($deviceid)) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->clip($device, $starttime, $endtime, 'lingyang');
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_CLIP_FAILED);
        
        return $result;
    }

    // 获取剪辑状态
    function oninfoclip() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        $clip_id = intval($this->input['clip_id']);
        
        if(!$deviceid || !$sign || !$expire || !$clip_id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->checkyoueryundevice($deviceid)) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->infoclip($device, $clip_id);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_CLIP_FAILED);
        
        return $result;
    }

    // 列出已经剪辑好的视频
    function onlistclip() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        $page = intval($this->input['page']);
        $count = intval($this->input['count']);
        
        if(!$deviceid || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->checkyoueryundevice($deviceid)) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $result = $_ENV['device']->listdeviceclip($device, $page, $count);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_CLIP_FAILED);
        
        return $result;
    }
}
