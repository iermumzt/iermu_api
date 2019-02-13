<?php

!defined('IN_API') && exit('Access Denied');

class pushmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->pushmodel($base);
    }

    function pushmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
        $this->base->load('user');
        $this->base->load('device');
    }
    
    function get_client_by_udid($udid, $uid, $pushid) {
        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."push_client WHERE udid='$udid' AND uid='$uid' AND pushid='$pushid'");
        if($client) {
            $client['config'] = unserialize($client['config']);
        }
        return $client?$client:array();
    }
    
    function get_push_service_by_pushid($pushid) {
        $service = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."push_service WHERE pushid='$pushid'");
        return $service?$service:array();
    }
    
    function client_register($uid, $udid, $pushid, $config, $connect_type, $active, $push_version, $appid, $client_id, $access_token) {
        if(!$uid || !$udid || !$pushid || !$config || !$client_id || !$access_token)
            return false;
        
        $config = serialize($config);
        
        $platform = $this->db->result_first("SELECT platform FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        $platform = intval($platform);
        
        $client = $this->get_client_by_udid($udid, $uid, $pushid);
        if($client) {
            $cid = $client['cid'];
            $this->db->query("UPDATE ".API_DBTABLEPRE."push_client SET pushid='$pushid', config='$config', connect_type='$connect_type', active='$active', appid='$appid', client_id='$client_id', platform='$platform', lang='".API_LANGUAGE."', push_version='$push_version', lastupdate='".$this->base->time."', status='1' WHERE cid='$cid'");
        } else {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."push_client SET udid='$udid', uid='$uid', pushid='$pushid', config='$config', connect_type='$connect_type', active='$active', appid='$appid', client_id='$client_id', platform='$platform', lang='".API_LANGUAGE."', push_version='$push_version', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='1'");
            $cid = $this->db->insert_id();
        }
        
        if($cid && $active) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."push_client SET active='0' WHERE udid='$udid' AND uid='$uid' AND cid<>'$cid'");
        }
        
        // 清除其他用户该设备登录状态
        $this->db->query("UPDATE ".API_DBTABLEPRE."push_client SET status='-1' WHERE udid='$udid' AND uid<>'$uid' AND platform='$platform'");
        
        // TODO: push config
        
        // update token
        if($access_token) 
            $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_access_token SET udid='$udid' WHERE oauth_token='$access_token'");
        
        return ''.$cid;
    }
    
    function device_alarm($device, $param, $time, $client_id, $appid) {
        if(!$device || !$device['deviceid'] || !$param || !$time || !$client_id || !$appid)
            return false;
        
        $deviceid = $device['deviceid'];
        $uid = $device['uid'];

        $user = $_ENV['user']->get_user_by_uid($uid);
        if(!$user)
            return false;
        
        $device = $_ENV['device']->update_cvr_info($device);

        $param['msgtype'] = 0;
        $param['toid'] = strval($uid);
        $param['toname'] = strval($user['username']);
        $param['totype'] = 1;
        
        $param['uid'] = $uid;
        
        $this->base->log('device alarm start', 'deviceid='.$deviceid.', time='.$time.', param='.json_encode($param));
        
        // 索引表
        $alarm_tableid = $device['alarm_tableid'];
        if(!$alarm_tableid) {
            $this->base->load('device');
            $alarm_tableid = $_ENV['device']->gen_alarm_tableid($deviceid);
            if(!$alarm_tableid)
                return false;
            
            $device['alarm_tableid'] = $alarm_tableid;
        }
        
        $this->base->log('device alarm start', 'deviceid='.$deviceid.', tableid='.$alarm_tableid);
        
        $param['natid'] = $deviceid;
        
        $this->base->log('device alarm', 'deviceid='.$deviceid.', timezone='.$device['timezone']);
        
        $fmtime = $this->base->time_format($time, 'Y-m-d H:i:s \U\T\CP', $device['timezone']);
        $filename = $this->base->time_format($time, 'YmdHis', $device['timezone']);
        
        $param['time'] = $time;
        $param['recdatetime'] = $fmtime;
        $param['alarmtime'] = $fmtime;
        
        //$cvr_day = $device['cvr_day']?$device['cvr_day']:7;
        //$expiretime = $time + $cvr_day*24*3600;
        //$expiretime = $this->base->day_end_time($expiretime, $device['timezone']);
        
        // 报警推送关键字段含义：
        // type\":%d,\"value\":%d
        // type值含义：
        // 0, //报警输入（433）
        // 1, //视频丢失
        // 2, //视频遮挡
        // 3, //移动侦测
        // 4, //事件
        // 5, //网络PAD，暂时未用
        // 6, //声音报警
        // 7, //外接报警输入和网络报警输入
	    // 8, //人脸
        // 9, //上身
        // 10, //身体
        // value值含义为通道号（从0开始）
        /*
    		"硬盘满",				//	0	cmsEvent_HddFull
    		"硬盘坏",				//	1	cmsEvent_HddBad
    		"IP冲突",				//	2	cmsEvent_IpCheck
    		"网络断开",				//	3	cmsEvent_NetBreak
    		"非法的网络访问",		//	4	cmsEvent_UnauthAccess
    		"视频制式不匹配",		//	5	cmsEvent_StandMisMatch
    		"视频输入错误",			//	6	cmsEvent_VideoErr
    		"无硬盘",				//	7	cmsEvent_NoDisk
    		"FTP错误",				//	8	cmsEvent_FtpErr
    		"配置过期",				//	9	cmsEvent_RefreshTokenErr
    		"有新版本",				//	10	cmsEvent_NewVersion
    		"设备被复位需重新配置"	//	11	cmsEvent_ResetDevice
        */
        $type = $param['type'];
        $sensorid = $actionid = '';
        $sensortype = $actiontype = 0;
        $sensorname = '';
        
        if(!in_array($type, array(0, 3, 6, 8, 9, 10))) {
            $this->base->log('device alarm failed', 'unsupported alarm type='.$type);
            return false;
        }
        
        $need_storage = 1;
        $need_client_push = 1;
        $need_wx_push = 1;
        
        if($type == 0) {
            $actionid = $param['value'];
            $action = $_ENV['device']->get_sensor_action_by_actionid($actionid);
            if(!$action || $action['status'] == 0)
                return false;
            
            $actiontype = $action['actiontype'];
            
            // 低电报警不产生图片、不微信报警
            if($actiontype == 1) {
                $need_storage = 0;
                $need_wx_push = 0;
            }
            
            $sensorid = $action['sensorid'];
            $sensor = $_ENV['device']->get_sensor_by_sensorid($sensorid);
            if(!$sensor || $sensor['status'] == 0)
                return false;
            
            $device_sensor = $_ENV['device']->get_device_sensor_by_sensorid($deviceid, $sensorid);
            if(!$device_sensor || $device_sensor['status'] == 0)
                return false;
            
            $sensorname = $sensor['name'];
            $sensortype = $sensor['type'];
            
            $param['sensorid'] = strval($sensorid);
            $param['sensorname'] = strval($sensorname);
            $param['sensortype'] = intval($sensortype);
            $param['actionid'] = strval($actionid);
            $param['actiontype'] = intval($actiontype);
        }
        
        if($need_storage) {
            // 存储服务
            $storage_file = $_ENV['device']->check_alarm_file_exist($deviceid, $uid, $alarm_tableid, $time);
            if($storage_file) {
                $this->base->log('device alarm storage file exist', 'file='.json_encode($storage_file));
                $storageid = $storage_file['storageid'];
                $pathname = $storage_file['pathname'];
                $filename = $storage_file['filename'];
                $expiretime = $storage_file['expiretime'];
            } else {
                //$storageid = $param['storageid'];
                $storageid = 0;
                if(!$storageid) {
                    $client = $this->base->load_connect($device['connect_type']);
                    if(!$client)
                        return false;
            
                    $storageid = $client->get_alarm_storageid($device);
                    if(!$storageid)
                        return false;
                }
            
                $storage_service = $this->base->load_storage($storageid);
                if(!$storage_service)
                    return false;
        
                $storage_info = $storage_service->alarm_info($device, $time, $filename);
                if(!$storage_info || !$storage_info['pathname'] || !$storage_info['filename'] || !$storage_info['filepath'] || !$storage_info['upload_token'])
                    return false;
        
                $pathname = $storage_info['pathname'];
                $filename = $storage_info['filename'];
                $expiretime = intval($storage_info['expiretime']);
        
                $this->base->log('device alarm get storage info success', 'deviceid='.$deviceid.', info='.json_encode($storage_info));
            }
        }
        
        // 推送服务
        $clients = array();
        if($need_client_push) {
            $clients = $this->_device_alarm_push_clients($device, $type);
        }
        
        
        // 文件表
        if($need_storage) {
            $status = $clients?0:-1;
            $alarm = $_ENV['device']->add_alarm($deviceid, $alarm_tableid, $uid, $type, $sensorid, $sensortype, $actionid, $actiontype, $time, $expiretime, $pathname, $filename, 0, $storageid, $param, $client_id, $appid, $status);
            if(!$alarm)
                return false;
            
            $this->base->log('device alarm add success', 'deviceid='.$deviceid.', alarm='.json_encode($alarm));
        } else {
            $alarm = array(
                'deviceid' => $deviceid,
                'uid' => $uid,
                'type' => $type,
                'sensorid' => $sensorid,
                'sensortype' => $sensortype,
                'actionid' => $actionid,
                'actiontype' => $actiontype,
                'time' =>$time,
                'need_storage' => 0
            );
            $this->base->log('device alarm no need storage', 'deviceid='.$deviceid.', alarm='.json_encode($alarm));
        }

        $alarm['title'] = $device['desc'];
        
        // 微信推送
        $weixins = array();
        if($need_wx_push) {
            $weixin_user = $this->db->fetch_first("SELECT w.* FROM ".API_DBTABLEPRE."member_connect c LEFT JOIN ".API_DBTABLEPRE."member_weixin w ON c.connect_uid=w.unionid WHERE c.connect_type='".API_WEIXIN_CONNECT_TYPE."' AND c.uid='$uid' AND c.status>0");
            if($weixin_user && $weixin_user['openid'] && $weixin_user['alarm_push']) {
                $openid = $weixin_user['openid'];
                $unionid = $weixin_user['unionid'];
            
                $weixin_alarm = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_weixin_alarm WHERE unionid='$unionid' AND deviceid='$deviceid'");
                if($weixin_alarm) {
                    $lastalarmpush = $weixin_alarm['lastalarmpush'];
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_weixin_alarm SET unionid='$unionid', deviceid='$deviceid'");
                    $lastalarmpush = 0;
                }
            
                // 40s认为连续报警，10分钟后报警
                if($type == 0 || ($time - $device['lastalarm'] > 40) || ($time - $lastalarmpush > 600)) {
                    $wconnect = $this->base->load_connect(API_WEIXIN_CONNECT_TYPE);
                    if($wconnect) {
                        $wtoken = $wconnect->get_weixin_token();
                        if($wtoken && $wtoken['access_token']) {
                            $weixins = array(
                                'openids' => array($openid),
                                'access_token' => $wtoken['access_token']
                            );
                        }
                    }
                
                    // update lastalarmpush
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_weixin_alarm SET lastalarmpush='$time', lastupdate='".$this->base->time."' WHERE unionid='$unionid' AND deviceid='$deviceid'");
                    $this->db->query("UPDATE ".API_DBTABLEPRE."member_weixin SET lastalarmpush='$time' WHERE unionid='$unionid'");
                }
            }
        }
        
        // update lastalarm
        if($device['lastalarm'] < $time)
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET lastalarm='$time' WHERE deviceid='$deviceid'");
        
        // 推送
        if($clients || $weixins) {
            $this->base->log('device alarm need push', 'deviceid='.$deviceid.', need_push=1');
            $push_server = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid='$appid' AND server_type='2' AND status>0");
            if($push_server && $push_server['api']) {
                $alarm['desc'] = $device['desc'];
        
                $url = $push_server['api'].'device/alarm';
                $params = array(
                    'alarm' => json_encode($alarm),
                    'param' => json_encode($param),
                    'clients' => json_encode($clients),
                    'weixins' => json_encode($weixins)
                );
        
                $ret = $this->_request($url, $params, 'POST');
                $this->base->log('device alarm need push', 'deviceid='.$deviceid.', ret='.json_encode($ret));
            
                if(!$ret || $ret['http_code'] != 200) {
                    $this->base->log('device alarm push failed.');
                    //return false;
                }
            }
        }
        
        if(!$need_storage) {
            return array('no_storage' => 1);
        } else if($storage_file) {
            return array('file_exist' => 1);
        } else {
            $cvr_alarm = $_ENV['device']->gen_cvr_alarm($device);
            $result = array('filepath' => $storage_info['filepath'], 'upload_token' => $storage_info['upload_token'], 'storageid' => intval($storageid), 'cvr_alarm' => $cvr_alarm?1:0);
            if(isset($storage_info['extras']) && is_array($storage_info['extras'])) {
                $result = array_merge($result, $storage_info['extras']);
            }
            return $result;
        }
    }
    
    function _request($url, $params = array(), $httpMethod = 'GET') {
        $ch = curl_init();
        
        $headers = array();
        if (isset($params['host'])) {
            $headers[] = 'X-FORWARDED-FOR: ' . $params['host'] . '; CLIENT-IP: ' . $params['host'];
            unset($params['host']);
        }

        $curl_opts = array(
            CURLOPT_CONNECTTIMEOUT  => 5,
            CURLOPT_TIMEOUT         => 10,
            CURLOPT_USERAGENT       => 'iermu api server/1.0',
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_FOLLOWLOCATION  => false,
        );

        if (stripos($url, 'https://') === 0) {
            $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
        }
    
        if (strtoupper($httpMethod) === 'GET') {
            $query = http_build_query($params, '', '&');
            $delimiter = strpos($url, '?') === false ? '?' : '&';
            $curl_opts[CURLOPT_URL] = $url . $delimiter . $query;
            $curl_opts[CURLOPT_POST] = false;
        } else {
            $body = http_build_query($params, '', '&');
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_POSTFIELDS] = $body;
        }

        if (!empty($headers)) {
            $curl_opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $curl_opts);
        $result = curl_exec($ch);
        
        $info = curl_getinfo($ch);
        $this->base->log('push _request info', 'errorno='.curl_errno($ch).', errormsg='.curl_error($ch).', url='.$info['url'].',total_time='.$info['total_time'].', namelookup_time='.$info['namelookup_time'].', connect_time='.$info['connect_time'].', pretransfer_time='.$info['pretransfer_time'].',starttransfer_time='.$info['starttransfer_time']);
        
        if ($result === false) {
            $this->base->log('push _request error', 'result false');
            curl_close($ch);
            return false;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->base->log('push _request finished', 'http_code='.$http_code.',result='.$result);
        
        return array('http_code' => $http_code, 'data' => json_decode($result, true)); 
    }
    
    function _device_alarm_push_clients($device, $type) {
        if(!$device || !$device['deviceid']  || !$device['uid'])
            return array();
        
        $deviceid = $device['deviceid'];
        $uid = $device['uid'];
        
        $alarm_push = $this->db->result_first("SELECT alarm_push FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        if(!$alarm_push)
            return array();
        
        $sqladd = " AND appid='".$device['appid']."'";
        
        // 屏蔽ios type=0 推送
        //if($type == 0) $sqladd = ' AND platform<>4';
        
        $clients = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."push_client WHERE uid='$uid' AND active>0 AND status>0 AND (connect_type='-1' OR connect_type='".$device['connect_type']."') $sqladd");
        if(!$clients)
            return array();
        
        return $clients;
    }
    
}
