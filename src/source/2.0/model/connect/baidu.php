<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

require_once API_SOURCE_ROOT.'lib/connect/baidu/Baidu.php';
require_once API_SOURCE_ROOT.'lib/connect/baidu/BaiduPCS.class.php';

define('BAIDU_API_DEVICE', 'https://pcs.baidu.com/rest/2.0/pcs/device');
define('BAIDU_API_FILE', 'https://pcs.baidu.com/rest/2.0/pcs/file');
define('BAIDU_API_QUOTA', 'https://pcs.baidu.com/rest/2.0/pcs/quota');

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
        $this->base->load('device');
    }
    
    function load_api($api) {
        if(empty($_ENV[$api.'baiduapi'])) {
            if(file_exists(API_SOURCE_ROOT."model/connect/baidu/$api.php")) {
                require_once API_SOURCE_ROOT."model/connect/baidu/$api.php";
            } else {
                return false;
            }
            
            eval('$_ENV[$api.\'baiduapi\'] = new '.$api.'baiduapi($this->base, $this->config);');
        }
        return $_ENV[$api.'baiduapi'];
    }

    function set_state($state) {
        $this->client->setState($state);
    }

    function _connect_auth() {
        return true;
    }

    function get_connect_info($uid, $sync=false) {
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
            if($sync && $connect['refresh_token'] &&  $connect['expires'] < $this->base->time) {
                $oauth2 = $this->client->getBaiduOAuth2Service();
                $token = $oauth2->getAccessTokenByRefreshToken($connect['refresh_token'], $this->config['scope']);
                if($token && $token['access_token'] && $token['refresh_token']) {
                    // add user token
                    $ret = $this->load_api('bce')->addusertoken($token);
                    $ret = $this->load_api('pcs')->addusertoken($token);

                    $expires = $this->base->time + intval($token['expires_in']);
                    $data = serialize($token);
                    $this->db->query("UPDATE ".API_DBTABLEPRE."member_connect SET access_token='".$token['access_token']."', refresh_token='".$token['refresh_token']."', expires='$expires', data='$data', lastupdate='".$this->base->time."', status='1' WHERE uid='$uid' AND connect_type='".$this->connect_type."'");

                    $connect['access_token'] = $token['access_token'];
                    $connect['status'] = 1;
                }
            }
            if(API_AUTH_TYPE == 'token') {
                $result = array(
                    'connect_type' => $this->connect_type,
                    'connect_uid' => $connect['connect_uid'],
                    'username' => $connect['username'],
                    'access_token' => $connect['access_token'],
                    'status' => intval($connect['status'])
                );
            } else {
                $result = array(
                    'connect_type' => $this->connect_type,
                    'connect_uid' => $connect['connect_uid'],
                    'username' => $connect['username'],
                    'status' => intval($connect['status'])
                );
            }
        }

        return $result;
    }

    function device_connect_token($device) {
        if(!$device || !$device['deviceid'] || !$device['uid'])
            return false;

        $uid = $device['uid'];
        $info = $this->get_connect_info($uid, true);
        if(!$info || !$info['access_token'])
            return false;

        return $info['access_token'];
    }

    function get_authorize_url($display = 'page', $force_login = 1) {
        return $this->client->getLoginUrl($this->config['scope'], $display, $force_login);
    }

    function get_connect_token() {
        $this->client->setSession(null);
        $token = $this->client->getSession();
        $this->client->setSession(null);

        // add user token
        $this->load_api('bce')->addusertoken($token);
        $this->load_api('pcs')->addusertoken($token);

        return $token;
    }

    function get_connect($uid) {
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE uid='$uid' AND connect_type='".$this->connect_type."'");
        if(!$connect)
            return '';

        return $connect;
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
        if(!$uid || !$deviceid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        //@todo 判断
        // $ret = $this->load_api('bce')->device_register($token, $deviceid, $desc);
        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->device_register($token, $deviceid, $desc);
        }else{
            $ret = $this->load_api('bce')->device_register_migrate($token, $deviceid, $desc);
        }
        
        if(!$ret)
            $this->_error($ret);

        //新百度已注册返回处理
        if(isset($ret['user_id'])){
            $uk = $ret['user_id'];
            $cuid = $this->db->result_first("SELECT uid FROM ".API_DBTABLEPRE."member_connect WHERE connect_uid='".$uk."' AND connect_type='".$this->connect_type."'");
            if($uid != $cuid){
                $ret['error_code'] = 31350;
                $ret['uk'] = $uk;
            }
        }
        

        if(isset($ret['error_code'])) {

            // 已注册处理
            $extras = array();
            if($ret['error_code'] == 31350 && isset($ret['uk'])) {
                $extras['isowner'] = 0;
                $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_uid='".$ret['uk']."' AND connect_type='".$this->connect_type."'");
                if($connect && $connect['username']) {
                    $extras['username'] = $connect['username'];
                    if($connect['uid'] == $this->base->uid) {
                        $extras['isowner'] = 1;

                        $device = $_ENV['device']->get_device_by_did($deviceid);
                        if($device && $device['uid'] == $connect['uid']) {
                            $extras['uid'] = $device['uid'];
                            $extras['connect_type'] = $device['connect_type'];
                            $extras['connect_cid'] = $device['connect_cid'];
                            $extras['stream_id'] = $device['stream_id'];

                            $connect_token = $this->device_connect_token($device);
                            if($connect_token) $extras['connect_token'] = $connect_token;
                        }
                    }
                }
            }
            $this->_error($ret, $extras);
        }

        return $ret;
    }

    function device_update($device, $desc) {
        if(!$device)
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->device_update($device, $token, $desc);
        }else{
            $ret = $this->load_api('bce')->device_update($device, $token, $desc);
        }
        //修改老系统摄像头描述 @todo
        // $this->load_api('pcs')->device_update($device, $token, $desc);


        return $ret;
    }

    function device_drop($device) {
        if(!$device || !$device['deviceid'])
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->device_drop($device, $token);
        }else{
            $ret = $this->load_api('bce')->device_drop($device, $token);
        }
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

        //@todo 添加is_migrated字段
        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->listdevice($token);
        }else{
            $ret = $this->load_api('bce')->listdevice($token);
        }
        
        return $ret;

    }

    function listdevice_by_page($uid, $page, $count) {
        if(!$uid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        //@todo 添加is_migrated字段
        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->listdevice_by_page($token, $page, $count);
        }else{
            $ret = $this->load_api('bce')->listdevice_by_page($token, $page, $count);
        }
        
        return $ret;
    }

    function grant($uid, $uk, $name, $auth_code, $device) {
        if(!$uid || !$uk || !$name || !$auth_code || !$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->grant($token, $uk, $name, $auth_code, $device);
        }else{
            $ret = $this->load_api('bce')->grant($token, $uk, $name, $auth_code, $device);
        }

        return $ret;
    }

    function listgrantuser($uid, $deviceid) {
        if(!$uid || !$deviceid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->listgrantuser($token, $deviceid);
        }else{
            $ret = $this->load_api('bce')->listgrantuser($token, $deviceid);
        }

        return $ret;
    }

    function listgrantdevice($uid) {
        if(!$uid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->listgrantdevice($token);
        }else{
            $ret = $this->load_api('bce')->listgrantdevice($token);
        }

        return $ret;
    }

    function subscribe($uid, $shareid, $uk) {
        if(!$uid || !$shareid || !$uk)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->subscribe($token, $shareid, $uk);
        }else{
            $ret = $this->load_api('bce')->subscribe($token, $shareid, $uk);
        }

        return $ret;
    }

    function unsubscribe($uid, $shareid, $uk) {
        if(!$uid || !$shareid || !$uk)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->unsubscribe($token, $shareid, $uk);
        }else{
            $ret = $this->load_api('bce')->unsubscribe($token, $shareid, $uk);
        }
        return $ret;
    }

    function listsubscribe($uid) {
        if(!$uid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->listsubscribe($token);
        }else{
            $ret = $this->load_api('bce')->listsubscribe($token);
        }
        return $ret;
    }

    function liveplay($device, $type, $ps) {
        $type = $type == 'rtmp'? '' : $type;

        $grant_auth_code = 0;
        if($device['grant_device'])
            $grant_auth_code = 5;
         $deviceid = $device['deviceid'];
         $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
         $token = $this->get_access_token($uid);
         // 分享设备播放
         // $ps['auth_type']

         // $is_migrated = $this->is_migratedbyid($deviceid);
         if(!$this->base->is_pcs_request()){
            $ret = $this->load_api('bce')->liveplay($token, $device, $type, $ps, $grant_auth_code);
         }else{
            $ret = $this->load_api('pcs')->liveplay($token, $device, $type, $ps);
         }

        $ret['url'] = str_replace("rtmp://hz.cam.baidu.com", "rtmp://bj.cam.baidu.com", $ret['url']);
        $ret['url'] = str_replace("rtmp://qd.cam.baidu.com", "rtmp://bj.cam.baidu.com", $ret['url']);
        
        return $ret;
    }
//@todo 设备列表 调用接口问题
    function multi_liveplay($uid, $devicelist) {
        if (!$uid || !$devicelist)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        $deviceid_list = array();
        // $deviceid_new = array();
        // $deviceid_old = array();
        foreach ($devicelist as $device) {
            // $is_migrated = $this->is_migratedbyid($device['deviceid']);
            //  if($is_migrated){
            //    $deviceid_new[] = $device['deviceid'];
            //  }else{
            //    $deviceid_old[] = $device['deviceid'];
            //  }
            $deviceid_list[] = $device['deviceid'];
        }
        // $retnew = array();
        // $retold = array();
        // if(count($deviceid_new)>0){
        //     $list_new = array();
        //     $list_new['list'] = $deviceid_new;
        //     $requestv1 = new baiduv1connect();
        //     $retnew = $requestv1->liveplay_muti($token, $list_new);
        // }

        // if(count($deviceid_old)>0){
        //     $list_old = array();
        //     $list_old['list'] = $deviceid_old;
        //     $requestv1 = new baiduv1connect();
        //     $retold = $requestv1->liveplay_muti($token, $list_old);
        // }

        $list = array();
        $list['list'] = $deviceid_list;

        if($this->base->is_pcs_request()){
            $liveplay_list = $this->load_api('pcs')->liveplay_muti($token, $list);
        }else{
            $liveplay_list = $this->load_api('bce')->liveplay_muti($token, $list);
        }

        // $params = array(
        //     'method' => 'liveplay',
        //     'access_token' => $token['access_token'],
        //     'param' => json_encode($list)
        // );

        // $ret = $this->_request(BAIDU_API_DEVICE, $params);
        // if(!$ret || isset($ret['error_code']))
        //     $this->_error($ret);

        // $liveplay_list = array_merge($retnew, $retold)
        return $liveplay_list;
    }

    function createshare($device, $share_type, $expires = 0) {
        if(!$device || !$device['deviceid'])
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->createshare($token, $device, $share_type, $expires);
        }else{
            $ret = $this->load_api('bce')->createshare($token, $device, $share_type, $expires);
        }
        
        return $ret;
    }

    function cancelshare($device) {
        if(!$device || !$device['deviceid'])
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->cancelshare($token, $device);
        }else{
            $ret = $this->load_api('bce')->cancelshare($token, $device);
        }
        return $ret;
    }

    function list_share($start, $num) {
        $expire = time() + 600;
        $realsign = md5($this->config['appid'].$expire.$this->config['client_id'].$this->config['client_secret']);
        $sign = $this->config['appid'].'-'.$this->config['client_id'].'-' . $realsign;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->list_share($start, $num, $sign, $expire);
        }else{
            $ret = $this->load_api('bce')->list_share($start, $num, $sign, $expire);
        }
       
        return $ret;
    }

    function dropgrantuser($device, $uk) {
        if(!$device || !$device['deviceid'] || !$uk)
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->dropgrantuser($token, $device, $uk);
        }else{
            $ret = $this->load_api('bce')->dropgrantuser($token, $device, $uk);
        }
        
        return $ret;
    }

    function playlist($device, $ps, $starttime, $endtime, $type) {
        if(!$starttime || !$endtime)
            return false;
        $grant_auth_code = 0;
        if($device['grant_uid'])
            $grant_auth_code = 5;
        $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
        $token = $this->get_access_token($uid);
        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if($is_migrated){
            $ret = $this->load_api('bce')->playlist($token, $device, $ps, $starttime, $endtime, $type, $grant_auth_code);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->playlist($token, $device, $ps, $starttime, $endtime, $type);
            return $ret;
        }

    }

    function vod($device, $ps, $starttime, $endtime) {
        if(!$starttime || !$endtime)
            return false;
        $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
        $token = $this->get_access_token($uid);

        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if($is_migrated){
            $ret = $this->load_api('bce')->vod($token, $device, $ps, $starttime, $endtime);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->vod($token, $device, $ps, $starttime, $endtime);
            return $ret;
        }

    }

    function vodseek($device, $ps, $time) {
        if(!$time)
            return false;
        $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
        $token = $this->get_access_token($uid);
        
        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if($is_migrated){
            $ret = $this->load_api('bce')->vodseek($token, $device, $ps, $time);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->vodseek($token, $device, $ps, $time);
            return $ret;
        }

    }

    function thumbnail($device, $ps, $starttime, $endtime, $latest) {
        if(!$latest && (!$starttime || !$endtime))
	        return false;
        $grant_auth_code = 0;
        if($device['grant_device'])
            $grant_auth_code = 5;
        $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
        $token = $this->get_access_token($uid);
        
        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if($is_migrated){
            $ret = $this->load_api('bce')->thumbnail($token, $device, $ps, $starttime, $endtime, $latest, $grant_auth_code);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->thumbnail($token, $device, $ps, $starttime, $endtime, $latest);
            return $ret;
        }

    }

    function clip($device, $starttime, $endtime, $name, $client_id) {

        if(!$device || !$device['deviceid'])
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;

        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if($is_migrated){
            $ret = $this->load_api('bce')->clip($token, $device, $starttime, $endtime, $name, $client_id);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->clip($token, $device, $starttime, $endtime, $name, $client_id);
            return $ret;
        }

    }

    function _clip_postfix() {
        return '_b'.time().rand(100, 999);
    }

    function _clip_extension() {
        return '.mp4';
    }

    function _clip_path() {
        return '/apps/iermu/clip/';
    }

    function infoclip($uid, $type, $clipid) {
        if(!$uid || !$clipid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        // $storageid = $this->config['storageid'];

        $clipinfo = $this->db->fetch_first('SELECT uid, clipid, deviceid, storageid, storage_cid,starttime, endtime, name, pathname, filename, status, progress FROM '.API_DBTABLEPRE."device_clip where uid=$uid AND clipid=$clipid");
        if (!$clipinfo)
            return false;

        $storageid = intval($clipinfo['storageid']);

        $result = array();
        $result['clipid'] = strval($clipid);
        $result['deviceid'] = $clipinfo['deviceid'];
        $result['starttime'] = strval($clipinfo['starttime']);
        $result['endtime'] = strval($clipinfo['endtime']);
        $result['name'] = $clipinfo['name'];
        $result['storageid'] = $storageid;

        // 下载地址处理
        $url = "";
        if($clipinfo['status'] == 0 && $storageid) {
            $params = array('uid'=>$clipinfo['uid'], 'type'=>'clip', 'container'=>$clipinfo['pathname'], 'object'=>$clipinfo['filename']);  
            $url = $this->base->storage_temp_url($storageid, $params);
        }
        $result['url'] = $url;

        // $result['storage_cid'] = intval($clipinfo['storage_cid']);
        if (intval($clipinfo['status']) != 1) {
            $result['status'] = intval($clipinfo['status']);
            $result['progress'] = intval($clipinfo['progress']);

            return $result;
        } else {
            // $is_migrated = $this->is_migratedbyid($clipinfo['deviceid']);
            if($storageid == 15){
                $ret = $this->load_api('bce')->infoClip($token,$clipinfo,$clipid);
                return $ret;
            }else{
                $ret = $this->load_api('pcs')->infoClip($token,$clipinfo,$clipid);
                return $ret;
            }
        }
    }

    //添加uk 设备uid
    function listuserclip($uid, $page, $count, $client_id, $uk=0) {
        if (!$uid || !$uk)
            return false;

        $storageid = $this->config['storageid'];
        $connect_type = $this->connect_type;

        $last_sync_time = $this->db->result_first("SELECT lastclipsync FROM ".API_DBTABLEPRE."member_connect WHERE uid=$uid AND connect_type=$connect_type");
        //@todo 老用户 云盘同步
        $list = $this->_get_increased_clips($uid, $last_sync_time);
        if ($list === false)
            return false;
        $this->db->query('UPDATE '.API_DBTABLEPRE."member_connect SET storageid=$storageid".', lastclipsync='.$this->base->time." WHERE uid=$uid AND connect_type=$connect_type");

        $locallist = $this->db->fetch_all('SELECT filename, status FROM '.API_DBTABLEPRE."device_clip WHERE uid=$uid AND storageid=$storageid AND md5='' order by dateline");

        foreach ($list as $item) {
            $pathname = $item['path'];
            $filename = basename($pathname);
            $mtime = $item['mtime'];
            $md5 = $item['md5'];

            $exist = false;

            foreach ($locallist as $localitem) {
                if ($localitem['filename'] == $filename) {
                    $exist = true;
                    break;
                }
            }

            if ($exist) {
                $setarray = array();
                if (intval($localitem['status']) == 1) {
                    $setarray[] = 'lastupdate='.$this->base->time;
                    $setarray[] = 'status=0';
                    $setarray[] = 'progress=100';
                }
                $setarray[] = 'md5="'.$md5.'"';
                $sets = implode(',', $setarray);
                $this->db->query('UPDATE '.API_DBTABLEPRE."device_clip SET $sets WHERE filename=\"".$localitem['filename']."\" AND uid=$uid AND storageid=$storageid");
            } 
            //兼容企业 用户 废除同步机制
            // else {
            //     $setarray = array();
            //     $setarray[] = 'deviceid="0"';
            //     $setarray[] = 'uid='.$uid;
            //     $setarray[] = 'storageid='.$storageid;
            //     $setarray[] = 'name="'.$filename.'"';
            //     $setarray[] = 'starttime=0';
            //     $setarray[] = 'endtime=0';
            //     $setarray[] = 'status=0';
            //     $setarray[] = 'progress=100';
            //     $setarray[] = 'pathname="'.$pathname.'"';
            //     $setarray[] = 'filename="'.$filename.'"';
            //     $setarray[] = 'client_id="'.$client_id.'"';
            //     $setarray[] = 'dateline='.$mtime;
            //     $setarray[] = 'lastupdate='.$this->base->time;
            //     $setarray[] = 'md5="'.$md5.'"';
            //     $sets = implode(',', $setarray);
            //     $this->db->query('INSERT INTO '.API_DBTABLEPRE."device_clip SET $sets");
            // }
        }

        return true;
    }

    function _get_increased_clips($uid, $last_sync_time) {
        if (!$uid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        $step = 100;
        $start = 0;
        $result = array();

        while (1) {
            $end = $start + $step;
            $limit = $start.'-'.$end;
            $params = array(
                'method' => 'list',
                'access_token' => $token['access_token'],
                'path' => $this->_clip_path(),
                'by' => 'time',
                'order' => 'desc',
                'limit' => $limit
            );

            $ret = $this->_request(BAIDU_API_FILE, $params);
            if(!$ret || isset($ret['error_code']))
                return false;

            $list = $ret['list'];
            $count = count($list);
            if ($count == 0) {
                break;
            } else if ($count < $step || $list[$count - 1]['mtime'] <= $last_sync_time) {
                $result = array_merge($result, $list);
                break;
            }

            $result = array_merge($result, $list);
            $start += $step;
        }

        $result_count = count($result);
        if ($result_count > 0) {
            for ($i = $result_count - 1; $i >= 0; $i--) {
                if (intval($result[$i]['mtime']) <= intval($last_sync_time))
                    unset($result[$i]);
                else
                    break;
            }
        }
        return $result;
    }

    function _connect_meta() {
        return true;
    }

    function device_meta($device, $ps) {
        $token = $this->get_access_token($device['uid']);
        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if(!$this->base->is_pcs_request()){
            $ret = $this->load_api('bce')->device_meta($token, $device, $ps);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->device_meta($token, $device, $ps);
            return $ret;
        }

    }
//@todo 获取设备列表
    function device_batch_meta($devices, $ps) {
        if(!$devices)
            return false;
        $uid = $ps['uid'];
        $token = $this->get_access_token($uid);

        if($this->base->is_pcs_request()){
            $ret = $this->load_api('pcs')->device_batch_meta($token, $devices, $ps);
        }else{
            $ret = $this->load_api('bce')->device_batch_meta($token, $devices, $ps);
        }
        return $ret;

    }

    function device_usercmd($device, $command, $response=0) {
        if(!$device || !$device['deviceid'] || !$command)
            return false;
        $uid = $device['grant_device']?$device['grant_uid']:$device['uid'];

        $token = $this->get_access_token($uid);

        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if($is_migrated){
            $ret = $this->load_api('bce')->device_usercmd($token, $device, $command, $response);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->device_usercmd($token, $device, $command, $response);
            return $ret;
        }

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

    function _need_sync_alarm() {
        return true;
    }

    function sync_alarm($device, $alarmtime) {
        if(!$device || !$device['deviceid']  || !$alarmtime || !$alarmtime['start'] || !$alarmtime['end'])
            return false;

        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $cvr_day = $device['cvr_day'];
        $cvr_end_time = $device['cvr_end_time'];
        $timezone = $device['timezone'];
        $alarm_tableid = $device['alarm_tableid'];

        $connect = $this->get_connect($uid);
        if(!$connect || !$connect['access_token'])
            return false;

        $token = $connect['access_token'];
        $lastalarmpicsync = $device['lastalarmpicsync'];

        $cvr_day = $cvr_day?$cvr_day:7;

        $end = $alarmtime['end'];
        $start = $alarmtime['start'];

        if($lastalarmpicsync >= $end)
            return true;

        if($lastalarmpicsync > $start && $lastalarmpicsync < $end) {
            $start = $lastalarmpicsync;
        }

        $page = 1;
        if($this->base->time - $lastalarmpicsync > 3600) {
            $count = 1000;
        } else {
            $count = 100;
        }

        $pcs = new BaiduPCS($token);
        if(!$pcs)
            return false;

        $path = '/apps/iermu/alarmjpg/'.$deviceid.'/';
        $by = 'name';
        $order = 'desc';
        $loop = true;

        $this->base->log('alarmmmm', 'deviceid='.$deviceid.', start='.date("Y-m-d H:i:s", $start).', end='.date("Y-m-d H:i:s", $end));

        while($loop) {
            $limit = (($page-1)*$count).'-'.($page*$count);
            $this->base->log('alarmmmm', 'start');
            $ret = $pcs->listFiles($path, $by, $order, $limit);
            $this->base->log('alarmmmm', 'end');
            if(!$ret || isset($ret['error_code']))
                return false;

            $ret = json_decode($ret, true);
            if(!$ret || isset($ret['error_code']))
                //$this->_error($ret);
                return false;

            $list = $ret['list'];
            if(!$list)
                return false;

            $this->base->log('alarmmmm', 'page='.$page.', count='.count($list));

            foreach($list as $pic) {
                $info = pathinfo($pic['path']);
                $pathname = $info['dirname'];
                $filename = $info['basename'];
                $size = $pic['size'];
                $time = $this->_alarmpic_time($info['filename'], $timezone);
                $expiretime = $time + $cvr_day*24*3600;
                //$expiretime = $this->base->day_end_time($expiretime, $device['timezone']);
                $expiretime = 0;

                if($time < $start) {
                    $loop = false;
                    break;
                } else {
                    $_ENV['device']->add_alarm($deviceid, $alarm_tableid, $uid, 3, '', 0, '', 0, $time, $expiretime, $pathname, $filename, $size, $this->config['storageid'], '', '', $device['appid'], -1);
                }
            }

            if($loop) {
                if(count($list) < $count) {
                    $loop = false;
                } else {
                    $page++;
                }
            }
        }

        // TODO: 更新统计状态
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET lastalarmpicsync='".$this->base->time."' WHERE deviceid='$deviceid'");

        return true;
    }

    function _alarmpic_time($time, $timezone) {
        $time = gmmktime(substr($time, -6, 2), substr($time, -4, 2), substr($time, -2, 2), substr($time, 4, 2), substr($time, 6, 2), substr($time, 0, 4));
        $tz_rule = $this->base->get_timezone_rule_from_timezone_id($timezone);
        $time -= $tz_rule['offset'];
        return $time;
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

        $is_migrated = $this->is_migratedbyid($deviceid);
        if($is_migrated){
            $content = $this->load_api('bce')->vodlist($deviceid, $token, $starttime, $endtime);
        }else{
            $content = $this->load_api('pcs')->vodlist($deviceid, $token, $starttime, $endtime);
        }

        // preg_match_all('/(http.+)'.$this->config['appid'].'&zhi=(\d+)&range=(\d+)-(\d+)&response_status=206/', $content, $temp);
        preg_match_all('/(http.+)[\.]ts/', $content, $temp);
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
                // $result[] = $temp[0][$j].'&rt=sh&owner='.$this->config['appid'].'&zhi='.$temp[1][$j].'&range='.$temp[2][$j].'-'.$temp[3][$j].'&response_status=206';
                $result[] = $temp[0][$j].'.ts';
            }
        }
        return $result;
    }

    //购买云录制
    function cvr_buy($deviceid, $uid, $ismobile){
        $deviceid_arr = explode(',', $deviceid);
        $device = "";
        foreach($deviceid_arr as $deviceid){
            $device .= $deviceid.'-'.$this->config['appid'].',';
        }
        $device = substr($device, 0, strlen($device)-1);
        if(!$device || !$uid)
            return false;

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        if($ismobile){
            $url = "http://x.baidu.com/Mobile/Service/selectService?access_token=".$token['access_token']."&device=".$device;
            header("Location: $url");
            exit();
            // return $url;
        }else{
            $temp_url = "http://x.baidu.com/kankan/user/getTempToken";
            $temp_ret = $this->_request($temp_url, array('access_token' => $token['access_token']));
            if(!$temp_ret['data']['temp_token'])
                return false;
            $temp_token = $temp_ret['data']['temp_token'];
            $buy_url = "http://x.baidu.com/Service/selectService?temp_token=".$temp_token."&device=".$device;
            header("Location: $buy_url");
            exit();
        }
        return;
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

    function _device_online($device) {
        if(!$device)
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;

        $is_migrated = $this->is_migratedbyid($device['deviceid']);
        if($is_migrated){
            $ret = $this->load_api('bce')->_device_online($token, $device);
            return $ret;
        }else{
            $ret = $this->load_api('pcs')->_device_online($token, $device);
            return $ret;
        }

    }

    function alarmspace($device) {
        if(!$device || !$device['uid'])
            return false;

        $uid = $device['uid'];

        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;

        $params = array(
            'method' => 'info',
            'access_token' => $token['access_token']
        );

        $ret = $this->_request(BAIDU_API_QUOTA, $params);
        if(!$ret || isset($ret['error_code']))
            $this->_error($ret);

        if(!$ret['quota'] || !$ret['used'])
            return false;

        $result = array(
            'total' => intval($ret['quota']),
            'used' => intval($ret['used'])
        );
        return $result;
    }
    //根据设备id 查询设备是否完成迁移
    function is_migratedbyid($deviceid){
        // 判断请求参数
        if($this->base->is_pcs_request())
            return 0;
        $is_migrated = $this->db->result_first("SELECT is_migrated FROM ".API_DBTABLEPRE."device WHERE deviceid='$deviceid'");
        $is_migrated = $is_migrated?$is_migrated:0;
        return $is_migrated;
    }

}