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
    }
    
    function onaddusertoken() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
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
        if(!$device || $device['uid'] != $uid)
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
        
        $result = array();
        $result['access_token'] = $token['access_token'];
        return $result;
    }
    
    function onregister() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $desc = addslashes(urldecode($this->input['desc']));
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $connect_type = $this->input['connect_type'];
        if(!$connect_type) {
            $connect_type = $_ENV['oauth2']->get_client_connect_type($this->client_id);
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
            if($device['connect_type'] == 0 && $device['uid'] != $uid) {
                $connect = $_ENV['user']->get_connect_by_uid($connect_type, $device['uid']);
                if($connect && $connect['username']) {
                    $extras['username'] = $connect['username'];
                } 
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ALREADY_REG, NULL, NULL, NULL, $extras);
            }
        }
        
        $device = $_ENV['device']->device_register($uid, $deviceid, $connect_type, $desc, $this->appid, $this->client_id, $timezone, $location_latitude, $location_longitude);
        if(!$device)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_FAILED);
        
        // init
        $_ENV['device']->device_init($device);
        
        // status
        $_ENV['device']->update_status($deviceid, $_ENV['device']->get_status(-1));
        
        $result = array();
        $result['deviceid'] = $device['deviceid'];
        $result['stream_id'] = $device['stream_id'];
        $result['connect_type'] = $device['connect_type'];
        $result['connect_did'] = $device['connect_did'];
        return $result;
    }
    
    function onupdate() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $desc = addslashes(urldecode($this->input['desc']));
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($device['desc'] != $desc && !$_ENV['device']->update_desc($device, $desc)) {
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_FAILED);
        }

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['desc'] = $this->input['desc'];
        return $result;
    }
    
    function onlist() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $share = $this->input('share');
        $online = $this->input('online');
        $category = $this->input('category');
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

        if(!$data_type || !in_array($data_type, array('my', 'grant', 'sub', 'all'))) {
            $data_type = 'my';
        }

        if(!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        
        $result = $_ENV['device']->list_devices($uid, $support_type, $device_type, $this->appid, $share, $online, $category, $orderby, $data_type, $list_type, $page, $count, $dids);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NOT_EXIST);
        
        return $result;
    }
    
    function onvalidate() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        return array();
    }
    
    function oncreateshare() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $share_type = intval($this->input['share']);
        $title = $this->input['title'];
        $intro = $this->input['intro'];
        
        if(!$deviceid || !$share_type) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$_ENV['device']->create_share($uid, $device, $title, $intro, $share_type, $this->appid, $this->client_id)) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_CREATE_SHARE_FAILED);
        
        $share = $_ENV['device']->get_share($deviceid);
        if(!$share) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_CREATE_SHARE_FAILED);
        
        $result = array();
        $result['shareid'] = $share['shareid'];
        $result['uk'] = $share['connect_uid'];
        return $result;
    }
    
    function oncancelshare() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $share_type = intval($this->input['share']);
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$_ENV['device']->cancel_share($device)) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_CANCEL_SHARE_FAILED);
        
        return array();
    }
    
    function onlistshare() {
        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        } else {
            $uid = 0;
        }
        
        $this->init_input();
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);

        if(!$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
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

        if($start<0 || $num<0)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if($start || $num) {
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $share = $this->input('share');
        $online = $this->input('online');
        $category = $this->input('category');
        $orderby = $this->input('orderby');

        $share = ($share === NULL || $share === '') ? -1 : intval($share);
        $online = ($online === NULL || $online === '') ? -1 : intval($online);
        $category = ($category === NULL || $category === '') ? -1 : intval($category);
        
        $support_type = $_ENV['oauth2']->get_client_connect_support($this->client_id);

        return $_ENV['device']->listsubscribe($uid, $support_type, $this->appid, $share, $online, $category, $orderby);
    }
    
    function ongrant() {
        $params = array();
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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

        if(!$deviceid || !$uk || ($uk == $uid)) 
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
                
                if($device['uid'] != $uid || $device['appid'] != $this->appid) 
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                
                if(!$_ENV['device']->check_grant($value, $device['appid']))
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_LIMIT);

                $dev_list[] = $device;
            }
        }

        if(empty($dev_list))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['device']->grant($dev_list, $user, $auth_code, $code, $this->appid, $this->client_id);
        if(!$result)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_FAILED);

        return $result;
    }
    
    function onlistgrantuser() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        return $_ENV['device']->listgrantuser($uid, $deviceid, $device['connect_type'], $this->appid, $this->client_id);
    }
    
    function onlistgrantdevice() {
        $this->init_input();
        
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
        $this->init_input();
        $uk = intval($this->input['uk']);
        $deviceid = $this->input['deviceid'];
        
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        if(!$deviceid || !$uk) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid || $device['appid'] != $this->appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if(!$_ENV['device']->dropgrantuser($device, $uk)) {
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_GRANT_DELETE_FAILED);
        }
        
        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }
    
    function ondropgrantdevice() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
        
        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }
    
    function onliveplay() {
        $this->init_input();
        
        $params = array();
        
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        if($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if($share) {
                $deviceid = $share['deviceid'];
                $uid = $share['uid'];
            
                if($uk != $uid && $uk != $share['connect_uid'])
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
                $uk = $share['connect_uid'];
            }
            
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;
        } else {
            $deviceid = $this->input['deviceid'];
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $uid = $_ENV['device']->check_device_auth($deviceid);
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
            
            if(!$uid) 
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
            
            $params['auth_type'] = 'token';
        }
        
        if($deviceid) {
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
            if($params['auth_type'] == 'token') {
                if($device['uid'] != $uid) {
                    if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                        $device['grant_device'] = 1;
                        $device['grant_uid'] = $uid;
                    } else {
                         $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                    }
                }
            }
        }
        
        $type = $this->input('type');
        if(!$type || !in_array($type, array('p2p', 'hls'))) {
            $type = 'rtmp';
        }
        
        $result = $_ENV['device']->liveplay($device, $type, $params, $this->client_id?$this->client_id:$device['client_id']);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_LIVE_URL_FAILED);
        
        return $result;
    }
    
    function onplaylist() {
        $this->init_input();
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        
        if(!$starttime || !$endtime || $endtime <= $starttime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        if($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if($share) {
                $deviceid = $share['deviceid'];
                $uid = $share['uid'];
            
                if($uk != $uid && $uk != $share['connect_uid'])
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
                $uk = $share['connect_uid'];
            }
            
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;
        } else {
            $deviceid = $this->input['deviceid'];
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $uid = $_ENV['device']->check_device_auth($deviceid);
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
            
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
            
            $params['auth_type'] = 'token';
        }
        
        if($deviceid) {
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
            if($params['auth_type'] == 'token') {
                if($device['uid'] != $uid) {
                    if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                        $device['grant_device'] = 1;
                        $device['grant_uid'] = $uid;
                    } else {
                         $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                    }
                }
            }
        }

        $result = $_ENV['device']->playlist($device, $params, $starttime, $endtime);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_PLAYLIST);
        
        return $result;
    }
    
    function onvod() {
        $this->init_input();
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        
        if(!$starttime || !$endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        if($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if($share) {
                $deviceid = $share['deviceid'];
                $uid = $share['uid'];
            
                if($uk != $uid && $uk != $share['connect_uid'])
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                
                $uk = $share['connect_uid'];
            }
            
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;
        } else {
            $deviceid = $this->input['deviceid'];
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $uid = $_ENV['device']->check_device_auth($deviceid);
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
            
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
            
            $params['auth_type'] = 'token';
        }
        
        if($deviceid) {
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
            if($params['auth_type'] == 'token') {
                if($device['uid'] != $uid) {
                    if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                        $device['grant_device'] = 1;
                        $device['grant_uid'] = $uid;
                    } else {
                         $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                    }
                }
            }
        }
        
        $result = $_ENV['device']->vod($device, $params, $starttime, $endtime);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_PLAYLIST);
        
        return $result;
    }
    
    function onvodseek() {
        $this->init_input();

        $time = intval($this->input['time']);   
        if(!$time)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        if($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if($share) {
                $deviceid = $share['deviceid'];
                $uid = $share['uid'];
            
                if($uk != $uid && $uk != $share['connect_uid'])
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                
                $uk = $share['connect_uid'];
            }
            
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;
        } else {
            $deviceid = $this->input['deviceid'];
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $uid = $_ENV['device']->check_device_auth($deviceid);
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
            
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
            
            $params['auth_type'] = 'token'; 
        }
        
        if($deviceid) {
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
            if($params['auth_type'] == 'token') {
                if($device['uid'] != $uid) {
                    if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                        $device['grant_device'] = 1;
                        $device['grant_uid'] = $uid;
                    } else {
                         $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                    }
                }
            }
        }
        
        $result = $_ENV['device']->vodseek($device, $params, $time);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_TS);
        
        return $result;
    }
    
    function onthumbnail() {
        $this->init_input();
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        $latest = intval($this->input('latest'));
        $download = intval($this->input('download'));
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        
        if(!$latest && (!$starttime || !$endtime))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if(!$latest && $endtime <= $starttime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $params = array();
        if($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if(!$share)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            
            $deviceid = $share['deviceid'];
            $uid = $share['uid'];
            
            if($uk != $uid  && $uk != $share['connect_uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $share['connect_uid'];
        } else {
            $deviceid = $this->input['deviceid'];
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $uid = $_ENV['device']->check_device_auth($deviceid);
            if(!$uid) {
                $this->init_user();
                $uid = $this->uid;
            }
            
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN); 
            
            $params['auth_type'] = 'token'; 
        }
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if($params['auth_type'] == 'token') {
            if($device['uid'] != $uid) {
                if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                    $device['grant_device'] = 1;
                    $device['grant_uid'] = $uid;
                } else {
                     $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                }
            }
        }
        
        $result = $_ENV['device']->get_thumbnail($device, $params, $starttime, $endtime, $latest); 
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NO_THUMBNAIL);

        if($latest && $download) {
            $image = $result['list'] ? $result['list'][0]['url'] : 'http://www.iermu.com/images/camera-logo.png';
            header('Location:' . $image);
            exit();
        }
        
        return $result;
    }
    
    function ondropvideo() {
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $starttime = intval($this->input['st']);
        $endtime = intval($this->input['et']);
        
        if(!$deviceid || !$starttime || !$endtime)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->drop_cvr($deviceid, $device['uid'], $starttime, $endtime)) 
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $starttime = intval($this->input('st'));
        $endtime = intval($this->input('et'));
        $name = $this->input('name');
        
        if(!$deviceid || !$starttime || !$endtime || !$name) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->clip($device, $starttime, $endtime, $name);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_CLIP_FAILED);
        
        return $result;
    }
    
    function oninfoclip() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        
        if(!$deviceid || !$type) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->infoclip($device, $type);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_NETWORK);
        
        return $result;
    }

    function onlistclip() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $page = $this->input['page'];
        $count = $this->input['count'];
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->listdeviceclip($device, $page, $count);
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
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
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        if($device['uid'] != 0 && !$_ENV['device']->drop($device)) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_DROP_FAILED);
        
        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }
    
    function oncontrol() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input['deviceid'];
        $command = stripcslashes(rawurldecode($this->input['command']));
     
        if(!$deviceid || !$command) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        // usercmd
        $ret = $_ENV['device']->control($device, $command);
        if(!$ret)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_USER_CMD_FAILED);
        
        return $ret;
    }
    
    function onsetting() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $fileds = $this->input('fileds');
        
        $this->log('get device setting '.$deviceid, json_encode($this->input));
     
        if(!$deviceid || !$type || !in_array($type, array('info', 'status', 'power', 'email', 'cvr', 'alarm', 'capsule', 'plat', 'bit', 'bw', 'night', 'normal'))) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $uid = $_ENV['device']->check_device_auth($deviceid);
        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN); 
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
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
        
        $uid = $_ENV['device']->check_device_auth($deviceid);
        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN); 
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        
        $paltform = $_ENV['oauth2']->get_client_platform($this->client_id);
        
        $result = $_ENV['device']->updatesetting($device, $fileds, $paltform);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        $this->log('update device setting success '.$deviceid, json_encode($result));
        
        return $result;
    }
    
    function onalarmpic() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        if(!$deviceid) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->alarmpic($device, $page, $count);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_LIST_ALARM_FAILED);
        
        return $result;
    }
    
    function ondropalarmpic() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $path = $this->input('path');
        if($path) {
            $path = split(",", $path);
        }
        
        if(!$deviceid || !$path || !is_array($path) || count($path) > 150)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->dropalarmpic($device, $path);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_DROP_ALARM_FAILED);
        
        return $result;
    }
    
    function ondownloadalarmpic() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $path = $this->input('path');
        
        if(!$deviceid || !$path)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        
        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $result = $_ENV['device']->downloadalarmpic($device, $path);
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
        $deviceid = $this->input['deviceid'];
        
        $params = array();
        $param = $this->input['param'];
        $params['param'] = $param ? $param : '';
        
        if($deviceid) {
            $auth = $_ENV['device']->check_device_auth($deviceid);
            if($auth) {
                $uid = $auth;
                $params['uid'] = $uid;
            } 
        }
        
        if(!$uid) {
            if($this->init_user()) {
                $uid = $this->uid;
                if(!$uid)
                    $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

                $params['uid'] = $uid;
            } else {
                $params['uid'] = 0;
            }
        }
        
        $shareid = $this->input['shareid'];
        $uk = $this->input['uk'];
        if($shareid && $uk) {
            $share = $_ENV['device']->get_share_by_shareid($shareid);
            if($share) {
                $deviceid = $share['deviceid'];
                $uid = $share['uid'];
            
                if($uk != $uid && $uk != $share['connect_uid'])
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            
                $uk = $share['connect_uid'];
            } 
            
            $params['auth_type'] = 'share';
            $params['shareid'] = $shareid;
            $params['uk'] = $uk;
        } elseif ($params['uid']) {
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $params['auth_type'] = 'token';
        } else {
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        }
        
        if($deviceid) {
            $device = $_ENV['device']->get_device_by_did($deviceid);
    
            if($params['auth_type'] == 'token') {
                if($device['uid'] != $uid) {
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                }
            }

            if(!$device) 
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        }
        
        $result = $_ENV['device']->meta($device, $params);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_NOT_EXIST);
        
        return $result;
    }

    // 获取用户分类
    function onlistcategory() {
        $this->init_user();

        $uid = $this->uid;

        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $result = $_ENV['user']->list_category($uid);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_GET_CATEGORY_FAILED);
        
        return $result;
    }

    // 保存用户分类
    function onaddcategory() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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

            $deviceid = $share['deviceid'];
        } else {
            $this->init_user();
        
            $uid = $this->uid;
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
            
            $deviceid = $this->input('deviceid');
            if(!$deviceid) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if($uid != $device['uid'])
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $st = intval($this->input('st'));
        $et = intval($this->input('et'));

        if(!$list_type || !in_array($list_type, array('page', 'timeline'))) {
            $list_type = 'page';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        $st = $st > 0 ? $st : 0;
        $et = $et > 0 ? $et : 0;

        $result = $_ENV['device']->list_comment($deviceid, $list_type, $page, $count, $st, $et);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_GET_COMMENT_FAILED);
        
        return $result;
    }

    // 添加设备评论
    function oncomment() {
        $this->init_user();

        $uid = $this->uid;

        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if(!$device['commentstatus'])
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ADD_COMMENT_NOT_ALLOWED);

        $comment = $this->input('comment');
        $parent_cid = $this->input('parent_cid');

        $result = $_ENV['device']->save_comment($uid, $deviceid, $comment, $parent_cid, $this->client_id, $this->appid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_COMMENT_FAILED);

        $user = $_ENV['user']->_format_user($uid);
        $result['comment']['uid'] = $user['uid'];
        $result['comment']['username'] = $user['username'];
        $result['comment']['avatar'] = $user['avatar'];
        
        return $result;
    }

    // 获取观看记录
    function onlistview() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = intval($this->input('type'));
        $reason = $this->input('reason');

        $type = $type > 0 ? $type : 0;

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        // 保存举报记录
        $_ENV['device']->save_report($uid, $deviceid, $type, $reason, $this->client_id, $this->appid);

        // 获取帐号的管理状态
        $admin = $_ENV['user']->get_admin_by_uid($uid);
        $result = $_ENV['device']->add_report($admin, $deviceid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_ADD_REPORT_FAILED);

        if ($admin) {
            $subject = '通知!设备号为'.$deviceid.'的设备已被管理员"'.$admin['username'].'"踢下线!';
            $message = '<p><img src="'.$_ENV['device']->get_device_thumbnail($device).'"></p>'
                       .'<p>设备号为'.$deviceid.'的设备已被管理员"'.$admin['username'].'"踢下线!</p>';

            $_ENV['device']->send_email_to_admin($subject, $message);
        } else {
            $num = $_ENV['device']->get_report_in_hour($deviceid);
            if($num > 20) {
                $share = $_ENV['device']->get_share_by_deviceid($deviceid);
                if (!$share) {
                    $subject = '警告!设备号为'.$deviceid.'的设备未分享,被用户多次举报,请核查!';
                    $message = '<p><img src="'.$_ENV['device']->get_device_thumbnail($device).'"></p>'
                               .'<p>设备号为'.$deviceid.'的设备未分享,被用户多次举报,无法查看直播内容</p>';
                } else {
                    $subject = '警告!设备号为'.$deviceid.'的设备已分享,被用户多次举报,请核查!';
                    $message = '<p><img src="'.$_ENV['device']->get_device_thumbnail($device).'"></p>'
                               .'<p>设备号为'.$deviceid.'的设备已分享,被用户多次举报,分享地址为<a href="http://www.iermu.com/video/'.$share['shareid'].'/'.($share['uid']?$share['uid']:$share['connect_uid']).'" target="_blank">点击这里</a></p>';
                }
                $_ENV['device']->send_email_to_admin($subject, $message);
            }
        }
        
        return $result;
    }

    // 获取授权码
    function ongrantcode() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
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
                
                if($device['uid'] != $uid || $device['appid'] != $this->appid) 
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $starttime = intval($this->input('st'));
        $endtime = intval($this->input('et'));
        
        if(!$deviceid || !$starttime || !$endtime) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device || !$device['uid']) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
    
        if($device['uid'] != $uid) 
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
                $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
            
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
        
        $uid = $_ENV['device']->check_device_auth($deviceid);
        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN); 
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }

        $preset = $this->input('preset');
        if($preset === NULL || $preset === '') {
            $x1 = intval($this->input('x1'));
            $y1 = intval($this->input('y1'));
            $x2 = intval($this->input('x2'));
            $y2 = intval($this->input('y2'));

            if($x1 < 0 || $y1 < 0 || $x2 < 0 || $y2 < 0)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $ret = $_ENV['device']->move_by_point($device, $x1, $y1, $x2, $y2);
            if(!$ret) 
                $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_MOVE_POINT_FAILED);
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
        
        $uid = $_ENV['device']->check_device_auth($deviceid);
        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN); 
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
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
        
        $uid = $_ENV['device']->check_device_auth($deviceid);
        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }

        $preset = intval($this->input('preset'));
        $title = $this->input('title');
        if($preset < 0 || !$title)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if(!$_ENV['device']->add_preset($device, $preset, $title)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_PLAT_ADD_PRESET_FAILED);

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
        
        $uid = $_ENV['device']->check_device_auth($deviceid);
        if(!$uid) {
            $this->init_user();
            $uid = $this->uid;
        }
        
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
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

        $ret = $_ENV['device']->get_preset_token($device, $preset, $time, $client_id, $sign);
        if(!$ret)
            $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GET_PRESET_TOKEN_FAILED);
        
        return $ret;
    }
    
    function onupdatecvr() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $cvr_day = $this->input('cvr_day');
        $cvr_end_time = $this->input('cvr_end_time');
        $client_id = $this->input('client_id');
        $expire = intval($this->input('expire'));
        $sign = $this->input('sign');

        if(!$deviceid || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if($cvr_day === NULL && $cvr_end_time === NULL)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $cstr = '';
        if($cvr_day !== NULL) $cstr .= $cvr_day;
        if($cvr_end_time !== NULL) $cstr .= $cvr_end_time;

        $check = $this->_check_client_sign($sign, $client_id, $expire, $cstr);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['appid'] != $appid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $result = $_ENV['device']->device_update_cvr($device, $cvr_day, $cvr_end_time);
        if(!$result) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_CVR_FAILED);

        return $result;
    }

    function onupgrade() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->upgrade($device)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPGRADE_FAILED);

        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }
    
    function onrepair() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        if(!$_ENV['device']->repair($device)) 
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_REPAIR_FAILED);

        $result = array();
        $result['deviceid'] = $deviceid;
        return $result;
    }

    // list 433alarm
    function onlist433alarm() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }

        $result = $_ENV['device']->list_433alarm($device);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);
        
        return $result;
    }

    // add 433alarm
    function onadd433alarm() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $fileds = stripcslashes(rawurldecode($this->input('fileds')));        
        if($fileds) {
            $fileds = json_decode($fileds, true);
        }

        if(!$deviceid || !$fileds)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }

        $result = $_ENV['device']->add_433alarm($device, $fileds);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        return $result;
    }

    // delete 433alarm
    function ondelete433alarm() {
        $this->init_user();
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $fileds = stripcslashes(rawurldecode($this->input('fileds')));        
        if($fileds) {
            $fileds = json_decode($fileds, true);
        }

        if(!$deviceid || !$fileds)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) {
            if($_ENV['device']->check_user_grant($deviceid, $uid)) {
                $device['grant_device'] = 1;
                $device['grant_uid'] = $uid;
            } else {
                 $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }

        $result = $_ENV['device']->delete_433alarm($device, $fileds);
        if(!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_UPDATE_SETTINGS_FAILED);
        
        return $result;
    }

    //检查设备升级状态
    function onupgradestatus() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

        $this->init_input();
        $deviceid = $this->input('deviceid');
        
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) 
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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        $deviceid = $this->input('deviceid');
        
        if(!$deviceid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if($device['uid'] != $uid) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $result = $_ENV['device']->get_upgrade_info($device);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_GET_SETTINGS_FAILED);

        return $result;
    }
    
}
