<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

class lyossstorage  extends storage {

    function __construct(&$base, $service) {
        $this->lyossstorage($base, $service);
    }

    function lyossstorage(&$base, $service) {
        parent::__construct($base, $service);
    }
    
    function temp_url($params) {
        $uid = $params['uid'];
        $type = $params['type'];
        $container = $params['container'];
        $object = $params['object'];
        $filename = $params['filename'];
        $expires_in = $params['expires_in'];
        $local = $params['local'];

        $device = $params['device'];
        
        if(!$device || !$device['deviceid'] || !$container || !$object)
            return '';
        
        $key = $container.$object;
        
        $expires_in = ($this->storage_config['download_token_validtime'] ? $this->storage_config['download_token_validtime'] : STORAGE_TEMP_URL_DEFAULT_VALIDTIME);
        $token = $this->_download_token($device, $key, $expires_in);
        if(!$token)
            return '';
        
        $url = $this->storage_config['upload_url'].'/files?access_token='.$token.'&key='.$key;
        
        return $url;
    }
    
    function alarm_info($device, $time, $filename) {
        if(!$device || !$device['deviceid'] || !$time || !$filename)
            return array();
        
        $pathname = '/'.$device['uid'].'/'.$device['deviceid'].'/alarm/';
        $filename = $filename.'.jpg';
        $filepath = $pathname.$filename;
        
        $cvr_day = $device['cvr_day'] ? $device['cvr_day'] : 7;
        if($cvr_day == 90) {
            $expiretype = 3;
        } else if($cvr_day == 30) {
            $expiretype = 2;
        } else {
            $expiretype = 1;
        }
        $expiretime = $time + $cvr_day*24*3600;
        
        $upload_token = $this->_upload_token($device);
        
        $info = array(
            'pathname' => $pathname,
            'filename' => $filename,
            'filepath' => $filepath,
            'upload_token' => $upload_token,
            'expiretime' => $expiretime
        );

        $extras = array(
            'upload_url' => $this->storage_config['upload_url'],
            'channel_id' => 0,
            'expiretype' => $expiretype,
            'event_id' => ''.$device['deviceid'].$time.rand(1000,9999),
            'desc' => $device['desc'],
            'connect_cid' => $this->_connect_cid($device)
        );

        $info['extras'] = $extras;
        
        $this->base->log('tttt lingyang', 'info='.json_encode($info));
        
        return $info;
    }

    function _connect_cid($device) {
        if(!$device || !$device['deviceid']) 
            return '';
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if($connect_type != API_LINGYANG_CONNECT_TYPE)
            return '';

        $client = $this->base->load_connect($connect_type);
        if(!$client)
            return '';
        
        // cid
        $connect_cid = $client->_device_connect_cid($device);
        if(!$connect_cid)
            return '';

        return $connect_cid;
    }

    function _upload_token($device) {
        if(!$device || !$device['deviceid']) 
            return '';
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if($connect_type != API_LINGYANG_CONNECT_TYPE)
            return '';

        $client = $this->base->load_connect($connect_type);
        if(!$client)
            return '';
        
        // cid
        $connect_cid = $client->_device_connect_cid($device);
        if(!$connect_cid)
            return '';

        // 录像保存日期
        if($device['cvr_type'] > 0 && ($device['cvr_free'] || $device['cvr_end_time'] > $this->base->time) && $device['cvr_day'] > 7) {
            $cvr_day = $device['cvr_day'];
        } else {
            $cvr_day = 7;
        }
        
        // uploadtoken
        $upload_token = $client->_uploadtoken($connect_cid, array('cvr_type'=>$device['cvr_type'], 'cvr_day'=>$cvr_day));
        if(!$upload_token)
            return false;

        return $upload_token;
    }

    function _download_token($device, $key, $expires_in) {
        if(!$device || !$device['deviceid']) 
            return '';
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if($connect_type != API_LINGYANG_CONNECT_TYPE)
            return '';

        $client = $this->base->load_connect($connect_type);
        if(!$client)
            return '';
        
        // cid
        $connect_cid = $client->_device_connect_cid($device);
        if(!$connect_cid)
            return '';

        return $client->device_accesstoken($connect_cid, $expires_in);
    }

}
