<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

require_once API_SOURCE_ROOT.'lib/connect/baidu/BaiduPCS.class.php';

define('BAIDU_API_DEVICE', 'https://pcs.baidu.com/rest/2.0/pcs/device');
define('BAIDU_API_FILE', 'https://pcs.baidu.com/rest/2.0/pcs/file');

class baidustorage  extends storage {
    
    var $tokens = array();

    function __construct(&$base, $service) {
        $this->baidustorage($base, $service);
    }

    function baidustorage(&$base, $service) {
        parent::__construct($base, $service);
    }
    
    function get_access_token($uid) {
        if(isset($this->tokens[$uid]))
            return $this->tokens[$uid];
        
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE uid='$uid' AND connect_type='".API_BAIDU_CONNECT_TYPE."'");
        if(!$connect)
            return '';
        
        $token = array(
            'access_token' => $connect['access_token'],
            'refresh_token' => $connect['refresh_token'],
            'expires' => $connect['expires']
        );
        
        $this->tokens[$uid] = $token;
        return $token;
    }
    
    function alarm_info($device, $time, $filename) {
        if(!$device || !$device['deviceid'] || !$time || !$filename)
            return array();
        
        $uid = $device['uid'];
        $pathname = '/apps/iermu/alarm/'.$device['deviceid'];
        $filename = $filename.'.jpg';
        $filepath = $pathname.'/'.$filename;

        $cvr_day = $device['cvr_day'] ? $device['cvr_day'] : 7;
        $expiretime = $time + $cvr_day*24*3600;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $info = array(
            'pathname' => $pathname,
            'filename' => $filename,
            'filepath' => $filepath,
            'upload_token' => $token['access_token'],
            'expiretime' => $expiretime
        );
        
        $this->base->log('tttt baidu', 'info='.json_encode($info));
        
        return $info;
    }

    function snapshot_info($device, $sid, $time, $client_id, $sign) {
        if(!$device || !$sid || !$time || !$client_id || !$sign)
            return array();

        $deviceid = $device['deviceid'];
        $uid = $device['grant_uid'] ? $device['grant_uid'] : $device['uid'];
        $date = $this->base->time_format($time, 'YmdHis', $device['timezone']);
        
        $pathname = '/apps/iermu/snapshot/'.$deviceid;
        $filename = 'd'.$deviceid.'t'.$date.'.jpg';
        $filepath = $pathname.'/'.$filename;
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $info = array(
            'pathname' => $pathname,
            'filename' => $filename,
            'filepath' => $filepath,
            'upload_token' => $token['access_token'],
            'expiretime' => 0
        );
        
        return $info;
    }
    
    function temp_url($params) {
        $uid = $params['uid'];
        $container = $params['container'];
        $object = $params['object'];
        $filename = $params['filename'];
        $expires_in = $params['expires_in'];
        $local = $params['local'];
        
        if(!$uid || !$container || !$object)
            return "";
        
        $token = $this->get_access_token($uid);
        if(!$token || !$token['access_token'])
            return false;
        
        $path = $container.'/'.$object;
        $url = BAIDU_API_FILE.'?method=download&access_token='.$token['access_token'].'&path='.$path;
        return $url;
    }
    
}
