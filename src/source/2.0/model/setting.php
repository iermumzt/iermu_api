<?php

!defined('IN_API') && exit('Access Denied');

class settingmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->settingmodel($base);
    }

    function settingmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function get_settings($keys = '') {
        if($keys) {
            $keys = $this->base->implode($keys);
            $sqladd = "k IN ($keys)";
        } else {
            $sqladd = '1';
        }
        $arr = array();
        $arr = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."settings WHERE $sqladd");
        if($arr) {
            foreach($arr as $k => $v) {
                $arr[$v['k']] = $v['v'];
                unset($arr[$k]);
            }
        }
        return $arr;
    }

}
