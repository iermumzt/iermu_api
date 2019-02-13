<?php

!defined('IN_API') && exit('Access Denied');

class tokenmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->tokenmodel($base);
    }

    function tokenmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function get_total_num($sqladd = '') {
        $data = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."oauth_access_token $sqladd");
        return $data;
    }

    function get_list($page, $ppp, $totalnum, $sqladd) {
        $start = $this->base->page_get_start($page, $ppp, $totalnum);
        $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."oauth_access_token $sqladd ORDER BY dateline DESC LIMIT $start, $ppp");
        return $data;
    }

    function get_tokens($col = '*', $where = '') {
        $arr = $this->db->fetch_all("SELECT $col FROM ".API_DBTABLEPRE."oauth_access_token".($where ? ' WHERE '.$where : ''), 'oauth_token');
        foreach($arr as $k => $v) {
            /*
            isset($v['extra']) && !empty($v['extra']) && $v['extra'] = unserialize($v['extra']);
            if($tmp = $this->base->authcode($v['authkey'], 'DECODE', API_MYKEY)) {
                $v['authkey'] = $tmp;
            }
            */
            $arr[$k] = $v;
        }
        return $arr;
    }

    function get_token_by_oauth_token($oauth_token, $includecert = FALSE) {
        $oauth_token = trim($oauth_token);
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_access_token WHERE oauth_token='$oauth_token'");
        /*
        $arr['extra'] = unserialize($arr['extra']);
        if($tmp = $this->base->authcode($arr['authkey'], 'DECODE', API_MYKEY)) {
            $arr['authkey'] = $tmp;
        }
        */
        if($includecert) {
            $this->load('plugin');
            $certfile = $_ENV['plugin']->cert_get_file();
            $tokendata = $_ENV['plugin']->cert_dump_decode($certfile);
            if(is_array($tokendata[$oauth_token])) {
                $arr += $tokendata[$oauth_token];
            }
        }
        return $arr;
    }

    function delete_tokens($oauth_tokens) {
        $oauth_tokens = $this->base->implode($oauth_tokens);
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."oauth_access_token WHERE oauth_token IN ($oauth_tokens)");
        return $this->db->affected_rows();
    }

/*  function update_token($tokenid, $name, $url, $authkey, $charset, $dbcharset) {
        if($name && $tokenid) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."applications SET name='$name', url='$url', authkey='$authkey', ip='$ip', synlogin='$synlogin', charset='$charset', dbcharset='$dbcharset' WHERE tokenid='$tokenid'");
            return $this->db->affected_rows();
        }
        return 0;
    }*/

}
