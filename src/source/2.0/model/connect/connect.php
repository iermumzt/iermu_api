<?php

!defined('IN_API') && exit('Access Denied');

class connect {

    var $db;
    var $redis;
    var $base;
    
    var $connect_type = 0;
    var $config = array();
    
    var $domain = '';
    
    var $client = NULL;

    function __construct(&$base, $_config) {
        $this->conncet($base, $_config);
    }

    function conncet(&$base, $_config) {
        $this->base = $base;
        $this->db = $base->db;
        $this->redis = $base->redis;
        $this->config = $_config;
        $this->connect_type = $_config['connect_type'];
        
        $this->domain = $base->connect_domain;
    }
    
    function _connect_auth() {
        return false;
    }
    
    function _connect_meta() {
        return false;
    }
    
    function _token_extras() {
        return true;
    }
    
    function _connect_list() {
        return false;
    }
    
    function get_authorize_url($display, $force_login) {
        return false;
    }
    
    function get_connect_info($uid, $sync=false) {
        return true;
    }
    
    function device_register($uid, $deviceid, $desc) {
        return true;
    }
    
    function device_update($device, $desc) {
        return true;
    }
    
    function device_meta($device, $params) {
        return true;
    }
    
    function device_batch_meta($devices, $params) {
        return false;
    }
    
    function device_drop($device) {
        return true;
    }
    
    function createshare($device, $share_type, $expires) {
        return true;
    }
    
    function cancelshare($device) {
        return true;
    }
    
    function liveplay($device, $type, $params) {
        return false;
    }

    function multi_liveplay($uid, $devicelist) {
        return false;
    }
    
    function listdevice($uid) {
        return true;
    }
    
    function listdevice_by_page($uid, $page, $count) {
        return false;
    }
    
    function subscribe($uid, $shareid, $connect_uid) {
        return true;
    }
    
    function unsubscribe($uid, $shareid, $connect_uid) {
        return true;
    }
    
    function listsubscribe($uid) {
        return true;
    }
    
    function grant($uid, $connect_uid, $username, $auth_code, $device) {
        return true;
    }
    
    function listgrantuser($uid, $deviceid) {
        return true;
    }
    
    function listgrantdevice($uid) {
        return true;
    }
    
    function dropgrantuser($device, $connect_uid) {
        return true;
    }
    
    function playlist($device, $params, $starttime, $endtime, $type) {
        return false;
    }
    
    function vod($device, $params, $starttime, $endtime) {
        return false;
    }
    
    function vodseek($device, $params, $time) {
        return false;
    }
    
    function thumbnail($device, $params, $starttime, $endtime) {
        return false;
    }
    
    function clip($device, $starttime, $endtime, $name, $client_id) {
        return false;
    }
    
    function infoclip($uid, $type, $clipid) {
        return false;
    }

    function listdeviceclip($device, $page, $count) {
        return false;
    }

    function listuserclip($uid, $page, $count, $client_id) {
        return false;
    }
    
    function device_usercmd($device, $command, $response) {
        return false;
    }
    
    function msg_notify($param) {
        return true;
    }

    function list_share($start, $num) {
        return false;
    }
    
    function vodlist($device, $starttime, $endtime) {
        return false;
    }
    
    function _set_config() {
        return false;
    }
    
    function need_cvr_after_save() {
        return false;
    }
    
    function set_cvr($device, $cvr) {
        return true;
    }
    
    function device_batch_usercmd($device, $commands) {
        return false;
    }
    
    function _check_setting_sync($device) {
        return 0;
    }
    
    function device_init_info($device) {
        return false;
    }
    
    function device_sync_config($device) {
        return false;
    }
    
    function avatar($avatar) {
        return '';
    }
    
    function device_uploadtoken($device, $request_no) {
        return false;
    }
    
    function _need_sync_alarm() {
        return false;
    }
    
    function sync_alarm($device, $alarmtime) {
        return false;
    }
    
    function _support_connect_login() {
        return false;
    }
    
    function get_connect_login_user($connect_uid) {
        return false;
    }
    
    function get_connect_user($connect_uid) {
        return false;
    }
    
    function get_alarm_storageid($device) {
        if($device && $device['deviceid']) {
            $deviceid = $device['deviceid'];
            $partner = $this->db->fetch_first("SELECT p.* FROM ".API_DBTABLEPRE."device_partner d LEFT JOIN ".API_DBTABLEPRE."partner p ON d.partner_id=p.partner_id
             WHERE d.deviceid='$deviceid'");
            if($partner && $partner['config']) {
                $config = json_decode($partner['config'], true);
                if($config && $config['alarm_storageid']) {
                    return $config['alarm_storageid'];
                }
            }
        }
        
        return $this->config['alarm_storageid'];
    }
    
    function get_snapshot_storageid() {
        return $this->config['snapshot_storageid'];
    }
    
    function need_cvr_record() {
        return false;
    }
    
    function on_device_online($device) {
        return true;
    }
    
    function _device_online($device) {
        if(!$device || !$device['deviceid'])
            return false;
        return $device['status']>0?true:false;
    }
    
    function device_register_push($device, $data) {
        return false;
    }
    
    function device_drop_push($device, $data) {
        return false;
    }
    
    function update_partner_data($uid, $partner, $data) {
        return true;
    }
    
    function alarmspace($device) {
        return false;
    }
    
    function device_connect_token($device) {
        return false;
    }
    
    function device_config($device, $config, $request_no) {
        return $config;
    }
    
}
