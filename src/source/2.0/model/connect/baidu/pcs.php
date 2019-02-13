<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/baidu/api.php';

require_once API_SOURCE_ROOT.'lib/connect/baidu/Baidu.php';
require_once API_SOURCE_ROOT.'lib/connect/baidu/BaiduPCS.class.php';

define('BAIDU_PCS_API_DEVICE', 'https://pcs.baidu.com/rest/2.0/pcs/device');
define('BAIDU_API_QUOTA', 'https://pcs.baidu.com/rest/2.0/pcs/quota');

class pcsbaiduapi extends baiduapi {

    function __construct(&$base, $_config) {
        $this->pcsbaiduapi($base, $_config);
    }

    function pcsbaiduapi(&$base, $_config) {
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
    
    function addusertoken($token){
        $param = array(
            'method' => 'addusertoken', 
            'access_token' => $token['access_token'], 
            'refresh_token' => $token['refresh_token']
            );
        $this->_request(BAIDU_PCS_API_DEVICE, $param);
    }

    function device_register($token, $deviceid, $desc) {

        $params = array(
            'method' => 'register',
            'deviceid' => $deviceid,
            'access_token' => $token['access_token'],
            'desc' => $desc,
            'refresh_token' => $token['refresh_token']
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret)
            return $this->_error($ret);

        if(isset($ret['error_code'])) {
            // 已注册处理
            return $ret;
        }

        return $ret;
    }

    function device_update($device, $token, $desc) {

        $params = array(
            'method' => 'update',
            'deviceid' => $device['deviceid'],
            'access_token' => $token['access_token'],
            'desc' => $desc
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return true;
    }

    function device_drop($device, $token) {

        $params = array(
            'method' => 'drop',
            'deviceid' => $device['deviceid'],
            'access_token' => $token['access_token']
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }

    function _connect_list() {
        return true;
    }

    function listdevice($token) {
        $list = array();

        $start = 0;
        $page = 50;

        $params = array(
            'method' => 'list',
            'access_token' => $token['access_token'],
            'start' => $start,
            'num' => $page
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || (isset($ret['error_code']) && $ret['error_code'] != 31353))
            return $this->_error($ret);

        if ($ret['count']) {
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

                $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
                if(isset($ret['error_code']) && $ret['error_code'] == 31353)
                    break;

                if(!$ret || isset($ret['error_code']))
                    return $this->_error($ret);

                $count += $ret['count'];
                $list = array_merge($list, $ret['list']);
            }
        }

        return array(
            'count' => count($list),
            'list' => $list
        );
    }

    function listdevice_by_page($token, $page, $count) {

        $params = array(
            'method' => 'list',
            'access_token' => $token['access_token'],
            'start' => ($page-1)*$count,
            'num' => $count
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(isset($ret['error_code']) && $ret['error_code'] == 31353) {
            $total = 0;
            $list = array();
        } elseif (!$ret || isset($ret['error_code'])) {
            return $this->_error($ret);
        } else {
            $total = $ret['total'];
            $list = $ret['list'];
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
            'method' => 'grant',
            'access_token' => $token['access_token'],
            'uk' => $uk,
            'name' => $name,
            'auth_code' => $auth_code,
            'deviceid' => $deviceid
        );
        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return true;
    }

    function listgrantuser($token, $deviceid) {

        $params = array(
            'method' => 'listgrantuser',
            'access_token' => $token['access_token'],
            'deviceid' => $deviceid
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }

    function listgrantdevice($token) {

        $params = array(
            'method' => 'listgrantdevice',
            'access_token' => $token['access_token']
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(isset($ret['error_code']) && $ret['error_code'] == 31353)
            return false;

        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }

    function subscribe($token, $shareid, $uk) {

        $params = array(
            'method' => 'subscribe',
            'access_token' => $token['access_token'],
            'shareid' => $shareid,
            'uk' => $uk
        );
        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return true;
    }

    function unsubscribe($token, $shareid, $uk) {
        $params = array(
            'method' => 'unsubscribe',
            'access_token' => $token['access_token'],
            'shareid' => $shareid,
            'uk' => $uk
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return true;
    }

    function listsubscribe($token) {
        $params = array(
            'method' => 'listsubscribe',
            'access_token' => $token['access_token']
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(isset($ret['error_code']) && $ret['error_code'] == 31353)
            return false;

        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }

    function liveplay_bytoken($token, $device, $type){
        $params = array(
            'method' => 'liveplay',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'type' => $type,
            'host' => $this->base->onlineip
        );
        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        $ret['connect_type'] = $this->connect_type;
        return $ret;
    }
    function liveplay_byshareid($ps, $type){
        $params = array(
            'method' => 'liveplay',
            'shareid' => $ps['shareid'],
            'uk' => $ps['uk'],
            'type' => $type,
            'host' => $this->base->onlineip
        );
        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        $ret['connect_type'] = $this->connect_type;
        return $ret;
    }

    function liveplay($token, $device, $type, $ps) {
        $type = $type=='rtmp'?'':$type;
        $auth_type = $ps['auth_type'];
        $deviceid = $device['deviceid'];
        if($auth_type == 'token') {
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
        
        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        $ret['connect_type'] = $this->connect_type;

        if($deviceid) {
            $status = $ret['status'];
            $connect_online = $status?1:0;
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `connect_online`='$connect_online', `status`='$status', `lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
            $_ENV['device']->update_power($deviceid, $status&4);
        }

        return $ret;
    }

    function liveplay_muti($token, $devicelist){
        $deviceid_list = array();
        foreach ($devicelist as $device) {
            $deviceid_list[] = $device['deviceid'];
        }

        $list = array();
        $list['list'] = $deviceid_list;

        $params = array(
            'method' => 'liveplay',
            'access_token' => $token['access_token'],
            'param' => json_encode($list)
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        $liveplay_list = $ret['list'];
        for ($i = 0; $i < count($liveplay_list); $i++) {
            $liveplay_list[$i]['connect_type'] = $this->connect_type;
        }

        return $liveplay_list;
    }

    function createshare($token, $device, $share_type, $expires=0) {

        $params = array(
            'method' => 'createshare',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'share' => $share_type
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }

    function cancelshare($token, $device) {

        $params = array(
            'method' => 'cancelshare',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid']
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return true;
    }

    function list_share($start, $num, $sign, $expire) {
        $params = array(
            'method' => 'listshare',
            'sign' => $sign,
            'expire' => $expire,
            'start' => $start,
            'num' => $num
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        if($ret['count'] && $ret['device_list']) {
            foreach($ret['device_list'] as $i => $v) {
                $ret['device_list'][$i]['connect_type'] = $this->connect_type;
                $ret['device_list'][$i]['connect_did'] = '';
            }
        }

        return $ret;
    }

    function dropgrantuser($token, $device, $uk) {
        $params = array(
            'method' => 'dropgrantuser',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'uk' => $uk
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return true;
    }

    function playlist($token, $device, $ps, $starttime, $endtime, $type) {
        if(!$starttime || !$endtime)
            return false;

        if($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                'method' => 'playlist',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'st' => $starttime,
                'et' => $endtime
            );
        } else {
            $params = array(
                'method' => 'playlist',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'st' => $starttime,
                'et' => $endtime
            );
        }

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }

    function vod($token, $device, $ps, $starttime, $endtime) {
        if(!$starttime || !$endtime)
            return false;

        if($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                'method' => 'vod',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'st' => $starttime,
                'et' => $endtime
            );
        } else {
            $params = array(
                'method' => 'vod',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'st' => $starttime,
                'et' => $endtime
            );
        }

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params, false);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        $j = json_decode($ret, true);
        if(json_last_error() == JSON_ERROR_NONE)
            return $this->_error($j);

        return $ret;
    }

    function vodseek($token, $device, $ps, $time) {
        if(!$time)
            return false;

        if ($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;

            $params = array(
                'method' => 'vodseek',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid'],
                'time' => $time
            );
        } else {
            $params = array(
                'method' => 'vodseek',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk'],
                'time' => $time
            );
        }

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if (!$ret || isset($ret['error_code']))
            return $this->_error($ret);
        
        return $ret;
    }

    function thumbnail($token, $device, $ps, $starttime, $endtime, $latest) {
        if(!$latest && (!$starttime || !$endtime))
	        return false;

        if ($ps['auth_type'] == 'token') {
            if(!$token || !$token['access_token'])
                return false;
            $params = array(
                'method' => 'thumbnail',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid']
            );
        } else {
            $params = array(
                'method' => 'thumbnail',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk']
            );
        }

        if (!$latest) {
            $params['st'] = $starttime;
            $params['et'] = $endtime;
        } else {
            $params['latest'] = 1;
        }

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if (!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }

    function infoClip($token, $clipinfo, $clipid){
        if(!$token || !$token['access_token'])
                return false;
        
        $ret = $this->db->fetch_first('SELECT clipid, status, progress, dateline FROM '.API_DBTABLEPRE."device_clip where clipid=$clipid");
        $time = $this->base->time - $ret['dateline'];
        // if($time > 3600){
        //     $this->db->query('UPDATE '.API_DBTABLEPRE.'device_clip SET status=-1, progress=100, lastupdate='.$this->base->time.' WHERE clipid='.$clipid);
        //     $result['clipid'] = strval($ret['clipid']);
        //     $result['status'] = -1;
        //     $result['progress'] = 100;

        //     return $result;
        // }
        $result['clipid'] = strval($ret['clipid']);
        $result['status'] = intval($ret['status']);
        $result['progress'] = intval($ret['progress']);

        return $result;
    }
    function infoclip_bak($token, $type, $clipid) {
        if(!$token || !$clipid)
            return false;

        $storageid = $this->config['storageid'];

        $clipinfo = $this->db->fetch_first('SELECT clipid, deviceid, storageid, name, status, progress FROM '.API_DBTABLEPRE."device_clip where uid=$uid AND storageid=$storageid AND clipid=$clipid");
        if (!$clipinfo)
            return false;

        $result = array();
        $result['clipid'] = intval($clipid);
        $result['deviceid'] = $clipinfo['deviceid'];
        $result['name'] = $clipinfo['name'];
        $result['storageid'] = intval($clipinfo['storageid']);

        if (intval($clipinfo['status']) != 1) {
            $result['status'] = intval($clipinfo['status']);
            $result['progress'] = intval($clipinfo['progress']);

            return $result;
        } else {
            $params = array(
                'method' => 'infoclip',
                'access_token' => $token['access_token'],
                'deviceid' => $clipinfo['deviceid'],
                'type' => 'task'
            );

            $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);

            if (isset($ret['error_code']) && intval($ret['error_code']) == 31376/*no clip task exist*/) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_clip SET status=0, progress=100, lastupdate='.$this->base->time.' WHERE clipid='.$clipid);
                $result['status'] = 0;
                $result['progress'] = 100;

                return $result;
            }

            if(!$ret || isset($ret['error_code']))
                return $this->_error($ret);

            $status = $ret['status'];
            $progress = $ret['progress'];
            $updatetime = $this->base->time;
            $this->db->query('UPDATE '.API_DBTABLEPRE."device_clip SET status=$status, progress=$progress, lastupdate=$updatetime WHERE clipid=$clipid");

            $result['status'] = intval($status);
            $result['progress'] = intval($progress);

            return $result;
        }
    }

    function clip($token, $device, $starttime, $endtime, $name, $client_id) {
        if(!$device || !$device['deviceid'])
            return false;

        $token = $this->get_access_token($device['uid']);
        if(!$token || !$token['access_token'])
            return false;
        
        $deviceid = $device['deviceid'];
        $uid = $device['uid'];
        $storageid = $this->config['storageid'];

        //兼容老百度剪辑
        $oauth_token = $this->db->result_first('SELECT oauth_token FROM '.API_DBTABLEPRE."oauth_access_token where uid=$uid order by expires desc");
        //限制一个用户最大一个剪辑任务
        $uid = $device['uid']; //= '251277';
        $latest_clip_info = $this->db->fetch_first('SELECT clipid, status, dateline FROM '.API_DBTABLEPRE."device_clip where uid=$uid AND deviceid='$deviceid' AND storageid=$storageid order by dateline desc limit 1");
        if ($latest_clip_info && intval($latest_clip_info['status']) == 1 && $this->base->time - $latest_clip_info['dateline'] < 1800){
            $this->base->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_CLIP_EXIST);
        }

        $result = $this->celeryclip($token['access_token'], $name, $oauth_token, $device, $starttime, $endtime, $client_id);
        return $result;

    }
    function celeryclip($token, $name, $oauth_token, $device, $st, $et, $client_id){
        if(!$token || !$name || !$device || !$st || !$et || !$oauth_token)
            return false;
        //增加30分钟限制
        if(($et-$st)>1800){
            $this->base->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        $deviceid = $device['deviceid'];
        $url = "https://api.iermu.com/v2/pcs/device?method=vod&access_token=$oauth_token&deviceid=$deviceid&st=$st&et=$et";
        // $url = '"https://api.iermu.com/v2/pcs/device?method=vod&access_token='.$oauth_token.'&deviceid='.$deviceid.'&st='.$st.'&et='.$et.'"';
        ////检查当前用户是否存在剪辑
        $url = urlencode($url);
        $tsnum = $this->checkts($url);
        if(!$tsnum){
            $this->base->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_CLIP_NODATA);
        }

        $name = $name.$this->_clip_postfix().$this->_clip_extension();
        $id = $this->clip_save($device, $name, $st, $et, $client_id);
        // 调用python任务
        $API_URL = $this->db->result_first('SELECT api FROM '.API_DBTABLEPRE."server where server_type = 7 limit 1");
        if(!$API_URL)
            return false;

        $CLIP_URL = $API_URL."baidu/pcs/clip";
        // $CLIP_URL = "http://127.0.0.1:5001/baidu/pcs/clip";
        // $token = "21.298cb1739d29b34efcf33c59a118759e.2592000.1514359450.337937895-1508471";
        $params = array(
                'url' => $url,
                'token' => $token,
                'deviceid' => $deviceid,
                'name' => $name,
                'clipid' => $id
            );
        
        $ret = $this->_request($CLIP_URL, $params, true, 'POST');
        
        // $cmd = "cd /data/python && python test.py $token $name $url $id";
        // system($cmd);

        $result = array();
        $result['name'] = $name;
        $result['clipid'] = strval($id);
        $result['deviceid'] = $deviceid;
        return $result;
    }
    function clip_save($device, $name, $st, $et, $client_id){
        $deviceid = $device['deviceid'];
        $uid = $device['uid'];
        $storageid = $this->config['storageid'];
        //存储
        $dateline = $this->base->time;
        $filename = $name;
        $pathname = $this->_clip_path();
        $setarray = array();
        $setarray[] = 'deviceid="'.$deviceid.'"';
        $setarray[] = 'uid='.$uid;
        $setarray[] = 'storageid='.$storageid;
        $setarray[] = 'name="'.$name.'"';
        $setarray[] = 'starttime='.$st;
        $setarray[] = 'endtime='.$et;
        $setarray[] = 'pathname="'.$pathname.'"';
        $setarray[] = 'filename="'.$filename.'"';
        $setarray[] = 'client_id="'.$client_id.'"';
        $setarray[] = 'dateline='.$dateline;
        $setarray[] = 'lastupdate='.$dateline;
        $setarray[] = 'status=1';
        $setarray[] = 'progress=0';
        $sets = implode(',', $setarray);
        $this->db->query('INSERT INTO '.API_DBTABLEPRE."device_clip SET $sets");
        $id = $this->db->insert_id();
        return intval($id);
    }
    function checkts($url){
        return 1;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($ch);
        curl_close($ch);
        preg_match_all('/(http.+)[\.]ts/', $content, $temp);
        // var_dump($temp);die;
        $num = count(array_shift($temp));
        return $num;
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
            //$uid = $device['grant_device']?$device['grant_uid']:$device['uid'];
            //$token = $this->get_access_token($uid);
            if($device['grant_device'])
                return false;

            if(!$token || !$token['access_token'])
                return false;

            $params = array(
                'method' => 'meta',
                'access_token' => $token['access_token'],
                'deviceid' => $device['deviceid']
            );
        } else {
            $params = array(
                'method' => 'meta',
                'shareid' => $ps['shareid'],
                'uk' => $ps['uk']
            );
        }

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if (!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        if (isset($ret['list']) && isset($ret['list'][0])) {
            $deviceid = $ret['list'][0]['deviceid'];
            $status = $ret['list'][0]['status'];
            $connect_online = $status?1:0;
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `connect_online`='$connect_online',`status`='$status',`lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
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
                'method' => 'meta',
                'access_token' => $token['access_token'],
                'param' => json_encode(array('list'=>$list))
            );
        } else {
            $list = array();
            foreach($devices as $v) {
                $list[] = array('shareid'=>$v['shareid'], 'uk'=>$v['uk']);
            }

            $params = array(
                'method' => 'meta',
                'param' => json_encode(array('list'=>$list))
            );
        }

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

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
        if(!$device || !$device['deviceid'] || !$command)
            return false;
        if(!$token || !$token['access_token'])
            return false;

        $params = array(
            'method' => 'control',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid'],
            'command' => $command
        );

        $this->base->log('baidu user cmd', json_encode($params));


        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        $this->base->log('baidu user cmd ret', json_encode($ret));
        if(!$ret || isset($ret['error_code']))
            return $this->_error($ret);

        return $ret;
    }


    function _request($url, $params, $json_decode = true, $method = "GET") {
        if(!$url || !$params)
            return false;
        $ret = BaiduUtils::request($url, $params, $method);

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
        if(!$this->base->connect_error) 
            return false;
        
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
    function vodlist($deviceid, $token, $starttime, $endtime) {
        if(!$token || !$token['access_token'])
            return false;

        $url = BAIDU_PCS_API_DEVICE.'?method=vod&access_token='.$token['access_token'].'&deviceid='.$deviceid.'&st='.$starttime.'&et='.$endtime;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($ch);
        curl_close($ch);
        return $content;
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
            'method' => 'meta',
            'access_token' => $token['access_token'],
            'deviceid' => $device['deviceid']
        );

        $ret = $this->_request(BAIDU_PCS_API_DEVICE, $params);
        if (!$ret || isset($ret['error_code']))
            return false;

        if (isset($ret['list']) && isset($ret['list'][0])) {
            $deviceid = $ret['list'][0]['deviceid'];
            $status = $ret['list'][0]['status'];
            $connect_online = $status?1:0;
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `connect_online`='$connect_online',`status`='$status',`lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
            $_ENV['device']->update_power($deviceid, $status&4);
            return $connect_online;
        }

        return false;
    }


}
