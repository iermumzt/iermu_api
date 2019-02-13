<?php

!defined('IN_API') && exit('Access Denied');

class servercontrol extends base {

    function __construct() {
        $this->servercontrol();
    }

    function servercontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
        $this->load('server');
    }
    
    function onindex() {
        $data = file_get_contents("php://input");
        if($data) {
            $codearr = json_decode($data, true);
            if($codearr['sk'] && $codearr['code']) {
                $server = $_ENV['server']->get_server_by_key($codearr['sk']);
                if($server) {
                    $decode = $this->authcode($codearr['code'], 'DECODE', $server['server_secret']);
                    
                    $this->log('server', 'data='.$decode);
                    
                    if($decode) {
                        $jsonarr = json_decode($decode, true);
                        if($jsonarr && $jsonarr['action']) {
                            $method = $jsonarr['action'];
                            if(method_exists($this, $method)) {
                                foreach($jsonarr as $k=>$v) {
                                    if($v && $k != 'controller' && $k != 'named' && 
                                        $k != 'action' && $k != 'plugin' && $k != 'url') {
                                        $this->input[$k] = get_magic_quotes_gpc()?$v:addslashes($v);
                                    }
                                    if($k == "access_token") {
                                        $_POST['access_token'] = $v;
                                    }
                                }
                                $this->input['_server'] = $server;
                                $data = $this->$method();
                                
                                $this->log('server', 'ret='.json_encode($data));
                                
                                return $data;
                            }
                        }
                    }
                }
            }
        }
        $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    }
    
    function on_connect() {
        $this->init_input();
        $ip = $this->input['ip'];
        $deviceid = $this->input['deviceid'];
        $user = intval($this->input['user']);
        $serverid = $this->input['_server']['serverid'];
        $connectionid = intval($this->input['client_id']);
        
        // 应用connect验证
        if($user) {
            if($this->init_user()) {
                $uid = $this->uid;
                if(!$uid) return 0;
                $device = $_ENV['device']->get_device_by_did($deviceid);
                if(!$device) return 0;
                if($device['uid'] != $uid) return 0;
                return 1;
            }
            return 0;
        }
        
        /*
        if(!$this->init_user()) {
            return -1;
        }
        
        $uid = $this->uid;
        if(!$uid)
            return -1;
        */
        
        if(!$deviceid) 
            return -2;
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            return -2;
        
        /*
        if($device['uid'] != $uid) 
            return -2;
        */
        
        // clean last cvr when reconnect 
        if($device['isalert'] || $device['isrecord']) {
            //$_ENV['device']->clear_cvr($deviceid, $device['lastserverid'], $device['lastconnectionid']);
        }
        
        if($serverid != $device['lastserverid']) {
            $_ENV['device']->update_server($deviceid, $device['lastserverid'], $serverid);
        }
        
        $_ENV['device']->update_connect($deviceid, $ip, $serverid, $connectionid);
        $_ENV['device']->update_status($deviceid, $_ENV['device']->get_status(0));

        return 0;
    }
    
    function on_streamproperty() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $streamid = $this->input['stream'];
        if(!$streamid) $streamid = $this->input['stream'];
        $cvrid = $this->input['cvrid'];
        $storage_starttime = intval($this->input['storage_starttime']);
        $streamproperty = intval($this->input['streamproperty']);
        $serverid = $this->input['_server']['serverid'];
        $serverkey = $this->input['_server']['server_secret'];
        $connectionid = intval($this->input['client_id']);
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $islastconnection = true;
        if($serverid != $device['lastserverid'] || $connectionid != $device['lastconnectionid']) { 
            $islastconnection = false;
        }
        
        if(!$islastconnection) {
            /*
            $this->init_user();

            $uid = $this->uid;

            if(!$uid)
                $this->user_error();
        
            if($device['uid'] != $uid ) 
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            */
        }
        
        $uid = $device['uid'];
        
        $result = array('can_cvr' => false);
        
        $status = $_ENV['device']->get_status($streamproperty);
        
        $this->log('server', 'streamproperty ='.$streamproperty.', status='.json_encode($status).', islastconnection='.$islastconnection);
        
        if($islastconnection) {
            
            $online = $streamproperty>0?1:0;
            $_ENV['device']->server_connect_status($deviceid, $online);
            
            if(($device['status'] != $status['status']) || ($device['isonline'] != $status['isonline']) || ($device['isalert'] != $status['isalert']) ||
            ($device['isrecord'] != $status['isrecord'])) {
                if(!$_ENV['device']->update_status($deviceid, $status))
                    $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_FAILED);
                
                if(!$status['isalert'] && !$status['isrecord']) {
                    // 停止录像
                    if($device['isalert'] || $device['isrecord']) {
                        //if(!$cvrid)
                        //    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
                    
                        $_ENV['device']->stop_cvr($cvrid);
                    }
                } 
                elseif($status['isalert'] || $status['isrecord']) {
                    // 录像权限
                    $can_cvr = false;
                    if($device['cvr_end_time'] && $device['cvr_end_time'] > $this->time) {
                        $can_cvr = true;
                    }
                    
                    // 开始录像
                    if($can_cvr) {
                        // 录像文件表
                        $cvr_tableid = $device['cvr_tableid']?$device['cvr_tableid']:$_ENV['device']->gen_cvr_tableid($deviceid);
                        
                        // 检查云存储状态
                        $cvr_storageid = $_ENV['device']->gen_cvr_storageid($device['appid'], $deviceid, $device['cvr_storageid']);
                        if(!$cvr_storageid) {
                            $can_cvr = false;
                        }
                    }
                    
                    if($can_cvr) {
                        $cvr_storage = $this->get_storage_service($cvr_storageid);
                        
                        // 录像类型：0正常1报警
                        $cvr_type = $status['isalert']?1:0;
                        $cvrid = $_ENV['device']->start_cvr($deviceid, $uid, $cvr_tableid, $cvr_storageid, $cvr_type, $storage_starttime, $serverid, $connectionid);
                        if($cvrid < 1) 
                            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_FAILED);
                        
                        $result['can_cvr'] = true;
                        $result['cvrid'] = "".$cvrid;
                        $result['storageid'] = "".$cvr_storage['storageid'];
                        $result['storage_type'] = $cvr_storage['storage_type'];
                        $result['storage_config'] = $cvr_storage['storage_config'];
                        
                        // disk
                        if($cvr_storage['storage_type'] == "disk" && $this->input['_server']['local_storage']) {
                            $local_storage = unserialize($this->input['_server']['local_storage']);
                            if($local_storage) {
                                $result['storage_config'] = array_merge($result['storage_config'], $local_storage);
                            }
                        }
                    }
                }
            }
        } else {
            // 停止录像
            if(!$status['isalert'] && !$status['isrecord']) {
                //if(!$cvrid)
                //   $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
                $_ENV['device']->stop_cvr($cvrid);
            } 
        }

        $this->log('server', 'result ='.json_encode($result));
        
        return $this->encode($result, $serverkey);
    }
    
    function on_publish() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $streamid = $this->input['stream'];
        
        if(!$deviceid || !$streamid) {
            return -1;
        }
        
        /*
        if(!$this->init_user()) {
            return -1;
        }
        
        $uid = $this->uid;
        if(!$uid)
            return -1;
        */
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            return -1;
        
        $uid = $device['uid'];
        
        //if($device['uid'] != $uid || $streamid != $device['stream_id']) 
        if($device['uid'] != $uid && !$_ENV['device']->check_user_grant($deviceid, $uid))
            return -1;
        
        return 0;
    }
    
    function on_unpublish() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $streamid = $this->input['stream'];
        
        if(!$deviceid || !$streamid) {
            return -1;
        }
        
        /*
        if(!$this->init_user()) {
            return -1;
        }
        
        $uid = $this->uid;
        if(!$uid)
            return -1;
        */
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            return -1;
        
        $uid = $device['uid'];
        
        //if($device['uid'] != $uid || $streamid != $device['stream_id']) 
        if($device['uid'] != $uid) 
            return -1;
        
        return 0;
    }
    
    function on_upload() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $streamid = $this->input['stream'];
        $cvrid = $this->input['cvrid'];
        $sequence_no = intval($this->input['sequence_no']);
        $starttime = intval($this->input['starttime']);
        $endtime = intval($this->input['endtime']);
        $duration = intval($this->input['duration']);
        $pathname = $this->input['pathname'];
        $filename = $this->input['filename'];
        $size = intval($this->input['size']);
        $serverid = $this->input['_server']['serverid'];
        $connectionid = intval($this->input['client_id']);
        $thumbnail = intval($this->input['thumbnail']);
        
        if(!$deviceid || !$streamid || !$cvrid || !$endtime) {
            return -1;
        }
        
        /*
        if(!$this->init_user()) {
            return -1;
        }
        
        $uid = $this->uid;
        if(!$uid)
            return -1;
        */
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            return -1;
        
        $uid = $device['uid'];
        
        if($device['uid'] != $uid || $streamid != $device['stream_id']) 
            return -1;
        
        return $_ENV['device']->upload($deviceid, $uid, $cvrid, $sequence_no, $starttime, $endtime, 
            $duration, $pathname, $filename, $size, $thumbnail);
    }
    
    function on_thumbnail() {
        $deviceid = $this->input['deviceid'];
        $cvrid = $this->input['cvrid'];
        $fileid = $this->input['fileid'];
        $thumbnail_file = $this->input['thumbnail_file'];
        $thumbnail_width = intval($this->input['thumbnail_width']);
        $thumbnail_height = intval($this->input['thumbnail_height']);
        
        if(!$deviceid || !$cvrid || !$fileid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        /*
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        */
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $uid = $device['uid'];
        
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$_ENV['device']->verify_thumbnail($deviceid, $cvrid, $fileid))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_THUMBNAIL_VERIFY_FAILED);
        
        $result = array(
            "deviceid" => $deviceid,
            "cvrid" => $cvrid,
            "fileid" => $fileid,
            "thumbnail_file" => $thumbnail_file,
            "thumbnail_width" => $thumbnail_width,
            "thumbnail_height" => $thumbnail_height
        );
        return $result;
    }
    
    function on_thumbnail_info() {
        if(!$this->_check_task_server())
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $deviceid = $this->input['deviceid'];
        $cvrid = $this->input['cvrid'];
        $fileid = $this->input['fileid'];
        $serverkey = $this->input['_server']['server_secret'];
        
        if(!$deviceid || !$cvrid || !$fileid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $info = $_ENV['device']->get_thumbnail_info($device, $cvrid, $fileid, true);
        if(!$info)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_THUMBNAIL_VERIFY_FAILED);
        
        return $this->encode($info, $serverkey);
    }
    
    function on_thumbnail_upload() {
        if(!$this->_check_task_server())
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $deviceid = $this->input['deviceid'];
        $cvrid = $this->input['cvrid'];
        $fileid = $this->input['fileid'];
        $thumbnail_path = $this->input['thumbnail_path'];
        $thumbnail_file = $this->input['thumbnail_file'];
        $thumbnail_size = intval($this->input['size']);
        
        if(!$deviceid || !$cvrid || !$fileid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if(!$_ENV['device']->thumbnail_upload($deviceid, $cvrid, $fileid, $thumbnail_path, $thumbnail_file, $thumbnail_size))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_THUMBNAIL_VERIFY_FAILED);
        
        $result = array();
        return $result;
    }
    
    function on_get_cron_delete_config() {
        if(!$this->_check_task_server())
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $config = unserialize($this->input['_server']['config']);
        if(!$config || !$config['cron_delete_db_config'])
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_CRON_DELETE_CONFIG_EMPTY);
        
        $storages = array();
        $datas = $this->get_storage_services();
        foreach($datas as $data) {
            $storageid = $data['storageid'];
            $storages[''.$storageid] = array(
                'storage_type' => $data['storage_type'],
                'storage_config' => unserialize($data['storage_config'])
            );
        }
        
        if(!$storages)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_CRON_DELETE_CONFIG_EMPTY);
        
        $result = array();
        $result['db_config'] = $config['cron_delete_db_config'];
        $result['storages'] = $storages;
        
        $serverkey = $this->input['_server']['server_secret'];
        return $this->encode($result, $serverkey);
    }
    
    function _check_task_server() {
        if(!$this->input['_server'] || $this->input['_server']['server_type'] != 1)
            return false;
        return true;
    }
    
    function on_play() {
        $this->init_input();
        $ip = $this->input['ip'];
        $streamid = $this->input['stream'];
        $param = $this->input['param'];
        
        if(!$streamid || !$param) 
            return -1;
        
        $params = $this->_param($param);
        if(!$params) 
            return -1;
        
        $deviceid = $params['deviceid'];
        $sign = $params['sign'];
        $expire = intval($params['expire']);
        
        if(!$deviceid || !$sign || !$expire)
            return -1;
        
        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check) {
            return -1;
        }
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            return -1;
        
        if($device['stream_id'] != $streamid || $device['appid'] != $check['appid']) 
            return -1;
        
        $_ENV['device']->update_play($deviceid, $ip);

        return 0;
    }
    
    function on_stop() {
        $this->init_input();
        $ip = $this->input['ip'];
        $streamid = $this->input['stream'];
        $param = $this->input['param'];
        
        if(!$streamid || !$param) 
            return -1;
        
        $params = $this->_param($param);
        if(!$params) 
            return -1;
        
        $deviceid = $params['deviceid'];
        if(!$deviceid)
            return -1;
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            return -1;
        
        if($device['stream_id'] != $streamid) 
            return -1;
        
        $_ENV['device']->update_stop($device);

        return 0;
    }
    
    function encode($data, $serverkey) {
        if(!$data || !$serverkey) return '';
        $json = json_encode($data);
        $code = $this->authcode($json, 'ENCODE', $serverkey, 600);
        return $code?$code:'';
    }
    
    function _param($param) {
        if(!$param) return array();
        if(substr($param, 0, 1) == '?') {
            $param = substr($param, 1);
        }
        $result = array();
        $params = explode('&', $param);
        if($params && is_array($params)) {
            foreach($params as $data) {
                $datas = explode('=', $data);
                if($datas && is_array($datas)) {
                    $key = trim($datas[0]);
                    $value = trim($datas[1]);
                    if($key && $value) {
                        $result[$key] = $value;
                    }
                }
            }
        }
        return $result;
    }

}
