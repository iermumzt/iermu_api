<?php

!defined('IN_API') && exit('Access Denied');

class devicecontrol extends base {

    function __construct() {
        $this->devicecontrol();
    }

    function devicecontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
        $this->load('server');
        $this->load('org');
        $this->load('osd');
    }
    
    function onaddusertoken() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $access_token = $this->input['access_token'];
        $refresh_token = $this->input['refresh_token'];
        
        if(!$access_token || !$refresh_token) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        return array();
    }
    
    function ongetusertoken() {
        $this->init_input();
        $access_token = $this->input['access_token'];
        $deviceid = $this->input['deviceid'];
        $sign = $this->input['sign'];
        
        if(!$access_token || !$deviceid | !$sign) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = explode('.', $access_token);
        if (!is_array($params) || count($params) < 5) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        $appid = $params[0];
        $clientid = $params[1];
        $access_token = $params[4];
        
        $token = $_ENV['oauth2']->getAccessToken($access_token);
        if(!$token || $appid != $token['appid'] || $clientid != $token['client_id'])
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $uid = $token['uid'];
        $scope = $token['scope'];
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || $_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $_ENV['device']->_check_token_sign($sign, $deviceid, $appid, $clientid);
        if(!$check)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_AUTH);
        
        $client_id = $check['client_id'];
        
        $_ENV['oauth2']->uid = $uid;
        $_ENV['oauth2']->appid = $appid;
        $_ENV['oauth2']->client_id = $client_id;
        
        // 生成Access Token
        $token = $_ENV['oauth2']->createAccessToken($client_id, $scope);
        if(!$token)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_TOKEN_FAILED);
        
        $result = array('access_token' => $token['access_token']);

        // log记录 ----------- init params
        log::$appid = $appid;
        log::$client_id = $client_id;
        log::$uid = $uid;

        return $result;
    }
    
    function onauthcode() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        
        $operation = $this->input('operation');
        if(!$operation || !in_array($operation, array('register')))
            $operation = 'register';
        
        $param = stripcslashes(rawurldecode($this->input('param')));
        $param = json_decode($param, true);
        if(!$param || !is_array($param)) {
            $param = array();
        }

        $authcode = $_ENV['device']->authcode($uid, $operation, $param, $this->client_id, $this->appid);
        if(!$authcode)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_AUTHCODE_FAILED);
        
        $result = array(
            'authcode' => $authcode['code'],
            'operation' => $authcode['operation'],
            'expires_in' => $authcode['expiredate'] - $this->time,
            'interval' => 5
        );
        return $result;
    }
    
    function onupdateauth() {
        $this->init_input();
        $authcode = $this->input('authcode');
        $operation = $this->input('operation');
        
        if(!$authcode || !$operation || !in_array($operation, array('register')))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        if($operation == 'register') {
            $deivceid = $this->input('deviceid');
            $type = $this->input('type');
            if(!$deivceid || !$type)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $params['deviceid'] = $deivceid;
            $params['type'] = $type;
        }
        
        $ret = $_ENV['device']->updateauth($authcode, $operation, $params);
        if(!$ret)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_AUTHCODE_FAILED);
        
        return $ret;
    }
    
    function onauthstatus() {
        $this->init_input();
        $authcode = $this->input('authcode');
        $status = $_ENV['device']->authstatus($authcode);
        return $status;
    }
    
    function ongrantauth() {
        $this->init_input();
        $authcode = $this->input('authcode');
        $operation = $this->input('operation');
        
        if(!$authcode || !$operation || !in_array($operation, array('register')))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $param = array();
        if($operation == 'register') {
            $param = stripcslashes(rawurldecode($this->input('param')));
            $param = json_decode($param, true);
            if(!$param || !is_array($param) || !$param['list'])
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        $ret = $_ENV['device']->grantauth($authcode, $operation, $param);
        if(!$ret)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GRANT_AUTHCODE_FAILED);
        
        return $ret;
    }
    
    function onregister() {
        $this->init_input();
        
        $deviceid = $this->input['deviceid'];
        $desc = strval(addslashes(urldecode(trim($this->input['desc']))));
        $timezone = '';
        if($this->input('timezone') !== NULL) {
            $timezone = $this->input['timezone'];
        }

        $this->init_user();
        $uid = $this->uid;
        $auth_type = 'user';
        if(!$uid) {
            $code = $this->input['authcode'];
            if($code) {
                $authcode = $_ENV['device']->get_authcode_by_code($code);
                if($authcode && $authcode['operation'] == 'register' && $authcode['status'] >= 0) {
                    $codeid = $authcode['codeid'];
                    if($authcode['expiredate'] > 0 && $authcode['expiredate'] < $this->time) {
                        $_ENV['device']->update_authcode_status_by_codeid($codeid, -2);
                    } else {
                        $authcode_register = $_ENV['device']->get_authcode_register_by_codeid($codeid, $deviceid);
                        if($authcode_register && $authcode_register['status'] > 0) {
                            $this->uid = $authcode['uid'];
                    		$this->client_id = $authcode['client_id'];
                    		$this->appid = $authcode['appid'];
                            
                            // partner相关信息
                            $partner = $_ENV['oauth2']->get_partner_by_client_id($authcode['client_id']);
                            if($partner && $partner['partner_id']) {
                                $this->partner_id = $partner['partner_id'];
                                $this->partner = $partner;
                                $this->connect_domain = $partner['connect_domain'];
                                $this->client_partner_type = $partner['partner_type'];;
                            }

                            $this->init_partner();
                            
                            $uid = $this->uid;
                            $auth_type = 'authcode';
                        }
                    }
                }
            }
            
            if(!$uid)
                $this->user_error();
        }
        
        // 授权类型
        if($authcode && $deviceid && $auth_type == 'authcode') {
            $this->register_error_hook('update_authcode_register', array('authcode'=>$authcode, 'deviceid'=>$deviceid));
            
            // 设备名称
            if($desc === '') {
                if($authcode_register && $authcode_register['desc']) {
                    $desc = $authcode_register['desc'];
                } else {
                    $desc = $_ENV['device']->get_default_desc($authcode_register['lang']);
                }
            }
            
            // 时区
            if(!$timezone && $authcode_register && $authcode_register['timezone']) {
                $timezone = $authcode_register['timezone'];
            }
        }
        
        // 时区格式
        if($timezone == 'GMT') $timezone = 'UTC';
        if($timezone && !$this->is_support_timezone($timezone)) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TIMEZONE_FORMAT_ILLEGAL);
        }

        $param = stripcslashes(rawurldecode($this->input('location')));
        if ($param && !is_array($param = json_decode($param, true))) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);
        }
        
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
        
        $this->log('device register'.$deviceid, json_encode($this->input));

        if($location && (!$location_type || !$location_name || !$location_latitude || !$location_longitude) || !$deviceid || $desc === '') {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        // 黑名单
        if($_ENV['device']->check_blacklist($deviceid))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_BLACKLIST);
        
        $connect_type = $this->input['connect_type'];
        if($connect_type === NULL) {
            $connect_type = $_ENV['device']->check_device_connect_type($deviceid);
        }
        $connect_type = intval($connect_type);

        if($connect_type > 0 && !$_ENV['user']->check_connect_user($connect_type, $uid)) {
            $this->error(API_HTTP_FORBIDDEN, CONNECT_ERROR_USER_NOT_EXIST, NULL, NULL, NULL, array('connect_type' => $connect_type));
        }
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        
        // 已注册设备
        if($device && $device['uid'] != 0) {
            $extras = array();
            
            // 切换平台
            if($device['connect_type'] != $connect_type) {
                $extras['connect_type'] = $device['connect_type'];
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_CONNECT_TYPE_REG, NULL, NULL, NULL, $extras);
            }
            
            // 切换用户
            if($device['connect_type'] == 0 && $_ENV['device']->check_user_grant($deviceid, $uid) != 2) {
                $connect = $_ENV['user']->get_connect_by_uid($connect_type, $device['uid']);
                if($connect && $connect['username']) {
                    $extras['username'] = $connect['username'];
                }
                $extras['isowner'] = 0;
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ALREADY_REG, NULL, NULL, NULL, $extras);
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

        // update partner device
        if($this->partner_id && $this->partner && $this->partner['update_partner_device']) {
            $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_partner WHERE deviceid='$deviceid'");
            if(!$check) {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_partner SET partner_id='".$this->partner_id."', deviceid='$deviceid', dateline='".$this->time."'");
            } else {
                if($check['partner_id'] != $this->partner_id)
                    $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_SUPPORT_PARTNER_DEVICE);
            }
        }
        
        // check partner client
        if(!$_ENV['device']->check_partner_client($deviceid, $this->client_id))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_SUPPORT_PARTNER_DEVICE);

        // check partner device
        if($this->partner_id && $this->partner && $this->partner['check_partner_device']) {
            if(!$_ENV['device']->check_partner_device($this->partner_id, $deviceid))
                $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_SUPPORT_PARTNER_DEVICE);
        }

        $device = $_ENV['device']->device_register($uid, $deviceid, $connect_type, $desc, $this->appid, $this->client_id, $timezone, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude);

        if(!$device) {
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_FAILED);
        }

        //@todo判断企业用户 更新企业设备总数
        $org_device = $this->org_user_register($uid, $deviceid);
        
        // init
        $_ENV['device']->device_init($device);
        
        // status
        $_ENV['device']->update_status($deviceid, $_ENV['device']->get_status(-1));
        
        // partner_push
        $partner_member = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_partner WHERE uid='$uid'");
        if($partner_member && $partner_member['partner_id']) {
            $partner_id = $partner_member['partner_id'];
            $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id' AND deviceid='$deviceid'");
            if(!$check) {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_partner SET partner_id='$partner_id', deviceid='$deviceid', dateline='".$this->time."'");
            }
            
            $this->log('partner_auth', 'partner_push='.$this->partner_push);
            $this->log('partner_auth', 'data='.$partner_member['data']);
            
            // 第三方推送
            if($this->partner_push && $partner_member['data']) {
                $partner = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."partner WHERE partner_id='$partner_id'");
                $this->log('partner_auth', 'partner='.json_encode($partner));
                if($partner && $partner['connect_type'] && $partner['pushid']) {
                    $data = unserialize($partner_member['data']);
                    $data['partner_id'] = $partner['partner_id'];
                    $data['pushid'] = $partner['pushid'];
                    $this->log('partner_auth', 'data='.json_encode($data));
                    $partner_client = $this->load_connect($partner['connect_type']);
                    if($partner_client) {
                        $partner_client->device_register_push($device, $data);
                    }
                }
            }
        } else {
            if($this->partner_push && $this->partner && $this->partner['connect_type']) {
                $partner_client = $this->load_connect($this->partner['connect_type']);
                if($partner_client) {
                    $data = array();
                    $data['partner_id'] = $this->partner['partner_id'];
                    $data['pushid'] = $this->partner['pushid'];
                    $partner_client->device_register_push($device, $data);
                }
            }
        }
 
        // authcode
        if($auth_type == 'authcode') {
            $_ENV['device']->update_authcode_register($authcode, $deviceid);
        }
        
        $result = array();
        $result['deviceid'] = $device['deviceid'];
        $result['uid'] = $device['uid'];
        $result['connect_type'] = $device['connect_type'];
        $result['connect_cid'] = $device['connect_cid'];
        $result['stream_id'] = $device['stream_id'];
        if($auth_type == 'authcode' && $device['connect_type'] == API_BAIDU_CONNECT_TYPE) {
            $connect_token = $_ENV['device']->get_connect_token($device);
            if($connect_token) $result['connect_token'] = $connect_token;
        }
        
        return $result;
    }

    function onupdatelocation() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];

        $param = stripcslashes(rawurldecode($this->input('location')));
        if ($param && !is_array($param = json_decode($param, true)))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);

        $location = $param ? 1 : 0; // 是否上传地理位置
        $location_type = strval($param['type']); // 使用地图 amap
        $location_name = strval($param['name']); // POI位置
        $location_address = strval($param['address']); // POI位置
        $location_latitude = floatval($param['latitude']); // 纬度
        $location_longitude = floatval($param['longitude']); // 经度

        $this->log('device updatelocation'.$deviceid, json_encode($this->input));

        if (!$deviceid || !$location)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if (!$location_type || !$location_name || !$location_latitude || !$location_longitude)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        // 保存或者更新
        if (!$_ENV['device']->update_location($deviceid, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude)) {
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_LOCATION_FAILED);
        }

        $result = array('deviceid' => $deviceid);
        
        // 获取地理位置
        $location = $_ENV['device']->get_location($deviceid);
        if ($location) {
            $result['location'] = array(
                'type' => $location['location_type'],
                'name' => $location['location_name'],
                'address' => $location['location_address'],
                'latitude' => floatval($location['location_latitude']),
                'longitude' => floatval($location['location_longitude'])
            );
        } else {
            $result['location'] = (object)array();
        }

        return $result;
    }

    function ongetlocation() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];

        if (!$deviceid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $result = array('deviceid' => $deviceid);

        // 获取地理位置
        $location = $_ENV['device']->get_location($deviceid);
        if ($location) {
            $result['location'] = array(
                'type' => $location['location_type'],
                'name' => $location['location_name'],
                'address' => $location['location_address'],
                'latitude' => floatval($location['location_latitude']),
                'longitude' => floatval($location['location_longitude'])
            );
        } else {
            $result['location'] = (object)array();
        }

        return $result;
    }

    function ondroplocation() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];

        if (!$deviceid) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if (!$_ENV['device']->drop_location($deviceid))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_LOCATION_FAILED);

        $result = array('deviceid' => $deviceid);

        return $result;
    }

    function onupdate() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $desc = strval(addslashes(urldecode(trim($this->input['desc']))));
        $location = $this->input('location');
        $timezone = $this->input('timezone');
        
        if(!$deviceid || ($desc === '' && $location === NULL && $timezone === NULL))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        // 地理位置格式校验
        if($location) {
            $data = json_decode(stripcslashes(rawurldecode($location)), true);
            if(!$data || !is_array($data))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);
            
            $location_drop = 0;
            if(!$data) {
                $location_drop = 1;
            } else {
                $location = $data ? 1 : 0; // 是否上传地理位置
                $location_type = $data["type"]; //使用地图 amap
                $location_name = strval($data["name"]); //POI位置
                $location_address =  strval($data["address"]); //POI位置
                $location_latitude = floatval($data["latitude"]);//纬度
                $location_longitude= floatval($data["longitude"]);//经度
            
                if(!$location_type || !$location_latitude ||!$location_longitude ||!$location_name) {
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);
                }
            }
        }
        
        // 时区格式校验
        if($timezone == 'GMT') $timezone = 'UTC';
        if($timezone && !$this->is_support_timezone($timezone))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TIMEZONE_FORMAT_ILLEGAL);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        $result = array();
        $result['deviceid'] = $deviceid;
        // 设备描述
        if($desc !== '') {
            if(strval($device['desc']) !== strval($desc) && !$_ENV['device']->update_desc($device, $desc)) {
                $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_FAILED);
            }
            $result['desc'] = $desc;
        }
        // 地理位置
        if($location) {
            if($location_drop) {
                if (!$_ENV['device']->drop_location($deviceid))
                    $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_LOCATION_FAILED);
            } else {
                if (!$_ENV['device']->update_location($deviceid, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude)) {
                    $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_LOCATION_FAILED);
                }
            }
            
            // 获取地理位置
            $location = $_ENV['device']->get_location($deviceid);
            if ($location) {
                $result['location'] = array(
                    'type' => $location['location_type'],
                    'name' => $location['location_name'],
                    'address' => $location['location_address'],
                    'latitude' => floatval($location['location_latitude']),
                    'longitude' => floatval($location['location_longitude'])
                );
            } else {
                $result['location'] = (object)array();
            }
        }
        
        // 设备时区
        if($timezone) {
            $isonline = NULL;
            if($timezone != $device['timezone']) {
                $isonline = $_ENV['device']->is_device_online($device);
                if(!$isonline)
                    $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_OFFLINE);
            }
            
            if(!$_ENV['device']->update_timezone($device, $timezone, $isonline)) {
                $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_TIMEZONE_FAILED);
            }
            $result['timezone'] = $timezone;
        }
        
        return $result;
    }
    
    function onlist() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $device_type = intval($this->input('device_type'));
        $share = $this->input('share');
        $online = $this->input('online');
        $category = $this->input('category');
        $keyword = strval($this->input('keyword'));
        $orderby = $this->input('orderby');
        $data_type = $this->input('data_type');
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        $share = ($share === NULL || $share === '') ? -1 : intval($share);
        $online = ($online === NULL || $online === '') ? -1 : intval($online);
        $category = ($category === NULL || $category === '') ? -1 : intval($category);
        
        $dids = strval($this->input('deviceid'));
        if($dids) {
            $dlist = split(",", $dids);
            $dids = "";
            if($dlist && is_array($dlist)) {
                $dids = "'";
                $dids .= join("', '", $dlist);
                $dids .= "'";
            }
        }

        if (!$data_type || !in_array($data_type, array('my', 'grant', 'sub', 'all'))) {
            $data_type = 'none';
        }

        if (!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        
        $result = $_ENV['device']->list_devices($uid, $support_type, $device_type, $this->appid, $share, $online, $category, $keyword, $orderby, $data_type, $list_type, $page, $count, $dids);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NOT_EXIST);
        return $result;
    }

    //获取ai摄像机列表
    function onlistaidevice(){
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $keyword = strval($this->input('keyword'));
        $orderby = $this->input('orderby');
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $dids = strval($this->input('deviceid'));
        $meeting_id = strval($this->input('meeting_id'));
        if($dids) {
            $dlist = split(",", $dids);
            $dids = "";
            if($dlist && is_array($dlist)) {
                $dids = "'";
                $dids .= join("', '", $dlist);
                $dids .= "'";
            }
        }

        if (!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        
        $result = $_ENV['device']->list_ai_devices($uid, $support_type, $this->appid, $list_type, $keyword, $orderby, $page, $count, $dids,$meeting_id);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NOT_EXIST);
        return $result;
    }
    
    function onvalidate() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        
        if (!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        return array();
    }
    
    function oncreateshare() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $share_type = intval($this->input['share']);
        $title = strval($this->input['title']);
        $intro = strval($this->input['intro']);
        $showlocation = $this->input['showlocation'] ? 1 : 0;

        $param = stripcslashes(rawurldecode($this->input('location')));
        if ($param && !is_array($param = json_decode($param, true)))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOCATION_FORMAT_ILLEGAL);

        $location = $param ? 1 : 0; // 是否上传地理位置
        $location_type = strval($param['type']); // 使用地图 amap
        $location_name = strval($param['name']); // POI位置
        $location_address = strval($param['address']); // POI位置
        $location_latitude = floatval($param['latitude']); // 纬度
        $location_longitude = floatval($param['longitude']); // 经度

        $this->log('device createshare'.$deviceid, json_encode($this->input));

        if ($location && (!$location_type || !$location_name || !$location_latitude || !$location_longitude) || !$deviceid || !$share_type) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if ($device['reportstatus']) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_REPORT);

        if ($showlocation && !$location && !$device['location'])
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_LOCATION_NOT_EXIST);

        // 更新地理位置信息
        if ($location && !$_ENV['device']->update_location($deviceid, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude)) {
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_LOCATION_FAILED);
        }

        // 私密分享可以设置过期时间和密码
        switch ($share_type) {
            case 2:
            case 4:
                $expires = intval($this->input['expires_in']);
                $password = strtolower($this->input['password']);

                if ($password !== '' && !preg_match('/^[a-z0-9]{1,8}$/', $password)) {
                    $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_INVAILD_SHARE_PASSWORD);
                }
                break;
            
            default:
                $password = '';
                $expires = 0;
                break;
        }
        
        $share = $_ENV['device']->create_share($uid, $device, $title, $intro, $share_type, $this->appid, $this->client_id, $showlocation, $password, $expires);
        if (!$share) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_CREATE_SHARE_FAILED);
        
        $result = array(
            'shareid' => $share['shareid'],
            'uk' => $share['uid'] ? $share['uid'] : $share['connect_uid']
        );

        return $result;
    }
    
    function oncancelshare() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        
        if (!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if (!$_ENV['device']->cancel_share($device)) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_CANCEL_SHARE_FAILED);
        
        return array();
    }
    
    function onlistshare() {
        if ($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->user_error();
        } else {
            $uid = 0;
        }
        
        $this->init_input();
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);

        if (!$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $check = $_ENV['device']->_check_sign($sign, $expire);
        if (!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_PUBLIC_SHARE_AUTH);

        $client_id = $check['client_id'];
        $appid = $check['appid'];
        
        $orderby = $this->input['orderby'];
        $category = intval($this->input['category']);
        $commentnum = intval($this->input['commentnum']);
        $page = intval($this->input['page']);
        $count = intval($this->input['count']);
        
        // 兼容老参数: start,num
        $start = intval($this->input['start']);
        $num = intval($this->input['num']);

        if ($start<0 || $num<0)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if ($start || $num) {
            if(!$num) $num = 10;
            $page = $start/$num + 1;
            $count = $num;
        }

        $category = $category > 0 ? $category : 0;
        $commentnum = $commentnum > 0 ? $commentnum : 0;
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $support_type = $_ENV['oauth2']->get_client_connect_support($client_id);
  
        return $_ENV['device']->list_share($uid, 0, $support_type, $category, $orderby, $commentnum, $page, $count, $appid, 0);
    }
    
    function onsubscribe() {
        $this->init_input();
        $shareid = $this->input['shareid'];
        $uk = intval($this->input['uk']);
        
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        if(!$shareid || !$uk) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $share = $_ENV['device']->get_share_by_shareid($shareid);
        if(!$share) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
        
        if($share['uid'] != $uk && $share['connect_uid'] != $uk) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        // 判断是否为用户自己订阅自己的分享
        if($share['uid'] == $uid){
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }
        
        $deviceid = $share['deviceid'];
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if($device['connect_type'] > 0 && !$_ENV['user']->get_connect_by_uid($device['connect_type'], $uid))
            $this->error(API_HTTP_FORBIDDEN, CONNECT_ERROR_USER_NOT_EXIST, NULL, NULL, NULL, array('connect_type' => $device['connect_type']));
        
        if(!$_ENV['device']->subscribe($uid, $share, $this->appid, $this->client_id)) {
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SUB_FAILED);
        }
        
        $result = array();
        $result['shareid'] = $share['shareid'];
        $result['uk'] = $share['connect_uid'];
        $result['share'] = $share['share_type'];
        return $result;
    }
    
    function onunsubscribe() {
        $this->init_input();
        $shareid = $this->input['shareid'];
        $uk = intval($this->input['uk']);
        
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        if(!$shareid || !$uk) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $share = $_ENV['device']->get_share_by_shareid($shareid);
        if(!$share) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
        if($share['uid'] != $uk && $share['connect_uid'] != $uk) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $deviceid = $share['deviceid'];
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$_ENV['device']->unsubscribe($uid, $share)) {
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_UNSUB_FAILED);
        }
        
        $result = array();
        $result['shareid'] = $share['shareid'];
        $result['uk'] = $share['connect_uid'];
        $result['share'] = $share['share_type'];
        return $result;
    }
    
    function onlistsubscribe() {
        $this->init_input();

        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $share = $this->input('share');
        $online = $this->input('online');
        $category = $this->input('category');
        $orderby = $this->input('orderby');

        $share = ($share === NULL || $share === '') ? -1 : intval($share);
        $online = ($online === NULL || $online === '') ? -1 : intval($online);
        $category = ($category === NULL || $category === '') ? -1 : intval($category);
        
        $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        return $_ENV['device']->listsubscribe($uid, $support_type, $this->appid, $share, $online, $category, '', $orderby);
    }
    
    function ongrant() {
        $params = array();
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $auth_code = intval($this->input['auth_code']);
        $code = $this->input['code'];

        if($code) {
            $uk = $uid;
            $name = '';

            $grant = $_ENV['device']->get_grant_by_code($code);
            if(!$grant)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_NOT_EXIST);

            if($this->time - $grant['dateline'] > 3600)
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GRANT_INVALID);

            if($grant['useid'])
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GRANT_USED);

            if($grant['uid'] == $uid)
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NOT_GRANT_SELF);

            $uid = $grant['uid'];
            $deviceid = $grant['deviceid'];
        } else {
            $uk = intval($this->input['uk']);
            $name = trim($this->input['name']);
            
            $deviceid = $this->input['deviceid'];
        }
        //增加企业用户 重复授权判断
        if(!$deviceid || !$uk || ($uk == $uid) || $_ENV['device']->check_user_grant($deviceid, $uk) == 2) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $user = $_ENV['user']->get_user_by_uid($uk);
        if(!$user || (!$code && $user['username'] != $name))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $dev_list = array();
        $list = explode(',', $deviceid);
        foreach ($list as $value) {
            if($value) {
                $device = $_ENV['device']->get_device_by_did($value);
                if(!$device) 
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
                
                if($_ENV['device']->check_user_grant($value, $uid) != 2 || $device['appid'] != $this->appid) 
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                
                if(!$_ENV['device']->check_grant($value, $device['appid']))
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_LIMIT);

                if($device['connect_type'] >0 && !$_ENV['user']->get_connect_by_uid($device['connect_type'], $uk))
                    $this->error(API_HTTP_FORBIDDEN, CONNECT_ERROR_USER_NOT_EXIST, NULL, NULL, NULL, array('connect_type' => $device['connect_type']));

                $dev_list[] = $device;
            }
        }

        if(empty($dev_list))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['device']->grant($dev_list, $user, $auth_code, $code, $this->appid, $this->client_id);
        if(!$result)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_FAILED);

        if ($grant) {
            $result['grant'] = $_ENV['device']->grantinfo($grant);
        }

        return $result;
    }
    
    function onlistgrantuser() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $uid = $device['uid'];
        return $_ENV['device']->listgrantuser($uid, $deviceid, $device['connect_type'], $this->appid, $this->client_id);
    }
    
    function onlistgrantdevice() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $share = $this->input('share');
        $online = $this->input('online');
        $category = $this->input('category');
        $orderby = $this->input('orderby');

        $share = ($share === NULL || $share === '') ? -1 : intval($share);
        $online = ($online === NULL || $online === '') ? -1 : intval($online);
        $category = ($category === NULL || $category === '') ? -1 : intval($category);
        
        $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        
        return $_ENV['device']->listgrantdevice($uid, $support_type, $this->appid, $share, $online, $category, $orderby);
    }
    
    function ondropgrantuser() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $uk = intval($this->input['uk']);
        $deviceid = $this->input['deviceid'];

        if(!$deviceid || !$uk) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2 || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$_ENV['device']->dropgrantuser($device, $uk)) {
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_DELETE_FAILED);
        }

        if (!$_ENV['device']->get_user_grantnum($uid, $uk)) {
            $_ENV['user']->drop_remarkname($uid, $uk);
        }
        
        $result = array();
        $result['deviceid'] = $deviceid;
        $result['grantnum'] = $_ENV['device']->get_grantnum($deviceid);
        return $result;
    }
    
    function ondropgrantdevice() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];

        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$_ENV['device']->dropgrantuser($device, $uid)) {
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_DELETE_FAILED);
        }

        if (!$_ENV['device']->get_user_grantnum($device['uid'], $uid)) {
            $_ENV['user']->drop_remarkname($device['uid'], $uid);
        }
        
        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }
    
    function onliveplay() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $type = $this->input('type');
        $lan = $this->input('lan');

        if (!$type || !in_array($type, array('p2p', 'hls'))) {
            $type = 'rtmp';
        }
        $params = array();
        
        if ($shareid && $uk) {
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;

            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if (!$share) {
                return $_ENV['device']->liveplay(null, $type, $params);
            }

            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['uk'] = $share['connect_uid'];
        } elseif ($deviceid) {
            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            } elseif ($this->init_user()) {
                $uid = $this->uid;
            } else {
                $this->user_error();
            }

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            
            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }
            
            $params['auth_type'] = 'token';
        } else {
            $this->init_user();
            $uid = $this->uid;
            if(!$uid) 
                $this->user_error();

            $list = stripcslashes(rawurldecode($this->input('list')));
            if ($list) {
                $list = json_decode($list, true);
            }

            $devicelist = array();
            foreach ($list as $deviceid) {
                $device = $_ENV['device']->get_device_by_did($deviceid);
                if (!$device)
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

                if (!$_ENV['device']->check_user_grant($deviceid, $uid))
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

                $devicelist[] = $device;
            }

            if (!$devicelist)
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_PARAM);
        }

        if ($params['auth_type']) {
            $dvrplay = $_ENV['device']->get_dvrplay($device);
            $params['dvrplay'] = $dvrplay['status'] ? 1 : 0;
            $params['lan'] = $lan;
            $result = $_ENV['device']->liveplay($device, $type, $params, $this->client_id?$this->client_id:$device['client_id']);
        } else {
            $result = $_ENV['device']->multi_liveplay($uid, $devicelist);
        }
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_LIVE_URL_FAILED);
        
        return $params['dvrplay'] ? $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_DVRPLAY_IN_PLAY, NULL, NULL, NULL, $result) : $result;
    }
    
    function onplaylist() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        $type = $this->input['type'];
        
        if(!$starttime || !$endtime || $endtime <= $starttime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        
        if($shareid && $uk) {
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;

            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if (!$share) {
                return $_ENV['device']->playlist(null, $params, $starttime, $endtime, $type);
            }

            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            // 不公开录像
            if($share['share_type'] < 3)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_PLAYLIST);

            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['uk'] = $share['connect_uid'];
        } else {
            if (!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            } elseif ($this->init_user()) {
                $uid = $this->uid;
            } else {
                $this->user_error();
            }

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            
            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }

            $params['auth_type'] = 'token';
        }

        $result = $_ENV['device']->playlist($device, $params, $starttime, $endtime, $type);
        if (!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_PLAYLIST);
        
        return $result;
    }
    
    function onvod() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        
        if (!$starttime || !$endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array(
            'type' => strval($this->input['type'])
        );

        if ($shareid && $uk) {
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;

            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if (!$share) {
                return $_ENV['device']->vod(null, $params, $starttime, $endtime);
            }

            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            // 不公开录像
            if($share['share_type'] < 3)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_PLAYLIST);

            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['uk'] = $share['connect_uid'];
        } else {
            if (!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            } elseif ($this->init_user()) {
                $uid = $this->uid;
            } else {
                $this->user_error();
            }

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }

            $params['auth_type'] = 'token';
        }
        
        $result = $_ENV['device']->vod($device, $params, $starttime, $endtime);
        if (!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_PLAYLIST);
        
        return $result;
    }
    
    function onvodseek() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $time = intval($this->input['time']);

        if (!$time)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $params = array(
            'type' => strval($this->input['type'])
        );

        if ($shareid && $uk) {
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;

            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if (!$share) {
                return $_ENV['device']->vodseek(null, $params, $time);
            }

            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            // 不公开录像
            if($share['share_type'] < 3)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_PLAYLIST);

            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['uk'] = $share['connect_uid'];
        } else {
            if (!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            } elseif ($this->init_user()) {
                $uid = $this->uid;
            } else {
                $this->user_error();
            }

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }

            $params['auth_type'] = 'token';
        }
        
        $result = $_ENV['device']->vodseek($device, $params, $time);
        if (!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_TS);
        
        return $result;
    }
    
    function onthumbnail() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        $latest = intval($this->input('latest'));
        $download = intval($this->input('download'));
        
        if (!$latest && (!$starttime || !$endtime || $endtime < $starttime))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();

        if($shareid && $uk) {
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;

            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if (!$share || $device['reportstatus']) {
                return $_ENV['device']->get_thumbnail(null, $params, $starttime, $endtime, $latest);
            }

            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            // 不公开录像
            if($share['share_type'] < 3)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_PLAYLIST);

            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['uk'] = $share['connect_uid'];
        } else {
            if (!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            } elseif ($this->init_user()) {
                $uid = $this->uid;
            } else {
                $this->user_error();
            }

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }

            $params['auth_type'] = 'token';
        }
        
        $result = $_ENV['device']->get_thumbnail($device, $params, $starttime, $endtime, $latest);
        if (!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_THUMBNAIL);

        if ($latest && $download) {
            $image = $result['list'] ? $result['list'][0]['url'] : 'http://www.iermu.com/images/camera-logo.png';
            header('Location:' . $image);
            exit();
        }
        
        return $result;
    }
    
    function ondropvideo() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        
        if (!$deviceid || !$starttime || !$endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if (!$_ENV['device']->drop_cvr($deviceid, $device['uid'], $starttime, $endtime)) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_VIDEO_FAILED);
        
        $result = array();
        $result['start_time'] = $starttime;
        $result['end_time'] = $endtime;
        return $result;
    }
    
    function onclip() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $starttime = intval($this->input('st'));
        $endtime = intval($this->input('et'));
        // $name = strval($this->input('name'));
        $name = strval(addslashes(urldecode(str_replace(" ","", $this->input['name']))));
        
        if(!$deviceid || !$starttime || !$endtime || $name === '') 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->clip($device, $starttime, $endtime, $name, $this->client_id, $uid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_CLIP_FAILED);
        
        return $result;
    }
    
    function oninfoclip() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $clipid = $this->input['clipid'];
        
        if(!$clipid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $result = $_ENV['device']->infoclip($uid, $type, $clipid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_NETWORK);
        
        return $result;
    }

    function onlistclip() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $page = $this->input['page'];
        $count = $this->input['count'];
        $client_id = $_ENV['oauth2']->client_id;
        $support_type = $_ENV['oauth2']->get_client_connect_support($client_id);

        $result = $_ENV['device']->listuserclip($uid, $support_type, $page, $count, $client_id);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_NETWORK);
        
        return $result;
    }
    
    function onlocateuploadex() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $streamid = $this->input['streamid'];
        
        if(!$deviceid || !$streamid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['stream_id'] != $streamid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $server = $_ENV['device']->list_upload($deviceid, $device['appid'], $device['lastserverid']);
        if(!$server) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_BMS_SERVER_FAILED);
        
        $result = array();
        $result['count'] = count($server);
        $result['server_list'] = $server;
        return $result;
    }
    
    function oninfo() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $streamid = $this->input['streamid'];
        
        if(!$deviceid || !$streamid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['stream_id'] != $streamid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) {
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }
        
        $server = $_ENV['device']->locate_upload($deviceid, $device['appid'], $device['lastserverid']);
        if(!$server) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_BMS_SERVER_FAILED);
        
        $result = array();
        $result['server'] = $server['url'];
        $result['status'] = $device['status'];
        return $result;
    }
    
    function ondrop() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if($device['uid'] != 0 && !$_ENV['device']->drop($device)) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_FAILED);
        
        // partner
        if($this->partner_push) {
            $partner_member = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_partner WHERE uid='$uid'");
            if($partner_member && $partner_member['partner_id']) {
                $partner_id = $partner_member['partner_id'];
                if($partner_member['data']) {
                    $partner = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."partner WHERE partner_id='$partner_id'");
                    if($partner && $partner['connect_type'] && $partner['pushid']) {
                        $data = unserialize($partner_member['data']);
                        $data['partner_id'] = $partner['partner_id'];
                        $data['pushid'] = $partner['pushid'];
                        $partner_client = $this->load_connect($partner['connect_type']);
                        if($partner_client) {
                            $partner_client->device_drop_push($device, $data);
                        }
                    }
                }
            }
        } else {
            if($this->partner_push && $this->partner && $this->partner['connect_type']) {
                $partner_client = $this->load_connect($this->partner['connect_type']);
                if($partner_client) {
                    $data = array();
                    $data['partner_id'] = $this->partner['partner_id'];
                    $data['pushid'] = $this->partner['pushid'];
                    $partner_client->device_drop_push($device, $data);
                }
            }
        }
        
        
        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }
    
    function oncontrol() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $command = stripcslashes(rawurldecode($this->input['command']));
     
        if(!$deviceid || !$command) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }
        
        // usercmd
        $ret = $_ENV['device']->control($device, $command);
        if(!$ret)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_USER_CMD_FAILED);
        
        return $ret;
    }
    
    function onsetting() {
        $this->init_input();
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $fileds = $this->input('fileds');
        
        $this->log('get device setting '.$deviceid, json_encode($this->input));
     
        if(!$type || !in_array($type, array('info', 'status', 'volume', 'power', 'email', 'cvr', 'alarm', 'capsule', 'storage', 'plat', 'bit', 'bw', 'night', 'init', 'ai', 'ai_set', 'pano', 'server'))) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $params = array();
        if($shareid && $uk && in_array($type, array('pano'))) {
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;

            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if (!$share) {
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            }

            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['uk'] = $share['connect_uid'];
            $params['uid'] = $this->init_user() ? $this->uid : 0;
            $this->connect_error = false;
        } else {
            if (!$deviceid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            }
    
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
            
            if(!$uid)
                $this->user_error(); 
            
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            
            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }
    
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['auth_type'] = 'token';
            $params['uid'] = $uid;
        }
        
        $result = $_ENV['device']->setting($device, $type, $fileds);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);
        
        $this->log('get device setting success '.$deviceid, json_encode($result));
        
        return $result;
    }
    
    function onupdatesetting() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        
        $fileds = stripcslashes(rawurldecode($this->input('fileds')));
        if($fileds) {
            $fileds = json_decode($fileds, true);
        }
        $this->log('update device setting '.$deviceid, json_encode($this->input));
     
        if(!$deviceid || !$fileds) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $_ENV['device']->check_device_auth($deviceid);
        if ($auth && $auth['uid']) {
            $uid = $auth['uid'];
        }

        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->user_error();
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }
        
        // check push client
        if($fileds['alarm_push'] !== NULL) {
            if(!$_ENV['device']->check_partner_push($deviceid) && !$_ENV['device']->check_push_client()) {
                $this->error(API_HTTP_BAD_REQUEST, PUSH_ERROR_CLIENT_UNREGISTER);
            }
        }

        $_ENV['device']->init_vars_by_deviceid($device['deviceid']);
        
        $paltform = $_ENV['oauth2']->get_client_platform($this->client_id);
        
        $result = $_ENV['device']->updatesetting($device, $fileds, $paltform);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        $this->log('update device setting success '.$deviceid, json_encode($result));
        
        return $result;
    }
    
    function onlistalarmdevice() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        $result = $_ENV['device']->listalarmdevice($uid, $page, $count, $this->appid);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_LIST_ALARMDEVICE_FAILED);
        
        return $result;
    }
    
    function onlistalarm() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        
        $type = $this->input('type');
        $sensorid = $this->input('sensorid');
        $sensortype = $this->input('sensortype');
        $actionid = $this->input('actionid');
        $actiontype = $this->input('actiontype');
        
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        if(!$deviceid || ($endtime < $starttime)) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($device['appid'] != $this->appid)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->listalarm($device, $type, $sensorid, $sensortype, $actionid, $actiontype, $starttime, $endtime, $page, $count);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_LIST_ALARM_FAILED);
        
        return $result;
    }
    
    function ondropalarm() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $time = '';
        $list = array();
        
        $param = stripcslashes(rawurldecode($this->input('param')));
        if($param) {
            $param = json_decode($param, true);
            if(!$param || !is_array($param) || !$param['list'])
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $list = $param['list'];
            if(!is_array($list) || count($list) > 150)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        } else {
            $time = $this->input('time');
            if($time) {
                $time = split(",", $time);
            }
            
            if(!$time || !is_array($time) || count($time) > 150)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->dropalarm($device, $list, $time);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_DROP_ALARM_FAILED);
        
        return $result;
    }
    
    function onalarm() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $sensorid = $this->input('sensorid');
        $sensortype = $this->input('sensortype');
        $actionid = $this->input('actionid');
        $actiontype = $this->input('actiontype');
        $time = $this->input('time');
        $download = $this->input('download');
        
        if(!$deviceid || !$time)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->alarminfo($device, $time, $type, $sensorid, $sensortype, $actionid, $actiontype, $download);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_ALARM_FAILED);
        
        return $result;
    }
    
    function onservertime() {  
        $time = round(microtime(true)*1000);
        $result['servertime'] = $time;
        return $result;
    }
    
    function onmeta() {
        $this->init_input();
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $deviceid = $this->input['deviceid'];

        $params = array(
            'param' => strval($this->input['param'])
        );

        if($shareid && $uk) {
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;

            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if (!$share) {
                return $_ENV['device']->meta(null, $params);
            }

            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $params['uk'] = $share['connect_uid'];
            $params['uid'] = $this->init_user() ? $this->uid : 0;
        } else {
            if (!$deviceid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            } elseif ($this->init_user()) {
                $uid = $this->uid;
            } else {
                $this->user_error();
            }
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            
            if ($auth) {
                $this->appid = $device['appid'];
            }

            if ($_ENV['device']->check_user_grant($deviceid, $uid)!= 2 || $device['appid'] != $this->appid)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            $params['auth_type'] = 'token';
            $params['uid'] = $uid;
        }
        
        $result = $_ENV['device']->meta($device, $params);
        if (!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_EXIST);

        if($auth && $auth['controls']) {
            $result['controls'] = $auth['controls'];
        }
        
        return $result;
    }

    // 获取用户分类
    function onlistcategory() {
        $this->init_input();
        $type = $this->input['type'];

        switch ($type) {
            case 'pub':
                $ctype = 0;
                $uid = 0;
                break;

            default:
                $ctype = 1;
                $this->init_user();
                $uid = $this->uid;
                if(!$uid) {
                    $this->user_error();
                }
                break;
        }

        $result = $_ENV['user']->list_category($ctype, $uid);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_GET_CATEGORY_FAILED);
        
        return $result;
    }

    // 保存用户分类
    function onaddcategory() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $category = $this->input('category');
        $deviceid = $this->input('deviceid');

        $dev_list = array();
        $list = explode(',', $deviceid);
        foreach ($list as $value) {
            if($value) {
                $device = $_ENV['device']->get_device_by_did($value);
                if(!$device) 
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

                $dev_list[] = $value;
            }
        }

        if(!$category || empty($dev_list))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $cid = $_ENV['user']->get_cid_by_category($uid, $category);
        if ($cid)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_CATEGORY_ALREADY_EXIST);

        $cid = $_ENV['user']->add_category($uid, $category);
        if(!$cid) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_ADD_CATEGORY_FAILED);

        $result = $_ENV['device']->add_category($dev_list, $cid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_CATEGORY_FAILED);
        
        return $result;
    }

    // 保存用户分类
    function onupdatecategory() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $cid = intval($this->input('cid'));
        $category = $this->input('category');
        $deviceid = $this->input('deviceid');

        $dev_list = array();
        $list = explode(',', $deviceid);
        foreach ($list as $value) {
            if($value) {
                $device = $_ENV['device']->get_device_by_did($value);
                if(!$device) 
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

                $dev_list[] = $value;
            }
        }

        if(!$cid || !$category || empty($dev_list))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if(!$_ENV['user']->check_category($uid, $cid))
            $this->error(API_HTTP_NOT_FOUND, API_ERROR_USER_CATEGORY_NOT_EXIST);

        $_cid = $_ENV['user']->get_cid_by_category($uid, $category);
        if ($_cid && $_cid != $cid)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_CATEGORY_ALREADY_EXIST);

        if(!$_ENV['user']->update_category_by_cid($cid, $category))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_UPDATE_CATEGORY_FAILED);

        $result = $_ENV['device']->update_category($dev_list, $cid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_CATEGORY_FAILED);
        
        return $result;
    }

    // 删除分类
    function ondropcategory() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $cid = intval($this->input('cid'));

        if(!$_ENV['user']->check_category($uid, $cid))
            $this->error(API_HTTP_NOT_FOUND, API_ERROR_USER_CATEGORY_NOT_EXIST);

        if(!$_ENV['device']->drop_category_by_cid($cid))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DEL_CATEGORY_FAILED);

        if(!$_ENV['user']->drop_category($uid, $cid))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_DEL_CATEGORY_FAILED);
        
        $result = array();
        $result['cid'] = $cid;
        return $result;
    }

    // 获取设备评论列表
    function onlistcomment() {
        $this->init_input();

        $shareid = $this->input('shareid');
        $uk = $this->input('uk');
        if($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if(!$share)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            
            $uid = $share['uid'];
            
            if($uk != $uid && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $deviceid = $share['deviceid'];
            
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        } else {
            $this->init_user();
        
            $uid = $this->uid;
            if(!$uid)
                $this->user_error();
            
            $deviceid = $this->input('deviceid');
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$_ENV['device']->check_user_grant($deviceid, $uid))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $st = intval($this->input('st'));
        $et = intval($this->input('et'));
        $reply_type = $this->input('reply_type');
        $reply_cid = $this->input('reply_cid');

        if(!$list_type || !in_array($list_type, array('page', 'timeline'))) {
            $list_type = 'page';
        }
        
        if(!$reply_type || !in_array($reply_type, array('reply'))) {
            $reply_type = '';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        $st = $st > 0 ? $st : 0;
        $et = $et > 0 ? $et : 0;

        $result = $_ENV['device']->list_comment($device, $list_type, $page, $count, $st, $et, $reply_type, $reply_cid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_COMMENT_FAILED);
        
        return $result;
    }

    // 添加设备评论
    function oncomment() {
        $this->init_user();

        $uid = $this->uid;
        $connect_type = 0;
        $connect_uid = '';
        
        if(!$uid) {
            if(!$this->connect_uid || !$this->connect_user)
                $this->user_error();
            
            $connect_type = $this->connect_type;
            $connect_uid = $this->connect_uid;
        }
        
        $this->init_input();
        $deviceid = $this->input('deviceid');

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if(!$device['commentstatus'])
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ADD_COMMENT_NOT_ALLOWED);

        $comment = $this->input('comment');
        $parent_cid = $this->input('parent_cid');

        $result = $_ENV['device']->save_comment($uid, $connect_type, $connect_uid, $deviceid, $comment, $parent_cid, $this->client_id, $this->appid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_COMMENT_FAILED);
        
        if($uid) {
            $user = $_ENV['user']->_format_user($uid);
        } else {
            $user = $this->connect_user;
        }
        
        $result['comment']['uid'] = $uid;
        if(!$uid) {
            $result['comment']['connect_type'] = $connect_type;
            $result['comment']['connect_uid'] = $connect_uid;
        }
        $result['comment']['username'] = $user['username'];
        $result['comment']['avatar'] = $user['avatar'];
        
        return $result;
    }

    // 获取观看记录
    function onlistview() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['device']->list_view($uid, $this->appid, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_LIST_VIEW_FAILED);
        
        return $result;
    }

    // 添加观看记录
    function onview() {
        $this->init_input();

        $deviceid = $this->input('deviceid');

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->user_error();

            $_ENV['device']->save_view($uid, $deviceid, $this->client_id, $this->appid);
        }

        $result = $_ENV['device']->add_view($deviceid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_VIEW_FAILED);
        
        return $result;
    }

    // 删除播放记录
    function ondropview() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');

        $dev_list = array();
        $list = explode(',', $deviceid);
        foreach ($list as $value) {
            if($value) {
                $device = $_ENV['device']->get_device_by_did($value);
                if(!$device) 
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

                $dev_list[] = $value;
            }
        }

        if(empty($dev_list))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['device']->drop_view($uid, $dev_list);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_VIEW_FAILED);
        
        return $result;
    }

    // 添加设备点赞
    function onapprove() {
        $this->init_input();

        $deviceid = $this->input('deviceid');

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->user_error();

            $_ENV['device']->save_approve($uid, $deviceid, $this->client_id, $this->appid);
        }

        $result = $_ENV['device']->add_approve($deviceid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_APPROVE_FAILED);
        
        return $result;
    }

    // 添加设备举报
    function onreport() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = intval($this->input('type'));
        $reason = $this->input('reason');

        $type = $type > 0 ? $type : 0;

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        // 保存举报记录
        $result = $_ENV['device']->add_report($uid, $this->user['username'], $device, $type, $reason, $this->client_id, $this->appid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_REPORT_FAILED);
        
        return $result;
    }

    // 获取授权码
    function ongrantcode() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $grant_type = intval($this->input('grant_type'));
        $deviceid = $this->input('deviceid');

        $dev_list = array();
        $list = explode(',', $deviceid);
        foreach ($list as $value) {
            if($value) {
                $device = $_ENV['device']->get_device_by_did($value);
                if(!$device) 
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
                
                if($_ENV['device']->check_user_grant($value, $uid) != 2 || $device['appid'] != $this->appid) 
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                
                if(!$_ENV['device']->check_grant($value, $device['appid']))
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_LIMIT);

                $dev_list[] = $value;
            }
        }

        if(empty($dev_list))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if (count($dev_list) > 20)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_DEVICE_LIST_TOO_LONG);

        $result = $_ENV['device']->grantcode($uid, $grant_type, $dev_list, $this->client_id, $this->appid);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_GRANT_FAILED);

        return $result;
    }

    // 获取授权码信息
    function ongrantinfo() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        $code = $this->input('code');
        $grant = $_ENV['device']->get_grant_by_code($code);
        if(!$grant)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_NOT_EXIST);

        if(!$grant['type'] || $this->time - $grant['dateline'] > 3600)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GRANT_INVALID);

        if($grant['useid'])
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GRANT_USED);

        $result = $_ENV['device']->grantinfo($grant);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_GRANT_INFO_FAILED);

        return $result;
    }

    // 获取m3u8录像片段列表
    function onvodlist() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $starttime = intval($this->input('st'));
        $endtime = intval($this->input('et'));
        
        if(!$deviceid || !$starttime || !$endtime) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->vodlist($device, $starttime, $endtime);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_TS_FAILED);
        
        return $result;
    }

    // 猜你喜欢推荐设备
    function onlistguess() {
        $this->init_input();

        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->user_error();
            
            $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        } else {
            $uid = $support_type = 0;
        }

        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        $result = $_ENV['device']->list_guess($uid, $support_type, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_GET_CHARTS_FAILED);
        
        return $result;
    }

    // 云台移动
    function onmove() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $_ENV['device']->check_device_auth($deviceid);
        if ($auth && $auth['uid']) {
            $uid = $auth['uid'];
        }

        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->user_error(); 

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }

        $delay = $this->input('delay');
        if (!($delay === NULL || $delay === '')) {
            $delay = floor(intval($delay) / 100);
            if($delay < 1 || $delay > 255)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            if(!$_ENV['device']->move_delay($device, $delay)) 
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_SET_DELAY_FAILED);
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
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

                $ret = $_ENV['device']->move_by_direction($device, $direction, $step);
                if(!$ret) 
                    $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_MOVE_DIRECTION_FAILED);
            } else {
               $ret = $_ENV['device']->move_by_point($device, $x1, $y1, $x2, $y2);
                if(!$ret) 
                    $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_MOVE_POINT_FAILED); 
            }
        } else {
            $preset = intval($this->input('preset'));
            if($preset < 0)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $ret = $_ENV['device']->move_by_preset($device, $preset);
            if(!$ret) 
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_MOVE_PRESET_FAILED);
        }

        $result = array();
        $result['result'] = $ret;
        return $result;
    }

    // 云台转动
    function onrotate() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $_ENV['device']->check_device_auth($deviceid);
        if ($auth && $auth['uid']) {
            $uid = $auth['uid'];
        }

        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->user_error(); 

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }

        $rotate = $this->input('rotate');
        if(!$rotate || !in_array($rotate, array('auto', 'stop')))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if(!$_ENV['device']->rotate($device, $rotate)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_ROTATE_FAILED);

        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }

    // 设置云台预置点
    function onaddpreset() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $_ENV['device']->check_device_auth($deviceid);
        if ($auth && $auth['uid']) {
            $uid = $auth['uid'];
        }

        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->user_error();

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }

        $preset = intval($this->input('preset'));
        $title = strval($this->input('title'));

        if($preset < 0 || $preset > 255 || $title === '')
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if(!$_ENV['device']->save_preset($device, $preset, $title, true)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_ADD_PRESET_FAILED);

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['preset'] = $preset;
        $result['title'] = $title;
        return $result;
    }

    // 更新预置点名称
    function onupdatepreset() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $_ENV['device']->check_device_auth($deviceid);
        if ($auth && $auth['uid']) {
            $uid = $auth['uid'];
        }

        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->user_error();

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }

        $preset = intval($this->input('preset'));
        $title = strval($this->input('title'));

        if($preset < 0 || $preset > 255 || $title === '')
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if(!$_ENV['device']->save_preset($device, $preset, $title, false)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_UPDATE_PRESET_FAILED);

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['preset'] = $preset;
        $result['title'] = $title;
        return $result;
    }

    // 删除云台预置点
    function ondroppreset() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $preset = intval($this->input('preset'));
        if(!$deviceid || $preset < 0)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $_ENV['device']->check_device_auth($deviceid);
        if ($auth && $auth['uid']) {
            $uid = $auth['uid'];
        }

        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->user_error();

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }

        if(!$_ENV['device']->drop_preset($deviceid, $preset)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_DROP_PRESET_FAILED);

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['preset'] = $preset;
        return $result;
    }

    // 获取云台预置点列表
    function onlistpreset() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }

        $result = $_ENV['device']->list_preset($deviceid);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_LIST_PRESET_FAILED);

        return $result;
    }

    // 获取预置点上传token
    function ongetpresettoken() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $preset = intval($this->input('preset'));
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if(!$deviceid || $preset<0 || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);
        
        // 时区处理
        $utc = intval($this->input('utc'));
        if(!$utc) {
            $tz_rule = $this->get_timezone_rule_from_timezone_id($device['timezone'], true);
            $time -= $tz_rule['offset'];
        }

        $ret = $_ENV['device']->get_preset_token($device, $preset, $time, $client_id, $sign);
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GET_PRESET_TOKEN_FAILED);
        
        return $ret;
    }

    function onupgrade() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->upgrade($device)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPGRADE_FAILED);

        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }

    function onsensorinfo() {
        $this->init_user();

        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $param = $this->input['param'];

        if (!is_numeric($param))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['device']->get_sensor_info($uid, $param);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            //$this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SENSOR_INFO_FAILED);

        return $result;
    }

    function onaddsensor() {
        $this->init_user();
        
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $name = $this->input['name'];
        $param = $this->input['param'];

        if (!$deviceid || !isset($name) || $name === '' || !is_numeric($param))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $uid_binded = $_ENV['device']->check_sensor_binded($device, $param);
        if ($uid_binded !== false) {
            $user = $_ENV['user']->get_user_by_uid($uid_binded);
            $extras = array();
            $extras['username'] = $user['username'];
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SENSOR_ALREADY_BINDED, NULL, NULL, NULL, $extras);
        }

        $valid = $_ENV['device']->check_sensor_name($name);
        if (!$valid) {
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SENSOR_NAME_ILLEGAL);
        }

        $recommedation = $_ENV['device']->recommendation_sensor_name($device, $name, $param);
        if ($recommedation) {
            $extras = array();
            $extras['name'] = $recommedation;
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SENSOR_NAME_CONFLICT, NULL, NULL, NULL, $extras);
        }

        $result = $_ENV['device']->addsensor_433($device, $param, $name);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_ADD_SENSOR);
        
        return $result;
    }

    function onlistsensor() {
        $this->init_user();
        
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        if (!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $result = $_ENV['device']->listsensor_433($device);
        if (!$result)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_LIST_SENSOR);
        
        return $result;
    }

    function onupdatesensor() {
        $this->init_user();

        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $sensorid = $this->input['sensorid'];        
        $name = $this->input['name'];
        $status = $this->input['status'];
        $nameexist = isset($name);
        $statusvalid = is_numeric($status);
        if (!$sensorid || !$deviceid || (!$nameexist && !$statusvalid))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if ($nameexist) {
            if (!$_ENV['device']->check_sensor_name($name))
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SENSOR_NAME_ILLEGAL);
            if (!$_ENV['device']->check_sensor_name_conflict($uid, $sensorid, $name))
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SENSOR_NAME_CONFLICT);
        }

        $ps = array();
        if ($statusvalid) {
            $ps['status'] = $status;
        }
        if ($nameexist) {
            $ps['name'] = $name;
        }

        $result = $_ENV['device']->update_sensor($device, $sensorid, $ps);
        if (!$result)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_SENSOR_UPDATE_FAILED);

        return $result;
    }

    function ondropsensor() {
        $this->init_user();
        
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $sensorid = $this->input['sensorid'];

        if (!$deviceid || !$sensorid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) {
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $result = $_ENV['device']->dropsensor_433($device, $sensorid);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_DROP_SENSOR);
        
        return $result;
    }

    //检查设备升级状态
    function onupgradestatus() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->get_upgrade_status($device);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);
        
        return $result;
    }

    function oncheckupgrade() {
        $this->init_user();

        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $result = $_ENV['device']->get_upgrade_info($device);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);

        return $result;
    }
    
    function ongetuploadtoken() { 
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $request_no = $this->input('request_no');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        $this->log('tttt uploadtoken', 'deivceid='.$deviceid.', request_no='.$request_no);
        
        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NEED_CONFIG);
        
        if($_ENV['device']->check_blacklist($deviceid))
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NEED_CONFIG);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);
        
        $this->uid = $device['uid'];
        
        /*
        // 时区处理
        $utc = intval($this->input('utc'));
        if(!$utc) {
            $tz_rule = $this->get_timezone_rule_from_timezone_id($device['timezone'], true);
            $time -= $tz_rule['offset'];
        }
        */
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $ret = $_ENV['device']->getuploadtoken($device, $request_no);
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GET_UPLOADTOKEN_FAILED);
        
        $this->log('tttt uploadtoken', 'ret='.json_encode($ret));
        
        if($ret['error'] && $ret['error'] == -1)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NEED_CONFIG);
        
        return $ret;
    }
    
    function onupdatestatus() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $status = intval($this->input('status'));
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        $this->log('tttt updatestatus', 'deivceid='.$deviceid.', status='.$status);
        
        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid.$status, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);
        
        // 时区处理
        $utc = intval($this->input('utc'));
        if(!$utc) {
            $tz_rule = $this->get_timezone_rule_from_timezone_id($device['timezone'], true);
            $time -= $tz_rule['offset'];
        }
        
        $this->log('tttt updatestatus', 'deivceid='.$deviceid.', time='.$time);
        
        if($time > $this->time + 10*60 || $time < $this->time + 10*60) {
            $time = $this->time;
        }

        $ret = $_ENV['device']->updatestatus($device, $status, $time);
        /*
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_UPDATE_STATUS_FAILED);
        */
        
        $this->log('tttt updatestatus', 'deivceid='.$deviceid.', time='.$time.', ret='.json_encode($ret));
        
        return array();
    }
    
    function ononline() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        $this->log('device_online', 'deivceid='.$deviceid);
        
        /*
        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        */
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $ret = $_ENV['device']->on_device_online($device);
        /*
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_UPDATE_STATUS_FAILED);
        */
        
        $this->log('device_online', 'deivceid='.$deviceid.', ret='.json_encode($ret));
        
        return array();
    }
    
    function onuploadcvralarm() { 
        return array('cvr_alarm' => 1);
    }
    
    function onsum() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);
      
        $result = $_ENV['device']->sum($uid, $support_type, $this->appid);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SUM_FAILED);

        return $result;
    }

    // 添加截屏
    function onsnapshot() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $uid = 0;
        
        if ($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if(!$share)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
                
            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
            
            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $device['share_device'] = 1;
        } else {
            if (!$deviceid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            } elseif ($this->init_user()) {
                $uid = $this->uid;
            } else {
                $this->user_error();
            }
            
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }
        }
        
        $notify = stripcslashes(rawurldecode($this->input('notify')));
        if ($notify) {
            if (!is_array($param = json_decode($notify, true)))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NOTIFY_FORMAT_ILLEGAL);

            $notify = $_ENV['device']->serialize_notify($param);
            if (!$notify)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NOTIFY_SERIALIZE_ILLEGAL);
        }

        $result = $_ENV['device']->snapshot($device, $notify, $uid);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_ADD_SNAPSHOT_FAILED);

        return $result;
    }

    // 删除截屏
    function ondropsnapshot() {
        $this->init_input();
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $deviceid = $this->input('deviceid');
        $sid = $this->input('sid');
        if(!$sid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if ($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if(!$share)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
                
            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
            
            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $device['share_device'] = 1;
            $uid = 0;
        } else {
            if(!$deviceid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            }
            
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
        
            if(!$uid)
                $this->user_error();

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if(!$_ENV['device']->check_user_grant($deviceid, $uid))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $snapshot_list = array();
        $list = explode(',', $sid);
        foreach ($list as $value) {
            if($value) {
                $snapshot = $_ENV['device']->get_snapshot_by_sid($value);
                if (!$snapshot)
                    $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SNAPSHOT_NOT_EXIST);
                
                if($snapshot['deviceid'] != $deviceid || $snapshot['uid'] != $uid) 
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_HANDLE_SNAPSHOT_REFUSED);

                $snapshot_list[] = $value;
            }
        }

        if(!$_ENV['device']->drop_snapshot($snapshot_list)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_DROP_SNAPSHOT_FAILED);

        $result = array('deviceid' => $deviceid);
        
        return $result;
    }

    // 截屏信息
    function onsnapshotinfo() {
        $this->init_input();
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $deviceid = $this->input('deviceid');
        $sid = $this->input('sid');
        if(!$sid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if ($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if(!$share)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
                
            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
            
            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $device['share_device'] = 1;
            $uid = 0;
        } else {
            if(!$deviceid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            }
         
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
        
            if(!$uid)
                $this->user_error();

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if(!$_ENV['device']->check_user_grant($deviceid, $uid))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }
        
        $snapshot = $_ENV['device']->get_snapshot_by_sid($sid);
        if (!$snapshot || !$snapshot['status'])
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SNAPSHOT_NOT_EXIST);
        
        if($snapshot['deviceid'] != $device['deviceid'] || $snapshot['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_HANDLE_SNAPSHOT_REFUSED);

        $result = $_ENV['device']->get_snapshot_info($snapshot);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_GET_SNAPSHOT_INFO_FAILED);

        return $result;
    }

    // 获取设备截屏列表
    function onlistsnapshot() {
        $this->init_input();
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        $deviceid = $this->input('deviceid');
        $uid = '';
        
        if ($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if(!$share)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
                
            if ($share['expires'] && $share['dateline']+$share['expires']<$this->time)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);
            
            $device = $_ENV['device']->get_device_by_did($share['deviceid']);
            if (!$device || $device['reportstatus'])
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_SHARE_NOT_EXIST);

            if ($uk != $share['uid'] && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if ($share['password'] !== strtolower($this->input['password']))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_BAD_SHARE_PASSWORD);
            
            $_ENV['device']->init_vars_by_deviceid($device['deviceid']);

            $device['share_device'] = 1;
            $uid = 0;
        } else {
            if(!$deviceid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
            $auth = $_ENV['device']->check_device_auth($deviceid);
            if ($auth && $auth['uid']) {
                $uid = $auth['uid'];
            }

            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
        
            if(!$uid)
                $this->user_error();

            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            
            $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
            if(!$check_grant)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            if($check_grant == 1) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            }
        }

        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if(!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['device']->list_snapshot($device, $list_type, $page, $count, $uid);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_LIST_SNAPSHOT_FAILED);

        return $result;
    }

    // 获取预置点上传token
    function ongetsnapshottoken() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $sid = intval($this->input('sid'));
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if (!$deviceid || !$sid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if (!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $snapshot = $_ENV['device']->get_snapshot_by_sid($sid);
        if (!$snapshot)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_SNAPSHOT_NOT_EXIST);
        
        if (!$snapshot['uid']) {
            $device['share_device'] = 1;
        } else if ($device['uid'] != $snapshot['uid']) {
            if($_ENV['device']->check_user_grant($deviceid, $snapshot['uid'])) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $snapshot['uid'];
            } else {
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        // 时区处理
        $utc = intval($this->input('utc'));
        if (!$utc) {
            $tz_rule = $this->get_timezone_rule_from_timezone_id($device['timezone'], true);
            $time -= $tz_rule['offset'];
        }

        $ret = $_ENV['device']->get_snapshot_token($device, $snapshot, $time, $client_id, $sign);
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GET_SNAPSHOT_TOKEN_FAILED);
        
        return $ret;
    }
    
    function onalarmsetting() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $result = $_ENV['device']->alarmsetting($uid);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);
        
        return $result;
    }
    
    function onupdatealarmsetting() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input();
        
        $fileds = stripcslashes(rawurldecode($this->input('fileds')));
        if($fileds) {
            $fileds = json_decode($fileds, true);
        }
        
        if(!$fileds)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        // check weixin
        if($fileds['weixin_push'] !== NULL) {
            $wx_connect = $_ENV['user']->get_connect_by_uid(API_WEIXIN_CONNECT_TYPE, $uid);
            if(!$wx_connect) {
                $this->error(API_HTTP_BAD_REQUEST, WEIXIN_ERROR_NOT_BIND);
            }
        }
        
        $result = $_ENV['device']->updatealarmsetting($uid, $fileds);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        return $result;
    }
    
    function onlistcvrrecord() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];

        if (!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $_ENV['device']->check_device_auth($deviceid);
        if ($auth && $auth['uid']) {
            $uid = $auth['uid'];
        } elseif ($this->init_user()) {
            $uid = $this->uid;
        } else {
            $this->user_error();
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) {
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $result = $_ENV['device']->list_cvr_record($device);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_LIST_CVR_RECORD_FAILED);

        return $result;
    }

    function onaddcontact() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $name = $this->input['name'];
        $phone = $this->input['phone'];

        if (!$deviceid || !$name || !$phone)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $list = $_ENV['device']->list_contact($deviceid);
        if (count($list) >= 5)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_CONTACT_LIMIT);

        $id = $_ENV['device']->add_contact($deviceid, $name, $phone);
        if (!$id)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ADD_CONTACT_FAILED);

        $result = array(
            'id' => intval($id),
            'name' => $name,
            'phone' => $phone
        );

        return $result;
    }

    function onlistcontact() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];

        if (!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $list = $_ENV['device']->list_contact($deviceid);

        $result = array(
            'count' => count($list),
            'list' => $list
        );

        return $result;
    }

    function onupdatecontact() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $id = intval($this->input['id']);
        $name = $this->input['name'];
        $phone = $this->input['phone'];

        if (!$id || !$name || !$phone)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $contact = $_ENV['device']->get_contact_by_id($id);
        if (!$contact)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_CONTACT_NOT_EXIST);

        $device = $_ENV['device']->get_device_by_did($contact['deviceid']);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($contact['deviceid'], $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if (!$_ENV['device']->update_contact($id, $name, $phone))
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_UPDATE_CONTACT_FAILED);

        $result = array(
            'id' => $id,
            'name' => $name,
            'phone' => $phone
        );

        return $result;
    }

    function ondropcontact() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $id = intval($this->input['id']);

        if (!$id)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $contact = $_ENV['device']->get_contact_by_id($id);
        if (!$contact)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_CONTACT_NOT_EXIST);

        $deviceid = $contact['deviceid'];
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if (!$_ENV['device']->drop_contact($id))
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_DROP_CONTACT_FAILED);

        $result = array(
            'id' => $id,
            'name' => $contact['name'],
            'phone' => $contact['phone']
        );

        return $result;
    }
    
    function onalarmspace() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];

        if (!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) {
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $result = $_ENV['device']->alarmspace($device);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_ALARM_SPACE_FAILED);

        return $result;
    }
    
    function onmediastatus() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];

        if (!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }
        
        if(!$_ENV['device']->is_support_media($device))
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NOT_SUPPORT_MEDIA);

        $result = $_ENV['device']->mediastatus($device);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_PLAY_STATUS_FAILED);

        return $result;
    }
    
    function onmediaplay() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $type = $this->input['type'];
        $action = $this->input['action'];

        if (!$deviceid || !$type || !$action)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if (!in_array($type, array('audio')))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if (!in_array($action, array('stop', 'pause', 'start', 'continue', 'next', 'prev', 'set')))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        if ($type == 'audio') {
            if ($action == 'start') {
                $albumid = $this->input['albumid'];
                $trackid = $this->input['trackid'];
                if (!$albumid || !$trackid)
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
                $this->load('audio');
                $album = $_ENV['audio']->get_album_by_id($albumid);
                if(!$album)
                    $this->error(API_HTTP_BAD_REQUEST, AUDIO_ERROR_ALBUM_NOT_EXSIT);
            
                $track = $_ENV['audio']->get_track_by_id($trackid);
                if(!$track)
                    $this->error(API_HTTP_BAD_REQUEST, AUDIO_ERROR_TRACK_NOT_EXSIT);
                
                $params = array(
                    'album' => $album,
                    'track' => $track
                );
                
                if(isset($this->input['mode'])) $params['mode'] = intval($this->input['mode']);
            } else if ($action == 'set') {
                if(!isset($this->input['mode']))
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
                
                $params = array(
                    'mode' => intval($this->input['mode'])
                );
            }
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        $check_grant = $_ENV['device']->check_user_grant($deviceid, $uid);
        if(!$check_grant)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($check_grant == 1) {
            $device['grant_device'] = 1;
            $device['grant_uid'] = $uid;
        }

        $result = $_ENV['device']->mediaplay($device, $type, $action, $params);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_PLAY_FAILED);

        return $result;
    }
    
    function onmediainfo() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);
        
        $next = intval($this->input('next'));
        if($next) {
            $status = $_ENV['device']->mediastatus($device);
            $type = $status && $status['type']?$status['type']:'audio';
            $ret = $_ENV['device']->mediaplay($device, $type, 'next', array('auto' => 1), FALSE);
            if(!$ret)
                $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_PLAY_FAILED);
        }

        $status = $_ENV['device']->mediastatus($device);
        if(!$status)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_PLAY_STATUS_FAILED);
        
        if($status['type'] == 'audio' && $status['status'] != 0 && $status['trackid']) {
            $this->load('audio');
            $track = $_ENV['audio']->get_track_by_id($status['trackid']);
            if($track) $status['track'] = $_ENV['audio']->_format_track($track);
        }
        
        return $status;
    }
    
    function onupdatemediastatus() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input['type'];
        $status = intval($this->input['status']);
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if(!$deviceid || !$type || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if(!in_array($type, array('audio')))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid.$status, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $params = array();
        if ($type == 'audio') {
            $albumid = $this->input['albumid'];
            $trackid = $this->input['trackid'];
            $size = intval($this->input['size']);
            $offset = intval($this->input['offset']);
            if (!$albumid || !$trackid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $params = array(
                'albumid' => $albumid,
                'trackid' => $trackid,
                'size' => $size,
                'offset' => $offset
            );
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $result = $_ENV['device']->updatemediastatus($device, $type, $status, $time, $params);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_PLAY_STATUS_FAILED);

        return $result;
    }

    function onnotify() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $request_no = $this->input('request_no');
        $data = $this->input('data');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');

        if (!$deviceid || !$request_no || !$data || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid.$request_no, $client_id, $time);
        if (!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);
        
        if (!$_ENV['device']->notify($device, $request_no, $data))
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NOTIFY_FAILED);
        
        return array('deviceid' => $deviceid);
    }

    function ondvrstatus() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $udid = $this->input('udid');
        $keeplive = $this->input('keeplive');

        if (!$deviceid || !$udid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $dvrplay = $_ENV['device']->get_dvrplay($device);
        $status = $dvrplay['status'] ? 1 : 0;

        if ($status && $dvrplay['udid'] != $udid)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_DVRPLAY_IN_USE);

        if ($keeplive) {
            $keeplive = $status ? DVR_KEEPLIVE_INTERVAL*2+$this->time : 0;
            $_ENV['device']->update_dvrplay_keeplive($deviceid, $keeplive);
        }

        $result = array(
            'status' => $status,
            'interval' => DVR_KEEPLIVE_INTERVAL
        );

        return $result;
    }

    function onupdatedvrstatus() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if (!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $status = intval($this->input('status')) ? 1 : 0; // 1播放，0停止
        
        $check = $this->_check_device_sign($sign, $deviceid.$status, $client_id, $time);
        if (!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        if (!$_ENV['device']->update_dvrplay_status($deviceid, $status))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_DVRPLAY_STATUS_FAILED);

        return array('deviceid' => $deviceid);
    }

    function ondvrlist() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $starttime = intval($this->input('st'));
        $endtime = intval($this->input('et'));
        
        if (!$deviceid || !$starttime || !$endtime || $starttime > $endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if (!in_array($type, array('sum', 'daily'))) {
            $type = 'daily';
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $result = $_ENV['device']->dvrlist($device, $type, $starttime, $endtime);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_DVRLIST_FAILED);

        return $result;
    }

    function ondvrplay() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $udid = $this->input('udid');
        $action = $this->input('action');
        $starttime = intval($this->input('st'));
        $endtime = intval($this->input('et'));

        if (!$deviceid || !$udid || !$action || $starttime > $endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if (!in_array($action, array('start', 'stop'))) {
            $action = 'start';
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $dvrplay = $_ENV['device']->get_dvrplay($device);
        $status = $dvrplay['status'] ? 1 : 0;

        $owner = 1;
        if ($status && $dvrplay['udid'] != $udid) {
            if ($action != 'start')
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_DVRPLAY_IN_USE);

            $owner = 0;
        }
        
        // 设备控制
        if ($owner) {
            $ret = $_ENV['device']->dvrplay($device, $udid, $action, $status, $starttime, $endtime);
            if (!$ret)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_DVRPLAY_FAILED);
        }
        
        // 返回数据
        if ($action == 'start') {
            $type = $this->input('type');
            if (!in_array($type, array('p2p', 'hls'))) {
                $type = 'rtmp';
            }
            
            $result = $_ENV['device']->liveplay($device, $type, array('auth_type'=>'token', 'dvrplay'=>1), $this->client_id);
            
            if (!$owner)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_DVRPLAY_IN_PLAY, NULL, NULL, NULL, $result);
        } else {
            $result = array('deviceid' => $deviceid);
        }

        return $result;
    }
    
    function onupdatecvr() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $cvr_type = $this->input('cvr_type');
        $cvr_day = $this->input('cvr_day');
        $cvr_end_time = intval($this->input('cvr_end_time'));
        $cvr_free = intval($this->input('cvr_free'));

        if (!$deviceid || $cvr_type == NULL || $cvr_day == NULL)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $cvr_free = $cvr_free?1:0; 

        if (!in_array($cvr_type, array(0, 1, 2)) || (!$cvr_free && $cvr_end_time < $this->time)) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$this->partner_id || !$this->partner || !$this->partner['allow_cvr_update'] || !$_ENV['device']->check_partner_device($this->partner_id, $deviceid)) {
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $ret = $_ENV['device']->update_device_cvr_by_partner($device, $this->partner, $cvr_type, $cvr_day, $cvr_end_time, $cvr_free);
        if(!$ret)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_UPDATE_FAILED);

        $result = array(
            'deviceid' => $deviceid
        );
        return $result;
    }

    function onlistlogserver() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $server = $_ENV['device']->listlogserver($device);
        if(!$server)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_LIST_LOG_SERVER_FAILED);
        
        $result = array();
        $result['count'] = count($server);
        $result['server_list'] = $server;
        return $result;
    }
    
    function onlistrelayserver() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $server = $_ENV['device']->listrelayserver($device);
        if(!$server)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_LIST_RELAY_SERVER_FAILED);
        
        $result = array();
        $result['count'] = count($server);
        $result['server_list'] = $server;
        return $result;
    }
    
    function ongetdevicesetting() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $request_no = $this->input('request_no');
        $firmware = $this->input('firmware');
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');
        
        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NEED_CONFIG);
        
        if($_ENV['device']->check_blacklist($deviceid))
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NEED_CONFIG);
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);
        
        $this->uid = $device['uid'];
        
        /*
        // 时区处理
        $utc = intval($this->input('utc'));
        if(!$utc) {
            $tz_rule = $this->get_timezone_rule_from_timezone_id($device['timezone'], true);
            $time -= $tz_rule['offset'];
        }
        */
        
        $_ENV['device']->init_vars_by_deviceid($deviceid);

        $ret = $_ENV['device']->getdevicesetting($device, $request_no);
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GET_UPLOADTOKEN_FAILED);
        
        if($ret['error'] && $ret['error'] == -1)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NEED_CONFIG);
        
        return $ret;
    }

    //判断是否企业用户添加设备
    function org_user_register($uid, $deviceid){
        if(!$uid || !$deviceid){
            return false;
        }
        $org = $_ENV['org']->getorgidbyuid($uid);
        if(!$org){
            return true;
        }
        $checkorg = $_ENV['org']->getorginfobyorgid($org['org_id']);
        if(!$checkorg){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_CHECKAUTH_FAILED);
        }
        //企业子用户添加授权表
        if($org['admin']==0){
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            $user = $_ENV['user']->get_user_by_uid($uid);
            if(!$user)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $_ENV['device']->enterprise_register_grant($device, $user, $this->appid, $this->client_id, $org['org_id']);
        }
        $ret = $_ENV['org']->orgdevice_insert($org['org_id'], $deviceid);
        $this->log('org_device', 'org_id='.$org['org_id'].'&device_id='.$deviceid);
        return $ret;
    }

    /**
     * 返回bmp水印图片
     * @return mixed
     */
    function onosd(){
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $alpha = $this->input('alpha'); //透明度
        $fontsize = $this->input('fontsize'); //字体大小
        $textcolor = $this->input('textcolor'); //字体颜色
        if($textcolor){
            $textcolorArr = explode(',' , $textcolor);
        }else{
            $textcolorArr = null;
        }

        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');

        $this->log('tttt uploadtoken', 'deivceid='.$deviceid);

        if(!$deviceid || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        // 验证权限
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);

        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        $result = $_ENV['osd']->getosd( $deviceid , $type , $alpha, $textcolorArr, $fontsize);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST , DEVICE_ERROR_GET_WATERMARK_FAILED);
        return $result;
    }

}