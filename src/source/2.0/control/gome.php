<?php

!defined('IN_API') && exit('Access Denied');

class gomecontrol extends base {
    
    var $partner_id = '';
    var $client_id = '';
    var $client_secret = '';
    var $connect_type = 0;
    var $appid = 0;

    function __construct() {
        $this->gomecontrol();
    }

    function gomecontrol() {
        parent::__construct();
        $this->load('oauth2');
        $this->load('device');
        $this->load('user');
        
        // 参数处理
        $client_id = $_GET['thirdCloudId'];
        $client = $_ENV['oauth2']->get_client_by_client_id($client_id);
        if(!$client || !$client['partner_type'] || !$client['partner_id'])
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_NO_AUTH);
        
        // 合作伙伴校验
        $partner_id = $client['partner_id'];
        $partner = $this->get_partner_by_partner_id($partner_id);
        
        if(!$partner || !$partner['connect_type'] || $partner['connect_type'] != API_GOME_CONNECT_TYPE)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_NO_AUTH);
        
        $timestamp = $_GET['timestamp'];
        $nonce = $_GET['nonce'];
        $sign = $_GET['sign'];
        
        $uri = $_GET['url'];
        $uri = substr($uri, strpos($uri, 'gome')+4);
        if(!$timestamp || !$nonce || !$sign || !$uri)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_NO_AUTH);
        
        $raw = file_get_contents("php://input");
        
        $data = $uri.$timestamp.$nonce.$client['client_secret'].$raw;
        $check = hash('sha256', $data);
        
        // 签名
        if($check != $sign)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_NO_AUTH);
        
        // 参数解密
        if($raw) {
            $input = json_decode($raw, true);
            if($input) {
                // 加密算法为AES128（AES/ECB/PKCS5Padding），加密密钥为dataKey。dataKey的生成规则：将
                // thirdCloudKey进行MD5（32位），并将其结果转化为16进制的小写字符串，取前16字节字符串作为AES128加密密钥dataKey。
                require_once API_SOURCE_ROOT.'lib/cryptaes.php';
                $aes = new CryptAES();
                $key = substr(md5($client['client_secret']), 0, 16);
                $aes->set_key($key);
                $aes->require_pkcs5();
                
                foreach($input as $k=>$v) {
                    if(in_array($k, array('thirdUId', 'accessToken', 'deviceId'))) {
                        $v = $aes->decrypt($v);
                    }
                    $this->input[$k] = $v;
                }
            }
        }
        
        $this->partner_id = $partner_id;
        $this->client_id = $client_id;
        $this->client_secret = $client['client_secret'];
        $this->connect_type = $partner['connect_type'];
        $this->appid = $client['appid'];
        
        // token处理
        $access_token = $this->input('accessToken');
        if($access_token) $_POST[OAUTH2_TOKEN_PARAM_NAME] = $access_token;
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
        $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    }
    
    function onuser_token() {
        $connect_uid = $this->input('thirdUId');
        if(!$connect_uid)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $partner_id = $this->partner_id;
        $connect_type = $this->connect_type;
        
        $user = $_ENV['user']->get_user_by_connect_uid($connect_type, $connect_uid);
        if(!$user) {
            $username = $this->gen_connect_username($connect_type, $connect_uid);
            if(!$username)
                $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_USER_USERNAME_EXISTS);
        
            $uid = $_ENV['user']->add_user($username, '', '');
            if($uid) {
                // add connect
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect SET uid='$uid', connect_type='$connect_type', connect_uid='".$connect_uid."', username='".$connect_uid."', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='1'");
                
                // add partner
                $p = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_partner WHERE partner_id='$partner_id' AND uid='$uid'");
                if(!$p) {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_partner SET partner_id='$partner_id', uid='$uid'");
                }
            }
        } else {
            $uid = $user['uid'];
        }
        
        if(!$uid)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);
        
        $_ENV['oauth2']->uid = $uid;
        $_ENV['oauth2']->appid = $this->appid;
        $_ENV['oauth2']->client_id = $this->client_id;
        
        $token = $_ENV['oauth2']->createAccessToken($this->client_id, '');
        
        // connect
        $connects = array();
        $types = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        foreach($types as $connect_type) {
            $connect = $this->load_connect($connect_type);
            if($connect && $connect->_token_extras()) {
                $extras = $connect->get_connect_info($uid);
                if($extras && is_array($extras)) {
                    $connects[] = $extras;
                } else {
                    //$this->_error(OAUTH2_HTTP_BAD_REQUEST, API_ERROR_USER_GET_CONNECT_FAILED);
                    $connects[] = array('connect_type' => $connect_type, 'status' => 0);
                }
            }
        }
        
        $result = array(
            'userId' => strval($uid),
            'accessToken' => $token['access_token'],
            'expireTime' => $token['expires_in'],
            'connect' => $connects
        );
        
        return $this->_success($result);
    }
    
    function ondevice_bind() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
       
        $isowner = $this->input('isOwner');
        if($isowner) {
            // 设备注册流程
            $desc = addslashes(urldecode(trim($this->input['desc'])));
            if($desc === '') {
                $desc = $deviceid;
            }
            
            $timezone = '';
            if($this->input('timezone') !== NULL) {
                $timezone = $this->input['timezone'];
                if(!$this->is_support_timezone($timezone)) {
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TIMEZONE_FORMAT_ILLEGAL);
                }
            }
        
            $param = stripcslashes(rawurldecode($this->input('location')));
            if ($param && !is_array($param = json_decode($param, true)))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);
        
            $location = $param ? 1 : 0;
            if($location) {
                $location_type = strval($param['type']); // 使用地图 amap
                $location_name = strval($param['name']); // POI位置
                $location_address = strval($param['address']); // POI位置
                $location_latitude = floatval($param['latitude']); // 纬度
                $location_longitude = floatval($param['longitude']); // 经度

                if(!$location_type || !$location_latitude ||!$location_longitude ||!$location_name) {
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);
                }
            }
            
            /*
            $connect_type = $this->input['connect_type'];
            if($connect_type === NULL) {
                $connect_type = $_ENV['device']->check_device_connect_type($deviceid);
            }
            $connect_type = intval($connect_type);
            */
            
            $connect_type = API_LINGYANG_CONNECT_TYPE;
        
            if($connect_type > 0 && !$_ENV['user']->get_connect_by_uid($connect_type, $uid))
                $this->_error(API_HTTP_FORBIDDEN, CONNECT_ERROR_USER_NOT_EXIST, NULL, NULL, NULL, array('connect_type' => $connect_type));
        
            $device = $_ENV['device']->get_device_by_did($deviceid);
        
            // 已注册设备
            $need_register = true;
            if($device && $device['uid'] != 0) {
                $extras = array();
            
                // 切换平台
                if($device['connect_type'] != $connect_type) {
                    $extras['connect_type'] = $device['connect_type'];
                    $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_CONNECT_TYPE_REG, NULL, NULL, NULL, $extras);
                }
            
                // 切换用户
                if($device['uid'] != $uid) {
                    $connect = $_ENV['user']->get_connect_by_uid($connect_type, $device['uid']);
                    if($connect && $connect['username']) {
                        $extras['username'] = $connect['username'];
                    } 
                    $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ALREADY_REG, NULL, NULL, NULL, $extras);
                } else {
                    $need_register = false;
                }
                
                // 未指定时区使用设备原有时区
                if($timezone == '') {
                   $timezone = $device['timezone']; 
                }
            }
            
            // 设备默认时区为北京时间
            if($timezone == '') {
                $timezone = 'Asia/Shanghai';
            } else {
                // 非羚羊设备注册时不能调整时区
                if($connect_type != API_LINGYANG_CONNECT_TYPE) {
                    if($device && $device['timezone']) {
                        $timezone = $device['timezone']; 
                    } else {
                        $timezone = 'Asia/Shanghai'; 
                    }
                }
            }
            
            if($need_register) {
                $device = $_ENV['device']->device_register($uid, $deviceid, $connect_type, $desc, $this->appid, $this->client_id, $timezone, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude);
                if(!$device)
                    $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_FAILED);
        
                // init
                $_ENV['device']->device_init($device);
        
                // status
                $_ENV['device']->update_status($deviceid, $_ENV['device']->get_status(-1));
        
                // partner
                $partner_id = $this->db->result_first("SELECT partner_id FROM ".API_DBTABLEPRE."member_partner WHERE uid='$uid'");
                if($partner_id) {
                    $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id' AND deviceid='$deviceid'");
                    if(!$check) {
                        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_partner SET partner_id='$partner_id', deviceid='$deviceid'");
                    }
                }
            }
        
            $result = array();
            $result['deviceid'] = $device['deviceid'];
            $result['stream_id'] = $device['stream_id'];
            $result['connect_type'] = $device['connect_type'];
            $result['connect_cid'] = $device['connect_cid'];
        } else {
            // 设备绑定流程
            if(!$deviceid) 
                $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
            if($device['uid'] == $uid || $device['appid'] != $this->appid) 
                $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            $user = $_ENV['user']->get_user_by_uid($uid);
            if(!$user)
                $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
            if(!$_ENV['device']->check_grant($device, $device['appid']))
                $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_LIMIT);

            if($device['connect_type'] >0 && !$_ENV['user']->get_connect_by_uid($device['connect_type'], $uid))
                $this->_error(API_HTTP_FORBIDDEN, CONNECT_ERROR_USER_NOT_EXIST, NULL, NULL, NULL, array('connect_type' => $device['connect_type']));

            $result = $_ENV['device']->grant(array($device), $user, $auth_code, $code, $this->appid, $this->client_id);
            if(!$result)
                $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_FAILED);
        }
        
        return $this->_success();
    }
    
    function ondevice_unbind() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
    
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $isowner = $this->input('isOwner');
        if($isowner) {
            // 注销流程
            if($device['uid'] != $uid) 
                $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
            if($device['uid'] != 0 && !$_ENV['device']->drop($device)) 
                $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_FAILED);
        } else {
            // 取消授权流程
            if($device['appid'] != $this->appid) 
                $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
            if(!$_ENV['device']->dropgrantuser($device, $uid)) {
                $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_DELETE_FAILED);
            }
        }
        
        return $this->_success();
    }
    
    function ondevice_online() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        $params['auth_type'] = 'token';
        $params['uid'] = $uid;
    
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $result = $_ENV['device']->meta($device, $params);
        if(!$result) 
            $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_EXIST);
        
        $result = array('online' => ($result['status']&4 == 4)?1:0);
        return $this->_success($result);
    }
    
    function ondevice_status() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $type = $this->input('type');
        $fileds = $this->input('fileds');
     
        if(!$type || !in_array($type, array('info', 'status', 'power', 'email', 'cvr', 'alarm', 'capsule', 'storage', 'plat', 'bit', 'bw', 'night', 'init'))) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $result = $_ENV['device']->setting($device, $type, $fileds);
        if(!$result)
            $this->_error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);
        
        return $this->_success($result);
    }
    
    function ondevice_control() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $deviceid = $this->input('deviceId');
        $fileds =  $this->input('command');
        
        $this->log('update device setting '.$deviceid, json_encode($this->input));
     
        if(!$deviceid || !$fileds) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        // check push client
        if($fileds['alarm_push'] !== NULL) {
            if(!$_ENV['device']->check_partner_push($deviceid) && !$_ENV['device']->check_push_client()) {
                $this->_error(API_HTTP_BAD_REQUEST, PUSH_ERROR_CLIENT_UNREGISTER);
            }
        }
        
        $paltform = $_ENV['oauth2']->get_client_platform($this->client_id);
        
        $result = $_ENV['device']->updatesetting($device, $fileds, $paltform);
        if(!$result) 
            $this->_error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        return $this->_success($result);
    }
    
    function ondevice_live() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $params = array();
        $params['auth_type'] = 'token';
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $type = $this->input('type');
        if(!$type || !in_array($type, array('p2p', 'hls'))) {
            $type = 'rtmp';
        }
        
        $result = $_ENV['device']->liveplay($device, $type, $params, $this->client_id?$this->client_id:$device['client_id']);
        if(!$result) 
            $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_LIVE_URL_FAILED);
        
        return $this->_success($result);
    }
    
    function ondevice_playlist() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $starttime = intval($this->input['startTime']);
        $endtime = intval($this->input['endTime']);
        $type = $this->input['type'];
        
        if(!$starttime || !$endtime || $endtime <= $starttime)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        $params['auth_type'] = 'token';
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $result = $_ENV['device']->playlist($device, $params, $starttime, $endtime, $type);
        if(!$result) 
            $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_PLAYLIST);
        
        return $this->_success($result);
    }
    
    function ondevice_vod() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $starttime = intval($this->input['startTime']);
        $endtime = intval($this->input['endTime']);
        
        if(!$starttime || !$endtime || $endtime <= $starttime)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        $params['auth_type'] = 'token';
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $result = $_ENV['device']->vod($device, $params, $starttime, $endtime);
        if(!$result) 
            $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_PLAYLIST);
        
        return $this->_success($result);
    }
    
    function ondevice_vodseek() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $time = intval($this->input['time']);   
        if(!$time)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        $params['auth_type'] = 'token';
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $result = $_ENV['device']->vodseek($device, $params, $time);
        if(!$result) 
            $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_TS);
        
        return $this->_success($result);
    }
    
    function ondevice_move() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $params = array();
        $params['auth_type'] = 'token';
        
        $deviceid = $this->input('deviceId');
        $command = $this->input('command');
        if(!$deviceid || !$command) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        foreach($command as $k=>$v) {
            $this->input[$k] = $v;
        }
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $delay = $this->input('delay');
        if (!($delay === NULL || $delay === '')) {
            $delay = floor(intval($delay) / 100);
            if($delay < 1 || $delay > 255)
                $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            if(!$_ENV['device']->move_delay($device, $delay)) 
                $this->_error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_SET_DELAY_FAILED);
        }

        $preset = $this->input('preset');
        if($preset === NULL || $preset === '') {
            $x1 = intval($this->input('x1'));
            $y1 = intval($this->input('y1'));
            $x2 = intval($this->input('x2'));
            $y2 = intval($this->input('y2'));

            if($x1 === 0 && $y1 === 0 && $x2 === 0 && $y2 === 0) {
                $direction = $this->input('direction');
                $step = intval($this->input('step'));

                if (!in_array($direction, array('up', 'down', 'left', 'right', 'leftup', 'rightup', 'leftdown', 'rightdown')) || $step < 1 || $step > 255)
                    $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

                $ret = $_ENV['device']->move_by_direction($device, $direction, $step);
                if(!$ret) 
                    $this->_error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_MOVE_DIRECTION_FAILED);
            } else {
               $ret = $_ENV['device']->move_by_point($device, $x1, $y1, $x2, $y2);
                if(!$ret) 
                    $this->_error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_MOVE_POINT_FAILED); 
            }
        } else {
            $preset = intval($this->input('preset'));
            if($preset < 0)
                $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $ret = $_ENV['device']->move_by_preset($device, $preset);
            if(!$ret) 
                $this->_error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_MOVE_PRESET_FAILED);
        }

        $result = array();
        $result['result'] = $ret;
        
        return $this->_success($result);
    }
    
    function ondevice_meta() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $params = array();
        $params['auth_type'] = 'token';
        
        $deviceid = $this->input('deviceId');
        if(!$deviceid) 
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->_error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->_error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $result = $_ENV['device']->meta($device, $params);
        if(!$result) 
            $this->_error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_EXIST);
        
        return $this->_success($result);
    }
    
    function _init_input() {
        $raw = file_get_contents("php://input");
        
    }
    
    function _error($http_status_code, $error, $error_description=NULL, $error_uri=NULL, $type=NULL, $extras=NULL) {
        $this->log('gome error', $http_status_code.'  '.$error);
        apierror(API_HTTP_OK, $error, $error_description, $error_uri, $type, $extras, 'gome');
    }
    
    function _success($result=array()) {
        $ret = array('code' => 0, 'desc' => 'success');
        if($result) {
            // 加密算法为AES128（AES/ECB/PKCS5Padding），加密密钥为dataKey。dataKey的生成规则：将
            // thirdCloudKey进行MD5（32位），并将其结果转化为16进制的小写字符串，取前16字节字符串作为AES128加密密钥dataKey。
            require_once API_SOURCE_ROOT.'lib/cryptaes.php';
            $aes = new CryptAES();
            $key = substr(md5($this->client_secret), 0, 16);
            $aes->set_key($key);
            $aes->require_pkcs5();
            
            foreach($result as $k=>$v) {
                if(in_array($k, array('thirdUId', 'accessToken', 'deviceId'))) {
                    $result[$k] = $aes->encrypt($v);
                }
            }
            
            $ret['result'] = $result;
        } 
        return $ret;
    }
    
    function gen_connect_username($connect_type, $username) {
        if(!$connect_type || !$username)
            return false;
        if(!$_ENV['user']->check_usernameexists($username)) 
            return $username;
        $username = $username.'_'.rand(100,999);
        if(!$_ENV['user']->check_usernameexists($username)) 
            return $username;
        return false;
    }
    
}
