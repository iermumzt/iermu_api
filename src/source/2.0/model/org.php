<?php

!defined('IN_API') && exit('Access Denied');

class orgmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->orgmodel($base);
    }

    function orgmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function getorgidbyuid($uid){
    	$data = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_org where uid ='".$uid."'");
        return $data;
    }

    function checkuserorgrole($uid){
        $data = $this->db->result_first("SELECT * FROM ".API_DBTABLEPRE."member_org where uid =".$uid." AND admin=1");
        return $data?TRUE:FALSE;
    }

    function getorginfobyorgid($orgid){
    	$data = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."org where org_id =".$orgid);
        return $data;
    }

    function orgdevice_insert($orgid, $deviceid){
    	$data = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_org where deviceid =".$deviceid);
    	if($data){
    		$ret = $this->db->query("UPDATE ".API_DBTABLEPRE."device_org SET org_id='$orgid', dateline='".$this->base->time."', lastupdate='".$this->base->time."' WHERE deviceid='$deviceid'");
    	}else{
    		$ret = $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_org SET org_id='$orgid', deviceid='$deviceid', dateline='".$this->base->time."', lastupdate='".$this->base->time."'");
    	}
    	return $ret?TRUE:FALSE;
    }

}