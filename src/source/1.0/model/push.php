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
    
    function client_register($uid, $udid, $pushid, $config, $appid, $client_id, $access_token) {
        if(!$uid || !$udid || !$pushid || !$config || !$client_id || !$access_token)
            return false;
        
        $config = serialize($config);
        
        $platform = $this->db->result_first("SELECT platform FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        $platform = intval($platform);
        
        $client = $this->get_client_by_udid($udid, $uid, $pushid);
        if($client) {
            $cid = $client['cid'];
            $this->db->query("UPDATE ".API_DBTABLEPRE."push_client SET pushid='$pushid', config='$config', active='1', appid='$appid', client_id='$client_id', platform='$platform', lastupdate='".$this->base->time."', status='1' WHERE cid='$cid'");
        } else {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."push_client SET udid='$udid', uid='$uid', pushid='$pushid', config='$config', active='1', appid='$appid', client_id='$client_id', platform='$platform', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='1'");
            $cid = $this->db->insert_id();
        }
        
        if($cid && $active) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."push_client SET active='0' WHERE udid='$udid' AND uid='$uid' AND cid<>'$cid'");
        }
        
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
        $param['natid'] = $deviceid;
        
        $tz = $this->base->timezone_info(8);
        if(!$tz)
            return false;
        
        list($hour, $min) = $tz;
        $fmtime = gmdate('Y-m-d H:i:s', $time).' UTC '.($hour>0?'+':'').$hour.($min>0?(':'.$min):'');
        $filename = gmdate('YmdHis', $time);
        
        $param['recdatetime'] = $fmtime;
        $param['alarmtime'] = $fmtime;
        
        $time_offset = ($hour<0?-1:1)*(abs($hour)*3600 + $min*60);
        $time -= $time_offset;
        
        $push_server = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid='$appid' AND server_type='2' AND status>0");
        if(!$push_server || !$push_server['api'])
            return false;
        
        $url = $push_server['api'].'device/alarm';
        $params = array(
            'uid' => $device['uid'],
            'deviceid' => $deviceid,
            'param' => json_encode($param),
            'time' => $time,
            'client_id' => $client_id,
            'appid' => $appid
        );
        
        $ret = $this->_request($url, $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        $storage = $this->base->load_storage($param['storageid']);
        if($storage)
            return $storage->alarm_info($device, $time, $filename);
        
        return array();
    }
    
    function _request($url, $params = array(), $httpMethod = 'GET') {
        $ch = curl_init();
        
        $headers = array();
        if (isset($params['host'])) {
            $headers[] = 'X-FORWARDED-FOR: ' . $params['host'] . '; CLIENT-IP: ' . $params['host'];
            unset($params['host']);
        }

        $curl_opts = array(
            CURLOPT_CONNECTTIMEOUT  => 3,
            CURLOPT_TIMEOUT         => 20,
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
}
