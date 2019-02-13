<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/baidu/api.php';

require_once API_SOURCE_ROOT.'lib/connect/baidu/Baidu.php';
require_once API_SOURCE_ROOT.'lib/connect/baidu/BaiduPCS.class.php';

//测试地址 http://bdcam-api.benbun.com
//正式地址 http://api.baiducam.baidubce.com
define('BAIDU_BCE_URL', 'https://api.baiducam.baidubce.com/');

define('BAIDU_BCE_API_DEVICE', BAIDU_BCE_URL.'v1/device/');
define('BAIDU_BCE_API_USR', BAIDU_BCE_URL.'v1/user/');
define('BAIDU_BCE_API_GRANT', BAIDU_BCE_URL.'v1/grant/');
define('BAIDU_BCE_API_SHARE', BAIDU_BCE_URL.'v1/share/');
define('BAIDU_BCE_API_STREAM', BAIDU_BCE_URL.'v1/stream/');

//特殊接口
define('BAIDU_BCE_API_REGISTER', BAIDU_BCE_URL.'v2/Device/Migrate/');
define('BAIDU_BCE_API_PLAYLIST', 'http://vims.baiducam.baidubce.com/v1/videoInfo/playList');
define('BAIDU_BCE_API_THUMBNAIL', 'http://vims.baiducam.baidubce.com/v1/videoInfo/thumbnailList');

define('BAIDU_API_FILE', 'https://pcs.baidu.com/rest/2.0/pcs/file');
define('BAIDU_API_QUOTA', 'https://pcs.baidu.com/rest/2.0/pcs/quota');

class bcebaiduapi extends baiduapi {

    function __construct(&$base, $_config) {
        $this->bcebaiduapi($base, $_config);
    }

    function bcebaiduapi(&$base, $_config) {
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

    function set_state($state) {
        $this->client->setState($state);
    }

    function _connect_auth() {
        return true;
    }

    //0612
    function addusertoken($token){
        $param = array(
            // 'method' => 'addusertoken', 
            'access_token' => $token['access_token'], 
            'refresh_token' => $token['refresh_token']
            );
        $this->_request(BAIDU_BCE_API_USR.'addUserToken', $param);
    }

    function device_register($token, $deviceid, $desc){
        $params = array(
            'deviceid' => $deviceid,
            'access_token' => $token['access_token'],
            'desc' => $desc,
            'refresh_token' => $token['refresh_token']
        );
        
        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'register', $params);
        if(!$ret)
            return $this->_error($ret);
        
        if(isset($ret['code']) && $ret['code'] != 0) {
            // 已注册处理
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            if(isset($ret['data']['user_id'])){
                $ret['uk'] = $ret['data']['user_id'];
            }
            return $ret;
        }

        return $ret['data'];
    }
    //老固件注册
    function device_register_migrate($token, $deviceid, $desc){
        $params = array(
            'deviceid' => $deviceid,
            'access_token' => $token['access_token'],
            'desc' => $desc,
            'refresh_token' => $token['refresh_token']
        );

        $ret = $this->_request(BAIDU_BCE_API_REGISTER.'register', $params);
        if(!$ret)
            return $this->_error($ret);
        
        if(isset($ret['code']) && $ret['code'] != 0) {
            // 已注册处理
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            if(isset($ret['data']['user_id'])){
                $ret['uk'] = $ret['data']['user_id'];
            }
            return $ret;
        }

        return $ret['data'];
    }
    //单个设备迁移
    function device_move_migrate($token, $deviceid){
        $ak = $this->config['client_id'];
        $sk = $this->config['client_secret'];
        $section1 = "S-1-2";
        $section2 = $ak;
        $section3 = mt_rand()."-".(time()+1800)."-".time();
        $section4 = md5($section1 . "." . $section2 . "." . $section3 . "." . $sk);
        $sign_token = $section1.'.'.$section2.'.'.$section3.'.'.$section4;

        $params = array(
            'deviceid' => $deviceid,
            'access_token' => $token['access_token'],
            'sign_token' => $sign_token
        );
        
        $ret = $this->_request(BAIDU_BCE_API_REGISTER.'moveSingle', $params);
        if(!$ret)
            return $this->_error($ret);
        
        if(isset($ret['code']) && $ret['code'] != 0) {
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $ret;
        }

        return $ret['data'];
    }

    function device_update($device, $token, $desc){
        $params = array(
            'deviceid' => $device['deviceid'],
            'access_token' => $token['access_token'],
            'desc' => $desc
        );

        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'update', $params);
        if(!$ret || $ret['code']!=0){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return true;
    }

    function device_drop($device, $token) {
        if(!$device || !$token || !$token['access_token']){
            return false;
        }

        $params = array(
            'deviceid' => $device['deviceid'],
            'access_token' => $token['access_token']
        );

        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'drop', $params);
        if(!$ret || $ret['code']!=0){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return $ret['data'];
    }

    function listdevice($token){
        $list = array();

        $start = 0;
        $page = 50;
        $params = array(
            'access_token' => $token['access_token'],
            'start' => $start,
            'num' => $page
        );
        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'list', $params);
        //if(!$ret || ($ret['code']!=0 && $ret['code'] != 31353)){
        if(!$ret || $ret['code'] != 0) {
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        if ($ret['data']['count']) {
            $total = $ret['data']['total'];
            $count = $ret['data']['count'];
            $list = array_merge($list, $ret['data']['list']);

            while($total > $count) {
                $start += $page;
                $params = array(
                    'access_token' => $token['access_token'],
                    'start' => $start,
                    'num' => $page
                );

                $ret = $this->_request(BAIDU_BCE_API_DEVICE.'list', $params);
                if(isset($ret['code']) && $ret['code'] == 31353)
                    break;

                if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
                    $ret['error_code'] = $ret['code'];
                    $ret['error_msg'] = $ret['msg'];
                    return $this->_error($ret);
                }

                $count += $page;
                $list = array_merge($list, $ret['data']['list']);
            }
            //@todo 添加is_marited字段
            foreach($list as $device){
                if(isset($device['is_migrated'])){
                    $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET is_migrated='.$device["is_migrated"].' WHERE deviceid="'.$device['deviceid'].'"');
                }
            }
        }

        return array(
            'count' => count($list),
            'list' => $list
        );

    }


    function listdevice_by_page($token, $page, $count) {

        $params = array(
            'access_token' => $token['access_token'],
            'start' => ($page-1)*$count,
            'num' => $count
        );

        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'list', $params);
        if(isset($ret['code']) && $ret['code'] == 31353) {
            $total = 0;
            $list = array();
        } elseif (!$ret || (isset($ret['code']) && $ret['code']!=0)) {
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        } else {
            $total = $ret['data']['total'];
            $list = $ret['data']['list'];
        }

        $page = $this->base->page_get_page($page, $count, $total);
        return array(
            'page' => $page['page'],
            'count' => count($list),
            'list' => $list
        );
    }

    function grant($token, $uk, $name, $auth_code, $device) {
        $deviceid = $device['deviceid'];
        if(!$deviceid)
            return false;

        $params = array(
            // 'method' => 'grant',
            'access_token' => $token['access_token'],
            'uk' => $uk,
            'name' => $name,
            'auth_code' => $auth_code,
            'deviceid' => $deviceid
        );
        $ret = $this->_request(BAIDU_BCE_API_GRANT.'grant', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return true;
    }

    function listgrantuser($token, $deviceid) {
        $params = array(
            // 'method' => 'listgrantuser',
            'access_token' => $token['access_token'],
            'deviceid' => $deviceid
        );

        $ret = $this->_request(BAIDU_BCE_API_GRANT.'listGrantUser', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0) ){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return $ret['data'];
    }

    function listgrantdevice($token) {
        $params = array(
            // 'method' => 'listgrantdevice',
            'access_token' => $token['access_token']
        );

        $ret = $this->_request(BAIDU_BCE_API_GRANT.'listGrantDevice', $params);
        if(isset($ret['code']) && $ret['code'] == 31353)
            return false;

        if(!$ret || (isset($ret['code'])&& $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return $ret['data'];
    }

    function subscribe($token, $shareid, $uk) {
        $params = array(
            // 'method' => 'subscribe',
            'access_token' => $token['access_token'],
            'shareid' => $shareid,
            'uk' => $uk
        );
        $ret = $this->_request(BAIDU_BCE_API_SHARE.'subscribe', $params);
        if(!$ret || (isset($ret['code'])&& $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return true;
    }

    function unsubscribe($token, $shareid, $uk) {
        $params = array(
            // 'method' => 'unsubscribe',
            'access_token' => $token['access_token'],
            'shareid' => $shareid,
            'uk' => $uk
        );

        $ret = $this->_request(BAIDU_BCE_API_SHARE.'unSubscribe', $params);

        if(!$ret || (isset($ret['error_code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return true;
    }

    function listsubscribe($token) {
        $params = array(
            // 'method' => 'listsubscribe',
            'access_token' => $token['access_token']
        );
        $ret = $this->_request(BAIDU_BCE_API_SHARE.'listSubscribe', $params);
        if(isset($ret['code']) && $ret['code'] == 31353)
            return false;

        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return $ret['data'];
    }

    function liveplay_bytoken($token, $device, $type){
        $params = array(
            // 'method' => 'liveplay',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'type' => $type,
            'host' => $this->base->onlineip
        );
        $ret = $this->_request(BAIDU_BCE_API_STREAM.'livePlay', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }
        $ret['connect_type'] = $this->connect_type;
        if(isset($ret['data']['src'])){
           $ret['data']['url'] = $ret['data']['src']; 
        }
        if(isset($ret['status'])){
           $ret['data']['status'] = $ret['status']; 
        }
        return $ret['data'];
    }
    function liveplay_byshareid($ps, $type){
        $params = array(
            // 'method' => 'liveplay',
            'shareid' => $ps['shareid'],
            'uk' => $ps['uk'],
            'type' => $type,
            'host' => $this->base->onlineip
        );
        $ret = $this->_request(BAIDU_BCE_API_STREAM.'livePlay', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }
        $ret['connect_type'] = $this->connect_type;
        if(isset($ret['data']['src'])){
           $ret['data']['url'] = $ret['data']['src']; 
        }
        if(isset($ret['status'])){
           $ret['data']['status'] = $ret['status']; 
        }
        return $ret['data'];

    }
    function liveplay($token, $device, $type, $ps, $grant_auth_code=0) {
        $type = $type==''?'rtmp':$type;
        $auth_type = $ps['auth_type'];
        $deviceid = $device['deviceid'];
        if($auth_type == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            
            $params = array(
                // 'method' => 'liveplay',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'type' => $type,
                'host' => $this->base->onlineip,
                'grant_auth_code' => $grant_auth_code 
            );
        } elseif($auth_type == 'share') {
            $params = array(
                // 'method' => 'liveplay',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'type' => $type,
                'host' => $this->base->onlineip 
            );
        }
        $ret = $this->_request(BAIDU_BCE_API_STREAM.'livePlay', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        $ret['data']['connect_type'] = $this->connect_type;
        $ret['data']['type'] = $type;
        $ret['data']['deviceid'] = $deviceid;
        if(isset($ret['data']['src'])){
           $ret['data']['url'] = $ret['data']['src']; 
           unset($ret['data']['src']);
        }
        //@todo 缺少status字段
        // if(isset($ret['status'])){
        //    $ret['data']['status'] = $ret['status']; 
        // }

        if($deviceid && isset($ret['data']['status'])) {
            $status = $ret['data']['status'];
            $connect_online = $status?1:0;
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `connect_online`='$connect_online', `status`='$status', `lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
            $_ENV['device']->update_power($deviceid, $status&4);
        }

        if(!isset($ret['data']['status']) && isset($device['status'])) {
            $ret['data']['status'] = $device['status'];
        }

        return $ret['data'];
    }

    function liveplay_muti($token, $list){
        //@TODO  接口文档中未找到相应参数
        $params = array(
            // 'method' => 'liveplay',
            'access_token' => $token['access_token'],
            'param' => json_encode($list)
        );

        $ret = $this->_request(BAIDU_BCE_API_STREAM.'livePlay', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        $liveplay_list = $ret['list'];
        for ($i = 0; $i < count($liveplay_list); $i++) {
            $liveplay_list[$i]['connect_type'] = $this->connect_type;
        }

        return $liveplay_list;

    }

    function createshare($token, $device, $share_type, $expires=0) {
        $expires = $expires?$expires:'622080000';//默认20年
        $start_time = $this->base->time;
        $end_time = $this->base->time + intval($expires);
        $params = array(
            // 'method' => 'createshare',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'share' => $share_type,
            'start_time' => $start_time,
            'end_time' => $end_time
        );

        $ret = $this->_request(BAIDU_BCE_API_SHARE.'createShare', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return $ret['data'];
    }

    function cancelshare($token, $device) {
        $params = array(
            // 'method' => 'cancelshare',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid']
        );

        $ret = $this->_request(BAIDU_BCE_API_SHARE.'cancelShare', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return true;
    }

    function list_share($start, $num, $sign, $expire) {

        $params = array(
            // 'method' => 'listshare',
            'sign' => $sign,
            'expire' => $expire,
            'start' => $start,
            'num' => $num
        );

        $ret = $this->_request(BAIDU_BCE_API_SHARE.'listShare', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        if($ret['data']['count'] && $ret['data']['device_list']) {
            foreach($ret['data']['device_list'] as $i => $v) {
                $ret['data']['device_list'][$i]['connect_type'] = $this->connect_type;
                $ret['data']['device_list'][$i]['connect_did'] = '';
            }
        }

        return $ret['data'];
    }

    function dropgrantuser($token, $device, $uk) {
        $params = array(
            // 'method' => 'dropgrantuser',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'uk' => $uk
        );

        $ret = $this->_request(BAIDU_BCE_API_GRANT.'dropGrantUser', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        return true;
    }


    function playlist($token, $device, $ps, $starttime, $endtime, $type, $grant_auth_code=0) {
        if(!$starttime || !$endtime)
            return false;
        $starttime = strval($starttime).'000';
        $endtime = strval($endtime).'999';
        if($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                // 'method' => 'playlist',
                'access_token' => $token['access_token'],
                'device_id' => $device['deviceid'],
                'start_t' => $starttime,
                'end_t' => $endtime,
                'grant_auth_code' => $grant_auth_code
            );
        } else {
            $params = array(
                // 'method' => 'playlist',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'start_t' => $starttime,
                'end_t' => $endtime
            );
        }
        $params['app_id'] = $this->config['appid'];

        $ret = $this->_request(BAIDU_BCE_API_PLAYLIST, $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0 && $ret['code']!='10005')){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }
        if($ret['code']=='10005' || ($ret['data']['items'] && count($ret['data']['items'])== 0)){
            $data['results'] = array();
            return $data;
        }

        //返回时间处理 --ykp
        // $data['request_id'] = $ret['request_id'];
        $data['results'] = array();
        if (isset($ret['data']) && is_array($ret['data'])){
            if(isset($ret['data']['items']) && is_array($ret['data']['items'])){
                foreach ($ret['data']['items'] as $key => $value) {
                    $arr[0] = $st = substr($value['start_t'], 0, 10);
                    $arr[1] = $et = substr($value['end_t'], 0, 10);
                    
                    $data['results'][] = $arr;
                }
            }
        }
        return $data;
    }

    function vod($token, $device, $ps, $starttime, $endtime) {
        if(!$starttime || !$endtime)
            return false;
        $starttime = strval($starttime).'000';
        $endtime = strval($endtime).'999';
        if($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                // 'method' => 'vod',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'st' => $starttime,
                'et' => $endtime
            );
        } else {
            $params = array(
                // 'method' => 'vod',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'st' => $starttime,
                'et' => $endtime
            );
        }

        $ret = $this->_request(BAIDU_BCE_API_STREAM.'vod', $params, true);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }
        //@todo 未测试
        if(isset($ret['data']['m3u8_path'])){
            $ret = $this->_request($ret['data']['m3u8_path'], array(), false);
            if(!$ret){
                $m3u8 = "#EXTM3U\n";
                $m3u8 .= "#EXT-X-TARGETDURATION:15\n";
                $m3u8 .= "#EXT-X-MEDIA-SEQUENCE:1\n";
                $m3u8 .= "#EXT-X-DISCONTINUITY\n";
                $m3u8 .= "#EXT-X-CYBERTRANDURATION:0\n";
                $m3u8 .= "#EXT-X-ENDLIST\n";
                return $m3u8;
            }
            return $ret;
            // $m3u8 = file_get_contents($ret['data']['m3u8_path']);
            // return $m3u8;
        }else{
            $m3u8 = "#EXTM3U\n";
            $m3u8 .= "#EXT-X-TARGETDURATION:15\n";
            $m3u8 .= "#EXT-X-MEDIA-SEQUENCE:1\n";
            $m3u8 .= "#EXT-X-DISCONTINUITY\n";
            $m3u8 .= "#EXT-X-CYBERTRANDURATION:0\n";
            $m3u8 .= "#EXT-X-ENDLIST\n";
            return $m3u8;
        }

        return $ret['data'];
    }

    function vodseek($token, $device, $ps, $time) {
        if(!$time)
            return false;
        $temp_time = strval($time).'999';
        if ($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                // 'method' => 'vodseek',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'time' => $temp_time
            );
        } else {
            $params = array(
                // 'method' => 'vodseek',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'time' => $temp_time
            );
        }

        $ret = $this->_request(BAIDU_BCE_API_STREAM.'vodSeek', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }
        if(!$ret['data']){
            $params['time'] = strval($time).'000';
            $ret = $this->_request(BAIDU_BCE_API_STREAM.'vodSeek', $params);
            if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
                $ret['error_code'] = $ret['code'];
                $ret['error_msg'] = $ret['msg'];
                return $this->_error($ret);
            }
        }

        //返回差异修改 --ykp
        if(isset($ret['data']['end_t'])){
            $ret['data']['end_time'] = substr($ret['data']['end_t'], 0, 10);
            unset($ret['data']['end_t']);
        }
        if(isset($ret['data']['start_t'])){
            $ret['data']['start_time'] = substr($ret['data']['start_t'], 0, 10);
            unset($ret['data']['start_t']);
        }
        $ret['data']['t'] = strval($time);//floor(($ret['data']['start_time'] + $ret['data']['end_time'])/2);
        if(!$ret['data']['start_time'])
            $ret['data']['start_time'] = strval($time);
        if(!$ret['data']['end_time'])
            $ret['data']['end_time'] = strval($time);
        return $ret['data'];
    }

    function thumbnail($token, $device, $ps, $starttime, $endtime, $latest, $grant_auth_code=0) {
        if(!$latest && (!$starttime || !$endtime))
	        return false;

        if ($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                // 'method' => 'thumbnail',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'grant_auth_code' => $grant_auth_code
            );
        } else {
            $params = array(
                // 'method' => 'thumbnail',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk']
            );
        }
        $params['app_id'] = $this->config['appid'];
        if (!$latest) {
            $params['device_id'] = $device['deviceid'];
            $params['start_t'] = $starttime."000";
            $params['end_t'] = $endtime."999";
            $ret = $this->_request(BAIDU_BCE_API_THUMBNAIL, $params);
            if($ret && (isset($ret['code']) && strval($ret['code'])=='10001')){
                $result= array('count' => 0, 'list' => array() );
                return $result;
            }
            if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
                $ret['error_code'] = $ret['code'];
                $ret['error_msg'] = $ret['msg'];
                return $this->_error($ret);
            }
            if($ret['data'] && count($ret['data']['items'])>0){
                foreach ($ret['data']['items'] as $k => $v) {
                    $ret['data']['items'][$k]['time'] = substr($v['time'], 0, 10);
                }
            }
            $result['count'] = $ret['data']['count'];
            $result['list'] = $ret['data']['items'];
            return $result;
        } else {
            $params['latest'] = 1;
            $ret = $this->_request(BAIDU_BCE_API_STREAM.'thumbnail', $params);
            if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
                $ret['error_code'] = $ret['code'];
                $ret['error_msg'] = $ret['msg'];
                return $this->_error($ret);
            }
            if($ret['data'] && count($ret['data']['list'])>0){
                foreach ($ret['data']['list'] as $k => $v) {
                    $ret['data']['list'][$k]['time'] = substr($v['time'], 0, 10);
                }
            }
            $result['count'] = $ret['data']['count'];
            $result['list'] = $ret['data']['list'];
            return $result;
        }
    }
    function infoClip($token,$clipinfo,$clipid){
        if(!$token || !$token['access_token'])
                return false;
        $params = array(
                // 'method' => 'infoclip',
                'access_token' => $token['access_token'],
                'task_no' => $clipinfo['storage_cid']
            );
        $ret = $this->_request(BAIDU_BCE_API_STREAM.'infoClip', $params);
        // echo json_encode($ret);die;
    if (isset($ret['code']) && intval($ret['code']) == 10401/*clip task filed*/) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_clip SET status=-1, lastupdate='.$this->base->time.' WHERE clipid='.$clipid);
            $result['status'] = -1;
            $result['progress'] = 0;

            return $result;
        }
        // if (isset($ret['code']) && intval($ret['code']) == 31376/*no clip task exist*/) {
        //     $this->db->query('UPDATE '.API_DBTABLEPRE.'device_clip SET status=0, progress=100, lastupdate='.$this->base->time.' WHERE clipid='.$clipid);
        //     $result['status'] = 0;
        //     $result['progress'] = 100;

        //     return $result;
        // }

        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }
        if(!$ret['data'] || $ret['data']==''){
            $ret2 = array(
                    "error_code" => 31295,
                    "error_msg" => "clip request failed",
                    "connect_type" => "1",
                    "request_id" => $ret['request_id']
                );
            return $ret2;
        }
        $status = $ret['data']['status'];
        $progress = $ret['data']['progress'];
        $updatetime = $this->base->time;
        $this->db->query('UPDATE '.API_DBTABLEPRE."device_clip SET status=$status, progress=$progress, lastupdate=$updatetime WHERE clipid=$clipid");

        if($progress == 100 && $status == 0){
            $clipinfo = $this->db->fetch_first('SELECT clipid, deviceid, storageid, storage_cid, name, starttime, endtime, pathname, status, progress FROM '.API_DBTABLEPRE."device_clip where clipid=$clipid");
            $re['clipid'] = strval($clipid);
            $re['deviceid'] = $clipinfo['deviceid'];
            $re['starttime'] = strval($clipinfo['starttime']);
            $re['endtime'] = strval($clipinfo['endtime']);
            $re['name'] = $clipinfo['name'];
            $re['url'] = $clipinfo['pathname'];
            $re['storageid'] = intval($clipinfo['storageid']);
            // $result['storage_cid'] = intval($clipinfo['storage_cid']);
            $re['status'] = intval($clipinfo['status']);
            $re['progress'] = intval($clipinfo['progress']);
            return $re;
        }

        $result['status'] = intval($status);
        $result['progress'] = intval($progress);
        return $result;
    }

    function clip($token, $device, $starttime, $endtime, $name, $client_id) {
        $deviceid = $device['deviceid'];
        $uid = $device['uid'];
        $storageid = $this->config['storageid_bce'];
        $error_occured_lastclip = 0;
        $latest_clip_info = $this->db->fetch_first('SELECT clipid, status, storage_cid FROM '.API_DBTABLEPRE."device_clip where uid=$uid AND deviceid='$deviceid' AND storageid=$storageid order by dateline desc limit 1");
        if ($latest_clip_info && intval($latest_clip_info['status']) == 1) {
            $params = array(
                'access_token' => $token['access_token'],
                'task_no' => $latest_clip_info['storage_cid']
            );
            //@todo 更新数据库状态

            $ret = $this->_request(BAIDU_BCE_API_STREAM.'infoClip', $params);

            if ($ret && isset($ret['data']['status']) && intval($ret['data']['status']) == -1) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_clip SET status=-1, progress=0, lastupdate='.$this->base->time.' WHERE clipid='.$latest_clip_info['clipid']);
                $error_occured_lastclip = 1;
            }
        }

        $filename = $name.$this->_clip_postfix();

        $st = $starttime;
        $et = $endtime;
        if(strlen($st)<13){
            $st = $st."000";
        }
        if(strlen($et)<13){
            $et = $et."999";
        }
        $params = array(
            // 'method' => 'clip',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'st' => $st,
            'et' => $et,
            'name' => $filename,
            'upload_netdisk' => 0
        );
        $ret = $this->_request(BAIDU_BCE_API_STREAM.'clip', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        $dateline = $this->base->time;
        //@todo 保存文件路径修改
        $filename = $filename.$this->_clip_extension();
        $pathname = $ret['data']['mct_file_path'];//$this->_clip_path().$filename;
        $setarray = array();
        $setarray[] = 'deviceid="'.$deviceid.'"';
        $setarray[] = 'uid='.$uid;
        $setarray[] = 'storageid='.$storageid;
        $setarray[] = 'name="'.$name.'"';
        $setarray[] = 'starttime='.$starttime;
        $setarray[] = 'endtime='.$endtime;
        $setarray[] = 'pathname="'.$pathname.'"';
        $setarray[] = 'filename="'.$filename.'"';
        $setarray[] = 'client_id="'.$client_id.'"';
        $setarray[] = 'dateline='.$dateline;
        $setarray[] = 'lastupdate='.$dateline;
        $setarray[] = 'status=1';
        $setarray[] = 'progress=0';
        //@todo 返回结果task_no添加到数据库
        if (isset($ret['data']['task_no'])){
            $setarray[] = 'storage_cid="'.$ret['data']['task_no'].'"';
        }
        $sets = implode(',', $setarray);
        $this->db->query('INSERT INTO '.API_DBTABLEPRE."device_clip SET $sets");
        $id = $this->db->insert_id();
        // if ($latest_clip_info && intval($latest_clip_info['status']) == 1 && !$error_occured_lastclip) {
        //     $this->db->query('UPDATE '.API_DBTABLEPRE.'device_clip SET status=0, progress=100, lastupdate='.$this->base->time.' WHERE clipid='.$latest_clip_info['clipid']);
        // }

        $result = array();
        $result['name'] = $name;
        $result['clipid'] = strval($id);
        $result['deviceid'] = $deviceid;

        return $result;
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

    function _connect_meta() {
        return true;
    }

    function device_meta($token, $device, $ps) {

        if ($ps['auth_type'] == 'token') {
            if($device['grant_device'])
                return false;
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                // 'method' => 'meta',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid']
            );
        } else {
            $params = array(
                // 'method' => 'meta',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk']
            );
        }

        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'meta', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        if(isset($ret['data'])){
            $ret = $ret['data'];
        }
        if(isset($ret['list']) && count($ret['list'])==0){
            $ret['error_code'] = 31365;
            $ret['error_msg'] = "device share not exist";
            $ret['connect_type'] = "1";
            return $this->_error($ret);
        }
        if (isset($ret['list']) && isset($ret['list'][0])) {
            $deviceid = $ret['list'][0]['deviceid'];
            $status = $ret['list'][0]['status'];
            $connect_online = $status?1:0;
            $is_migrated = 0;
            if(isset($ret['list'][0]['is_migrated'])){
                $is_migrated = $ret['list'][0]['is_migrated'];
            }
            $cvr_type = $ret['list'][0]['cvr_end_time']>$this->base->time?1:0;
            $cvr_day = $ret['list'][0]['cvr_day'];
            $cvr_end_time = $ret['list'][0]['cvr_end_time'];
            $thumbnail = $ret['list'][0]['thumbnail'];
            $data = "`connect_online`='$connect_online',`status`='$status', is_migrated='$is_migrated'";
            if($cvr_day && $cvr_end_time){
                $data .= ", cvr_type = '$cvr_type', cvr_day = '$cvr_day', cvr_end_time = '$cvr_end_time'";
            }
            if($thumbnail){
                $data .= ", connect_thumbnail = '$thumbnail'";
            }
            $data .= ", `lastupdate`='".$this->base->time."'";
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET $data WHERE deviceid='$deviceid'");
            $_ENV['device']->update_power($deviceid, $status&4);
            return $ret['list'][0];
        }

        return false;
    }

    function device_batch_meta($token, $devices, $ps) {
        
        if(!$devices)
            return false;

        if($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $list = array();
            foreach($devices as $v) {
                $list[] = $v['deviceid'];
            }

            $params = array(
                // 'method' => 'meta',
                'access_token' => $token['access_token'],
                'param' => json_encode(array('list'=>$list))
            );
        } else {
            $list = array();
            foreach($devices as $v) {
                $list[] = array('shareid'=>$v['shareid'], 'uk'=>$v['uk']);
            }

            $params = array(
                // 'method' => 'meta',
                'param' => json_encode(array('list'=>$list))
            );
        }

        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'meta', $params);
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }

        if(isset($ret['data'])){
            $ret = $ret['data'];
        }
        if(isset($ret['list'])) {
            foreach($ret['list'] as $device) {
                $deviceid = $device['deviceid'];
                $status = $device['status'];
                $connect_online = $status?1:0;
                $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `connect_online`='$connect_online', `status`='$status', `lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
                $_ENV['device']->update_power($deviceid, $status&4);
            }
            return true;
        }

        return false;
    }

    function device_usercmd($token, $device, $command, $response=0) {
        if(!$token || !$token['access_token'])
                return false;
        $params = array(
            // 'method' => 'control',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'command' => $command
        );

        $this->base->log('baidu user cmd', json_encode($params));

        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'control', $params);
        $this->base->log('baidu user cmd ret', json_encode($ret));
        if(!$ret || (isset($ret['code']) && $ret['code']!=0)){
            $ret['error_code'] = $ret['code'];
            $ret['error_msg'] = $ret['msg'];
            return $this->_error($ret);
        }
        //ykp 字符串转换
        $result =  '{';
        $result .= '"data":["_result",{';
        $result .= '"userData":"'.addslashes($ret['data']['user_data']).'"}]';
        // $result .= '"request_id":'.$ret['request_id'];
        $result .=  '}';
        return json_decode($result, true);
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
        if(!$url)
            return false;

        $ret = BaiduUtils::request($url, $params);
        // log记录 ----------- debug_log
        log::debug_log('BAIDU', $url, $params, BaiduUtils::$errno, $ret, BaiduUtils::$errmsg);

        if ($ret && $json_decode) {
            $ret = json_decode($ret, true);
            //返回结果修改 ---ykp
            // if（ isset($ret['data']) && is_array($ret['data']) ）{
            //     foreach ($ret['data'] as $key => $value) {
            //         $ret["$key"] = $value;
            //     }
            // }
            if(json_last_error() != JSON_ERROR_NONE)
                return false;
        }

        return $ret;
    }

    function _error($error, $extras=NULL) {
        if(!$this->base->connect_error) 
            return false;
        
        if($error && $error['error_code']) {
            $error_code = $error['error_code'];

            $errors = array(
                '110' => array(API_HTTP_FORBIDDEN, API_ERROR_TOKEN),
                '31042' => array(API_HTTP_FORBIDDEN, API_ERROR_TOKEN)
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
                //return $this->_error($ret);
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
                //$expiretime = $time + $cvr_day*24*3600;
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
    function vodlist($deviceid ,$token,  $starttime, $endtime) {
        if(!$token || !$token['access_token'])
            return false;

        $starttime = strval($starttime).'000';
        $endtime = strval($endtime).'999';
        $url = BAIDU_BCE_API_STREAM.'vod?access_token='.$token['access_token'].'&deviceid='.$deviceid.'&st='.$starttime.'&et='.$endtime;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($ch);
        curl_close($ch);
        //@todo m3u8文件获取
        $content = json_decode($content,true);
        if(isset($content['data']['m3u8_path']) && $content['data']['m3u8_path']!='' ){
            $ret = $this->_request($content['data']['m3u8_path'], array(), false);
            if(!$ret){
                $m3u8 = "#EXTM3U\n";
                $m3u8 .= "#EXT-X-TARGETDURATION:15\n";
                $m3u8 .= "#EXT-X-MEDIA-SEQUENCE:1\n";
                $m3u8 .= "#EXT-X-DISCONTINUITY\n";
                $m3u8 .= "#EXT-X-CYBERTRANDURATION:0\n";
                $m3u8 .= "#EXT-X-ENDLIST\n";
                return $m3u8;
            }
            return $ret;
            // return file_get_contents($content['data']['m3u8']);
        }else{
            $m3u8 = "#EXTM3U\n";
            $m3u8 .= "#EXT-X-TARGETDURATION:15\n";
            $m3u8 .= "#EXT-X-MEDIA-SEQUENCE:1\n";
            $m3u8 .= "#EXT-X-DISCONTINUITY\n";
            $m3u8 .= "#EXT-X-CYBERTRANDURATION:0\n";
            $m3u8 .= "#EXT-X-ENDLIST\n";
            return $m3u8;
        }

        // return $content;
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

    function _device_online($token, $device) {
        if(!$device)
            return false;

        if(!$token || !$token['access_token'])
            return false;

        $params = array(
            // 'method' => 'meta',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid']
        );

        $ret = $this->_request(BAIDU_BCE_API_DEVICE.'meta', $params);
        if (!$ret || (isset($ret['code']) && $ret['code']!=0))
            return false;

        if (isset($ret['data']['list']) && isset($ret['data']['list'][0])) {
            $deviceid = $ret['data']['list'][0]['deviceid'];
            $status = $ret['data']['list'][0]['status'];
            $connect_online = $status?1:0;
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `connect_online`='$connect_online',`status`='$status',`lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
            $_ENV['device']->update_power($deviceid, $status&4);
            return $connect_online;
        }

        return false;
    }

}
