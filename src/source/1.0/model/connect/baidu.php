<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

require_once API_SOURCE_ROOT.'lib/connect/baidu/Baidu.php';
require_once API_SOURCE_ROOT.'lib/connect/baidu/BaiduPCS.class.php';

define('BAIDU_API_DEVICE', 'https://pcs.baidu.com/rest/2.0/pcs/device');
define('BAIDU_API_FILE', 'https://pcs.baidu.com/rest/2.0/pcs/file');

class baiduconnect extends connect {

    function __construct(&$base, $_config) {
        $this->baiduconncet($base, $_config);
    }

    function baiduconncet(&$base, $_config) {
        parent::__construct($base, $_config);
        /*
        if($base->redis) {
            $store = new BaiduRedisStore($this->config['client_id'], $base->redis, request_id());
        } else {
            $store = new BaiduSessionStore($this->config['client_id']);
        }
        */
        if(defined('API_BAIDU_REDIRECT_URI') && API_BAIDU_REDIRECT_URI) {
            $redirect_uri = API_BAIDU_REDIRECT_URI;
        } else {
            $redirect_uri = $this->config['redirect_uri'];
        }
        $this->client = new Baidu($this->config['client_id'], $this->config['client_secret'], $redirect_uri);
    }
    
    function set_state($state) {
        $this->client->setState($state);
    }
    
    function _connect_auth() {
        return true;
    }

    function get_token_extras($uid) {
        if(!$uid)
            return false;
    
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
        if(!$connect) {
            $result = array(
                'connect_type' => $this->connect_type,
                'status' => 0
            );
        } else {
            // refresh token
            if($connect['refresh_token']) {
                $oauth2 = $this->client->getBaiduOAuth2Service();
                $token = $oauth2->getAccessTokenByRefreshToken($connect['refresh_token'], $this->config['scope']);
                if(!$token || !$token['access_token'] || !$token['refresh_token']) {
                    $connect['access_token'] = '';
                    $connect['status'] = -1;
                } else {
                    $expires = $this->base->time + intval($token['expires_in']);
                    $data = serialize($token);
                    $this->db->query("UPDATE ".API_DBTABLEPRE."member_connect SET access_token='".$token['access_token']."', refresh_token='".$token['refresh_token']."', expires='$expires', data='$data', lastupdate='".$this->base->time."', status='1' WHERE uid='$uid' AND connect_type='".$this->connect_type."'");
                
                    $connect['access_token'] = $token['access_token'];
                    $connect['status'] = 1; 
                }
            }
            
            $result = array(
                'connect_type' => $this->connect_type,
                'connect_uid' => $connect['connect_uid'],
                'username' => $connect['username'],
                'access_token' => $connect['access_token'],
                'status' => $connect['status']
            );
        }
    
        return $result;
    }
    
    function get_authorize_url($display = 'page', $force_login = 1) {
        return $this->client->getLoginUrl($this->config['scope'], $display, $force_login);
    }
    
    function get_connect_token() {
        $this->client->setSession(null);
        $token = $this->client->getSession();
        $this->client->setSession(null);
        
        // add user token
        $this->_request(BAIDU_API_DEVICE, array('method' => 'addusertoken', 'access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token']));

        return $token;
    }
    
    function get_access_token($uid) {
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE uid='$uid' AND connect_type='".$this->connect_type."'");
        if(!$connect || !$connect['connect_uid'])
            return '';
        
        $token = array(
            'access_token' => $connect['access_token'],
            'refresh_token' => $connect['refresh_token'],
            'expires' => $connect['expires']
        );
        
        return $token;
    }
    
    function device_register($uid, $deviceid, $desc) {
        if(!$uid || !$deviceid || !$desc) 
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'register',
            'deviceid' => $deviceid,
            'access_token' => $token['access_token'],
            'desc' => $desc,
            'refresh_token' => $token['refresh_token']
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret) 
            $this->_error($ret);
        
        if(isset($ret['error_code'])) {
            // 已注册处理
            $extras = array();
            if($ret['error_code'] == 31350 && isset($ret['uk'])) {
                $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_uid='".$ret['uk']."' AND connect_type='".$this->connect_type."'");
                if($connect && $connect['username']) {
                    $extras['username'] = $connect['username'];
                } 
            }
            $this->_error($ret, $extras);
        }
        
        return $ret;
    }
    
    function device_update($device, $desc) {
        if(!$device || !$desc) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'update',
            'deviceid' => $device['deviceid'],
            'access_token' => $token['access_token'],
            'desc' => $desc
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return true;
    }
    
    function device_drop($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'drop',
            'deviceid' => $device['deviceid'],
            'access_token' => $token['access_token']
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function _connect_list() {
        return true;
    }
    
    function listdevice($uid) {
        if(!$uid) 
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $list = array();
        
        $start = 0;
        $page = 50;
        
        $params = array(
            'method' => 'list',
            'access_token' => $token['access_token'],
            'start' => $start,
            'num' => $page
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || (isset($ret['error_code']) && $ret['error_code'] != 31353)) 
            $this->_error($ret);
        
        $total = $ret['total'];
        $count = $ret['count'];
        $list = array_merge($list, $ret['list']);
        
        while($total > $count) {
            $start += $page;
            $params = array(
                'method' => 'list',
                'access_token' => $token['access_token'],
                'start' => $start,
                'num' => $page
            );
        
            $ret = $this->_request(BAIDU_API_DEVICE, $params);
            if(isset($ret['error_code']) && $ret['error_code'] == 31353)
                break;

            if(!$ret || isset($ret['error_code']))
                $this->_error($ret);
            
            $count += $ret['count'];
            $list = array_merge($list, $ret['list']); 
        }
        
        return array(
            'count' => count($list),
            'list' => $list
        );
    }

    function grant($uid, $uk, $name, $auth_code, $device) {
        if(!$uid || !$uk || !$name || !$auth_code || !$device || !$device['deviceid']) 
            return false;
        
        $deviceid = $device['deviceid'];
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'grant',
            'access_token' => $token['access_token'],
            'uk' => $uk,
            'name' => $name,
            'auth_code' => $auth_code,
            'deviceid' => $deviceid
        );
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return true;
    }

    function listgrantuser($uid, $deviceid) {
        if(!$uid || !$deviceid) 
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'listgrantuser',
            'access_token' => $token['access_token'],
            'deviceid' => $deviceid
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function listgrantdevice($uid) {
        if(!$uid) 
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'listgrantdevice',
            'access_token' => $token['access_token']
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(isset($ret['error_code']) && $ret['error_code'] == 31353)
            return false;

        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }

    function subscribe($uid, $shareid, $uk) {
        if(!$uid || !$shareid || !$uk) 
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'subscribe',
            'access_token' => $token['access_token'],
            'shareid' => $shareid,
            'uk' => $uk
        );
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return true;
    }

    function unsubscribe($uid, $shareid, $uk) {
        if(!$uid || !$shareid || !$uk) 
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'unsubscribe',
            'access_token' => $token['access_token'],
            'shareid' => $shareid,
            'uk' => $uk
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return true;
    }
    
    function listsubscribe($uid) {
        if(!$uid) 
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'listsubscribe',
            'access_token' => $token['access_token']
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(isset($ret['error_code']) && $ret['error_code'] == 31353)
            return false;

        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }

    function liveplay($device, $type, $ps) {
        $type = $type=='rtmp'?'':$type;
        $auth_type = $ps['auth_type'];
        if($auth_type == 'token') {
            $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
            $token = $this->get_access_token($uid);
            if(!$token || !$token['access_token'])
                return false;
            
            $params = array(
                'method' => 'liveplay',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'type' => $type,
                'host' => $this->base->onlineip 
            );
        } elseif($auth_type == 'share') {
            $params = array(
                'method' => 'liveplay',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'type' => $type,
                'host' => $this->base->onlineip 
            );
        }
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function createshare($device, $share_type) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'createshare',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'share' => $share_type
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function cancelshare($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'cancelshare',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid']
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return true;
    }

    function list_share($start, $num) {
        $expire = time() + 600;
        $realsign = md5($this->config['appid'].$expire.$this->config['client_id'].$this->config['client_secret']);
        $sign = $this->config['appid'].'-'.$this->config['client_id'].'-' . $realsign;

        $params = array(
            'method' => 'listshare',
            'sign' => $sign,
            'expire' => $expire,
            'start' => $start,
            'num' => $num
        );

        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        if($ret['count'] && $ret['device_list']) {
            foreach($ret['device_list'] as $i => $v) {
                $ret['device_list'][$i]['connect_type'] = $this->connect_type;
                $ret['device_list'][$i]['connect_did'] = '';
            }
        }
        
        return $ret;
    }

    function dropgrantuser($device, $uk) {
        if(!$device || !$device['deviceid'] || !$uk) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'dropgrantuser',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'uk' => $uk
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return true;
    }
    
    function playlist($device, $ps, $starttime, $endtime) {
        if(!$starttime || !$endtime)
            return false;
        
        $auth_type = $ps['auth_type'];
        if($auth_type == 'token') {
            $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
            $token = $this->get_access_token($uid);
            if(!$token || !$token['access_token'])
                return false;
            
            $params = array(
                'method' => 'playlist',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'st' => $starttime,
                'et' => $endtime 
            );
        } elseif($auth_type == 'share') {
            $params = array(
                'method' => 'playlist',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'st' => $starttime,
                'et' => $endtime 
            );
        }
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function vod($device, $ps, $starttime, $endtime) {
        if(!$starttime || !$endtime)
            return false;
        
        $auth_type = $ps['auth_type'];
        if($auth_type == 'token') {
            $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
            $token = $this->get_access_token($uid);
            if(!$token || !$token['access_token'])
                return false;
            
            $params = array(
                'method' => 'vod',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'st' => $starttime,
                'et' => $endtime 
            );
        } elseif($auth_type == 'share') {
            $params = array(
                'method' => 'vod',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'st' => $starttime,
                'et' => $endtime 
            );
        }
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params, false);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        $j = json_decode($ret, true);
        if(json_last_error() == JSON_ERROR_NONE)
            $this->_error($j);
        
        return $ret;
    }
    
    function vodseek($device, $ps, $time) {
        if(!$time)
            return false;
        
        $auth_type = $ps['auth_type'];
        if($auth_type == 'token') {
            $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
            $token = $this->get_access_token($uid);
            if(!$token || !$token['access_token'])
                return false;
            
            $params = array(
                'method' => 'vodseek',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'time' => $time
            );
        } elseif($auth_type == 'share') {
            $params = array(
                'method' => 'vodseek',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'time' => $time
            );
        }
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function thumbnail($device, $ps, $starttime, $endtime, $latest) {
        if(!$device || !$device['deviceid'])
            return false;
        
        if(!$latest && (!$starttime || !$endtime))
            return false;
        
        $auth_type = $ps['auth_type'];
        if($auth_type == 'token') {
            $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
            $token = $this->get_access_token($uid);
            if(!$token || !$token['access_token'])
                return false;
            
            $params = array(
                'method' => 'thumbnail',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid']
            );
        } elseif($auth_type == 'share') {
            $params = array(
                'method' => 'thumbnail',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk']
            );
        }
        
        if(!$latest) {
            $params['st'] = $starttime;
            $params['et'] = $endtime;
        } else {
            $params['latest'] = 1;
        }
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        /*
        if($ret['count'] && $ret['list']) {
            foreach($ret['list'] as $k => $v) {
                $url = $v['url'];
                $url = str_replace('d.pcs.baidu.com', 'pcs.baidu.com', $url);
                $ret['list'][$k]['url'] = $url;
            }
        }
        */
        
        return $ret;
    }
    
    function clip($device, $starttime, $endtime, $name) {
        if(!$device || !$device['deviceid'] || !$starttime || !$endtime || !$name)
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'clip',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'st' => $starttime,
            'et' => $endtime,
            'name' => $name
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function infoclip($device, $type) {
        if(!$device || !$device['deviceid'] || !$type)
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'infoclip',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'type' => $type
        );
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function _connect_meta() {
        return true;
    }
    
    function device_meta($device, $ps) {
        $auth_type = $ps['auth_type'];
        if($auth_type == 'token') {
            //$uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
            //$token = $this->get_access_token($uid);
            if($device['grant_device'])
                return false;
            
            $token = $this->get_access_token($device['uid']);
            if(!$token || !$token['access_token'])
                return false;
            
            $params = array(
                'method' => 'meta',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid']
            );
        } elseif($auth_type == 'share') {
            $params = array(
                'method' => 'meta',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk']
            );
        }
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        if(isset($ret['list']) && isset($ret['list'][0]))
            return $ret['list'][0];
        
        return false;
    }
    
    function device_usercmd($device, $command, $response=0) {
        if(!$device || !$device['deviceid'] || !$command) 
            return false;
        
        $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $params = array(
            'method' => 'control',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'command' => $command 
        );
        
        $this->base->log('baidu user cmd', json_encode($params));
        
        $ret = $this->_request(BAIDU_API_DEVICE, $params);
        $this->base->log('baidu user cmd ret', json_encode($ret));
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function device_batch_usercmd($device, $commands) {
        if(!$device || !$device['deviceid'] || !$commands) 
            return false;
        
        $this->base->log('baidu batch cmd', json_encode($commands));
        
        foreach($commands as $k => $v) {
            $this->base->log('baidu batch cmd', 'k='.$k);
            $ret = $this->device_usercmd($device, $v['command'], $v['response']);
            if(!$ret)
                return false;
            
            if($v['response']) {
                if(!$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
                    return false;
                $commands[$k]['data'] = $ret['data'][1]['userData'];
            } else {
                $commands[$k]['data'] = '';
            }
        }
        
        return $commands;
    }
    
    function _cmd_params($ret) {
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;
        
        $data = $ret['data'][1]['userData'];
        $data = json_decode($data, true);
        $params = $data['params'];
        return $params;
    }
    
    function _request($url, $params, $json_decode = true) {
        if(!$url || !$params) 
            return false;
        
        $ret = BaiduUtils::request($url, $params);

        // log记录 ----------- debug_log
        log::debug_log('BAIDU', $url, $params, BaiduUtils::$errno, $ret, BaiduUtils::$errmsg);
        
        if ($ret && $json_decode) {
            $ret = json_decode($ret, true);
            if(json_last_error() != JSON_ERROR_NONE)
                return false;
        }
        
        return $ret;
    }
    
    function _error($error, $extras=NULL) {
        if($error && $error['error_code']) {
            $error_code = $error['error_code'];
            
            $errors = array(
                '110' => array(API_HTTP_FORBIDDEN, API_ERROR_TOKEN)
            );
            
            if(isset($errors[$error_code])) {
                $api_status = $errors[$error_code][0];
                $api_error = $errors[$error_code][1];
            } else {
                $api_status = API_HTTP_BAD_REQUEST;
                $api_error = $error['error_code'].':'.$error['error_msg'];
            }
        } else {
            $api_status = API_HTTP_INTERNAL_SERVER_ERROR;
            $api_error = CONNECT_ERROR_API_FAILED;
        }
        if($extras === NULL) $extras = array();
        $extras['connect_type'] = $this->connect_type;
        $this->base->error($api_status, $api_error, NULL, NULL, NULL, $extras);
    }
    
    function alarmpic($device, $page, $count) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $pcs = new BaiduPCS($token['access_token']);
        if(!$pcs)
            return false;
        
        $path = '/apps/iermu/alarmjpg/'.$device['deviceid'].'/';
        $by = 'name';
        $order = 'desc';
        $limit = (($page-1)*$count).'-'.($page*$count);
        
        $ret = $pcs->listFiles($path, $by, $order, $limit);
        if(!$ret || isset($ret['error_code']))
            return false;
        
        $ret = json_decode($ret, true);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        $list = $ret['list'];
        if(!$list)
            return false;
        
        $result = array();
        foreach($list as $pic) {
            if(!$pic['isdir']) {
                $value = array(
                    'deviceid' => $device['deviceid'],
                    'path' => $pic['path'],
                    'time' => $pic['ctime'],
                    'url' => BAIDU_API_FILE.'?method=download&access_token='.$token['access_token'].'&path='.$pic['path'],
                    'size' => $pic['size']
                );
                $result[] = $value;
            }
        }
        return $result;
    }
    
    function dropalarmpic($device, $path) {
        if(!$device || !$device['deviceid'] || !$path) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $pcs = new BaiduPCS($token['access_token']);
        if(!$pcs)
            return false;
        
        $ret = $pcs->deleteBatch($path);
        if(!$ret || isset($ret['error_code']))
            return false;
        
        $ret = json_decode($ret, true);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);
        
        return $ret;
    }
    
    function downloadalarmpic($device, $path) {
        if(!$device || !$device['deviceid'] || !$path) 
            return false;
        
        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $pic = BAIDU_API_FILE.'?method=download&access_token='.$token['access_token'].'&path='.$path;
        header('Location:' . $pic);
        exit();
    }

    // 获取m3u8录像片段列表
    function vodlist($device, $starttime, $endtime) {
        $deviceid = $device['deviceid'];
        $uid = $device['uid'];

        if(!$deviceid || !$uid || !$starttime || !$endtime)
            return false;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        $url = BAIDU_API_DEVICE.'?method=vod&access_token='.$token['access_token'].'&deviceid='.$deviceid.'&st='.$starttime.'&et='.$endtime;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($ch);
        curl_close($ch);

        preg_match_all('/(http.+)&rt=sh&owner='.$this->config['appid'].'&zhi=(\d+)&range=(\d+)-(\d+)&response_status=206/', $content, $temp);
        $num = count(array_shift($temp));

        // 合并ts文件
        for($i = 1; $i < $num; ++$i) {
            if ($temp[0][$i] == $temp[0][$i-1] && $temp[2][$i] - $temp[3][$i-1] == 1) {
                $temp[2][$i] = $temp[2][$i-1];
                unset($temp[0][$i-1]);
                unset($temp[1][$i-1]);
                unset($temp[2][$i-1]);
                unset($temp[3][$i-1]);
            }
        }

        // 拼接为uri
        $result = array();
        for($j = 0; $j < $num; ++$j) {
            if(isset($temp[0][$j])) {
                $result[] = $temp[0][$j].'&rt=sh&owner='.$this->config['appid'].'&zhi='.$temp[1][$j].'&range='.$temp[2][$j].'-'.$temp[3][$j].'&response_status=206';
            }
        }

        return $result;
    }
    
    function _check_setting_sync($device) {
        if(!$device || !$device['deviceid']) 
            return 0;
        
        $deviceid = $device['deviceid'];
        
        $lastsettingsync = $this->db->result_first("SELECT lastsettingsync FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='$deviceid'");
        if(!$lastsettingsync || $lastsettingsync + 5*60 < $this->base->time) {
            return 2;
        }
        
        return 0;
    }
    
    function avatar($avatar) {
        return 'http://tb.himg.baidu.com/sys/portrait/item/'.$avatar;
    }
    
}
