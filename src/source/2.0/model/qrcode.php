<?php

!defined('IN_API') && exit('Access Denied');

class qrcodemodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->qrcodemodel($base);
    }

    function qrcodemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }
    
    function get_qrcode_by_cid($cid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_qrcode WHERE cid='$cid'");
    }

    function get_qrcode_by_code($code) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_qrcode WHERE code='$code'");
    }
    
    function get_qrcode_by_key($key) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_qrcode WHERE `key`='$key'");
    }
    
    function qrcode_key($cid, $key='') {
        $key = $key?$key:API_KEY;
        return $this->base->base64_url_encode(hash_hmac('sha1', $cid, $key, true));
    }
    
    function scan_qrcode($cid, $uid) {
        if(!$cid || !$uid) return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_qrcode SET uid='$uid',status='1',scandate='".$this->base->time."' WHERE cid='$cid'");
        return true;
    }
    
    function confirm_qrcode($cid) {
        if(!$cid) return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_qrcode SET status='2',confirmdate='".$this->base->time."' WHERE cid='$cid'");
        return true;
    }
    
    function cancel_qrcode($cid) {
        if(!$cid) return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_qrcode SET status='-3',canceldate='".$this->base->time."' WHERE cid='$cid'");
        return true;
    }
    
    function use_qrcode($cid) {
        if(!$cid) return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_qrcode SET status='-4',usedate='".$this->base->time."' WHERE cid='$cid'");
        return true;
    }
    
    function update_qrcode_status($cid, $status) {
        if(!$cid) return;
        $status = intval($status);
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_qrcode SET status='$status' WHERE cid='$cid'");
    }
    
}
