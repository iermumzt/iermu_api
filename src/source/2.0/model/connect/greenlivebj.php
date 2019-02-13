<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

define('GREENLIVEBJ_API_GATEWAY', 'http://lngateway.gosmarthome.cn/api/gateway');

class greenlivebjconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->greenlivebjconnect($base, $_config);
    }

    function greenlivebjconnect(&$base, $_config) {
        parent::__construct($base, $_config);
    }
    
    function update_partner_data($uid, $partner, $data) {
        $this->base->log('greenlivebj partner_auth', 'uid='.$uid.', partner='.json_encode($partner).', data='.json_encode($data));
        if(!$uid || !$partner || !$partner['partner_id'] || !$data)
            return false;
        $this->base->log('greenlivebj partner_auth', '111');
        $partner_id = $partner['partner_id'];
        
        if($data['deviceId'] && $data['sensorId']) {
            $this->base->log('greenlivebj partner_auth', "UPDATE ".API_DBTABLEPRE."device_partner SET partner_did='".$data['sensorId']."' WHERE partner_id='$partner_id' AND deviceid='".$data['deviceId']."'");
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_partner SET partner_did='".$data['sensorId']."' WHERE partner_id='$partner_id' AND deviceid='".$data['deviceId']."'");
        }
        
        $this->base->log('greenlivebj partner_auth', '333');
        
        return true;
    }
    
    function device_register_push($device, $data) {
        if(!$device || !$device['deviceid'] || !$data || !$data['pushid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $partner_id = $data['partner_id'];
        $pushid = $data['pushid'];
        
        $url = GREENLIVEBJ_API_GATEWAY."/AddSensor?JSESSIONID=".$data['JSESSIONID']."&SYSID=".$data['SYSID']."&JDSUSERID=".$data['JDSUSERID'];
        
        $params = array(
            'gatewayid' => $data['gatewayId'],
            'serialno' => $deviceid,
            'typeno' => $data['typeno']
        );
        
        $ret = $this->_request($url, $params);
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        return true;
    }
    
    function device_drop_push($device, $data) {
        if(!$device || !$device['deviceid'] || !$data || !$data['pushid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $partner_id = $data['partner_id'];
        $pushid = $data['pushid'];
        
        $partner_did = $this->db->result_first("SELECT partner_did FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id' AND deviceid='$deviceid'");
        if(!$partner_did)
            return false;
        
        $url = GREENLIVEBJ_API_GATEWAY."/DeleteSensor?JSESSIONID=".$data['JSESSIONID']."&SYSID=".$data['SYSID']."&JDSUSERID=".$data['JDSUSERID']
            ."&sensorId=".$partner_did;
        
        $ret = $this->_request($url);
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        return true;
    }
    
    function _request($url, $params = array(), $httpMethod = 'POST', $json = true) {
        if(!$url)
            return false;
        
        $this->base->log('greenlivebj _request start', 'url='.$url.',params='.json_encode($params));
        
        $ch = curl_init();

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
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($params);
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_CUSTOMREQUEST] = $httpMethod;
            $curl_opts[CURLOPT_POSTFIELDS] = $body;
        }

        if (!empty($headers)) {
            $curl_opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $curl_opts);
        $result = curl_exec($ch);

        if ($result === false) {
            curl_close($ch);
            return false;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->base->log('greenlivebj _request finished', 'http_code='.$http_code.',result='.$result);
        
        if ($json) {
            return array('http_code' => $http_code, 'data' => json_decode($result, true)); 
        } else {
            return array('http_code' => $http_code, 'data' => $result);
        }
    } 
    
}
