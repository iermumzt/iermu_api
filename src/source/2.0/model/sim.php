<?php

!defined('IN_API') && exit('Access Denied');

class simmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->simmodel($base);
    }

    function simmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
        $this->base->load('device');
    }

    function get_sim_by_id($id) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."sim WHERE simid='$id'");
    }

    function _format_sim_by_id($id) {
        $sim = $this->get_sim_by_id($id);
        return $this->_format_sim($sim);
    }

    function _format_sim($sim) {
        if(!$sim || !$sim['simid'] || !$sim['deviceid'])
            return FALSE;
        
        $device = $_ENV['device']->get_device_by_did($sim['deviceid']);
        if(!$device)
            return FALSE;
        
        $result = array(
            'simid' => strval($sim['simid']),
            'device' => array(
                'deviceid' => strval($device['deviceid']),
                'description' => strval($device['desc'])
            )
        );
        return $result;
    }

    function check_device_bind($simid, $deviceid) {
        $temp = $this->db->result_first("SELECT simid FROM ".API_DBTABLEPRE."sim WHERE deviceid='$deviceid'");
        if(!$temp) return TRUE;
        return $temp == $simid ? TRUE : FALSE;
    }

    function register($uid, $simid, $deviceid, $client_id, $appid) {
        if(!$uid || !$simid || !$deviceid)
            return FALSE;

        $now = $this->base->time;

        $sim = $this->get_sim_by_id($simid);
        if($sim) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."sim SET uid='$uid', deviceid='$deviceid', client_id='$client_id', appid='$appid', lastupdate='$now' WHERE simid='$simid'");
        } else {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."sim SET simid='$simid', uid='$uid', deviceid='$deviceid', client_id='$client_id', appid='$appid', dateline='$now', lastupdate='$now'");
        }
        return $this->_format_sim_by_id($simid);
    }

    function meta($sim) {
        return $this->_format_sim($sim);
    }

    function drop($sim) {
        if(!$sim || !$sim['simid'])
            return FALSE;
        $simid = $sim['simid'];
        $this->db->query("UPDATE ".API_DBTABLEPRE."sim SET uid=0 WHERE simid='$simid'");
        return TRUE;
    }

    function list_sim($uid, $keyword, $orderby, $list_type, $page, $count, $appid) {
        $result = array();

        $table = API_DBTABLEPRE.'sim';

        $where = 'WHERE uid='.$uid;
        if($appid > 0) $where .=' AND appid='.$appid;

        $orderbysql=' ORDER BY dateline DESC';

        $total = $this->db->result_first("SELECT count(*) FROM $table $where");

        $limit = '';
        if($list_type == 'page') {
            $page = $page > 0 ? $page : 1;
            $count = $count > 0 ? $count : 10;

            $pages = $this->base->page_get_page($page, $count, $total);

            $result['page'] = $pages['page'];
            $limit = ' LIMIT '.$pages['start'].', '.$count;
        }

        $list = array();
        if($total) {
            $data = $this->db->fetch_all("SELECT * FROM $table $where $orderbysql $limit");
            foreach($data as $value) {
                $sim = $this->_format_sim($value);
                if($sim) $list[] = $sim;
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

}
