<?php

!defined('IN_API') && exit('Access Denied');

class appmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->appmodel($base);
    }

    function appmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function get_apps($col = '*', $where = '') {
        $arr = $this->db->fetch_all("SELECT $col FROM ".API_DBTABLEPRE."oauth_app".($where ? ' WHERE '.$where : ''), 'appid');
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

    function get_clients($appid = 0, $col = '*', $where = '') {
        $appid = intval($appid);
        if($appid) $where .= ' appid='.$appid;
        $arr = $this->db->fetch_all("SELECT $col FROM ".API_DBTABLEPRE."oauth_client".($where ? ' WHERE '.$where : ''), 'client_id');
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

    function get_app_by_appid($appid, $includecert = FALSE) {
        $appid = trim($appid);
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_app WHERE appid='$appid'");
        /*
        $arr['extra'] = unserialize($arr['extra']);
        if($tmp = $this->base->authcode($arr['authkey'], 'DECODE', API_MYKEY)) {
            $arr['authkey'] = $tmp;
        }
        */
        if($includecert) {
            $this->load('plugin');
            $certfile = $_ENV['plugin']->cert_get_file();
            $appdata = $_ENV['plugin']->cert_dump_decode($certfile);
            if(is_array($appdata[$client_id])) {
                $arr += $appdata[$client_id];
            }
        }
        return $arr;
    }

    function get_client_by_client_id($client_id, $includecert = FALSE) {
        $client_id = trim($client_id);
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        /*
        $arr['extra'] = unserialize($arr['extra']);
        if($tmp = $this->base->authcode($arr['authkey'], 'DECODE', API_MYKEY)) {
            $arr['authkey'] = $tmp;
        }
        */
        if($includecert) {
            $this->load('plugin');
            $certfile = $_ENV['plugin']->cert_get_file();
            $appdata = $_ENV['plugin']->cert_dump_decode($certfile);
            if(is_array($appdata[$client_id])) {
                $arr += $appdata[$client_id];
            }
        }
        return $arr;
    }

    function get_app_by_client_id($client_id, $includecert = FALSE) {
        $app = $client = array();
        if($client_id) {
            $client = $this->get_client_by_client_id($client_id, $includecert);
            if($client && $client['appid']) {
                $app = $this->get_app_by_appid($client['appid'], $includecert);
            }
        }
        return $app;
    }

    function delete_apps($appids) {
        $client_ids = $this->base->implode($appids);
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."oauth_client WHERE appid IN ($appids)");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."oauth_app WHERE appid IN ($appids)");
        return $this->db->affected_rows();
    }

    function delete_clients($client_ids) {
        $client_ids = $this->base->implode($client_ids);
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."oauth_client WHERE client_id IN ($client_ids)");
        return $this->db->affected_rows();
    }


/*  function update_app($appid, $name, $url, $authkey, $charset, $dbcharset) {
        if($name && $appid) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."applications SET name='$name', url='$url', authkey='$authkey', ip='$ip', synlogin='$synlogin', charset='$charset', dbcharset='$dbcharset' WHERE appid='$appid'");
            return $this->db->affected_rows();
        }
        return 0;
    }*/

    //private
    function test_api($url, $ip = '') {
        $this->base->load('misc');
        if(!$ip) {
            $ip = $_ENV['misc']->get_host_by_url($url);
        }

        if($ip < 0) {
            return FALSE;
        }
        return $_ENV['misc']->dfopen($url, 0, '', '', 1, $ip);
    }

    function get_inits() {
        $inits = array();
        return $inits;
    }

    function get_update($client_id, $check_type=0) {
        $version = array();
        $client_id = trim($client_id);
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client_version WHERE client_id='$client_id' AND vtype='$check_type' AND status>0 ORDER BY vnumorder DESC");
        if($arr) {
            $version = array(
                'client_id' => $arr['client_id'],
                'vnum' => $arr['vnum'],
                'vtype' => $arr['vtype'],
                'forceupdate' => $arr['forceupdate'],
                'pubdate' => $arr['pubdate']?gmdate('Y-m-d H:i:s \U\T\CP', $arr['pubdate']):'',
                'vdesc' => $arr['vdesc'],
                'size' => $arr['size'],
                'url' => $arr['url']
            );
        }
        return $version;
    }

    function get_new_poster($client_id, $width, $height, $status) {
        $client = $this->get_client_by_client_id($client_id);
        if (!$client)
            return false;

        $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'activity_info a LEFT JOIN '.API_DBTABLEPRE.'activity_file b ON a.aid=b.aid WHERE a.platform="'.$client['platform'].'" AND a.status="'.$status.'" AND starttime<'.$this->base->time.' AND endtime>'.$this->base->time.' ORDER BY b.width');
        if (!$arr)
            return false;

        $resolution = $width * $height;
        $proportion = $width / $height;
        $baseurl = $status ? API_QINIU_IMG_BASEURL : API_TEST_IMG_BASEURL;
        for ($i = 0, $n = count($arr); $i < $n; $i++) {
            $imgurl = $baseurl . $arr[$i]['pathname'] . $arr[$i]['filename'];
            $weburl = $arr[$i]['weburl'];
            $starttime = $arr[$i]['starttime'];
            $endtime = $arr[$i]['endtime'];

            $deviation = max(($resolution-$arr[$i]['width']*$arr[$i]['height'])/$resolution, ($proportion-$arr[$i]['width']/$arr[$i]['height'])/$proportion);
            if ($deviation < 0.1) {
                break;
            }
        }

        $result = array();
        $result['imgurl'] = $imgurl;
        $result['weburl'] = $weburl;
        $result['starttime'] = $starttime;
        $result['endtime'] = $endtime;
        return $result;
    }

    function feedback($uid, $client_id, $opinion, $contact, $telmodel, $version) {
        $client = $this->get_client_by_client_id($client_id);
        if (!$client)
            return false;

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'feedback SET uid="'.$uid.'",opinion="'.$opinion.'",contact="'.$contact.'",telmodel="'.$telmodel.'",version="'.$version.'",appid="'.$appid.'",client_id="'.$client_id.'",platform="'.$platform.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');
        return true;
    }
}
