<?php

!defined('IN_API') && exit('Access Denied');

class cronmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->cronmodel($base);
    }

    function cronmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function note_delete_user() {
        //
    }

    function note_delete_pm() {
        //
        $data = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."badwords");
        return $data;
    }

}
