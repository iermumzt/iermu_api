<?php

!defined('IN_API') && exit('Access Denied');

class storage {
    var $db;
    var $redis;
    var $base;
    
    var $storageid = 0;
    var $appid = 0;
    var $storage_type = '';
    var $storage_config = array();
    var $status = 0;
    
    var $client = NULL;

    function __construct(&$base, $service) {
        $this->storage($base, $service);
    }

    function storage(&$base, $service) {
        $this->base = $base;
        $this->db = $base->db;
        $this->redis = $base->redis;
        
        $this->storageid = $service['storageid'];
        $this->appid = $service['appid'];
        $this->storage_type = $service['storage_type'];
        $this->storage_config = $service['storage_config'];
        $this->status = $service['status'];
    }
    
    function temp_url($params) {
        return '';
    }
    
    function alarm_info($device, $time, $filename) {
        return array();
    }

    function preset_info($device, $preset, $time, $client_id, $sign) {
        return array();
    }
    
    function snapshot_info($device, $sid, $time, $client_id, $sign) {
        return array();
    }
}
