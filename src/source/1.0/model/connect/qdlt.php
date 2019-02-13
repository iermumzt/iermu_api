<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class qdltconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->qdltconnect($base, $_config);
    }

    function qdltconnect(&$base, $_config) {
        parent::__construct($base, $_config);
        $this->base->load('device');
        $this->base->load('user');
    }
    
    function get_token_extras($uid) {
        if(!$uid)
            return false;
        
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
        if(!$connect || !$connect['connect_uid']) {
            return array(
                'connect_type' => $this->connect_type,
                'status' => 0
            );
        } else {
            return array(
                'connect_type' => $this->connect_type,
                'connect_uid' => $connect['connect_uid'],
                'status' => $connect['status']
            );
        }
    }
    
}
