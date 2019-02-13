<?php

!defined('IN_API') && exit('Access Denied');

class connect {

    var $db;
    var $redis;
    var $base;
    
    var $connect_type = 0;
    var $config = array();
    
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
    
    function get_token_extras($uid) {
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
    
    function device_drop($device) {
        return true;
    }
    
    function createshare($device, $share_type) {
        return true;
    }
    
    function cancelshare($device) {
        return true;
    }
    
    function liveplay($device, $type, $params) {
        return false;
    }
    
    function listdevice($uid) {
        return true;
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
    
    function playlist($device, $params, $starttime, $endtime) {
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
    
    function clip($device, $starttime, $endtime, $name) {
        return false;
    }
    
    function infoclip($device, $type) {
        return false;
    }

    function listdeviceclip($device, $page, $count) {
        return false;
    }

    function listuserclip($uid, $page, $count) {
        return false;
    }
    
    function device_usercmd($device, $command, $response) {
        return false;
    }
    
    function alarmpic($device, $page, $count) {
        return false;
    }
    
    function dropalarmpic($device, $path) {
        return false;
    }
    
    function downloadalarmpic($device, $path) {
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
    
    function set_bitrate($device, $bitrate) {
        return true;
    }
    
    function set_cvr($device, $cvr) {
        return true;
    }
    
    function set_audio($device, $audio) {
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
    
    function device_update_cvr($device, $cvr_day, $cvr_end_time) {
        return false;
    }
    
    function device_repair($device) {
        return true;
    }
    
    function _check_need_savemenu($params) {
        return true;
    }

    function _check_need_sendcvrcmd($deviceid, $open) {
        return true;
    }
    
    function avatar($avatar) {
        return '';
    }
    
    function device_uploadtoken($device) {
        return false;
    }
    
}
