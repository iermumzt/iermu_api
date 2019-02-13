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
                'pubdate' => $arr['pubdate']?gmdate('Y-m-d H:i:s T', $arr['pubdate']):'',
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

        $platform = intval($client['platform']);

        $poster = $this->db->fetch_first('SELECT a.aid,a.weburl,a.option,a.starttime,a.endtime FROM '.API_DBTABLEPRE.'activity_info a LEFT JOIN '.API_DBTABLEPRE.'activity_client b ON a.aid=b.aid AND a.status=b.status WHERE a.platform='.$platform.' AND a.status='.$status.' AND a.lang="'.API_LANGUAGE.'" AND a.starttime<'.$this->base->time.' AND a.endtime>'.$this->base->time.' AND ((b.type=1 AND b.tid="'.$platform.'") OR (b.type=2 AND b.tid="'.$client_id.'")) LIMIT 1');
        if (!$poster)
            return false;

        $result = array(
            'weburl' => $poster['weburl'],
            'option' => $poster['option'],
            'starttime' => $poster['starttime'],
            'endtime' => $poster['endtime']
        );

        $resolution = $width * $height;
        $proportion = $width / $height;
        $baseurl = $status ? API_QINIU_IMG_BASEURL : API_TEST_IMG_BASEURL;

        $image = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'activity_file WHERE aid='.$poster['aid'].' ORDER BY width');
        for ($i = 0, $n = count($image); $i < $n; $i++) {
            $result['imgurl'] = $baseurl . $image[$i]['pathname'] . $image[$i]['filename'];

            $deviation = max(($resolution-$image[$i]['width']*$image[$i]['height'])/$resolution, ($proportion-$image[$i]['width']/$image[$i]['height'])/$proportion);
            if ($deviation < 0.1) {
                break;
            }
        }

        return $result;
    }

    function feedback($uid, $client_id, $opinion, $contact, $telmodel, $version) {
        $client = $this->get_client_by_client_id($client_id);
        if (!$client)
            return false;

        $appid = $client['appid'];
        $platform = $client['platform'];
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'feedback SET uid="'.$uid.'",opinion="'.$opinion.'",contact="'.$contact.'",telmodel="'.$telmodel.'",version="'.$version.'",appid="'.$appid.'",client_id="'.$client_id.'",platform="'.$platform.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');
        return true;
    }
}
