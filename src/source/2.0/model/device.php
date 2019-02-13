<?php

!defined('IN_API') && exit('Access Denied');

class devicemodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->devicemodel($base);
    }

    function devicemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;

        $this->base->load('org');
    }

    function get_total_num($sqladd = '') {
        $data = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device $sqladd");
        return $data;
    }

    function get_list($page, $ppp, $totalnum, $sqladd) {
        $start = $this->base->page_get_start($page, $ppp, $totalnum);
        $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device $sqladd LIMIT $start, $ppp");
        return $data;
    }

    function get_devices($col = '*', $where = '') {
        $arr = $this->db->fetch_all("SELECT $col FROM ".API_DBTABLEPRE."device".($where ? ' WHERE '.$where : ''), 'mdid');
        foreach($arr as $k => $v) {
            $arr[$k] = $v;
        }
        return $arr;
    }
    
    function _check_only_device($uid, $connect_type) {
        return !$this->db->result_first('SELECT COUNT(*) FROM '.API_DBTABLEPRE.'device WHERE uid="'.$uid.'" AND connect_type!="'.$connect_type.'"');
    }

    function list_devices($uid, $support_type, $device_type, $appid=0, $share, $online, $category, $keyword, $orderby, $data_type='my', $list_type='all', $page=1, $count=10, $dids='') {
        // 百度分页处理 @todo swain
        $isorg = $_ENV['org']->getorgidbyuid($uid);
        if(false && !$isorg){
            if(in_array(API_BAIDU_CONNECT_TYPE, $support_type) && $data_type == "my" && $list_type == "page" 
                && $share == -1 && $online == -1 && $category == -1 && !$keyword && !$orderby && $this->_check_only_device($uid, API_BAIDU_CONNECT_TYPE)) {
                return $this->listdevice_by_page($uid, API_BAIDU_CONNECT_TYPE, $device_type, $appid, $page, $count);
            }
        }
        if($dids) $data_type = 'my';

        $total = 0;
        $lists = array();

        $this->base->log('list device', 'my start');
        if(in_array($data_type, array('my', 'all', 'none'))) {
            $my_list = $this->listdevice($uid, $support_type, $device_type, $appid, $share, $online, $category, $keyword, $orderby, $dids);
            if($my_list) {
                $total += $my_list['count'];
                $lists = array_merge($lists, $my_list['list']);
            }
        }
        $this->base->log('list device', 'grant start');
        if(in_array($data_type, array('grant', 'all', 'none'))) {
            $grant_list = $this->listgrantdevice($uid, $support_type, $appid, $share, $online, $category, $keyword, $orderby);
            if($grant_list) {
                $total += $grant_list['count'];
                $lists = array_merge($lists, $grant_list['list']);
            }
        }
        $this->base->log('list device', 'sub start');
        if(in_array($data_type, array('sub', 'all'))) {
            $sub_list = $this->listsubscribe($uid, $support_type, $appid, $share, $online, $category, $keyword, $orderby);
            if($sub_list) {
                $total += $sub_list['count'];
                $lists = array_merge($lists, $sub_list['device_list']);
            }
        }
        $this->base->log('list device', 'end');

        $result = array();
        if($list_type == 'page') {
            $page = $page > 0 ? $page : 1;
            $count = $count > 0 ? $count : 10;

            $pages = $this->base->page_get_page($page, $count, $total);

            $lists = array_slice($lists, $pages['start'], $count);
            $result['page'] = $pages['page'];
        }

        $count = count($lists);
        $result['count'] = $count;
        $result['list'] = $lists;
        return $result;
    }

    function list_ai_devices($uid, $support_type, $appid, $list_type, $keyword, $orderby, $page, $count, $dids,$meeting_id=''){
        $total = 0;
        $lists = array();

        $table = API_DBTABLEPRE.'devicefileds a LEFT JOIN '.API_DBTABLEPRE.'device dev ON a.deviceid=dev.deviceid LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid';
        $where = 'WHERE a.ai=1';

        if($appid > 0) $where .=' AND dev.appid='.$appid;
        if($dids) $where .=' AND dev.deviceid in ('.$dids.')';
        if ($keyword !== '') {
            $this->base->load('search');
            $where .= ' AND dev.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
        }

        //判断企业用户 我的设备机制修改
        $isorg = $_ENV['org']->getorgidbyuid($uid);
        if($isorg && $isorg['admin'] == 1){
            $org_id = $isorg['org_id'];
            $table .= ' LEFT JOIN '.API_DBTABLEPRE.'device_org o ON a.deviceid=o.deviceid';
            $where .= ' AND o.org_id='.$org_id;
            
        }else if ($isorg && $isorg['admin'] == 0) {
            //添加企业子用户查询授权列表
            $table .= ' LEFT JOIN '.API_DBTABLEPRE.'device_grant g ON a.deviceid=g.deviceid';
            $where .= ' AND g.uk='.$uid.' AND g.auth_type=1';
            # code...
        }else{
            $where .= ' AND dev.uid='.$uid;
        }
        $list = array();
        $total = $this->db->result_first("SELECT count(*) FROM $table $where");
        if($total) {
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all("SELECT a.deviceid,dev.device_type,dev.connect_type,m.connect_cid,m.connect_thumbnail,dev.cvr_thumbnail,dev.stream_id,m.connect_online,dev.status,dev.laststatusupdate,REPLACE(dev.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,dev.cvr_type,dev.cvr_day,dev.cvr_end_time,dev.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,dev.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,dev.location,dev.location_type,dev.location_name,dev.location_address,dev.location_latitude,dev.location_longitude,dev.viewnum,dev.approvenum,dev.commentnum,dev.timezone,dev.reportstatus FROM $table LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON m.deviceid=a.deviceid $where AND m.connect_type=dev.connect_type AND m.connect_domain='".$this->base->connect_domain."'");
            } else {
                $data = $this->db->fetch_all("SELECT a.deviceid,dev.device_type,dev.connect_type,dev.connect_cid,dev.connect_thumbnail,dev.cvr_thumbnail,dev.stream_id,dev.connect_online,dev.status,dev.laststatusupdate,REPLACE(dev.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,dev.cvr_type,dev.cvr_day,dev.cvr_end_time,dev.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,dev.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,dev.location,dev.location_type,dev.location_name,dev.location_address,dev.location_latitude,dev.location_longitude,dev.viewnum,dev.approvenum,dev.commentnum,dev.timezone,dev.reportstatus FROM $table $where");
            }
            $list = $this->get_devicelist($uid, $data);
        }
        //是否该设备在其他会议中被使用
        if($meeting_id){
            foreach ($list as $k=>$value){
                $check = $this->in_use_device($uid,$meeting_id,$value['deviceid']);
                if($check){
                    $list[$k]['in_use_device'] = 1;
                }else{
                    $list[$k]['in_use_device'] = 0;
                }
            }
        }

        if($list_type == 'page') {
            $page = $page > 0 ? $page : 1;
            $count = $count > 0 ? $count : 10;

            $pages = $this->base->page_get_page($page, $count, $total);

            $list = array_slice($list, $pages['start'], $count);
            $result['page'] = $pages['page'];
        }

        $count = count($list);
        $result['count'] = $count;
        $result['list'] = $list;
        return $result;
    }

    //是否该设备在其他会议中被使用
    function in_use_device($uid,$meeting_id,$deviceid){
        if(!$uid || !$meeting_id || !$deviceid){
            return false;
        }
        $result = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."ai_meeting as m LEFT JOIN ".API_DBTABLEPRE."ai_meeting_device as md ON m.mid=md.mid WHERE m.mid<>'".$meeting_id."' AND md.deviceid='".$deviceid."'");
        if($result){
            return true;
        }
        return false;
    }
    
    function get_default_desc($l) {
        if(!$l) $l = API_LANGUAGE;
        if(!$l || !in_array($l, array('zh-Hans', 'en', 'ja', 'ko'))) $l = 'zh-Hans';
        include API_ROOT.'./view/locale/'.$l.'/main.lang.php';
        $data = &$lang;
        return $data['device_default_desc'];
    }
    
    function listdevice_by_page($uid, $connect_type, $device_type, $appid, $page, $count) {
        if(!$uid)
            return false;
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
         
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;
        
        $list = $client->listdevice_by_page($uid, $page, $count);
        if($list && is_array($list) && $list['count']) {
            $clean = false;
            if($list['count'] == $list['page']['total']) {
                $clean = true;
            }
            $this->sync_device($uid, $appid, $connect_type, $list, $clean);
            
            $devices = array();
            foreach($list['list'] as $v) {
                $value = $this->get_device_by_did($v['deviceid']);

                // 更新设备云录制信息
                $value = $this->update_cvr_info($value);
                
                $share = $this->get_share_by_deviceid($value['deviceid']);
                if (!$share) {
                    $share = array();
                }
                
                if($value['laststatusupdate'] + 60 < $this->base->time) {
                    if($value['connect_type'] > 0 && $value['connect_online'] == 0 && $value['status'] > 0) {
                        $value['status'] = 0;
                    }
                }

                //判断是否需要升级
                $firmware_info = $this->_check_need_upgrade($value);
                $need_upgrade = $firmware_info ? 1 : 0;
                $force_upgrade = 0;
                if($need_upgrade) {
                    $force_upgrade = intval($firmware_info['force_upgrade']);
                }
                
                $expires_in = ($value['cvr_end_time'] > $this->base->time)?($value['cvr_end_time'] - $this->base->time):0;
                $device = array(
                    'deviceid' => $value['deviceid'],
                    'data_type' => 0,
                    'cid' => $this->get_cid_by_pk($uid, $value['deviceid']),
                    'device_type' => intval($value['device_type']),
                    'connect_type' => $value['connect_type'],
                    'connect_cid' => $value['connect_cid'],
                    'stream_id' => $value['stream_id'],
                    'status' => strval($value['status']),
                    'description' => addslashes($value['desc']),
                    'cvr_type' => intval($value['cvr_type']),
                    'cvr_day' => $value['cvr_day'],
                    'cvr_end_time' => $value['cvr_end_time'],
                    'cvr_expires_in' => $expires_in,
                    'share' => strval(intval($share['share_type'])),
                    'shareid' => strval($share['shareid']),
                    'uk' => strval(intval($share['uid'] ? $share['uid'] : $share['connect_uid'])),
                    'intro' => strval($share['intro']),
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'timezone' => strval($value['timezone']),
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum'],
                    'grantnum' => $this->get_grantnum($value['deviceid']),
                    'need_upgrade' => $need_upgrade,
                    'force_upgrade' => $force_upgrade
                );

                if (isset($share['password']) && $share['password'] !== '') {
                    $device['needpassword'] = 1;
                }

                if ($share['expires']) {
                    $device['share_end_time'] = $share['dateline']+$share['expires'];
                    $device['share_expires_in'] = $device['share_end_time']-$this->base->time;
                }

                if ($share['share_type']) {
                    $device['showlocation'] = !$share['showlocation'] || !$value['location'] ? 0 : 1;
                }

                if ($value['location']) {
                    $device['location'] = array(
                        'type' => $value['location_type'],
                        'name' => $value['location_name'],
                        'address' => $value['location_address'],
                        'latitude' => floatval($value['location_latitude']),
                        'longitude' => floatval($value['location_longitude'])
                    );
                }
                
                if($value['cvr_free'])
                    $device['cvr_free'] = 1;
                
                if($value['reportstatus'])
                    $device['reportstatus'] = intval($value['reportstatus']);
                
                $devices[] = $device;
            }
            
            $list['list'] = $devices;
        }
        
        if(!$list) {
            $pages = $this->base->page_get_page($page, $count, 0);
            $list = array('page' => $pages['page'], 'count' => 0, 'list' => array());
        }
        
        return $list;
    }

    function listdevice($uid, $support_type, $device_type, $appid=0, $share, $online, $category, $keyword, $orderby, $dids='') {

        foreach($support_type as $connect_type) {
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if($client && $client->_connect_list()) {
                    $my_list = $client->listdevice($uid);
                    if($my_list && is_array($my_list)) {
                        $this->sync_device($uid, $appid, $connect_type, $my_list);
                    }
                }
            }
        }

        //判断企业用户 我的设备机制修改
        $isorg = $_ENV['org']->getorgidbyuid($uid);
        if($isorg){
            if($isorg['admin'] == 1){
                //管理员
                $result = $this->listdevice_org_admin($uid, $support_type, $device_type, $appid, $share, $online, $category, $keyword, $orderby, $dids);
            }else{
                //子用户
                $result = $this->listdevice_org_staff($uid, $support_type, $device_type, $appid, $share, $online, $category, $keyword, $orderby, $dids);
            }
        }else{
            $result = $this->get_listdevice_common($uid, $support_type, $device_type, $appid, $share, $online, $category, $keyword, $orderby, $dids);
        }
        return $result;
    }
    function get_listdevice_common($uid, $support_type, $device_type, $appid=0, $share, $online, $category, $keyword, $orderby, $dids=''){
       

        $table = API_DBTABLEPRE.'device a LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid';
        $where = 'WHERE a.uid='.$uid;

        if($appid > 0) $where .=' AND a.appid='.$appid;
        
        if($dids) $where .=' AND a.deviceid in ('.$dids.')';

        if($share > -1) {
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND (a.connect_online=0 OR a.status&4=0)'; break;
            case 1: $where .= ' AND a.status&4!=0 AND a.connect_online=1'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON a.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        if ($keyword !== '') {
            $this->base->load('search');
            $where .= ' AND a.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY a.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: $orderbysql=' ORDER BY a.dateline DESC';break;
        }

        $list = array();

        $count = $this->db->result_first("SELECT count(*) FROM $table $where");

        if($count) {
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all("SELECT a.deviceid,a.device_type,a.connect_type,m.connect_cid,m.connect_thumbnail,a.cvr_thumbnail,a.stream_id,m.connect_online,a.status,a.laststatusupdate,REPLACE(a.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,a.cvr_type,a.cvr_day,a.cvr_end_time,a.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,a.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,a.location,a.location_type,a.location_name,a.location_address,a.location_latitude,a.location_longitude,a.viewnum,a.approvenum,a.commentnum,a.timezone,a.reportstatus FROM $table LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON m.deviceid=a.deviceid $where AND m.connect_type=a.connect_type AND m.connect_domain='".$this->base->connect_domain."' $orderbysql");
            } else {
                $data = $this->db->fetch_all("SELECT a.deviceid,a.device_type,a.connect_type,a.connect_cid,a.connect_thumbnail,a.cvr_thumbnail,a.stream_id,a.connect_online,a.status,a.laststatusupdate,REPLACE(a.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,a.cvr_type,a.cvr_day,a.cvr_end_time,a.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,a.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,a.location,a.location_type,a.location_name,a.location_address,a.location_latitude,a.location_longitude,a.viewnum,a.approvenum,a.commentnum,a.timezone,a.reportstatus FROM $table $where $orderbysql");
            }
            $list = $this->get_devicelist($uid, $data);
        }

        $result = array(
            'count' => $count,
            'list' => $list
        );

        return $result;
    }

    function listdevice_org_staff($uid, $support_type, $device_type, $appid=0, $share, $online, $category, $keyword, $orderby, $dids=''){

        $table = API_DBTABLEPRE.'device_grant a LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid LEFT JOIN '.API_DBTABLEPRE.'device dev ON a.deviceid=dev.deviceid';
        $where = 'WHERE a.uk='.$uid.' AND a.auth_type=1';

        if($appid > 0) $where .=' AND dev.appid='.$appid;
        
        if($dids) $where .=' AND dev.deviceid in ('.$dids.')';

        if($share > -1) {
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND (dev.connect_online=0 OR dev.status&4=0)'; break;
            case 1: $where .= ' AND dev.status&4!=0 AND dev.connect_online=1'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON a.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        if ($keyword !== '') {
            $this->base->load('search');
            $where .= ' AND dev.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY dev.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: break;
        }

        $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM $table $where");

        if($count) {
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all("SELECT a.deviceid,dev.device_type,dev.connect_type,m.connect_cid,m.connect_thumbnail,dev.cvr_thumbnail,dev.stream_id,m.connect_online,dev.status,dev.laststatusupdate,REPLACE(dev.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,dev.cvr_type,dev.cvr_day,dev.cvr_end_time,dev.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,dev.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,dev.location,dev.location_type,dev.location_name,dev.location_address,dev.location_latitude,dev.location_longitude,dev.viewnum,dev.approvenum,dev.commentnum,dev.timezone,dev.reportstatus FROM $table LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON m.deviceid=a.deviceid $where AND m.connect_type=dev.connect_type AND m.connect_domain='".$this->base->connect_domain."' $orderbysql");
            } else {
                $data = $this->db->fetch_all("SELECT a.deviceid,dev.device_type,dev.connect_type,dev.connect_cid,dev.connect_thumbnail,dev.cvr_thumbnail,dev.stream_id,dev.connect_online,dev.status,dev.laststatusupdate,REPLACE(dev.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,dev.cvr_type,dev.cvr_day,dev.cvr_end_time,dev.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,dev.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,dev.location,dev.location_type,dev.location_name,dev.location_address,dev.location_latitude,dev.location_longitude,dev.viewnum,dev.approvenum,dev.commentnum,dev.timezone,dev.reportstatus FROM $table $where $orderbysql");
            }
            $list = $this->get_devicelist($uid, $data);
        }

        $result = array(
            'count' => $count,
            'list' => $list
        );

        return $result;
    }
    function listdevice_org_admin($uid, $support_type, $device_type, $appid=0, $share, $online, $category, $keyword, $orderby, $dids=''){
        $isorg = $_ENV['org']->getorgidbyuid($uid);
        if(!$isorg)
            return false;
        $org_id = $isorg['org_id'];
        $table = API_DBTABLEPRE.'device_org a LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid LEFT JOIN '.API_DBTABLEPRE.'device dev ON a.deviceid=dev.deviceid';
        $where = 'WHERE a.org_id='.$org_id;

        if($appid > 0) $where .=' AND dev.appid='.$appid;
        
        if($dids) $where .=' AND dev.deviceid in ('.$dids.')';

        if($share > -1) {
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND (dev.connect_online=0 OR dev.status&4=0)'; break;
            case 1: $where .= ' AND dev.status&4!=0 AND dev.connect_online=1'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON a.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        if ($keyword !== '') {
            $this->base->load('search');
            $where .= ' AND dev.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY dev.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: break;
        }

        $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM $table $where");

        if($count) {
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all("SELECT a.deviceid,dev.device_type,dev.connect_type,m.connect_cid,m.connect_thumbnail,dev.cvr_thumbnail,dev.stream_id,m.connect_online,dev.status,dev.laststatusupdate,REPLACE(dev.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,dev.cvr_type,dev.cvr_day,dev.cvr_end_time,dev.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,dev.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,dev.location,dev.location_type,dev.location_name,dev.location_address,dev.location_latitude,dev.location_longitude,dev.viewnum,dev.approvenum,dev.commentnum,dev.timezone,dev.reportstatus FROM $table LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON m.deviceid=a.deviceid $where AND m.connect_type=dev.connect_type AND m.connect_domain='".$this->base->connect_domain."' $orderbysql");
            } else {
                $data = $this->db->fetch_all("SELECT a.deviceid,dev.device_type,dev.connect_type,dev.connect_cid,dev.connect_thumbnail,dev.cvr_thumbnail,dev.stream_id,dev.connect_online,dev.status,dev.laststatusupdate,REPLACE(dev.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,dev.cvr_type,dev.cvr_day,dev.cvr_end_time,dev.cvr_tableid,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,dev.uid) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,dev.location,dev.location_type,dev.location_name,dev.location_address,dev.location_latitude,dev.location_longitude,dev.viewnum,dev.approvenum,dev.commentnum,dev.timezone,dev.reportstatus FROM $table $where $orderbysql");
            }
            $list = $this->get_devicelist($uid, $data);
        }

        $result = array(
            'count' => $count,
            'list' => $list
        );

        return $result;
    }

    function get_devicelist($uid, $data){
        $list = array();
        foreach($data as $value) {
            // 更新设备云录制信息
            $value = $this->update_cvr_info($value);
            
            if($value['laststatusupdate'] + 60 < $this->base->time) {
                if($value['connect_type'] > 0 && $value['connect_online'] == 0 && $value['status'] > 0) {
                    $value['status'] = 0;
                }
            }
            
            //判断是否需要升级
            $firmware_info = $this->_check_need_upgrade($value);
            $need_upgrade = $firmware_info ? 1 : 0;
            $force_upgrade = 0;
            if($need_upgrade) {
                $force_upgrade = intval($firmware_info['force_upgrade']);
            }
            
            $expires_in = ($value['cvr_end_time'] > $this->base->time)?($value['cvr_end_time'] - $this->base->time):0;
            $device = array(
                'deviceid' => $value['deviceid'],
                'data_type' => 0,
                'cid' => $this->get_cid_by_pk($uid, $value['deviceid']),
                'device_type' => intval($value['device_type']),
                'connect_type' => $value['connect_type'],
                'connect_cid' => $value['connect_cid'],
                'stream_id' => $value['stream_id'],
                'status' => strval($value['status']),
                'description' => addslashes($value['desc']),
                'cvr_type' => intval($value['cvr_type']),
                'cvr_day' => $value['cvr_day'],
                'cvr_end_time' => $value['cvr_end_time'],
                'cvr_expires_in' => $expires_in,
                'share' => $value['share_type'],
                'shareid' => $value['shareid'],
                'uk' => $value['uid'] ? $value['uid'] : $value['connect_uid'],
                'intro' => $value['intro'],
                'thumbnail' => $this->get_device_thumbnail($value),
                'timezone' => strval($value['timezone']),
                'viewnum' => $value['viewnum'],
                'approvenum' => $value['approvenum'],
                'commentnum' => $value['commentnum'],
                'grantnum' => $this->get_grantnum($value['deviceid']),
                'need_upgrade' => $need_upgrade,
                'force_upgrade' => $force_upgrade
            );

            if ($value['password'] !== '') {
                $device['needpassword'] = 1;
            }

            if ($value['expires']) {
                $device['share_end_time'] = $value['dateline']+$value['expires'];
                $device['share_expires_in'] = $device['share_end_time']-$this->base->time;
            }

            if ($value['share_type']) {
                $device['showlocation'] = !$value['showlocation'] || !$value['location'] ? 0 : 1;
            }

            if ($value['location']) {
                $device['location'] = array(
                    'type' => $value['location_type'],
                    'name' => $value['location_name'],
                    'address' => $value['location_address'],
                    'latitude' => floatval($value['location_latitude']),
                    'longitude' => floatval($value['location_longitude'])
                );
            }
            
            if($value['cvr_free'])
                $device['cvr_free'] = 1;
            
            if($value['reportstatus'])
                $device['reportstatus'] = intval($value['reportstatus']);
            
            $list[] = $device;
        }
        return $list;
    }

    function get_grantnum($deviceid) {
        return $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'device_grant WHERE deviceid="'.$deviceid.'" AND auth_type=0');
    }

    function get_user_grantnum($uid, $uk) {
        return $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'device_grant WHERE uid="'.$uid.'" AND uk="'.$uk.' AND auth_type=0 "');
    }

    function sync_device($uid, $appid, $connect_type, $lists, $clean=true) {
        if(!$uid || !$lists)
            return false;

        $count = $lists['count'];
        $list = $lists['list'];

        $lastsync = $this->base->time;

        if($count && $list) {
            foreach($list as $device) {
                $deviceid = $device['deviceid'];
                $desc = addslashes($device['description']);
                $stream_id = $device['stream_id'];
                $status = $device['status'];
                $connect_online = $status?1:0;
                $description = $device['description'];
                $cvr_type = $device['cvr_end_time']>$this->base->time?1:0;
                $cvr_day = $device['cvr_day'];
                $cvr_end_time = $device['cvr_end_time'];
                $thumbnail = $device['thumbnail'];
                $share_type = $device['share'];

                $device = $this->get_device_by_did($deviceid);
                if($device) {
                    if($device['uid'] && $device['connect_type'] != $connect_type)
                        continue;
                    
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid='$uid', connect_type='$connect_type', connect_thumbnail='$thumbnail', stream_id='$stream_id', connect_online='$connect_online', status='$status', share_type='$share_type', `desc`='$desc', cvr_type='$cvr_type', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync' WHERE deviceid='$deviceid'");
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET deviceid='$deviceid', uid='$uid', appid='$appid', connect_type='$connect_type', connect_thumbnail='$thumbnail', stream_id='$stream_id', status='$status', share_type='$share_type', `desc`='$desc', cvr_type='$cvr_type', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync', dateline='$lastsync'");
                }
                
                $this->update_power($deviceid, $status&4);
            }
        }

        // 处理未更新数据
        if($clean) {
            //$this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid='0', isonline='0', isalert='0', isrecord='0', status='0' WHERE uid='$uid' AND connect_type='$connect_type' AND lastupdate<'$lastsync'");
        }

        return true;
    }

    function sync_grant_device($uk, $appid, $connect_type, $lists) {
        if(!$uk || !$lists)
            return false;

        $count = $lists['count'];
        $list = $lists['list'];

        $lastsync = $this->base->time;

        if($count && $list) {
            foreach($list as $device) {
                $deviceid = $device['deviceid'];
                $stream_id = $device['stream_id'];
                $desc = addslashes($device['description']);
                $cvr_day = $device['cvr_day'];
                $status = $device['status'];
                $connect_online = $status?1:0;
                $connect_thumbnail = $device['thumbnail'];
                $cvr_type = $device['cvr_end_time']>$this->base->time?1:0;
                $cvr_day = $device['cvr_day'];
                $cvr_end_time = $device['cvr_end_time'];

                $device = $this->get_device_by_did($deviceid);
                if($device) {
                    if($device['uid'] && $device['connect_type'] != $connect_type)
                        continue;

                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_type='$connect_type', connect_thumbnail='$connect_thumbnail', stream_id='$stream_id', connect_online='$connect_online', status='$status', `desc`='$desc', cvr_type='$cvr_type', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync' WHERE deviceid='$deviceid'");
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET deviceid='$deviceid', connect_type='$connect_type', appid='$appid', connect_thumbnail='$connect_thumbnail', stream_id='$stream_id', connect_online='$connect_online', status='$status', `desc`='$desc', cvr_type='$cvr_type', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync', dateline='$lastsync'");
                }
                
                $this->update_power($deviceid, $status&4);

                $grantid = $this->db->result_first("SELECT grantid FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' and uk='$uk' AND connect_type='$connect_type' AND auth_type=0");
                if(!$grantid) {
                    $uid = intval($device['uid']);
                    $name = $this->db->result_first("SELECT username FROM ".API_DBTABLEPRE."members WHERE uid='$uk'");
                    $name = $name?$name:'';
                    $connect_uid = $this->db->result_first("SELECT connect_uid FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='$connect_type' AND uid='$uk'");
                    $connect_uid = intval($connect_uid);

                    if($uid != $uk) {
                        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_grant SET deviceid='$deviceid', connect_type='$connect_type', `connect_uid`='$connect_uid', uid='$uid', uk='$uk', `name`='$name', appid='$appid', lastupdate='$lastsync', dateline='$lastsync'");
                    }
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_grant SET lastupdate='$lastsync' WHERE grantid='$grantid'");
                }
            }
        }

        // 处理未更新数据
        //$this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE uk='$uk' AND connect_type='$connect_type' AND lastupdate<'$lastsync' AND auth_type=0");

        return true;
    }

    function sync_sub_device($uid, $appid, $connect_type, $lists) {
        if(!$uid || !$lists)
            return false;

        $count = $lists['count'];
        $list = $lists['device_list'];

        $lastsync = $this->base->time;

        if($count && $list) {
            foreach($list as $device) {
                $deviceid = $device['deviceid'];
                $desc = $device['description'];
                $status = $device['status'];
                $connect_online = $status?1:0;
                $thumbnail = $device['thumbnail'];
                $shareid = $device['shareid'];
                $connect_uid = $device['uk'];
                $share_type = $device['share'];

                $device = $this->get_device_by_did($deviceid);
                if($device) {
                    if($device['uid'] && $device['connect_type'] != $connect_type)
                        continue;

                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_type='$connect_type', connect_thumbnail='$thumbnail', connect_online='$connect_online', status='$status', `desc`='$desc', lastupdate='$lastsync' WHERE deviceid='$deviceid'");
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET deviceid='$deviceid', appid='$appid', connect_type='$connect_type', connect_thumbnail='$thumbnail', connect_online='$connect_online', status='$status', `desc`='$desc', lastupdate='$lastsync', dateline='$lastsync'");
                }

                $this->update_power($deviceid, $status&4);

                $duid = $this->db->result_first("SELECT uid FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='$connect_type' AND connect_uid='$connect_uid'");
                if(!$duid) $duid = 0;

                $share = $this->get_share($deviceid);
                if($share) {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_share SET shareid='$shareid', connect_type='$connect_type', share_type='$share_type', connect_uid='$connect_uid', uid='$duid', lastupdate='$lastsync' WHERE deviceid='$deviceid'");
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_share SET shareid='$shareid', deviceid='$deviceid', appid='$appid', connect_type='$connect_type', share_type='$share_type', connect_uid='$connect_uid', uid='$duid', lastupdate='$lastsync', dateline='$lastsync'");
                }

                // sub
                $sub = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_subscribe WHERE shareid='$shareid' and uid='$uid'");
                if(!$sub) {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_subscribe SET shareid='$shareid', deviceid='$deviceid', connect_type='$connect_type', `connect_uid`='$connect_uid', uid='$uid', uk='$duid', share_type='$share_type', appid='$appid', lastupdate='$lastsync', dateline='$lastsync'");
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_subscribe SET connect_type='$connect_type', `connect_uid`='$connect_uid', uk='$duid', share_type='$share_type', lastupdate='$lastsync' WHERE shareid='$shareid' and uid='$uid'");
                }
            }
        }

        // 处理未更新数据
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_subscribe WHERE uid='$uid' AND connect_type='$connect_type' AND lastupdate<'$lastsync'");

        return true;
    }

    function get_device_thumbnail($device) {
        if(!$device || (!$device['connect_thumbnail'] && !$device['cvr_thumbnail']))
            return '';

        if($device['connect_thumbnail']) 
            return $device['connect_thumbnail'];

        $tablename = API_DBTABLEPRE."device_cvr_file_".$device['cvr_tableid'];
        $thumbnail = $this->db->fetch_first("SELECT * FROM ".$tablename." WHERE fileid='".$device['cvr_thumbnail']."' and deviceid='".$device['deviceid']."' and uid='".$device['uid']."'");
        if(!$thumbnail)
            return '';
        
        $params = array('uid'=>$device['uid'], 'type'=>'thumbnail', 'container'=>$thumbnail['pathname'], 'object'=>$thumbnail['filename'], 'device' => $device);
        return $this->base->storage_temp_url($thumbnail['storageid'], $params);
    }

    function delete_devices($dids) {
        $dids = $this->base->implode($dids);
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device WHERE deviceid IN ($dids)");
        return $this->db->affected_rows();
    }

    function get_device_by_did($id, $fileds='*', $extend=false) {
        if($extend) {
            $arr = $this->db->fetch_first("SELECT $fileds FROM ".API_DBTABLEPRE."device d LEFT JOIN ".API_DBTABLEPRE."devicefileds df ON df.deviceid=d.deviceid WHERE d.deviceid='$id'");
        } else {
            $arr = $this->db->fetch_first("SELECT $fileds FROM ".API_DBTABLEPRE."device WHERE deviceid='$id'");
        }
        if($arr) {
            if($arr['timezone'] === '') $arr['timezone'] = 'Asia/Shanghai';
            if($this->base->connect_domain) {
                $connect_domain = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_connect_domain WHERE deviceid='$id' AND connect_type='".$arr['connect_type']."' AND connect_domain='".$this->base->connect_domain."'");
                if($connect_domain) {
                    $arr['connect_cid'] = $connect_domain['connect_cid'];
                    $arr['connect_thumbnail'] = $connect_domain['connect_thumbnail'];
                    $arr['connect_online'] = $connect_domain['connect_online'];
                }
            }
        }
        return $arr;
    }

    function add_device($uid, $deviceid, $connect_type, $connect_cid, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude) {
        $stream_id = $stream_id ? $stream_id : $this->_stream_id($uid, $deviceid);

        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET uid='$uid', deviceid='$deviceid', connect_type='$connect_type', appid='$appid', client_id='$client_id', device_type='1', `desc`='$desc', stream_id='$stream_id', status='0', dateline='".$this->base->time."', regip='$regip', timezone='$timezone', location='$location', location_type='$location_type', location_name='$location_name', location_address='$location_address', location_latitude='$location_latitude', location_longitude='$location_longitude'");
        
        $this->update_device_connect_cid($deviceid, $connect_type, $connect_cid);

        return $this->get_device_by_did($deviceid);
    }

    function update_device($uid, $deviceid, $connect_type, $connect_cid, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude) {
        $stream_id = $stream_id ? $stream_id : $this->_stream_id($uid, $deviceid);

        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid='$uid', connect_type='$connect_type', appid='$appid', client_id='$client_id', `desc`='$desc', stream_id='$stream_id', status='0', dateline='".$this->base->time."', regip='$regip', timezone='$timezone', location='$location', location_type='$location_type', location_name='$location_name', location_address='$location_address', location_latitude='$location_latitude', location_longitude='$location_longitude' WHERE deviceid='$deviceid'");
        
        $this->update_device_connect_cid($deviceid, $connect_type, $connect_cid);

        return $this->get_device_by_did($deviceid);
    }

    function get_desc($deviceid) {
        $desc = $this->db->result_first("SELECT `desc` FROM ".API_DBTABLEPRE."device WHERE deviceid='$deviceid'");
        return $desc ? $desc : '';
    }

    function update_location($deviceid, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude) {
        $this->base->load('map');
        $_ENV['map']->clean_building_device($deviceid);

        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET location='$location', location_type='$location_type', location_name='$location_name', location_address='$location_address', location_latitude='$location_latitude', location_longitude='$location_longitude' WHERE deviceid='$deviceid'");

        return true;
    }

    function get_location($deviceid) {
        return $this->db->fetch_first('SELECT location_type,location_name,location_address,location_latitude,location_longitude FROM '.API_DBTABLEPRE.'device WHERE deviceid="'.$deviceid.'" AND location=1');
    }

    function drop_location($deviceid) {
        $this->base->load('map');
        $_ENV['map']->clean_building_device($deviceid);
        
        $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET location=0 WHERE deviceid="'.$deviceid.'"');

       return true;
    }

    function update_desc($device, $desc) {
        if(!$device || !$device['deviceid'])
            return FALSE;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return FALSE;

            $ret = $client->device_update($device, $desc);
            if(!$ret)
                return FALSE;
        }
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `desc`='$desc' WHERE deviceid='$deviceid'");
        //是否具有osd功能权限
        $partner = $this->db->fetch_first("SELECT p.* FROM ".API_DBTABLEPRE."device_partner d LEFT JOIN ".API_DBTABLEPRE."partner p ON d.partner_id=p.partner_id
            WHERE d.deviceid='$deviceid'");
        if($partner && $partner['config']) {
            $partner_config = json_decode($partner['config'], true);
            if(isset($partner_config['osd']) && $partner_config['osd'] ==1 ){
                $this->set_osd_desc($client ,$device, 1);
            }
        }
        return TRUE;
    }

    // 更新设备osd
    function set_osd_desc($client, $device, $set_osd) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $set_osd = $set_osd ? 1 : 0;

        if(!$set_osd)
            return false;

        $command = '{"main_cmd":65,"sub_cmd":13,"param_len":1,"params":"01"}';
        // api request
        if(!$client->device_usercmd($device, $command, 0))
            return false;

        return true;
    }
    
    
    function update_timezone($device, $timezone, $isonline=NULL) {
        if(!$device || !$device['deviceid'])
            return FALSE;
        
        $this->base->log('timezone_log', 'device='.$device['deviceid'].', connect_type='.$device['connect_type'].', timezone='.$timezone);
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;
        
        // 设备在线
        if($isonline === NULL) $isonline = $this->_device_online($client, $device);
        if(!$isonline)
            return false;
        
        if(!$this->sync_setting($device, 'timezone', 1))
            return false;
        
        if(!$this->set_timezone($client, $device, $timezone))
            return false;
        
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `timezone`='$timezone' WHERE deviceid='$deviceid'");
        return TRUE;
    }
    
    function is_device_online($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;
        
        if($this->_device_online($client, $device)) {
            return true;
        }
        
        return false;
    }
    
    function _device_online($client, $device) {
        if(!$client || !$device)
            return false;
        
        return $client->_device_online($device);
    }
    
    function update_power($deviceid, $power) {
        $this->_check_fileds($deviceid);
        $power = $power?1:0;
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power`='$power' WHERE deviceid='$deviceid'");
        return TRUE;
    }
    
    function server_connect_status($deviceid, $online) {
        $online = $online?1:0;
        $sqladd = $online?(",`laststatusupdate`='".$this->base->time."'"):"";
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `connect_online`='$online' $sqladd WHERE deviceid='$deviceid'");
        return TRUE;
    }

    function update_status($deviceid, $status) {
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `isonline`='".$status['isonline']."', `isalert`='".$status['isalert']."', `isrecord`='".$status['isrecord']."', `status`='".$status['status']."', `lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
        $this->update_power($deviceid, $status['isonline']);
        return TRUE;
    }

    function _stream_id($uid, $deviceid) {
        return md5('iermu'.$uid.$deviceid.'umrei');
    }

    function subscribe($uid, $share, $appid, $client_id) {
        if(!$uid || !$share)
            return FALSE;

        $deviceid = $share['deviceid'];
        $shareid = $share['shareid'];
        $uk = $share['uid'];
        $share_type = $share['share_type'];
        $connect_type = $share['connect_type'];
        $connect_uid = $share['connect_uid'];

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);

            if(!$client)
                return FALSE;

            $ret = $client->subscribe($uid, $shareid, $connect_uid);
            if(!$ret)
                return FALSE;
        }
        $sub = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_subscribe WHERE uid='$uid' AND shareid='$shareid'");
        if(!$sub) {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_subscribe SET uid='$uid', deviceid='$deviceid', connect_type='$connect_type', connect_uid='$connect_uid', shareid='$shareid', share_type='$share_type', uk='$uk', appid='$appid', client_id='$client_id', dateline='".$this->base->time."', lastupdate='".$this->base->time."'");
        }
        return TRUE;
    }

    function unsubscribe($uid, $share) {
        if(!$uid || !$share)
            return FALSE;

        $shareid = $share['shareid'];
        $connect_type = $share['connect_type'];

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            $connect_uid = $share['connect_uid'];
            if(!$client)
                return FALSE;

            $ret = $client->unsubscribe($uid, $shareid, $connect_uid);
            if(!$ret)
                return FALSE;
        }
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_subscribe WHERE uid='$uid' AND shareid='$shareid'");
        return TRUE;
    }

    function listsubscribe($uid, $support_type, $appid=0, $share, $online, $category, $keyword, $orderby) {
        foreach($support_type as $connect_type) {
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if($client && $client->_connect_list())  {
                    $sub_list = $client->listsubscribe($uid);
                    if($sub_list && is_array($sub_list)) {
                        $this->sync_sub_device($uid, $appid, $connect_type, $sub_list);
                    }
                }
            }
        }

        $table = API_DBTABLEPRE.'device_subscribe d LEFT JOIN '.API_DBTABLEPRE.'device a ON d.deviceid=a.deviceid LEFT JOIN '.API_DBTABLEPRE.'device_share b ON d.deviceid=b.deviceid';
        $where = 'WHERE d.uid='.$uid;

        if($appid > 0) $where .=' AND a.appid='.$appid;

        if($share > -1) {
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND (a.connect_online=0 OR a.status&4=0)'; break;
            case 1: $where .= ' AND a.status&4!=0 AND a.connect_online=1'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON d.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        if ($keyword !== '') {
            $this->base->load('search');
            $where .= ' AND a.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY a.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: break;
        }

        $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM $table $where");
        if($count) {
            $this->base->load('user');
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all("SELECT a.uid,a.deviceid,a.device_type,a.connect_type,m.connect_cid,m.connect_thumbnail,a.cvr_thumbnail,a.stream_id,m.connect_online,a.status,a.laststatusupdate,REPLACE(a.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,a.cvr_day,a.cvr_end_time,d.share_type,d.shareid,d.uk,d.connect_uid,b.password,b.dateline,b.expires,b.showlocation,a.location,a.location_type,a.location_name,a.location_address,a.viewnum,a.approvenum,a.commentnum,a.timezone,b.intro,a.reportstatus FROM $table LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON a.deviceid=m.deviceid $where  AND m.connect_type=a.connect_type AND m.connect_domain='".$this->base->connect_domain."' $orderbysql");
            } else {
                $data = $this->db->fetch_all("SELECT a.uid,a.deviceid,a.device_type,a.connect_type,a.connect_cid,a.connect_thumbnail,a.cvr_thumbnail,a.stream_id,a.connect_online,a.status,a.laststatusupdate,REPLACE(a.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,a.cvr_day,a.cvr_end_time,d.share_type,d.shareid,d.uk,d.connect_uid,b.password,b.dateline,b.expires,b.showlocation,a.location,a.location_type,a.location_name,a.location_address,a.viewnum,a.approvenum,a.commentnum,a.timezone,b.intro,a.reportstatus FROM $table $where $orderbysql");
            }
            foreach($data as $value) {
                $user = $_ENV['user']->_format_user($value['uid']);
                
                if($value['laststatusupdate'] + 60 < $this->base->time) {
                    if($value['connect_type'] > 0 && $value['connect_online'] == 0 && $value['status'] > 0) {
                        $value['status'] = 0;
                    }
                }

                //判断是否需要升级
                $firmware_info = $this->_check_need_upgrade($value);
                $need_upgrade = $firmware_info ? 1 : 0;
                $force_upgrade = 0;
                if($need_upgrade) {
                    $force_upgrade = intval($firmware_info['force_upgrade']);
                }
                
                $share = array(
                    'shareid' => $value['shareid'],
                    'uk' => $value['uk'] ? $value['uk'] : $value['connect_uid'],
                    'deviceid' => $value['deviceid'],
                    'data_type' => 2,
                    'cid' => $this->get_cid_by_pk($uid, $value['deviceid']),
                    'device_type' => intval($value['device_type']),
                    'connect_type' => $value['connect_type'],
                    'connect_cid' => $value['connect_cid'],
                    'description' => $value['desc'],
                    'share' => $value['share_type'],
                    'intro' => $value['intro'],
                    'status' => strval($value['status']),
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'timezone' => strval($value['timezone']),
                    'uid' => $user['uid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'subscribe' => 1,
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum'],
                    'need_upgrade' => $need_upgrade,
                    'force_upgrade' => $force_upgrade
                );

                if ($value['password'] !== '') {
                    $share['needpassword'] = 1;
                }

                if ($value['expires']) {
                    $share['share_end_time'] = $value['dateline']+$value['expires'];
                    $share['share_expires_in'] = $share['share_end_time']-$this->base->time;
                }

                if ($value['share_type']) {
                    $share['showlocation'] = !$value['showlocation'] || !$value['location'] ? 0 : 1;
                }

                if ($share['showlocation']) {
                    $share['location'] = array(
                        'type' => $value['location_type'],
                        'name' => $value['location_name'],
                        'address' => $value['location_address']
                    );
                }
                
                if($value['reportstatus'])
                    $share['reportstatus'] = intval($value['reportstatus']);

                $list[] = $share;
            }
        }

        $result = array(
            'count' => $count,
            'device_list' => $list
        );

        return $result;
    }

    function check_grant($deviceid, $appid) {
        if(!$appid || !$deviceid) return FALSE;
        $app = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_app WHERE appid=".$appid);
        if(!$app) return FALSE;
        $maxnum = $app['grantmaxnum'];
        if($maxnum > 0) {
            $count = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid'");
            return ($count < $maxnum)?TRUE:FALSE;
        }
        return TRUE;
    }
    //检查用户权限1.普通授权 2.全部授权 0.无权限
    function check_user_grant($deviceid, $uk) {
        if(!$deviceid || !$uk) return FALSE;

        //大账号小账号模式
        $partner_id = $this->base->partner_id;
        $client_partner_type = $this->base->client_partner_type;
        $partner_admin = $this->base->partner_admin;
        if($partner_id && $client_partner_type == 1 && $partner_admin ==1){
            $device = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_partner WHERE deviceid='$deviceid' AND partner_id='$partner_id'");
            if($device)
                return 2;
        }

        $org = $_ENV['org']->getorgidbyuid($uk);
        if(!$org){
            $device = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device WHERE deviceid='$deviceid' AND uid='$uk'");
            if($device)
                return 2;
            $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk' AND auth_type=0");
            if($check)
                return 1;

            return 0;
        }else{
            $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk' AND auth_type=0");
            if($check)
                return 1;
            $check_type = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk' AND auth_type=1");
            $check_org = $this->db->fetch_first("SELECT a.org_id FROM ".API_DBTABLEPRE."device_org a LEFT JOIN ".API_DBTABLEPRE."member_org b ON a.org_id=b.org_id WHERE a.deviceid ='$deviceid' AND b.uid = '$uk' AND b.admin = 1");
            if($check_type || $check_org)
                return 2;

            return 0;
        }
        
    }

    function check_user_grant_bak($deviceid, $uk) {
        if(!$deviceid || !$uk) return FALSE;
        $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk'");
        $check_org = $this->db->fetch_first("SELECT a.org_id FROM ".API_DBTABLEPRE."device_org a LEFT JOIN ".API_DBTABLEPRE."member_org b ON a.org_id=b.org_id WHERE a.deviceid ='$deviceid' AND b.uid = '$uk' AND b.admin = 1");
        if($check || $check_org){
            return TRUE;
        }
        return FALSE;
    }

    function grant($dev_list, $user, $auth_code, $code, $appid, $client_id) {
        $uk = $user['uid'];
        $name = $user['username'];

        foreach ($dev_list as $device) {
            $deviceid = $device['deviceid'];
            $uid = $device['uid'];
            $connect_type = $device['connect_type'];

            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                $connect_arr = $_ENV['user']->get_connect_by_uid($connect_type, $uk);
                if(!$client || !$connect_arr)
                    return FALSE;

                $ret = $client->grant($uid, $connect_arr['connect_uid'], $connect_arr['username'], $auth_code, $device);
                
                if(!$ret)
                    return FALSE;
            }
            //增加企业授权判断
            $org_id = 0;
            $is_org = $this->db->result_first("SELECT a.org_id FROM ".API_DBTABLEPRE."device_org a LEFT JOIN ".API_DBTABLEPRE."member_org b on b.org_id=a.org_id WHERE a.deviceid='$deviceid' AND b.uid='$uk'");
            if($is_org){
                $org_id = $is_org;
            }

            $grant = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk' AND auth_type=0");
            if(!$grant) {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_grant SET uid='$uid', deviceid='$deviceid', org_id='$org_id', connect_type='$connect_type', connect_uid='".$connect_arr['connect_uid']."', uk='$uk', name='$name', auth_code='$auth_code', appid='$appid', client_id='$client_id', dateline='".$this->base->time."'");
            } else {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_grant SET auth_code='$auth_code', org_id='$org_id', appid='$appid', client_id='$client_id', dateline='".$this->base->time."' WHERE grantid='".$grant['grantid']."'");
            }

            if($code) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_grant_code SET useid="'.$uk.'", usedate="'.$this->base->time.'" WHERE code="'.$code.'"');
            }

            // 观看次数清零
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_view SET num="0" WHERE `uid`="'.$uk.'" AND `deviceid`="'.$deviceid.'"');
        }

        $result = array(
            'uid' => $uk,
            'username' => $name
        );

        return $result;
    }

    function enterprise_register_grant($device, $user, $appid, $client_id, $org_id=0) {
        $uk = $user['uid'];
        $name = $user['username'];

        $deviceid = $device['deviceid'];
        $uid = $device['uid'];
        $connect_type = $device['connect_type'];
        $connect_arr = $_ENV['user']->get_connect_by_uid($connect_type, $uk);

        $grant = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk'");
        if(!$grant) {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_grant SET uid='$uid', deviceid='$deviceid', org_id='$org_id' ,connect_type='$connect_type', connect_uid='".$connect_arr['connect_uid']."', uk='$uk', name='$name', auth_type='1', appid='$appid', client_id='$client_id', dateline='".$this->base->time."'");
        } else {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_grant SET auth_type='1', org_id='$org_id', appid='$appid', client_id='$client_id', dateline='".$this->base->time."' WHERE grantid='".$grant['grantid']."'");
        }
        return true;
    }

    function listgrantuser($uid, $deviceid, $connect_type, $appid=0, $client_id) {
        if(!$uid || !$deviceid)
            return FALSE;
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client && $client->_connect_list()) {
                $grant_list = $client->listgrantuser($uid, $deviceid);
                if ($grant_list && is_array($grant_list)) {
                    $this->sync_grant_user($uid, $deviceid, $connect_type, $appid, $client_id, $grant_list);
                }
            }
        }

        
        $where = ' WHERE a.uid="'.$uid.'" AND a.deviceid="'.$deviceid.'" AND auth_type=0';
        if($appid > 0)
            $where .= ' AND a.appid='.$appid;

        $orderby = ' ORDER BY a.dateline DESC';

        $list = array();
        $count = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'device_grant a'.$where);
        if($count) {
            $list = $this->db->fetch_all('SELECT IF(a.uk,a.uk,a.connect_uid) AS uk,a.name,a.auth_code,a.dateline AS time,IFNULL(b.remarkname,"") AS remarkname,IFNULL(c.num,0) AS viewnum,IFNULL(c.lastupdate,0) AS lastview FROM '.API_DBTABLEPRE.'device_grant a LEFT JOIN '.API_DBTABLEPRE.'member_remarkname b ON a.uid=b.uid AND a.uk=b.uk LEFT JOIN '.API_DBTABLEPRE.'device_view c ON a.uk=c.uid AND a.deviceid=c.deviceid'.$where.$orderby);

            $this->base->load('user');
            for ($i = 0; $i < $count; $i++) {
                $list[$i]['avatar'] = $_ENV['user']->get_avatar($list[$i]['uk']);
                $list[$i]['viewnum'] = intval($list[$i]['viewnum']);
                $list[$i]['lastview'] = $list[$i]['viewnum'] ? intval($list[$i]['lastview']) : 0;
            }
        }

        $result = array(
            'count' => $count,
            'list' => $list
        );

        return $result;
    }

    function sync_grant_user($uid, $deviceid, $connect_type, $appid, $client_id, $lists) {
        $count = $lists['count'];
        $list = $lists['list'];

        if ($count && $list) {
            foreach ($list as $user) {
                $connect_uid = $user['uk'];
                $name = $user['name'];
                $auth_code = $user['auth_code'];
                $dateline = $user['time'];
                $lastsync = $this->base->time;

                $grantid = $this->db->result_first("SELECT grantid FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND connect_uid='$connect_uid' AND connect_type='$connect_type' AND auth_type=0");
                if(!$grantid) {
                    $uk = $this->db->result_first("SELECT uid FROM ".API_DBTABLEPRE."member_connect WHERE connect_uid='$connect_uid' AND connect_type='$connect_type'");
                    if (!$uk)
                        $uk = 0;

                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_grant SET connect_type='$connect_type', `connect_uid`='$connect_uid', uid='$uid', deviceid='$deviceid', uk='$uk', `name`='$name', auth_code='$auth_code', appid='$appid', client_id='$client_id', lastupdate='$lastsync', dateline='$dateline'");
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_grant SET lastupdate='$lastsync' WHERE grantid='$grantid'");
                }
            }
        }

        // 处理未更新数据
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND connect_type='$connect_type' AND lastupdate<'$lastsync' AND auth_type=0");
    }

    function listgrantdevice($uid, $support_type, $appid=0, $share, $online, $category, $keyword, $orderby) {
        if(is_array($support_type)){
            foreach($support_type as $connect_type) {
                if($connect_type > 0) {
                    $client = $this->_get_api_client($connect_type);
                    if($client && $client->_connect_list()) {
                        $grant_list = $client->listgrantdevice($uid);
                        if($grant_list && is_array($grant_list)) {
                            $this->sync_grant_device($uid, $appid, $connect_type, $grant_list);
                        }
                    }
                }
            }
        }
        $table = API_DBTABLEPRE.'device_grant d LEFT JOIN '.API_DBTABLEPRE.'device a ON d.deviceid=a.deviceid LEFT JOIN '.API_DBTABLEPRE.'device_share b ON d.deviceid=b.deviceid';
        $where = 'WHERE d.uk='.$uid.' AND d.auth_type=0';

        if($appid > 0) $where .=' AND a.appid='.$appid;

        if($share > -1) {
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND (a.connect_online=0 OR a.status&4=0)'; break;
            case 1: $where .= ' AND a.status&4!=0 AND a.connect_online=1'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON d.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        if ($keyword !== '') {
            $this->base->load('search');
            $where .= ' AND a.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY a.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: break;
        }

        $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM $table $where");
        if($count) {
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all("SELECT d.auth_type,a.deviceid,a.device_type,a.connect_type,a.connect_cid,m.connect_thumbnail,a.cvr_thumbnail,a.stream_id,m.connect_online,a.status,a.laststatusupdate,REPLACE(a.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,a.cvr_type,a.cvr_day,a.cvr_end_time,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,0) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,a.location,a.location_type,a.location_name,a.location_address,a.viewnum,a.approvenum,a.commentnum,a.timezone,a.reportstatus FROM $table LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON m.deviceid=a.deviceid $where AND m.connect_type=a.connect_type AND m.connect_domain='".$this->base->connect_domain."' $orderbysql");
            } else {
                $data = $this->db->fetch_all("SELECT d.auth_type,a.deviceid,a.device_type,a.connect_type,a.connect_cid,a.connect_thumbnail,a.cvr_thumbnail,a.stream_id,a.connect_online,a.status,a.laststatusupdate,REPLACE(a.desc,'$keyword','<span class=hl_keywords>$keyword</span>') AS `desc`,a.cvr_type,a.cvr_day,a.cvr_end_time,IFNULL(b.share_type,0) AS share_type,IFNULL(b.shareid,'') AS shareid,IFNULL(b.uid,0) AS uid,IFNULL(b.connect_uid,0) AS connect_uid,IFNULL(b.intro,'') AS intro,IFNULL(b.password,'') AS password,IFNULL(b.dateline,0) AS dateline,IFNULL(b.expires,0) AS expires,IFNULL(b.showlocation,0) AS showlocation,a.location,a.location_type,a.location_name,a.location_address,a.viewnum,a.approvenum,a.commentnum,a.timezone,a.reportstatus FROM $table $where $orderbysql");
            }
 
            foreach($data as $value) {
                // 更新设备云录制信息
                $value = $this->update_cvr_info($value);

                if($value['laststatusupdate'] + 60 < $this->base->time) {
                    if($value['connect_type'] > 0 && $value['connect_online'] == 0 && $value['status'] > 0) {
                        $value['status'] = 0;
                    }
                }

                //判断是否需要升级
                $firmware_info = $this->_check_need_upgrade($value);
                $need_upgrade = $firmware_info ? 1 : 0;
                $force_upgrade = 0;
                if($need_upgrade) {
                    $force_upgrade = intval($firmware_info['force_upgrade']);
                }
                
                $device = array(
                    'deviceid' => $value['deviceid'],
                    'data_type' => 1,
                    'cid' => $this->get_cid_by_pk($uid, $value['deviceid']),
                    'device_type' => intval($value['device_type']),
                    'connect_type' => $value['connect_type'],
                    'connect_cid' => $value['connect_cid'],
                    'stream_id' => $value['stream_id'],
                    'status' => strval($value['status']),
                    'description' => $value['desc'],
                    'cvr_type' => intval($value['cvr_type']),
                    'cvr_day' => $value['cvr_day'],
                    'cvr_end_time' => $value['cvr_end_time'],
                    'share' => $value['share_type'],
                    'shareid' => $value['shareid'],
                    'uk' => $value['uid'] ? $value['uid'] : $value['connect_uid'],
                    'intro' => $value['intro'],
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'timezone' => strval($value['timezone']),
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum'],
                    'need_upgrade' => $need_upgrade,
                    'force_upgrade' => $force_upgrade,
                    'auth_type' => $value['auth_type']
                );

                if ($value['password'] !== '') {
                    $device['needpassword'] = 1;
                }

                if ($value['expires']) {
                    $device['share_end_time'] = $value['dateline']+$value['expires'];
                    $device['share_expires_in'] = $device['share_end_time']-$this->base->time;
                }

                if ($value['share_type']) {
                    $device['showlocation'] = !$value['showlocation'] || !$value['location'] ? 0 : 1;
                }

                if ($device['showlocation']) {
                    $device['location'] = array(
                        'type' => $value['location_type'],
                        'name' => $value['location_name'],
                        'address' => $value['location_address']
                    );
                }
                
                if($value['cvr_free'])
                    $device['cvr_free'] = 1;
                
                if($value['reportstatus'])
                    $device['reportstatus'] = intval($value['reportstatus']);
                
                $list[] = $device;
            }
        }

        $result = array(
            'count' => $count,
            'list' => $list
        );

        return $result;
    }

    function dropgrantuser($device, $uk) {
        if(!$device || !$device['deviceid'] || !$uk)
            return false;

        $deviceid = $device['deviceid'];
        $uk = trim($uk, ',');
        $uids = split(',', $uk);
        if($uids && is_array($uids) && $uids[0]) {
            $connect_type = $device['connect_type'];
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    return false;

                foreach($uids as $uid) {
                    $del = 'uk';
                    $grant = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uid' AND auth_type=0");
                    if(!$grant) {
                        $grant = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND connect_uid='$uid' AND auth_type=0");
                        $del = 'connect_uid';
                    }
                    
                    if($grant && $grant['connect_type'] == $connect_type) {
                        $ret = $client->dropgrantuser($device, $grant['connect_uid']);
                        if(!$ret)
                            return false;
                    }
                    
                    if($del == 'uk') {
                        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uid' AND auth_type=0");

                        // 清除楼宇摄像机
                        $this->db->query('DELETE a FROM '.API_DBTABLEPRE.'building_device a LEFT JOIN '.API_DBTABLEPRE.'building_preview b ON a.pid=b.pid LEFT JOIN '.API_DBTABLEPRE.'map_marker c ON b.bid=c.tid WHERE a.deviceid="'.$deviceid.'" AND c.uid="'.$uid.'" AND c.type="1"');
                    } else {
                        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND connect_uid='$uid'");

                        // 清除楼宇摄像机
                        $this->db->query('DELETE a FROM '.API_DBTABLEPRE.'building_device a LEFT JOIN '.API_DBTABLEPRE.'building_preview b ON a.pid=b.pid LEFT JOIN '.API_DBTABLEPRE.'map_marker c ON b.bid=c.tid LEFT JOIN '.API_DBTABLEPRE.'member_connect d ON c.uid=d.uid WHERE a.deviceid="'.$deviceid.'" AND c.type="1" AND d.connect_uid="'.$uid.'"');
                    }
                }
            }
            
            return true;
        }

        return false;
    }

    function create_share($uid, $device, $title, $intro, $share_type, $appid, $client_id, $showlocation = 0, $password = '', $expires = 0) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $shareid = '';
        $connect_uid = $uid;
        $status = 1;
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $ret = $client->createshare($device, $share_type, $expires);
            if(!$ret) 
                return false;

            if(is_array($ret) && $ret['uk']) {
                $shareid = $ret['shareid'];
                $connect_uid = $ret['uk'];
                if(isset($ret['status'])) 
                    $status = $ret['status'];
            }
        }

        // 幼儿云设备
        if($status > 0) {
            $youeryun_did = $this->db->result_first('SELECT deviceid FROM '.API_DBTABLEPRE.'device_youeryun WHERE deviceid="'.$deviceid.'"');
            if($youeryun_did)
                $status = 0;
        }

        $share = $this->get_share($deviceid);
        if ($share) {
            $shareid = $shareid ? $shareid : $share['shareid'];

            $this->db->query("UPDATE ".API_DBTABLEPRE."device_share SET shareid='$shareid',title='$title',intro='$intro',connect_type='$connect_type',connect_uid='$connect_uid',uid='$uid',share_type='$share_type',password='$password',expires='$expires',dateline='".$this->base->time."',lastupdate='".$this->base->time."',appid='$appid',client_id='$client_id',status='$status',showlocation='$showlocation' WHERE shareid='".$share['shareid']."'");

            // 删除收藏
            $this->db->query('DELETE FROM '.API_DBTABLEPRE.'device_subscribe WHERE shareid="'.$share['shareid'].'"');
        } else {
            $shareid = $shareid ? $shareid : $this->_shareid();

            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_share SET shareid='$shareid',title='$title',intro='$intro',connect_type='$connect_type',connect_uid='$connect_uid',uid='$uid',deviceid='$deviceid',share_type='$share_type',password='$password',expires='$expires',dateline='".$this->base->time."',lastupdate='".$this->base->time."',appid='$appid',client_id='$client_id',status='$status',showlocation='$showlocation'");
        }
        
        // 设备重新分享取消下线状态
        if($device['reportstatus'] > 0) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET reportstatus='0' WHERE deviceid='$deviceid'");
        }

        return $this->get_share_by_shareid($shareid);
    }

    function get_share($deviceid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");
    }

    function get_share_by_shareid($shareid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE shareid='$shareid'");
    }

    function get_share_by_deviceid($deviceid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");
    }

    function cancel_share($device) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $ret = $client->cancelshare($device);
            if(!$ret) 
                return false;
        }

        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");
        // 删除收藏
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_subscribe WHERE deviceid='$deviceid'");
        return true;
    }

    // 获取设备评论
    function get_comment_by_did($deviceid, $commentnum=0) {
        $list = array();

        if($commentnum) {
            $this->base->load('user');
            $query = $this->db->query('SELECT cid,parent_cid,uid,ip,comment,dateline FROM '.API_DBTABLEPRE.'device_comment WHERE deviceid="'.$deviceid.'" AND delstatus=0 ORDER BY cid DESC LIMIT '.$commentnum);
            while($data = $this->db->fetch_array($query)) {
                $user = $_ENV['user']->_format_user($data['uid']);

                $data['username'] = $user['username'];
                $data['avatar'] = $user['avatar'];
                $data['comment'] = $this->base->userTextDecode($data['comment']);

                $list[] = $data;
            }
        }

        return $list;
    }

    // 获取订阅设备列表
    function get_subscribe_by_uid($uid, $appid) {
        $arr = array();
        $query = $this->db->query('SELECT * FROM '.API_DBTABLEPRE.'device_subscribe WHERE uid="'.$uid.'" AND appid="'.$appid.'"');
        while($data = $this->db->fetch_array($query)) {
            $arr[] = $data['deviceid'];
        }
        return $arr;
    }

    function list_share($uid, $uk, $support_type, $category, $orderby, $commentnum, $page, $count, $appid=0, $app_type=0, $dids='') {
        $commentnum = $commentnum > 0 ? $commentnum : 0;
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        if($support_type) {
            $support_type = '0,'.implode(',', $support_type);
        } else {
            $support_type = '0';
        }

        // 不同客户端过滤
        $where = 'WHERE b.share_type in (1,3) AND b.connect_type in ('.$support_type.') AND c.reportstatus=0';
        switch ($app_type) {
            case 1:
                $table = API_DBTABLEPRE.'device_youeryun a INNER JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid JOIN '.API_DBTABLEPRE.'device c ON a.deviceid=c.deviceid';
                $where .= ' AND b.status=0';
                break;

            default:
                $table = API_DBTABLEPRE.'device_share b LEFT JOIN '.API_DBTABLEPRE.'device c ON b.deviceid=c.deviceid';
                if($this->base->connect_domain) {
                    $where .= ' AND c.status&0x4!=0 AND m.connect_online=1 AND b.status=1';
                } else {
                    $where .= ' AND c.status&0x4!=0 AND c.connect_online=1 AND b.status=1';
                }
                break;
        }

        if($uk) $where .= ' AND c.uid='.$uk;
        if($appid) $where .= ' AND b.appid='.$appid;
        if($dids) $where .= ' AND a.deviceid in ('.$dids.')';

        if($category) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category d ON b.deviceid=d.deviceid';
            $where .= ' AND d.cid='.$category;
        }

        switch ($orderby) {
            case 'all': $orderbysql=' ORDER BY b.displayorder DESC,c.viewnum DESC,c.approvenum DESC,c.commentnum DESC,b.dateline DESC'; break;
            case 'view': $orderbysql=' ORDER BY b.displayorder DESC,c.viewnum DESC,b.dateline DESC'; break;
            case 'approve': $orderbysql=' ORDER BY b.displayorder DESC,c.approvenum DESC,b.dateline DESC'; break;
            case 'comment': $orderbysql=' ORDER BY b.displayorder DESC,c.commentnum DESC,b.dateline DESC'; break;
            case 'recommend': $orderbysql=' ORDER BY b.displayorder DESC,b.recommend DESC,b.dateline DESC'; $where.=' AND b.recommend>0'; break;
            default: $orderbysql=' ORDER BY b.displayorder DESC,b.dateline DESC'; break;;
        }
        
        if($this->base->connect_domain) {
            $table .= " LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON m.deviceid=c.deviceid";
            $where .= " AND m.connect_type=c.connect_type AND m.connect_domain='".$this->base->connect_domain."'";
        }

        $list = array();
        $total = $this->db->result_first("SELECT count(*) FROM $table $where");

        $pages = $this->base->page_get_page($page, $count, $total);
        if($dids) {
            $limit = '';
        } else {
            $limit = ' LIMIT '.$pages['start'].', '.$count;
        }

        $count = 0;

        if($total) {
            $sub_list = $uid ? $this->get_subscribe_by_uid($uid, $appid) : array();
            $check_sub = !empty($sub_list);

            $this->base->load('user');
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all("SELECT b.shareid,b.connect_type,b.showlocation,b.connect_uid,b.uid,b.deviceid,b.share_type,b.title,b.intro,b.password,b.dateline,b.expires,c.`desc`,m.connect_cid,c.status,m.connect_thumbnail,c.cvr_thumbnail,c.location,c.location_type,c.location_name,c.location_address,c.viewnum,c.approvenum,c.commentnum,c.timezone,c.device_type FROM $table $where $orderbysql $limit");
            } else {
                $data = $this->db->fetch_all("SELECT b.shareid,b.connect_type,b.showlocation,b.connect_uid,b.uid,b.deviceid,b.share_type,b.title,b.intro,b.password,b.dateline,b.expires,c.`desc`,c.connect_cid,c.status,c.connect_thumbnail,c.cvr_thumbnail,c.location,c.location_type,c.location_name,c.location_address,c.viewnum,c.approvenum,c.commentnum,c.timezone,c.device_type FROM $table $where $orderbysql $limit");
            }
            foreach($data as $value) {
                $user = $_ENV['user']->_format_user($value['uid']);
                
                $share = array(
                    'shareid' => $value['shareid'],
                    'uk' => $value['uid'] ? $value['uid'] : $value['connect_uid'],
                    'device_type' => intval($value['device_type']),
                    'connect_type' => $value['connect_type'],
                    'connect_cid' => $value['connect_cid'],
                    'description' => $value['title'] ? $value['title'] : $value['desc'],
                    'deviceid' => $value['deviceid'],
                    'share' => $value['share_type'],
                    'intro' => $value['intro'],
                    'status' => strval($value['status']),
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'timezone' => strval($value['timezone']),
                    'uid' => $value['uid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'subscribe' => ($check_sub && in_array($value['deviceid'], $sub_list)) ? 1 : 0,
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum'],
                    'comment_list' => $this->get_comment_by_did($value['deviceid'], 5)
                );

                if ($value['password'] !== '') {
                    $share['needpassword'] = 1;
                }

                if ($value['expires']) {
                    $share['share_end_time'] = $value['dateline']+$value['expires'];
                    $share['share_expires_in'] = $share['share_end_time']-$this->base->time;
                }

                if ($value['share_type']) {
                    $share['showlocation'] = !$value['showlocation'] || !$value['location'] ? 0 : 1;
                }

                if ($share['showlocation']) {
                    $share['location'] = array(
                        'type' => $value['location_type'],
                        'name' => $value['location_name'],
                        'address' => $value['location_address']
                    );
                }

                $list[] = $share;
                $count++;
            }
        }

        $result = array(
            'page' => $pages['page'],
            'count' => $count,
            'device_list' => $list
        );
        
        return $result;
    }

    function _shareid() {
        return md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
    }

    function locate_upload($deviceid, $appid, $default=0) {
        if(!$deviceid || !$appid)
            return array();

        // 是否测试设备
        $isdev = $this->isdev($deviceid);
        $default = ($isdev && $isdev['serverid'])?$isdev['serverid']:$default;

        $this->base->load('server');
        $server = $default ? $_ENV['server']->get_server_by_id($default) : array();

        // 检查server状态
        if($server && $server['url'] && $server['status']>0) {
            return $server;
        }
        // 设置server状态错误
        if($server) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."server SET status=-1 WHERE serverid=".$server['serverid']);
        }

        // TODO: 服务器选择策略 maybe consistent hashing
        $server = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid=".$appid." AND server_type=0 AND status>0 AND isdev=".($isdev?1:0)." ORDER BY devicenum ASC");
        if($server && $server['url']) {
            return $server;
        } else {
        // 设置server状态错误
            if($server) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."server SET status=-1 WHERE serverid=".$server['serverid']);
            }
        }

        return array();
    }

    function list_upload($deviceid, $appid, $default=0) {
        if(!$deviceid || !$appid)
            return array();

        $list = array();

        // 是否测试设备
        $isdev = $this->isdev($deviceid);
        $default = ($isdev && $isdev['serverid'])?$isdev['serverid']:$default;

        $this->base->load('server');
        $server = $default ? $_ENV['server']->get_server_by_id($default) : array();

        // 检查server状态
        if($server && $server['url'] && $server['status']>0) {
            $list[] = $server['url'];
        }

        $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid=".$appid." AND server_type=0 AND status>0 AND isdev=".($isdev?1:0)." ORDER BY devicenum ASC");
        foreach($query as $v) {
            if($v['serverid'] == $default)
                continue;

            $value = $_ENV['server']->get_server_by_id($v['serverid']);
            if($value && $value['url']) {
                $list[] = $value['url'];
            }
        }

        return $list;
    }

    function isdev($deviceid) {
        if(!$deviceid) return array();
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_dev WHERE deviceid='$deviceid'");
    }
    
    function liveplay($device, $type, $params, $client_id = '') {
        if($device && $device['deviceid']) {
            $connect_type = $device['connect_type'];
            $device['client_id'] = $client_id;
        } else {
            $connect_type = API_BAIDU_CONNECT_TYPE;
        }

        if($params['lan'] && $params['lan'] =='1'){
            $res = $this->liveplay_lan($device);
            if($res){
                return $res;
            }
        }
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $ret = $client->liveplay($device, $type, $params);
        if(!$device)
            return $ret;

        if(!$ret)
            return false;

        return $ret;
    }
    //局域网
    function liveplay_lan($device) {
        if(!$device || !$device['deviceid']){
            return false;
        }
        $status = $device['status'];
        $url = '';
        $result = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='".$device['deviceid']."'");
        if($result){
            if($result['ip']){
               $status = 5;
               $url = "rtmp://".$result['ip'].":1935/live/".$device['deviceid'];
            }
        }else{
            return false;
        }
        return array(
            "description" => $device['desc'],
            "type" => "rtmp",
            'status' => $status,
            'url' => $url
        );
    }

    function multi_liveplay($uid, $devicelist) {
        if (!$uid || !$devicelist)
            return false;

        $device_type_list = array();

        foreach ($devicelist as $device) {
            $connect_type = $device['connect_type'];
            $device_type_list[$connect_type][] = $device;
        }

        $list = array();
        foreach ($device_type_list as $key => $value) {
            if ($key > 0) {
                $client = $this->_get_api_client($key);
                if ($client) {
                    $ret = $client->multi_liveplay($uid, $value);
                    if ($ret)
                        $list = array_merge($list, $ret);
                } else {
                    return false;
                }
            } else {
                return false;
            }            
        }

        $result = array();
        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    function _get_b($status, $position) {
        $t = $status & pow(2, $position - 1) ? 1 : 0;
        return $t;
    }

    function _set_b($position, $value, $baseon = null) {
        $t = pow(2, $position - 1);
        if($value) {
            $t = $baseon | $t;
        } elseif ($baseon !== null) {
            $t = $baseon & ~$t;
        } else {
            $t = ~$t;
        }
        return $t & 0xFFFF;
    }

    function get_status($streamproperty) {
        $result = array(
            'isonline' => 0,
            'isalert' => 0,
            'isrecord' => 0,
            'status' => 0
        );

        if($streamproperty < 0) {
            return $result;
        }

        // 设备streamproperty:1位在线,2位报警,3位录像
        $isonline = $this->_get_b($streamproperty, 1)?1:0;
        $isalert = $this->_get_b($streamproperty, 2)?1:0;
        $isrecord = $this->_get_b($streamproperty, 3)?1:0;
        $isupload = ($isalert || $isrecord)?1:0;

        $status = $streamproperty << 2;
        $status = $this->_set_b(1, 1, $status);
        $status = $this->_set_b(2, $isupload, $status);

        $result['isonline'] = $isonline;
        $result['isalert'] = $isalert;
        $result['isrecord'] = $isrecord;
        $result['status'] = $status;
        return $result;
    }

    function drop($device) {
        if(!$device || !$device['deviceid'])
            return FALSE;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return FALSE;

            if(!$client->device_drop($device)) 
                return FALSE;
        }

        $uid = $device['uid']; 

        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid=0,isonline=0,isalert=0,isrecord=0,status=0,cvr_type=0,cvr_day=0,cvr_start_time=0,cvr_end_time=0,cvr_thumbnail='',alarmnum=0,share_type=0,viewnum=0,approvenum=0,commentnum=0,commentstatus=1,location=0 WHERE deviceid='$deviceid'");
        
        // 删除分组信息
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_category WHERE deviceid='$deviceid'");
        
        // 删除剪辑
        $this->db->query("UPDATE ".API_DBTABLEPRE."device_clip SET delstatus=1 WHERE deviceid='$deviceid'");
        
        // TO-DO: 删除事件录像
        
        // 删除截图
        $this->db->query("UPDATE ".API_DBTABLEPRE."device_snapshot SET delstatus=1 WHERE deviceid='$deviceid'");
        
        // 删除评论
        $this->db->query("UPDATE ".API_DBTABLEPRE."device_comment SET delstatus=1 WHERE deviceid='$deviceid'");
        
        // 删除观看记录
        $this->db->query("UPDATE ".API_DBTABLEPRE."device_view SET delstatus=1 WHERE deviceid='$deviceid'");
        
        // 删除报警图片
        $alarm_tableid = $device['alarm_tableid'];
        if($alarm_tableid) {
            $alarm_index_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid;
            $data = $this->db->fetch_all("SELECT * FROM $alarm_index_table WHERE `deviceid`='$deviceid'");
            foreach($data as $pic) {
                $alarm_file_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid.'_'.$pic['table'];
                $this->db->query("UPDATE $alarm_file_table SET `delstatus`=1 WHERE `aid`='".$pic['aid']."'");
            }
            $this->db->query("DELETE FROM $alarm_index_table WHERE `deviceid`='$deviceid'");
        }
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid'");

        // 删除用户录像/截图
        $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='$deviceid'");
        foreach($query as $cvr) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_file_".$cvr['tableid']." SET delstatus=1 WHERE cvrid=".$cvr['cvrid']);
        }
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='$deviceid'");

        // 删除分享
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");

        // 删除收藏
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_subscribe WHERE deviceid='$deviceid'");

        // 删除授权
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid'");

        // 删除推送
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_alarmlist WHERE deviceid='$deviceid'");

        // 删除配置
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");

        // 删除预置点
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_preset WHERE deviceid='$deviceid'");

        // 删除设备联系人
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_contact WHERE deviceid='$deviceid'");

        // 删除433设备
        $this->db->query('DELETE FROM '.API_DBTABLEPRE."device_sensor WHERE uid=$uid AND deviceid='$deviceid'");
        $this->db->query('UPDATE '.API_DBTABLEPRE.'sensor a LEFT JOIN '.API_DBTABLEPRE."device_sensor b ON a.sensorid=b.sensorid SET a.uid=0 WHERE a.uid=$uid AND ISNULL(b.deviceid)");

        // 删除设备播放
        $this->db->query('DELETE FROM '.API_DBTABLEPRE."device_mediaplay WHERE deviceid='$deviceid'");

        // 删除企业设备
        $this->db->query('DELETE FROM '.API_DBTABLEPRE."device_org WHERE deviceid='$deviceid'");

        // 渠道设备处理
        $pcheck = $this->db->fetch_first("SELECT d.partner_id FROM ".API_DBTABLEPRE."device_partner d LEFT JOIN ".API_DBTABLEPRE."partner p on d.partner_id=p.partner_id WHERE d.deviceid='$deviceid' AND p.update_partner_device>0");
        if($pcheck) {
            $this->db->query('DELETE FROM '.API_DBTABLEPRE."device_partner WHERE deviceid='$deviceid'");
        }

        // 删除AI数据
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'ai_event WHERE deviceid='.$deviceid);
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'ai_statistics_device WHERE deviceid='.$deviceid);

        // 删除签到机
        $sim = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."sim WHERE deviceid='$deviceid'");
        if($simid) {
            $this->base->load('sim');
            $_ENV['sim']->drop($sim);
        }

        return TRUE;
    }

    function clear_cvr($deviceid, $serverid, $connectionid) {
        if(!$deviceid || !$serverid || !$connectionid) {
            return -1;
        }

        $list = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='".$deviceid."' AND serverid='".$serverid."' AND connectionid='".$connectionid."' AND (wtstatus=0 OR wtstatus=1) AND delstatus=0");
        foreach($list as $cvr) {
            if($cvr['wtstatus'] == 0) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus='-1' WHERE cvrid='".$cvr['cvrid']."'");
            }
            if($cvr['wtstatus'] == 1) {
                $sum = $this->get_cvr_sum($cvr['tableid'], $cvr['cvrid']);
                if($sum) {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus='2', starttime='".$sum['starttime']."', endtime='".$sum['endtime']."', duration='".$sum['duration']."', size='".$sum['size']."' WHERE cvrid='".$cvr['cvrid']."'");
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus='-1' WHERE cvrid='".$cvr['cvrid']."'");
                }
            }
        }
    }

    function get_cvr_sum($tableid, $cvrid) {
        if(!$tableid || !$cvrid) return array();
        $file = $this->db->fetch_first("SELECT min(starttime) as starttime, max(endtime) as endtime, sum(size) as size FROM ".API_DBTABLEPRE."device_cvr_file_".$tableid." WHERE cvrid=".$cvrid." AND delstatus=0");
        if(!$file) return array();
        $duration = $file['endtime'] - $file['starttime'];
        if($duration <= 0) return array();
        return array('starttime'=>$file['starttime'], 'endtime'=>$file['endtime'], 'duration'=>$duration, 'size'=>$file['size']);
    }

    function upload($deviceid, $uid, $cvrid, $sequence_no, $starttime, $endtime, 
        $duration, $pathname, $filename, $size, $thumbnail=0) {
        if(!$deviceid || !$uid || !$cvrid || !$endtime || !$duration) {
            return -1;
        }   

        $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE cvrid='".$cvrid."'");
        if($cvr) {
            if(($deviceid != $cvr['deviceid']) || ($uid != $cvr['uid']))
                return -1;

            if($cvr['starttime'] > 0 && ($starttime > 0 && $starttime < $cvr['starttime']) && ($cvr['starttime'] - $starttime > 10 * $duration)) {
                return -1;
            }

            if($cvr['endtime'] > 0 && ($endtime > 0 && $endtime > $cvr['endtime']) && ($endtime - $cvr['endtime'] > 10 * $duration)) {
                return -1;
            }

            $stime = ($cvr['starttime'] == 0 || ($starttime > 0 && $starttime < $cvr['starttime']))?$starttime:$cvr['starttime'];
            $etime = ($endtime > 0 && $endtime > $cvr['endtime'])?$endtime:$cvr['endtime'];

            $tablename = API_DBTABLEPRE."device_cvr_file_".$cvr['tableid'];

            $this->db->query("INSERT INTO ".$tablename." SET `cvrid`='".$cvrid."', `deviceid`='".$deviceid."', `uid`='".$uid."', `storageid`='".$cvr['storageid']."', `sequence_no`='".$sequence_no."', `starttime`='".$starttime."', `endtime`='".$endtime."', `duration`='".$duration."', `pathname`='".$pathname."', `filename`='".$filename."', `size`='".$size."', `thumbnail`='".($thumbnail?1:0)."'");
            if($this->db->error()) {
                return -1;
            }
            $fileid = $this->db->insert_id();

            $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET `filenum`=`filenum`+1, `starttime`='".$stime."', `endtime`='".$etime."', `duration`=`duration`+".$duration.", `size`=`size`+".$size." WHERE cvrid='".$cvrid."'");
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_table SET `filenum`=`filenum`+1, `size`=`size`+".$size." WHERE tableid='".$cvr['tableid']."'");

            if($cvr['wtstatus'] == 0) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus=1 WHERE cvrid='".$cvrid."'");
            } else if($cvr['wtstatus'] == -1) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus=2 WHERE cvrid='".$cvrid."'");
            }

            return $fileid;
        }
        return -1;   
    }

    function playlist($device, $params, $starttime, $endtime, $type) {
        if(!$starttime || !$endtime)
            return false;
        
        $connect_type = $device ? $device['connect_type'] : API_BAIDU_CONNECT_TYPE;
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if (!$client)
                return false;

            $ret = $client->playlist($device, $params, $starttime, $endtime, $type);
            if (!$ret)
                return false;

            return $ret;
        } else {
            $deviceid = $device['deviceid'];
            $uid = $device['uid'];

            $where = 'WHERE deviceid='.$deviceid.' AND uid='.$uid;
            $where .=' AND endtime>='.$starttime.' AND starttime<='.$endtime;
            $where .=' AND filenum>0 AND delstatus=0';
            $orderby = ' ORDER BY starttime ASC';

            $list = array();
            $count = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_cvr_list $where");
            if($count) {
                $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list $where $orderby");
                foreach($data as $value) {
                    $cvr = array(($value['starttime']<$starttime?$starttime:$value['starttime']), ($value['endtime']>$endtime?$endtime:$value['endtime']), $value['cvr_type']);
                    $list[] = $cvr;
                }
            }

            if(!$list)
                return false;

            $result = array();
            $result['stream_id'] = $device['stream_id'];
            $result['results'] = $list;
            return $result;
        }
    }

    function vod($device, $params, $starttime, $endtime) {
        if(!$starttime || !$endtime)
            return false;
        
        $connect_type = $device ? $device['connect_type'] : API_BAIDU_CONNECT_TYPE;
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $m3u8 = $client->vod($device, $params, $starttime, $endtime);
            if(!$m3u8)
                return false;

            $filename = $deviceid.'.m3u8';
            ob_end_clean();
            header('Content-type: application/vnd.apple.mpegurl');
            header('Content-Disposition: attachment; filename='.$filename);
            echo $m3u8;
            exit();
        } else {
            $deviceid = $device['deviceid'];
            $uid = $device['uid'];

            $where = 'WHERE deviceid='.$deviceid.' AND uid='.$uid;
            $where .=' AND endtime>='.$starttime.' AND starttime<='.$endtime;
            $where .=' AND filenum>0 AND delstatus=0';
            $orderby = ' ORDER BY starttime ASC';

            $list = $files = array();
            $media_sequence = $targetduration = -1;
            $cybertranduration = 0;
            $count = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_cvr_list $where");
            if($count) {
                $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list $where $orderby");
                foreach($data as $value) {
                    $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_cvr_file_".$value['tableid']." WHERE  filetype=0 AND cvrid=".$value['cvrid']." AND delstatus=0 AND endtime>=".$starttime." AND starttime<=".$endtime." ORDER BY starttime ASC");
                    foreach($query as $record) {
                        $filename = $deviceid.'-'.$record['sequence_no'].'.ts';
                        $params = array('uid'=>$device['uid'], 'type'=>'video', 'container'=>$record['pathname'], 'object'=>$record['filename'], 'filename'=>$filename, 'device' => $device);
                        $url = $this->base->storage_temp_url($record['storageid'], $params);
                        if($url) {
                            if($media_sequence < 0) $media_sequence = $record['sequence_no'];
                            $targetduration = ($record['duration']>$targetduration)?$record['duration']:$targetduration;
                            $cybertranduration += $record['duration'];
                            $file = array(
                                'sequence_no'=>$record['sequence_no'],
                                'duration'=>$record['duration'],
                                'url'=>$url
                                );
                            $files[] = $file;
                        }
                    }
                }

                if($targetduration < 0)
                    return array();

                // TODO: maybe need to take an around value
                $targetduration += 1;

                $m3u8 = "#EXTM3U\n";
                $m3u8 .= "#EXT-X-TARGETDURATION:".$targetduration."\n";
                $m3u8 .= "#EXT-X-MEDIA-SEQUENCE:".$media_sequence."\n";
                $m3u8 .= "#EXT-X-DISCONTINUITY\n";
                $m3u8 .= "#EXT-X-CYBERTRANDURATION:".$cybertranduration."\n";
                foreach($files as $f) {
                    $m3u8 .= "#EXTINF:".$f['duration'].",\n";
                    $m3u8 .= $f['url']."\n";
                }
                $m3u8 .= "#EXT-X-ENDLIST\n";

                $filename = $deviceid.'.m3u8';
                ob_end_clean();
                header('Content-type: application/vnd.apple.mpegurl');
                header('Content-Disposition: attachment; filename='.$filename);
                echo $m3u8;
                exit();
            }

            return false;
        }
    }

    function vodseek($device, $params, $time) {
        if(!$time)
            return false;
        
        $connect_type = $device ? $device['connect_type'] : API_BAIDU_CONNECT_TYPE;
        if ($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if (!$client)
                return false;

            $ret = $client->vodseek($device, $params, $time);
            if (!$ret)
                return false;

            return $ret;
        } else {
            $deviceid = $device['deviceid'];
            $uid = $device['uid'];

            $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='".$deviceid."' AND uid='".$uid."' AND starttime<='".$time."' AND ((wtstatus=2 AND endtime>='".$time."') OR (wtstatus=1 AND endtime=0)) AND delstatus=0 ORDER BY starttime DESC");

            
            if($cvr) {
                $file = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_file_".$cvr['tableid']." WHERE cvrid='".$cvr['cvrid']."' AND starttime<='".$time."' AND endtime>='".$time."' AND delstatus=0 ORDER BY starttime DESC");
            }

            if(!$file)
                return false;

            $result = array();
            $result['start_time'] = $file['starttime'];
            $result['end_time'] = $file['endtime'];
            $result['t'] = $time;
            $result['deviceid'] = $deviceid;
            return $result;
        }
    }

    function drop_cvr($deviceid, $uid, $starttime, $endtime) {
        if(!$deviceid || !$uid || !$starttime || !$endtime)
            return FALSE;

        $where = 'WHERE deviceid='.$deviceid.' AND uid='.$uid;
        $where .=' AND starttime='.$starttime.' AND endtime='.$endtime;
        $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list $where");
        if($cvr) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_file_".$cvr['tableid']." SET delstatus=1 WHERE cvrid=".$cvr['cvrid']);
            $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_cvr_list WHERE cvrid=".$cvr['cvrid']);
        }
        return TRUE;
    }

    function get_thumbnail($device, $params, $starttime, $endtime, $latest) {
        if(!$device)
            return false;
        
        $connect_type = $device ? $device['connect_type'] : API_BAIDU_CONNECT_TYPE;
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;
            
            $ret = $client->thumbnail($device, $params, $starttime, $endtime, $latest);
            if (!$ret)
                return false;

            return $ret;
        } else {
            if(!$device || !$device['deviceid'] || !$device['uid'] || !$device['cvr_tableid'])
                return false;

            $deviceid = $device['deviceid'];
            $uid = $device['uid'];
            $cvr_tableid = $device['cvr_tableid'];
            
            if(!$latest && (!$starttime || !$endtime))
                return false;
            
            if($latest) {
                $starttime = $this->base->time - 3600;
                $endtime = $this->base->time;
            }

            $table = API_DBTABLEPRE.'device_cvr_file_'.$cvr_tableid;
            $where = 'WHERE filetype=1 AND deviceid='.$deviceid.' AND uid='.$uid;
            $where .=' AND starttime>='.$starttime.' AND starttime<='.$endtime;
            $where .=' AND delstatus=0';
            $orderby = ' ORDER BY starttime ASC';

            $list = array();
            $count = $this->db->result_first("SELECT count(*) FROM $table $where");
            if($count) {
                $data = $this->db->fetch_all("SELECT * FROM $table $where $orderby");
                foreach($data as $value) {
                    $params = array('uid'=>$device['uid'], 'type'=>'thumbnail', 'container'=>$value['pathname'], 'object'=>$value['filename'], 'device' => $device);
                    $url = $this->base->storage_temp_url($value['storageid'], $params);
                    if($url) {
                        $list[] = array(
                            'time' => intval($value['starttime']), 
                            'url' => $url
                        );
                    }
                }
            }

            if($latest && !$list) {
                $list[] = array(
                    'time' => $this->base->time, 
                    'url' => $this->get_device_thumbnail($device)
                );
            }
            
            if(!$list) 
                return false;
            
            $result = array();
            $result['count'] = count($list);
            $result['list'] = $list;
            return $result;
        }
    }

    function clip($device, $starttime, $endtime, $name, $client_id, $uk=0) {
        if(!$device || !$device['deviceid'] || !$starttime || !$endtime || $name==='' || !$client_id)
            return false;

        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->clip($device, $starttime, $endtime, $name, $client_id, $uk);
        } else {
            // TODO: add clip
            return false;
        }
    }

    function infoclip($uid, $type/*reserved*/, $clipid) {
        if(!$uid)
            return false;

        $deviceid = $this->db->result_first('SELECT deviceid FROM '.API_DBTABLEPRE."device_clip where uid=$uid AND clipid=$clipid");
        if ($deviceid === false)
            return false;

        if ($deviceid == 0) { //只有baidu同步才会产生deviceid==0的剪辑
            $connect_type = API_BAIDU_CONNECT_TYPE;
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;
            //@todo swain
            return $client->infoclip($uid, $type, $clipid);
        } else {
            $device = $this->get_device_by_did($deviceid);
            if (!$device)
                return false;
            $connect_type = $device['connect_type'];
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    return false;
                $uk = $device['uid'];
                return $client->infoclip($uid, $type, $clipid, $uk);
            } else {
                return false;
            }
        }        
    }

    function listdeviceclip($device, $page = 1, $count = 10) {
        if(!$device)
            return false;

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->listdeviceclip($device, $page, $count);
        } else {
            // TODO: add clip
            return false;
        }
    }

    function listuserclip($uid, $support_type, $page = 1, $count = 10, $client_id) {
        if (!$uid)
            return false;

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        foreach($support_type as $connect_type) {
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    continue;

                //兼容企业用户 获取设备uid
                $device_uid = $this->db->fetch_all('SELECT a.uid FROM '.API_DBTABLEPRE."device a LEFT JOIN ".API_DBTABLEPRE."device_clip b on b.deviceid=a.deviceid WHERE b.uid=$uid GROUP BY b.deviceid");
                if(count($device_uid) > 0){
                    foreach ($device_uid as $uk) {
                        $ret = $client->listuserclip($uid, $page, $count, $client_id, $uk['uid']);
                        if ($ret === false)
                            continue;
                    }
                }
            }
        }

        $limit = (($page - 1) * $count).','.$count;
        $cliplist = $this->db->fetch_all('SELECT clipid,deviceid,name,status,progress,storageid FROM '.API_DBTABLEPRE."device_clip WHERE uid=$uid AND delstatus=0 ORDER BY dateline DESC LIMIT $limit");
        if ($cliplist === false)
            return false;

        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE."device_clip WHERE uid=$uid AND delstatus=0");
        $pages = $this->base->page_get_page($page, $count, $total);
        
        $result = array();
        $list = array();
        foreach ($cliplist as $item) {
            $list[] = array(
                'clipid' => strval($item['clipid']),
                'deviceid' => $item['deviceid'],
                'name' => $item['name'],
                'status' => intval($item['status']),
                'progress' => intval($item['progress']),
                'storageid' => intval($item['storageid'])
            );
        }
        $result['page'] = $pages['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    function update_server($deviceid, $old, $new) {
        $oldserver = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE serverid=".$old);
        if($oldserver) {
            $devicenum = ($oldserver['devicenum'] > 0) ? ($oldserver['devicenum']-1) : 0;
            $this->db->query("UPDATE ".API_DBTABLEPRE."server SET devicenum=".$devicenum." WHERE serverid=".$old);
        }
        $newserver = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE serverid=".$new);
        if($newserver) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET lastserverid='$serverid' WHERE deviceid='$deviceid'");
            $this->db->query("UPDATE ".API_DBTABLEPRE."server SET devicenum=devicenum+1 WHERE serverid=".$new);
        }
    }

    function update_connect($deviceid, $ip, $serverid, $connectionid) {
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET lastupdate='".$this->base->time."', lastconnectip='$ip', lastconnectdate='".$this->base->time."', lastserverid='$serverid', lastconnectionid='$connectionid' WHERE deviceid='$deviceid'");
        return $this->get_device_by_did($deviceid);
    }

    function update_play($deviceid, $ip) {
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET isplay=1, playnum=playnum+1, lastplaydate='".$this->base->time."' WHERE deviceid='$deviceid'");
        return $this->get_device_by_did($deviceid);
    }

    function update_stop($device) {
        if($device['playnum'] > 1) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET isplay=1, playnum=playnum-1 WHERE deviceid='".$device['deviceid']."'");
        } else {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET isplay=0, playnum=0 WHERE deviceid='".$device['deviceid']."'");
        }
        return;
    }

    function start_cvr($deviceid, $uid, $tableid, $storageid, $cvr_type, $starttime, $serverid, $connectionid) {
        if(!$deviceid || !$tableid || !$storageid || !$serverid || !$connectionid) return -1;
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_cvr_list SET `tableid`='".$tableid."', `deviceid`='".$deviceid."', `uid`='".$uid."',  `cvr_type`='".$cvr_type."', `storageid`='".$storageid."', `serverid`='".$serverid."', `connectionid`='".$connectionid."', `starttime`='".$starttime."'", 'SILENT');
        if($this->db->error()) return -1;
        return $this->db->insert_id();
    }

    function stop_cvr($cvrid) {
        if(!$cvrid) return -1;
        $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE cvrid='".$cvrid."'");
        if($cvr) {
            if($cvr['wtstatus'] == 0) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus='-1' WHERE cvrid='".$cvr['cvrid']."'");
            }
            if($cvr['wtstatus'] == 1) {
                /*
                $sum = $this->get_cvr_sum($cvr['tableid'], $cvr['cvrid']);
                if($sum) {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus='2', starttime='".$sum['starttime']."', endtime='".$sum['endtime']."', duration='".$sum['duration']."', size='".$sum['size']."' WHERE cvrid='".$cvr['cvrid']."'");
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus='-1' WHERE cvrid='".$cvr['cvrid']."'");
                }
                */
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET wtstatus='2' WHERE cvrid='".$cvr['cvrid']."'");
            }
        }
        return 1;
    }

    function gen_cvr_tableid($deviceid) {
        if(!$deviceid) return 0;

        $max = $this->db->result_first("SELECT max(tableid) FROM ".API_DBTABLEPRE."device_cvr_table");
        $max = $max?$max:0;

        if($max < CVR_FILE_TABLE_MAXCOUNT) {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_cvr_table SET `devicenum`=0");
            $create_tableid = $this->db->insert_id();
            $check = $this->db->fetch_first("SHOW TABLES IN `".API_DBNAME."` LIKE '"."%cvr_file_".$create_tableid."'");
            if($check) {
                $devicenum = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device WHERE `lastserverid`=".$create_tableid);
                $filenum = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_cvr_file_".$create_tableid);
                $size = $this->db->result_first("SELECT sum(size) FROM ".API_DBTABLEPRE."device_cvr_file_".$create_tableid);
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_table SET `devicenum`='".$devicenum."', `filenum`='".$filenum."', `size`='".$size."' WHERE tableid=".$create_tableid);
            } else {
                $create_table_sql = "CREATE TABLE ".API_DBTABLEPRE."device_cvr_file_".$create_tableid." (
                    `fileid` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `filetype` tinyint(1) NOT NULL DEFAULT '0' COMMENT '文件类型, 0视频1截图',
                    `deviceid` varchar(40) NOT NULL DEFAULT '',
                    `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
                    `cvrid` int(10) unsigned NOT NULL DEFAULT '0',
                    `storageid` smallint(6) unsigned NOT NULL DEFAULT '0',
                    `sequence_no` varchar(20) NOT NULL DEFAULT '0',
                    `starttime` int(10) unsigned NOT NULL DEFAULT '0',
                    `endtime` int(10) unsigned NOT NULL DEFAULT '0',
                    `pathname` varchar(100) NOT NULL DEFAULT '',
                    `filename` varchar(100) NOT NULL DEFAULT '',
                    `size` int(10) unsigned NOT NULL DEFAULT '0',
                    `duration` smallint(6) unsigned NOT NULL DEFAULT '0',
                    `ispart` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否部分存储',
                    `startpos` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '存储开始位置',
                    `endpos` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '存储结束位置',
                    `thumbnail` tinyint(1) NOT NULL DEFAULT '0',
                    `thumbnailtime` int(10) unsigned NOT NULL DEFAULT '0',
                    `delstatus` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除状态',
                    PRIMARY KEY (`fileid`),
                    KEY `storageid` (`storageid`,`pathname`,`delstatus`),
                    KEY `uid` (`uid`, `deviceid`),
                    KEY `starttime` (`starttime`),
                    KEY `endtime` (`endtime`)
                    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

                $this->db->query($create_table_sql, 'SILENT');
                if($this->db->error()) return 0;
            }
        }

        // TODO: choose table mechanism
        $table = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_table ORDER BY devicenum ASC, filenum ASC, tableid DESC LIMIT 1");
        if($table) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_table SET `devicenum`=`devicenum`+1 WHERE tableid=".$table['tableid']);
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `cvr_tableid`='".$table['tableid']."' WHERE deviceid='$deviceid'");
            return $table['tableid'];
        }

        return 0;
    }

    function gen_cvr_storageid($appid, $deviceid, $default=0) {
        if(!$appid || !$deviceid) return 0;

        $storageid = $default;

        // 是否测试设备
        $isdev = $this->isdev($deviceid);
        if($isdev && $isdev['storageid']) {
            $storageid = $isdev['storageid'];
        }
        // 应用默认存储
        if(!$storageid) {
            $storageid = $this->db->result_first("SELECT defaultstorageid FROM ".API_DBTABLEPRE."oauth_app WHERE appid=".$appid);
        }
        // 选择存储
        if(!$storageid) {
            $storageid = $this->db->result_first("SELECT storageid FROM ".API_DBTABLEPRE."storage_service WHERE appid=".$appid." AND status>0 ORDER BY storageid ASC LIMIT 1");
        }

        if(!$storageid || !$this->base->get_storage_service($storageid)) {
            return 0;
        }

        if($storageid != $default) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `cvr_storageid`='".$storageid."' WHERE deviceid='$deviceid'");
        }

        return $storageid;
    }

    function update_cvr($deviceid, $cvr_type, $cvr_day, $cvr_end_time) {
        if(!$deviceid) return;
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `cvr_type`='".intval($cvr_type)."', `cvr_day`='".intval($cvr_day)."', `cvr_end_time`='".intval($cvr_end_time)."' WHERE deviceid='$deviceid'");
        return;
    }

    function _check_token_sign($sign, $deviceid, $appid, $clientid) {
        if (!$sign || !$deviceid || !$appid || !$clientid)
            return array();

        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE type=1");
        if (!$client || $sign !== md5($deviceid.$appid.$clientid.$client['client_secret']))
            return array();

        // log记录 ----------- init params
        log::$appid = $appid;
        log::$client_id = $clientid;

        return $client;
    }

    function _check_sign($sign, $expire) {
        if (!$sign || !$expire)
            return array();

        if ($expire < $this->base->time)
            return array();

        $sarr = explode('-', $sign);
        if (!is_array($sarr) || count($sarr) !== 3)
            return array();

        $appid = $sarr[0];
        $ak = $sarr[1];
        $rsign = $sarr[2];

        $sk = $this->db->result_first("SELECT client_secret FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$ak'");
        if (!$sk || $rsign !== md5($appid.$expire.$ak.$sk))
            return array();

        // log记录 ----------- init params
        log::$appid = $appid;
        log::$client_id = $ak;

        return array('appid'=>$appid, 'client_id'=>$ak);
    }

    function verify_thumbnail($deviceid, $cvrid, $fileid) {
        if(!$deviceid || !$cvrid || !$fileid)
            return false;

        $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='$deviceid' AND cvrid='$cvrid'");
        if(!$cvr)
            return false;

        $table = API_DBTABLEPRE."device_cvr_file_".$cvr['tableid'];
        $file = $this->db->fetch_first("SELECT * FROM ".$table." WHERE cvrid='$cvrid' AND fileid='$fileid'");
        if(!$file || !$file['thumbnail'])
            return false;

        return true;
    }

    function get_thumbnail_info($device, $cvrid, $fileid, $local=false) {
        if(!$device || !$device['deviceid'] || !$cvrid || !$fileid)
            return array();

        $deviceid = $device['deviceid'];

        $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='$deviceid' AND cvrid='$cvrid'");
        if(!$cvr)
            return array();

        $table = API_DBTABLEPRE."device_cvr_file_".$cvr['tableid'];
        $file = $this->db->fetch_first("SELECT * FROM ".$table." WHERE cvrid='$cvrid' AND fileid='$fileid'");
        if(!$file || !$file['thumbnail'])
            return array();

        $cvr_storage = $this->base->get_storage_service($file['storageid']);
        $params = array('uid'=>$file['uid'], 'type'=>'thumbnail', 'container'=>$file['pathname'], 'object'=>$file['filename'], 'local'=>$local, 'device'=>$device);  
        $url = $this->base->storage_temp_url($file['storageid'], $params);
        $info = array(
            'storage_temp_url' => $url,
            'storageid' => "".$cvr_storage['storageid'],
            'storage_type' => $cvr_storage['storage_type'],
            'storage_config' => $cvr_storage['storage_config']
        );
        return $info;
    }

    function thumbnail_upload($deviceid, $cvrid, $fileid, $thumbnail_path, $thumbnail_file, $thumbnail_size) {
        if(!$deviceid || !$cvrid || !$fileid)
            return false;

        $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='$deviceid' AND cvrid='$cvrid'");
        if(!$cvr)
            return false;

        $table = API_DBTABLEPRE."device_cvr_file_".$cvr['tableid'];
        $file = $this->db->fetch_first("SELECT * FROM ".$table." WHERE cvrid='$cvrid' AND fileid='$fileid'");
        if(!$file || !$file['thumbnail'])
            return false;

        $this->db->query("INSERT INTO ".$table." SET `filetype`='1', `cvrid`='".$cvrid."', `deviceid`='".$deviceid."', `uid`='".$file['uid']."', `storageid`='".$file['storageid']."', `starttime`='".$file['starttime']."', `pathname`='".$thumbnail_path."', `filename`='".$thumbnail_file."', `size`='".$thumbnail_size."'");
        if($this->db->error()) {
            return false;
        }
        $fileid = $this->db->insert_id();

        $this->db->query("UPDATE ".$table." SET `thumbnailtime`='".$this->base->time."' WHERE `fileid`='".$file['fileid']."'");
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `cvr_thumbnail`='".$fileid."' WHERE `deviceid`='".$deviceid."'");

        $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_list SET `filenum`=`filenum`+1, `size`=`size`+".$thumbnail_size." WHERE cvrid='".$cvrid."'");
        $this->db->query("UPDATE ".API_DBTABLEPRE."device_cvr_table SET `filenum`=`filenum`+1, `size`=`size`+".$thumbnail_size." WHERE tableid='".$cvr['tableid']."'");

        return true;
    }

    function setting($device, $type='info', $fs='') {
        if(!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];

        $allows = $fileds = array();

        // info
        $allows['info'] = array('intro', 'model', 'nameplate', 'platform', 'resolution', 'sn', 'mac', 'wifi', 'ip', 'sig', 'firmware', 'firmdate', 'channel', 'media', 'volume');

        // status
        $allows['status'] = array('power', 'light', 'invert', 'audio', 'nc', 'encrypt', 'localplay', 'scene', 'nightmode', 'exposemode', 'bitlevel', 'bitrate', 'maxspeed', 'minspeed');

        // email
        $allows['email'] = array('mail_to', 'mail_cc', 'mail_server', 'mail_port', 'mail_from', 'mail_user', 'mail_passwd');

        // power
        $allows['power'] = array('power_cron', 'power_start', 'power_end', 'power_repeat');

        // cvr
        $allows['cvr'] = array('cvr', 'cvr_cron', 'cvr_start', 'cvr_end', 'cvr_repeat');

        // alarm
        $allows['alarm'] = array('alarm_push', 'alarm_mail', 'alarm_audio', 'alarm_audio_level', 'alarm_move', 'alarm_move_level', 'alarm_zone', 'alarm_cron', 'alarm_start', 'alarm_end', 'alarm_repeat', 'alarm_count', 'alarm_interval');

        // capsule
        $allows['capsule'] = array('temperature', 'humidity');

        // storage
        $allows['storage'] = array('nas_status', 'nas', 'sdcard_status', 'sdcard', 'storage_type', 'storage_total_size', 'storage_free_size', 'storage_read_write', 'storage_start_time', 'storage_end_time', 'storage_bitmode', 'd.timezone');

        // plat
        $allows['plat'] = array('plat', 'plat_move', 'plat_type', 'plat_rotate', 'plat_rotate_status', 'plat_track_status');

        // bit
        $allows['bit'] = array('bitrate', 'bitlevel', 'framerate', 'storage_bitrate', 'storage_bitlevel', 'storage_framerate', 'audio');

        // bw
        $allows['bw'] = array('maxspeed', 'minspeed');

        // night
        $allows['night'] = array('scene', 'nightmode', 'exposemode');
        
        // volume
        $allows['volume'] = array('volume', 'media', 'volume_talk', 'volume_media');

        // init
        $allows['init'] = array('model', 'firmware', 'firmdate', 'ip', 'mac', 'cloudserver', 'resolution', 'sn', 'sig', 'wifi', 'temperature', 'humidity', 'plat', 'plat_move', 'plat_type', 'plat_rotate', 'plat_rotate_status', 'plat_track_status', 'alarm_push', 'light', 'invert', 'cvr', 'bitrate', 'audio', 'timezone', 'maxspeed', 'minspeed', 'nightmode', 'exposemode', 'alarm_audio', 'alarm_audio_level', 'alarm_move', 'alarm_move_level', 'alarm_zone', 'alarm_mail', 'mail_from', 'mail_to', 'mail_cc', 'mail_server', 'mail_port', 'mail_user', 'mail_passwd');

        // aiface
        $allows['ai'] = array('ai', 'ai_face_detect', 'ai_face_blur', 'ai_body_count');

        // 上传服务器与人脸设置
        $allows['ai_set'] = array('ai_upload_port', 'ai_upload_host', 'ai_face_port', 'ai_face_host', 'ai_api_key', 'ai_secret_key', 'ai_face_ori', 'ai_face_pps', 'ai_face_position', 'ai_face_frame', 'ai_face_min_width', 'ai_face_min_height', 'ai_face_reliability', 'ai_face_retention', 'ai_face_group_id', 'ai_lan', 'ai_face_type', 'ai_face_group_type', 'ai_face_detect_zone', 'ai_face_storage');

        // pano
        $allows['pano'] = array('pano', 'pano_config');

        $allows['server'] = array('api_server_host', 'api_server_port');

        if(in_array($type, array('info', 'status', 'volume', 'power', 'email', 'cvr', 'alarm', 'capsule', 'storage', 'plat', 'bit', 'bw', 'night', 'init', 'ai', 'ai_set', 'pano', 'server'))) {
            if($type == 'status') {
                $fileds = array_merge($allows['status'], $allows['email']);
            } else {
                $fileds = $allows[$type];
            }
        } else if($type == 'all') {
            $fileds = array_merge($allows['info'], $allows['status'], $allows['email'], $allows['power'], $allows['cvr'], $allows['alarm'], $allows['capsule'], $allows['storage'], $allows['plat'], $allows['init'], $allows['ai']);
        }

        if(!$fileds)
            return false;

        /*
        if ($type != 'init' && $this->_get_device_firmware($deviceid) >= 6123020 && $this->_check_issubarray($fileds, $allows['init'])) {
            $type = 'init';
        }
        */
        if(!$this->sync_setting($device, $type)) {
            $this->base->log('setting', 'sync setting failed.');
            return false;
        }

        $fileds_str = implode(',', $fileds);
        $settings = $this->get_device_by_did($deviceid, $fileds_str, true);
        $settings = $this->_format_settings($device, $allows, $type, $settings);
        return $settings;
    }

    function getalarmzonesetting($deviceid){
        if(!$deviceid)
            return false;
        $fileds_str = 'df.params_alarm_zone';
        $settings = $this->get_device_by_did_bak($deviceid, $fileds_str, true);
        $settings = $this->_format_settings($device, $allows, $type, $settings);
        return $settings;
    }

    function _check_issubarray($arr_sub, $arr_main) {
        $b = true;
        foreach ($arr_sub as $item) {
            if (!in_array($item, $arr_main)) {
                echo $item;
                print_r($arr_main);
                $b = false;
                break;
            }
        }
        return $b;
    }

    function _get_device_firmware($deviceid) {
        $fileds = $this->db->fetch_first("SELECT firmware FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='".$deviceid."'");
        if(!$fileds['firmware']) {
            return 0;
        } else {
            $arr = explode("_", $fileds['firmware']);
            return floatval($arr[0]) * 1000 * 1000 + intval($arr[2]);
        }
    }

    function _check_fileds($deviceid) {
        $fileds = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='".$deviceid."'");
        if(!$fileds) {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."devicefileds SET `deviceid`='".$deviceid."'");
            $fileds = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='".$deviceid."'");
        }
        return $fileds;
    }

    function sync_setting($device, $type, $set=0/*0:setting, 1:updatesetting*/) {
        if(!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $fileds = $this->_check_fileds($deviceid);
        if(!$fileds)
            return false;

        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $setting_sync = $client->_check_setting_sync($device);
        $commands = array();

        // sync_info
        if(in_array($type, array('info', 'all')) || ($type === 'volume' && !$fileds['params_info']) || ($type === 'ai' && $fileds['ai']== '-2')) {
            $commands[] = array('type' => 'info', 'command' => '{"main_cmd":74,"sub_cmd":3,"param_len":0,"params":0}', 'response' => 1);
            $commands[] = array('type' => 'debug', 'command' => '{"main_cmd":74,"sub_cmd":48,"param_len":0,"params":0}', 'response' => 1);
        }

        // sync_storage
        if((in_array($type, array('storage'))) || (in_array($type, array('status', 'bit', 'bits', 'all')) && !$fileds['params_storage'])){
            $commands[] = array('type' => 'storage', 'command' => '{"main_cmd":74,"sub_cmd":1,"param_len":0,"params":0}', 'response' => 1);
        }

        // sync_capsule
        if($type === 'capsule') {
            $commands[] = array('type' => 'capsule', 'command' => '{"main_cmd":74,"sub_cmd":74,"param_len":0,"params":0}', 'response' => 1);
        }

        // sync_plat
        if($type === 'plat') {
            $commands[] = array('type' => 'plat', 'command' => '{"main_cmd":74,"sub_cmd":66,"param_len":0,"params":0}', 'response' => 1);
        }

        // sync_init
        if($type === 'init') {
            $commands[] = array('type' => 'init', 'command' => '{"main_cmd":74,"sub_cmd":76,"param_len":0,"params":0}', 'response' => 1);
        }
        
        // firmware
        if(in_array($type, array('firmware', 'cron'))) {
            $commands[] = array('type' => 'debug', 'command' => '{"main_cmd":74,"sub_cmd":48,"param_len":0,"params":0}', 'response' => 1);
        }
        
        // sync_volume
        if($type === 'volume') {
            if(!($fileds['params_info'] && !$fileds['volume'])) {
                $commands[] = array('type' => 'volume_talk', 'command' => '{"main_cmd":74,"sub_cmd":87,"param_len":0,"params":0}', 'response' => 1);
                
                if(!($fileds['params_info'] && !$fileds['media'])) {
                    $commands[] = array('type' => 'volume_media', 'command' => '{"main_cmd":74,"sub_cmd":86,"param_len":0,"params":0}', 'response' => 1);
                }
            }
        }

        //aiface
        if($type === 'ai') {
            // if($fileds['ai'] == '-2' || $fileds['ai_face_detect'] == '-2' || $fileds['ai_face_blur'] == '-2' || $fileds['ai_body_count'] == '-2') {
                $commands[] = array('type' => 'ai', 'command' => '{"main_cmd":74,"sub_cmd":89,"param_len":0,"params":0}', 'response' => 1);
            // }
        }

        //pano
        if($type === 'pano') {
            if($fileds['pano'] == '-2') {
                $commands[] = array('type' => 'debug', 'command' => '{"main_cmd":74,"sub_cmd":48,"param_len":0,"params":0}', 'response' => 1);
            }
            if(!$fileds['pano_config']) {
                $commands[] = array('type' => 'pano', 'command' => '{"main_cmd":74,"sub_cmd":94,"param_len":4,"params":"00000001"}', 'response' => 1);
            }
        }

        //server
        if($type === 'server') {
            $commands[] = array('type' => 'server', 'command' => '{"main_cmd":74,"sub_cmd":95,"param_len":0,"params":0}', 'response' => 1);
        }

        //上传服务器与face设置
        if($type === 'ai_set') {
            //upload_domin
            $commands[] = array('type' => 'ai_set_upload', 'command' => '{"main_cmd":74,"sub_cmd":91,"param_len":0,"params":0}', 'response' => 1);
            //ai_setting
            $commands[] = array('type' => 'ai_set_face', 'command' => '{"main_cmd":74,"sub_cmd":90,"param_len":0,"params":0}', 'response' => 1);
            //ai_setting
            $commands[] = array('type' => 'ai_set_item', 'command' => '{"main_cmd":74,"sub_cmd":92,"param_len":0,"params":0}', 'response' => 1);
            //ai_face_detect_zone
            $commands[] = array('type' => 'ai_face_detect_zone', 'command' => '{"main_cmd":74,"sub_cmd":93,"param_len":0,"params":0}', 'response' => 1);
        }

        $sync_all = false;
        $sync_config = false;

        /*废除同步时间内5分钟不再同步的机制*/
        // if($setting_sync != 0)
        {
            if($type == 'all') $sync_all = true;

            // bits
            if(in_array($type, array('bits'))) {
                if(!$fileds['params_debug']) {
                    $commands[] = array('type' => 'debug', 'command' => '{"main_cmd":74,"sub_cmd":48,"param_len":0,"params":0}', 'response' => 1);
                }
            }
            
            // sync_timezone
            if(in_array($type, array('timezone', 'all'))) {
                $firmware = $this->_get_current_firmware($device);
                if ($firmware > 7125020) {
                    if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_timezone']) || ($set == 1 && !$fileds['params_timezone'])) {
                        $commands[] = array('type' => 'timezone', 'command' => '{"main_cmd":74,"sub_cmd":9,"param_len":0,"params":0}', 'response' => 1);
                    }
                } else {
                    if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_cloud_publish']) || ($set == 1 && !$fileds['params_cloud_publish'])) {
                        $commands[] = array('type' => 'cloud_publish', 'command' => '{"main_cmd":74,"sub_cmd":40,"param_len":0,"params":0}', 'response' => 1);
                    }
                }
            }

            // sync_status
            if((in_array($type, array('storage'))) || (in_array($type, array('status', 'bit', 'bits', 'all')) && !$fileds['params_storage'])){
            $commands[] = array('type' => 'storage', 'command' => '{"main_cmd":74,"sub_cmd":1,"param_len":0,"params":0}', 'response' => 1);
        }
            if((in_array($type, array('cvr', 'status', 'all'))) || (in_array($type, array('storage')) && $fileds['storage_type']<0) ) {
                /*
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_status']) || ($set == 1 && !$fileds['params_status'])) {
                    $commands[] = array('type' => 'status', 'command' => '{"main_cmd":74,"sub_cmd":43,"param_len":0,"params":0}', 'response' => 1);
                }
                */
                $commands[] = array('type' => 'status', 'command' => '{"main_cmd":74,"sub_cmd":43,"param_len":0,"params":0}', 'response' => 1);
                $sync_config = true;
            }

            // sync_bit
            if(in_array($type, array('status', 'bit', 'bits', 'all'))) {
                if ($type == bit || ($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_bit']) || ($set == 1 && !$fileds['params_bit'])) {
                    $commands[] = array('type' => 'bit', 'command' => '{"main_cmd":74,"sub_cmd":5,"param_len":0,"params":0}', 'response' => 1);
                }
                
                // 新增帧率处理
                if ($fileds['params_bit'] && !$fileds['framerate']) {
                    $this->sync_bit($device, $fileds['params_bit']);
                }
                
                $sync_config = true;
            }

            // sync_bw
            if(in_array($type, array('status', 'bw', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_bw']) || ($set == 1 && !$fileds['params_bw'])) {
                    $commands[] = array('type' => 'bw', 'command' => '{"main_cmd":74,"sub_cmd":49,"param_len":0,"params":0}', 'response' => 1);
                }
            }

            // sync_night
            if(in_array($type, array('status', 'night', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_night']) || ($set == 1 && !$fileds['params_night'])) {
                    $commands[] = array('type' => 'night', 'command' => '{"main_cmd":74,"sub_cmd":41,"param_len":0,"params":0}', 'response' => 1);
                }
            }

            // sync_email
            if(in_array($type, array('email', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_email']) || ($set == 1 && !$fileds['params_email'])) {
                    $commands[] = array('type' => 'email', 'command' => '{"main_cmd":74,"sub_cmd":12,"param_len":0,"params":0}', 'response' => 1);
                }
            }

            // sync_cron
            if(in_array($type, array('power', 'cvr', 'alarm', 'cron', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && (!$fileds['params_cron'] || !$fileds['params_cvr_cron'])) || ($set == 1 && (!$fileds['params_cron'] || !$fileds['params_cvr_cron']))) {
                    $commands[] = array('type' => 'cron', 'command' => '{"main_cmd":74,"sub_cmd":51,"param_len":0,"params":0}', 'response' => 1);
                    $commands[] = array('type' => 'cvr_cron', 'command' => '{"main_cmd":74,"sub_cmd":6,"param_len":0,"params":0}', 'response' => 1);
                }
            }
            // sync_alarm
            if(in_array($type, array('alarm', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_debug']) || ($set == 1 && !$fileds['params_debug'])) {
                    $commands[] = array('type' => 'debug', 'command' => '{"main_cmd":74,"sub_cmd":48,"param_len":0,"params":0}', 'response' => 1);
                }
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_alarm_level']) || ($set == 1 && !$fileds['params_alarm_level'])) {
                    $commands[] = array('type' => 'alarm_level', 'command' => '{"main_cmd":74,"sub_cmd":60,"param_len":0,"params":0}', 'response' => 1);
                }
                //监控区域
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && (!$fileds['alarm_zone'] || $fileds['alarm_zone']=='-1') ) || ($set == 1 && (!$fileds['alarm_zone'] || $fileds['alarm_zone']=='-1') )) {
                    $commands[] = array('type' => 'alarm_zone', 'command' => '{"main_cmd":74,"sub_cmd":88,"param_len":0,"params":0}', 'response' => 1);
                }
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_alarm_mail']) || ($set == 1 && !$fileds['params_alarm_mail'])) {
                    $commands[] = array('type' => 'alarm_mail', 'command' => '{"main_cmd":74,"sub_cmd":16,"param_len":0,"params":0}', 'response' => 1);
                }
                if(!$this->_check_push_version($device)) {
                    if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_alarm_list']) || ($set == 1 && !$fileds['params_alarm_list'])) {
                        $commands[] = array('type' => 'alarm_list', 'command' => '{"main_cmd":72,"sub_cmd":4,"param_len":0,"params":0}', 'response' => 1);
                    }
                }
            }

            // sync_aiface
            if(in_array($type, array('ai', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['ai_body_count']) || ($set == 1 && !$fileds['ai_face_detect']) || ($set == 1 && !$fileds['ai_face_blur'])) {
                    $commands[] = array('type' => 'ai', 'command' => '{"main_cmd":74,"sub_cmd":89,"param_len":0,"params":0}', 'response' => 1);
                }
            }

            // sync_upload_domin
            if(in_array($type, array('ai_set', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && $fileds['ai_upload_port']=='-2') || ($set == 1 && $fileds['ai_upload_host']=='-2') ) {
                    $commands[] = array('type' => 'ai_set_upload', 'command' => '{"main_cmd":74,"sub_cmd":91,"param_len":0,"params":0}', 'response' => 1);
                }
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && $fileds['ai_api_key']=='-2') || ($set == 1 && $fileds['ai_secret_key']=='-2')) {
                    $commands[] = array('type' => 'ai_set_face', 'command' => '{"main_cmd":74,"sub_cmd":90,"param_len":0,"params":0}', 'response' => 1);
                }
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && $fileds['ai_lan']==-2) || ($set == 1 && $fileds['ai_face_type']==-2) || ($set == 1 && $fileds['ai_face_storage']==-2) ) {
                    $commands[] = array('type' => 'ai_set_item', 'command' => '{"main_cmd":74,"sub_cmd":92,"param_len":0,"params":0}', 'response' => 1);
                }
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && $fileds['ai_face_detect_zone']==-2) ) {
                    $commands[] = array('type' => 'ai_face_detect_zone', 'command' => '{"main_cmd":74,"sub_cmd":93,"param_len":0,"params":0}', 'response' => 1);
                }
                
            }

        }

        $this->base->log('sync setting', 'type='.$type.', count='.count($commands));
        if($commands) {
            // api request
            $ret = $client->device_batch_usercmd($device, $commands);
            if(!$ret)
                return false;

            foreach($ret as $value) {
                //$main_cmd = $this->_main_cmd($value['data']);
                $sub_cmd = $this->_sub_cmd($value['data']);
                $params = $this->_cmd_params($value['data']);
                switch($value['type']) {
                    case 'info':
                        if ($sub_cmd == 3) $params_info = $params;
                        break;

                    case 'debug':
                        if ($sub_cmd == 48) $params_debug = $params;
                        break;

                    case 'status':
                        if ($sub_cmd == 43) $params_status = $params;
                        break;
                    
                    case 'timezone':
                        if ($sub_cmd == 9) $params_timezone = $params;
                        break;
                        
                    case 'cloud_publish':
                        if ($sub_cmd == 40) $params_cloud_publish = $params;
                        break;
                        
                    case 'bit':
                        if ($sub_cmd == 5) $params_bit = $params;
                        break;

                    case 'bw':
                        if ($sub_cmd == 49) $params_bw = $params;
                        break;

                    case 'night':
                        if ($sub_cmd == 41) $params_night = $params;
                        break;

                    case 'email':
                        if ($sub_cmd == 12) $params_email = $params;
                        break;

                    case 'cron':
                        if ($sub_cmd == 51) $params_cron = $params;
                        break;

                    case 'cvr_cron':
                        if ($sub_cmd == 6) $params_cvr_cron = $params;
                        break;

                    case 'alarm_level':
                        if ($sub_cmd == 60) $params_alarm_level = $params;
                        break;

                    case 'alarm_zone':
                        if ($sub_cmd == 88) $params_alarm_zone = $params;
                        break;

                    case 'alarm_mail':
                        if ($sub_cmd == 16) $params_alarm_mail = $params;
                        break;

                    case 'alarm_list':
                        if ($sub_cmd == 4) $params_alarm_list = $params;
                        break;

                    case 'capsule':
                        if ($sub_cmd == 74) $params_capsule = $params;
                        break;

                    case 'plat':
                        if ($sub_cmd == 66) $params_plat = $params;
                        break;

                    case 'init':
                        if ($sub_cmd == 76) $params_init = $params;
                        break;

                    case 'storage':
                        if ($sub_cmd == 1) $params_storage = $params;
                        break;
                
                    case 'volume_talk':
                        if ($sub_cmd == 87) $params_volume_talk = $params;
                        break;
                        
                    case 'volume_media':
                        if ($sub_cmd == 86) $params_volume_media = $params;
                        break;

                    case 'ai':
                        if ($sub_cmd == 89) $params_face = $params;
                        break;

                    case 'ai_set_upload':
                        if ($sub_cmd == 91) $params_set_upload = $params;
                        break;

                    case 'ai_set_face':
                        if ($sub_cmd == 90) $params_set_face = $params;
                        break;

                    case 'ai_set_item':
                        if ($sub_cmd == 92) $params_set_item = $params;
                        break;

                    case 'ai_face_detect_zone':
                        if ($sub_cmd == 93) $params_ai_face_detect_zone = $params;
                        break;

                    case 'pano':
                        if ($sub_cmd == 94) $params_pano = $params;
                        break;

                    case 'server':
                        if ($sub_cmd == 95) $params_server = $params;
                        break;
                }
            }

            $this->base->log('sync setting', 'count(ret)='.count($ret));

            // sync_info
            if($params_info && $params_debug) {
                $this->base->log('sync setting', 'sync_info');
                $this->sync_info($device, $params_info, $params_debug);
            } else {
                // sync_debug
                if($params_debug) {
                    $this->base->log('sync setting', 'sync_debug');
                    $this->sync_debug($device, $params_debug);
                }
                $sync_all = false;
            }
            
            // sync_storage
            if($params_storage) {
                $this->base->log('sync setting', 'sync_storage');
                $this->sync_storage($device, $params_storage);
            } else {
                $sync_all = false;
            }

            // sync_status
            if($params_status) {
                $this->base->log('sync setting', 'sync_status');
                $this->sync_status($device, $params_status);
            } else {
                $sync_all = false;
            }
            
            // sync_timezone
            if($params_timezone) {
                $this->base->log('sync setting', 'sync_timezone');
                $this->sync_timezone($device, $params_timezone);
            } else {
                $sync_all = false;
            }
            
            // sync_cloud_publish
            if($params_cloud_publish) {
                $this->base->log('sync setting', 'sync_cloud_publish');
                $this->sync_cloud_publish($device, $params_cloud_publish);
            } else {
                $sync_all = false;
            }

            // sync_bit
            if($params_bit) {
                $this->base->log('sync setting', 'sync_bit');
                $this->sync_bit($device, $params_bit);
            } else {
                $sync_all = false;
            }

            // sync_bw
            if($params_bw) {
                $this->base->log('sync setting', 'sync_bw '.$params_bw);
                $this->sync_bw($device, $params_bw);
            } else {
                $sync_all = false;
            }

            // sync_night
            if($params_night) {
                $this->base->log('sync setting', 'sync_night');
                $this->sync_night($device, $params_night);
            } else {
                $sync_all = false;
            }

            // sync_email
            if($params_email) {
                $this->base->log('sync setting', 'sync_email');
                $this->sync_email($device, $params_email);
            } else {
                $sync_all = false;
            }

            // sync_cron
            if($params_cron) {
                $this->base->log('sync setting', 'sync_cron');
                $this->sync_cron($device, $params_cron, $params_cvr_cron);
            } else {
                $sync_all = false;
            }
            // sync_alarm
            if($params_alarm_level && $params_alarm_mail) {
                $this->base->log('sync setting', 'sync_alarm');
                $this->sync_alarm($device, $params_alarm_level, $params_alarm_mail, $params_alarm_list);
            } else {
                $sync_all = false;
            }
            //sync_alarm_zone
            if($params_alarm_zone){
                $this->base->log('sync_alarm');
                $this->sync_alarm_zone($device, $params_alarm_zone);
            } else {
                $sync_all = false;
            }
            // sync_capsule
            if($params_capsule) {
                $this->base->log('sync setting', 'sync_capsule');
                $this->sync_capsule($device, $params_capsule);
            } else {
                $sync_all = false;
            }

            // sync_plat
            if($params_plat) {
                $this->base->log('sync setting', 'sync_plat');
                $this->sync_plat($device, $params_plat);
            } else {
                $sync_all = false;
            }

            // sync_init
            if($params_init) {
                $this->base->log('sync setting', 'sync_init');
                $this->sync_init($device, $params_init);
            } else {
                $sync_all = false;
            }
            
            // sync_volume
            if($params_volume_talk || $params_volume_media) {
                $this->base->log('sync setting', 'sync_volume');
                $this->sync_volume($device, $params_volume_talk, $params_volume_media);
            } else {
                $sync_all = false;
            }

            //sync_aiface
            if($params_face){
                $this->base->log('sync_aiface');
                $this->sync_ai_face($device, $params_face);
            } else {
                $sync_all = false;
            }

            //sync_pano
            if($params_pano){
                $this->base->log('sync_pano');
                $this->sync_pano($client, $device, $params_pano);
            } else {
                $sync_all = false;
            }

            //sync_server
            if($params_server){
                $this->base->log('sync_server');
                $this->sync_server($device, $params_server);
            } else {
                $sync_all = false;
            }

            //sync_upload_domin
            if($params_set_upload){
                $this->base->log('sync_set_upload');
                $this->sync_set_upload($device, $params_set_upload);
            } else {
                $sync_all = false;
            }
            //sync_ai_setting
            if($params_set_face){
                $this->base->log('sync_set_face');
                $this->sync_set_face($device, $params_set_face);
            } else {
                $sync_all = false;
            }
            //功能配置项
            if($params_set_item){
                $this->base->log('sync_set_item');
                $this->sync_set_ai_item($device, $params_set_item);
            } else {
                $sync_all = false;
            }
            //人脸抓拍区域
            if($params_ai_face_detect_zone){
                $this->base->log('sync_ai_face_detect_zone');
                $this->sync_ai_face_detect_zone($device, $params_ai_face_detect_zone);
            } else {
                $sync_all = false;
            }


            if($sync_all) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `lastsettingsync`='".$this->base->time."' WHERE `deviceid`='".$deviceid."'");
            }
        }

        return true;
    }

    function sync_info($device, $params_info, $params_debug) {
        if(!$device || !$device['deviceid'] || !$params_info || !$params_debug)
            return false;

        $deviceid = $device['deviceid'];

        // 设备型号
        $model = $this->_hex2bin($params_info, 0, 32);

        // 设备版本(debug协议取版本)
        // $firmware = intval(substr($params_info, 64, 8), 16) / 1000;

        // 版本日期
        $p = intval(substr($params_info, 72, 8), 16);
        $firmdate = ((($p >> 26) & 0x3F) + 1984).'-'.(($p >> 22) & 0xF).'-'.(($p >> 17) & 0x1F);

        // IP
        $p = intval(substr($params_info, 128, 8), 16);
        $ip = (($p >> 24) & 0xFF).'.'.(($p >> 16) & 0xFF).'.'.(($p >> 8) & 0xFF).'.'.($p & 0xFF);

        // mac
        $p = substr($params_info, 136, 12);
        $mac = substr($p, 0, 2);
        for($i=2; $i<strlen($p); $i+=2) {
            $mac .= ':'.substr($p, $i, 2);
        }

        // sn
        $sn = $this->_hex2bin($params_info, 148, 64);

        // 大版本
        $big_firmware = intval(substr($params_debug, 0, 4), 16);

        $p = intval(substr($params_debug, 4, 4), 16);

        // 平台版本
        $plat = ($p >> 8) & 0xFF;

        // 子版本
        $sub_firmware = $p & 0xFF;

        // 调试版本
        $firmware = ($big_firmware/1000).'_'.$plat.'_'.$sub_firmware;

        // 渠道
        $channel = $big_firmware >= 7125 && $sub_firmware >= 12 ? intval(substr($params_info, 234, 8), 16) : 0;
        
        $e = intval(substr($params_info, 242, 2), 16);
        // 是否支持音量调节
        $volume = (intval($e&128) == 128)?1:0;
        // 是否支持媒体播放
        $media = (intval($e&64) == 64)?1:0;
        // 是否支持ai人脸识别
        $ai = (intval($e&16) == 16)?1:-1;

        // 全景
        $pano = ($plat == 202)?1:0;

        // 铭牌
        switch ($plat) {
            case 0:
            case 1: $nameplate = 'HDB'; break;
            case 2: $nameplate = 'HDSM'; break;
            case 4: $nameplate = 'HDW'; break;
            case 5: $nameplate = 'HDM'; break;
            case 6: $nameplate = 'HR101'; break;
            case 7: $nameplate = 'HDP'; break;
            case 16: $nameplate = 'Z1'; break;
            case 80:
            case 81: $nameplate = 'HDP';; break;
            case 86: $nameplate = 'Z1H'; break;
            case 100:
            case 180: $nameplate = 'HDQ'; break;
            default: $nameplate = 'HD'; break;
        }

        // 分辨率
        switch ($plat) {
            case 52:
            case 80:
            case 81:
            case 84:
            case 86:
            case 90:
            case 91:
            case 92:
            case 94:
            case 95:
            case 96:
            case 202:
            case 88:
            case 180: $resolution = 1080; break;
            default: $resolution = 720; break;
        }

        // sig
        $p = intval(substr($params_debug, 8, 8), 16);
        $sig = ($p >> 16) & 0xFF;

        // wifi
        $wifi = $this->_hex2bin($params_debug, 16, 64);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `firmware`='".$firmware."', `firmdate`='".$firmdate."', `channel`='".$channel."', `model`='".$model."', `nameplate`='".$nameplate."', `ip`='".$ip."', `mac`='".$mac."', `sn`='".$sn."', `sig`='".$sig."', `platform`='".$plat."', `resolution`='".$resolution."', `wifi`='".$wifi."', `media`='".$media."', `volume`='".$volume."', `params_info`='".$params_info."', `params_debug`='".$params_debug."', `ai`='".$ai."', `pano`='".$pano."'  WHERE `deviceid`='".$deviceid."'");
        
        // 摄像机类型：商铺
        switch ($plat) {
            case 10:
            case 11: $device_type = '2'; break;
            default: $device_type = '1'; break;
        }
        
        // update db
        if($device_type != $device['device_type']) 
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `device_type`='".$device_type."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_debug($device, $params_debug) {
        if(!$device || !$device['deviceid'] || !$params_debug)
            return false;

        $deviceid = $device['deviceid'];

        // 设备版本
        $firmware = intval(substr($params_debug, 0, 4), 16) / 1000;

        $p = intval(substr($params_debug, 4, 4), 16);

        // 平台版本
        $plat = ($p >> 8) & 0xFF;

        // 全景
        $pano = ($plat == 202)?1:0;

        // 铭牌
        switch ($plat) {
            case 0:
            case 1: $nameplate = 'HDB'; break;
            case 2: $nameplate = 'HDSM'; break;
            case 4: $nameplate = 'HDW'; break;
            case 5: $nameplate = 'HDM'; break;
            case 6: $nameplate = 'HR101'; break;
            case 16: $nameplate = 'Z1'; break;
            case 7:
            case 80:
            case 81: $nameplate = 'HDP'; break;
            case 86: $nameplate = 'Z1H'; break;
            case 100:
            case 180: $nameplate = 'HDQ'; break;
            default: $nameplate = 'HD'; break;
        }

        // 分辨率
        switch ($plat) {
            case 52:
            case 80:
            case 81:
            case 84:
            case 86:
            case 90:
            case 91:
            case 92:
            case 94:
            case 95:
            case 96:
            case 202:
            case 88:
            case 180: $resolution = 1080; break;
            default: $resolution = 720; break;
        }

        // 调试版本
        $firmware .= '_'.$plat.'_'.($p & 0xFF);

        // sig
        $p = intval(substr($params_debug, 8, 8), 16);
        $sig = ($p >> 16) & 0xFF;

        // wifi
        $wifi = $this->_hex2bin($params_debug, 16, 64);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `firmware`='".$firmware."', `sig`='".$sig."', `platform`='".$plat."', `nameplate`='".$nameplate."', `resolution`='".$resolution."', `wifi`='".$wifi."', `params_debug`='".$params_debug."', `pano`='".$pano."' WHERE `deviceid`='".$deviceid."'");
        
        // 摄像机类型：商铺
        switch ($plat) {
            case 10:
            case 11: $device_type = '2'; break;
            default: $device_type = '1'; break;
        }
        
        // update db
        if($device_type != $device['device_type']) 
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `device_type`='".$device_type."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_status($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $allows['status'] = array('alarm_push', 'light', 'invert', 'cvr');

        $deviceid = $device['deviceid'];

        // 报警通知
        $alarm_push = (intval(substr($params, 0, 2), 16) & 0x80) === 0x80 ? 1 : 0;

        // 指示灯：0为关闭，1为开启
        $light = ($params[3] === '0' ? 1 : 0);

        // 画面倒置：0为正常，1为180°翻转
        $invert = $params[5];

        // 云录制：0为关闭，1为开启
        $cvr = ($params[7] === '0' ? 1 : 0);

        // 本地存储类型：0禁止1事件2持续
        $storage_type = 0;
        if($params[11] === '0') {
            if($params[10] === '8') {
                $storage_type = 1;
            } else {
                $storage_type = 2;
            }
        }

        // 降噪：-1不支持，0为关闭，1为开启
        $nc = -1;
        switch (intval(substr($params, 14, 2), 16)) {
            case '0': $nc = 0; break;
            case '1': $nc = 1; break;
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_push`='".$alarm_push."', `light`='".$light."', `invert`='".$invert."', `cvr`='".$cvr."', `storage_type`='".$storage_type."', `nc`='".$nc."', `params_status`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_bit($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $allows['bit'] = array('bitrate', 'bitlevel', 'storage_bitrate', 'storage_bitlevel', 'audio');

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if (!$settings)
            return false;

        $cloudserver = $settings['cloudserver'] ? $settings['cloudserver'] : ($connect_type - 1);
        $platform = $settings['platform'];
        $storage_bitmode = $settings['storage_bitmode'];
        
        $storage_framerate = intval(substr($params, 12, 4), 16);
        $storage_bitrate = intval(substr($params, 20, 4), 16);
        $storage_bitlevel = $this->_bitrate_to_bitlevel($storage_bitrate, $storage_framerate, $cloudserver, $platform);

        // 音频状态：0为关闭，1为开启
        $audio = $storage_bitmode?$params[51]:$params[27];

        if ($storage_bitmode) {
            $framerate = intval(substr($params, 36, 4), 16);
            $bitrate = intval(substr($params, 44, 4), 16);
            $bitlevel = $this->_bitrate_to_bitlevel($bitrate, $framerate, $cloudserver, $platform);
        } else {
            $framerate = $storage_framerate;
            $bitrate = $storage_bitrate;
            $bitlevel = $storage_bitlevel;
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `bitrate`='".$bitrate."', `bitlevel`='".$bitlevel."', `framerate`='".$framerate."', `storage_bitrate`='".$storage_bitrate."', `storage_bitlevel`='".$storage_bitlevel."', `storage_framerate`='".$storage_framerate."', `audio`='".$audio."', `params_bit`='".$params."' WHERE `deviceid`='".$deviceid."'");

        // 老设备主码流和子码流音频开关不一致
        /*
        if ($storage_bitmode && $params[27] != $params[51]) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $this->set_audio($client, $device, $audio);
            $this->save_settings($client, $device);
        }
        */

        return true;
    }

    function sync_bw($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $allows['bw'] = array('maxspeed', 'minspeed');

        $deviceid = $device['deviceid'];

        // 带宽
        $maxspeed = intval($params, 16);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `maxspeed`='".$maxspeed."', `params_bw`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_night($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $allows['night'] = array('scene', 'nightmode', 'exposemode');

        $deviceid = $device['deviceid'];

        // 场景状态：0为室内，1为室外
        // 夜视模式：0为自动，1为日间，2为夜间
        $scene = ($params[9] < 3 ? 0 : 1);
        $nightmode = ($params[9] % 3);

        // 曝光模式：0为自动，1为高光优先，2为低光优先
        switch ($params[11]) {
            case '2': $exposemode = 0; break;
            case '0': $exposemode = 1; break;
            case '1': $exposemode = 2; break;
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `scene`='".$scene."', `nightmode`='".$nightmode."', `exposemode`='".$exposemode."', `params_night`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_email($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];

        // 发件人
        $mail_from = $this->_hex2bin($params, 0, 64);

        // 收件人
        $mail_to = $this->_hex2bin($params, 64, 64);

        // 抄送
        $mail_cc = $this->_hex2bin($params, 128, 64);

        // 邮件服务器
        $mail_server = $this->_hex2bin($params, 192, 64);

        // 发件用户名
        $mail_user = $this->_hex2bin($params, 256, 64);

        // 发件密码
        $mail_passwd = $this->_hex2bin($params, 320, 32);

        // 邮件服务器端口
        $mail_port = intval(substr($params, 352, 4), 16);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `mail_from`='".$mail_from."', `mail_to`='".$mail_to."', `mail_cc`='".$mail_cc."', `mail_server`='".$mail_server."', `mail_user`='".$mail_user."', `mail_passwd`='".$mail_passwd."', `mail_port`='".$mail_port."', `params_email`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_cron($device, $params_cron, $params_cvr_cron) {
        if(!$device || !$device['deviceid'] || !$params_cron || !$params_cvr_cron)
            return false;

        $deviceid = $device['deviceid'];
        $cvr_type = $device['cvr_type'];

        // 羚羊云全部处理为持续云录制
        if ($device['connect_type'] == 2) {
            $cvr_type = 2;
        }

        // 工作日
        $work_day = substr($params_cron, 3, 1);
        for ($i = 2; $i < 8; $i++) {
            $work_day .= substr($params_cron, $i * 2 + 1, 1);
        }

        // 解析定时任务
        $temp = $cron = array();
        for ($i = 0, $n = intval(substr($params_cron, 0, 2), 16); $i < $n; $i++) { 
            $cron_item = substr($params_cron, $i * 24 + 16, 24);

            // 定时任务开关
            $temp['status'] = (intval(substr($cron_item, 0, 2), 16) === 5 ? 1 : 0);

            // 定时任务工作日
            switch (intval(substr($cron_item, 2, 2), 16)) {
                case 0: $temp['work_day'] = '1000000';break;
                case 1: $temp['work_day'] = '0100000';break;
                case 2: $temp['work_day'] = '0010000';break;
                case 3: $temp['work_day'] = '0001000';break;
                case 4: $temp['work_day'] = '0000100';break;
                case 5: $temp['work_day'] = '0000010';break;
                case 6: $temp['work_day'] = '0000001';break;
                case 7: $temp['work_day'] = $work_day;break;
                case 8: $temp['work_day'] = $this->_parse_hex(decbin(bindec($work_day) ^ 0x7F), 7);break;
                case 9: $temp['work_day'] = '1111111';break;
            }

            // 定时任务起始时间
            $p = intval(substr($cron_item, 8, 8), 16);
            $temp['starttime'] = $this->_parse_hex((($p >> 16) & 0xFF), 2).$this->_parse_hex((($p >> 8) & 0xFF), 2).$this->_parse_hex(($p & 0xFF), 2);

            // 定时任务结束时间
            $p = intval(substr($cron_item, 16, 8), 16);
            $temp['endtime'] = $this->_parse_hex((($p >> 16) & 0xFF), 2).$this->_parse_hex((($p >> 8) & 0xFF), 2).$this->_parse_hex(($p & 0xFF), 2);

            // 定时任务类别
            switch (intval(substr($cron_item, 4, 4), 16)) {
                case 0: $cron['move'][] = $temp;break;
                case 2: $cron['power'][] = $temp;break;
                case 12: $cron['alarm'][] = $temp;break;
                case 16: $cron['cvr'][] = $temp;break;
            }
        }

        // 解析开关机定时任务
        $temp = $this->_parse_cron($cron['power']);
        extract($temp, EXTR_PREFIX_ALL, 'power');

        // 解析云录制定时任务
        if ($cvr_type == 2) {
            $cron['cvr'] = $this->_serial_cvr_cron($device, $params_cvr_cron);
        }

        $temp = $this->_parse_cron($cron['cvr']);
        extract($temp, EXTR_PREFIX_ALL, 'cvr');

        // 解析报警定时任务
        $temp = $this->_parse_cron($cron['alarm']);
        extract($temp, EXTR_PREFIX_ALL, 'alarm');

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power_cron`='".$power_cron."', `power_start`='".$power_start."', `power_end`='".$power_end."', `power_repeat`='".$power_repeat."', `cvr_cron`='".$cvr_cron."', `cvr_start`='".$cvr_start."', `cvr_end`='".$cvr_end."', `cvr_repeat`='".$cvr_repeat."', `alarm_cron`='".$alarm_cron."', `alarm_start`='".$alarm_start."', `alarm_end`='".$alarm_end."', `alarm_repeat`='".$alarm_repeat."', `params_cron`='".$params_cron."', `params_cvr_cron`='".$params_cvr_cron."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function _parse_cron($arr_cron) {
        $result = array(
            'cron' => 0,
            'start' => '000000',
            'end' => '000000',
            'repeat' => '0000000'
        );

        $n = count($arr_cron);
        if ($n) {
            $multi = $n > 1 && intval($arr_cron[0]['endtime']) === 235959 && intval($arr_cron[$n - 1]['starttime']) === 0 ? 1 : 0;
            if ($multi) {
                $cron_parts = array($arr_cron[0], $arr_cron[$n - 1]);
            } else {
                $cron_parts = array($arr_cron[0]);
            }

            for ($i = 1, $j = 0; $i < $n; $i++) { 
                if($arr_cron[$i]['status'] === $cron_parts[$j]['status'] && $arr_cron[$i]['starttime'] === $cron_parts[$j]['starttime'] && $arr_cron[$i]['endtime'] === $cron_parts[$j]['endtime']) {
                    $cron_parts[$j]['work_day'] = $this->_parse_hex(decbin(bindec($cron_parts[$j]['work_day']) | bindec($arr_cron[$i]['work_day'])), 7);
                } elseif ($j++ < $multi) {
                    $i--;
                } else {
                    break;
                }
            }

            $result['cron'] = $cron_parts[0]['status'];
            $result['start'] = $cron_parts[0]['starttime'];
            $result['end'] = $multi && substr($cron_parts[1]['work_day'], 1).substr($cron_parts[1]['work_day'], 0, 1) === $cron_parts[0]['work_day'] ? $cron_parts[1]['endtime'] : $cron_parts[0]['endtime'];
            $result['repeat'] = $cron_parts[0]['work_day'];
        }

        return $result;
    }

    // 新协议支持定时持续云录制
    function _serial_cvr_cron($device, $params) {
        if(!$device || !$params)
            return false;

        $deviceid = $device['deviceid'];

        // 工作日
        $work_day = substr($params, 3, 1);
        for ($i = 2; $i < 8; $i++) { 
            $work_day .= substr($params, $i * 2 + 1, 1);
        }

        // 解析定时任务
        $cron = array();
        for ($i = 0, $n = strlen($params); $i * 24 + 24 < $n; $i++) { 
            $cron_item = substr($params, $i * 24 + 24, 24);

            // 过滤定时录像类型
            if (intval(substr($cron_item, 6, 2), 16) !== 2)
                continue;

            $temp = array();

            // 定时任务开关
            $temp['status'] = (intval(substr($cron_item, 2, 2), 16) === 5 ? 1 : 0);

            // 定时任务工作日
            switch (intval(substr($cron_item, 4, 2), 16)) {
                case 0: $temp['work_day'] = '1000000';break;
                case 1: $temp['work_day'] = '0100000';break;
                case 2: $temp['work_day'] = '0010000';break;
                case 3: $temp['work_day'] = '0001000';break;
                case 4: $temp['work_day'] = '0000100';break;
                case 5: $temp['work_day'] = '0000010';break;
                case 6: $temp['work_day'] = '0000001';break;
                case 7: $temp['work_day'] = $work_day;break;
                case 8: $temp['work_day'] = $this->_parse_hex(decbin(bindec($work_day) ^ 0x7F), 7);break;
                case 9: $temp['work_day'] = '1111111';break;
            }

            // 定时任务起始时间
            $p = intval(substr($cron_item, 8, 8), 16);
            $temp['starttime'] = $this->_parse_hex((($p >> 16) & 0xFF), 2).$this->_parse_hex((($p >> 8) & 0xFF), 2).$this->_parse_hex(($p & 0xFF), 2);

            // 定时任务结束时间
            $p = intval(substr($cron_item, 16, 8), 16);
            $temp['endtime'] = $this->_parse_hex((($p >> 16) & 0xFF), 2).$this->_parse_hex((($p >> 8) & 0xFF), 2).$this->_parse_hex(($p & 0xFF), 2);

            $cron[] = $temp;
        }

        return $cron;
    }

    function sync_alarm($device, $params_alarm_level, $params_alarm_mail, $params_alarm_list='') {
        if(!$device || !$device['deviceid'] || !$params_alarm_level || !$params_alarm_mail)
            return false;

        $deviceid = $device['deviceid'];

        // 声音报警开关
        $alarm_audio = intval(substr($params_alarm_level, 0, 2), 16) ? 1 : 0;

        // 声音报警灵敏度:0低,1中,2高
        $alarm_audio_level = intval(substr($params_alarm_level, 2, 2), 16);
        $alarm_audio_level = $alarm_audio_level > 3 ? 2 : ($alarm_audio_level > 1 ? 1 : 0);

        // 移动侦测报警
        $alarm_move_level = intval(substr($params_alarm_level, 4, 2), 16);
        $alarm_move = $alarm_move_level === 127 ? 0 : 1;
        $alarm_move_level = $alarm_move_level > 3 ? 2 : ($alarm_move_level > 1 ? 1 : 0);

        // 录像灵敏度
        $alarm_record_level = intval(substr($params_alarm_level, 6, 2), 16);
        $alarm_record_level = $alarm_record_level > 3 ? 2 : ($alarm_record_level > 1 ? 1 : 0);

        // 邮件通知
        $alarm_mail = intval(substr($params_alarm_mail, 54, 2), 16) & 0x1 === 0x1 ? 1 : 0;

        // 兼容老推送机制需同步设备推送列表
        // 百度推送
        // 解析报警列表
        if($params_alarm_list) {
            $this->base->init_user();
            $uid = $this->base->uid;
            $appid = $this->base->appid;
            $client_id = $this->base->client_id;

            $pushid = 1;

            $alarm_count = intval(substr($params_alarm_list, 0, 8), 16);
            for ($i = 0; $i < $alarm_count; $i++) { 
                $alarm_item = substr($params_alarm_list, 192 * $i + 8, 192);

                $udid = $this->_hex2bin($alarm_item, 0, 60);
                $push_client = $this->db->fetch_first('SELECT c.* FROM '.API_DBTABLEPRE.'push_client c LEFT JOIN '.API_DBTABLEPRE.'push_service s ON c.pushid=s.pushid WHERE c.udid="'.$udid.'" AND c.uid="'.$uid.'" AND s.push_type="baidu"');
                if($push_client) {
                    $cid = $push_client['cid'];
                } else {
                    $user_id = $this->_hex2bin($alarm_item, 64, 64);
                    $channel_id = $this->_hex2bin($alarm_item, 128, 64);
                    $config = array('user_id'=>$user_id, 'channel_id'=>$channel_id);
                    $config = serialize($config);
                    
                    $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."push_client WHERE udid='$udid' AND uid='$uid' AND status>0 AND active>0");
                    $active = $check?0:1;
                    
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."push_client SET udid='$udid', uid='$uid', pushid='$pushid', config='$config', appid='$appid', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='1', active='$active'");
                    $cid = $this->db->insert_id();
                }

                $alarmlist = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_alarmlist WHERE `deviceid`="'.$deviceid.'" AND `cid`="'.$cid.'"');
                if(!$alarmlist) {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_alarmlist SET deviceid='$deviceid', cid='$cid'");
                } 
            }
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_mail`='".$alarm_mail."', `alarm_audio`='".$alarm_audio."', `alarm_audio_level`='".$alarm_audio_level."', `alarm_move`='".$alarm_move."', `alarm_move_level`='".$alarm_move_level."', `alarm_record_level`='".$alarm_record_level."', `params_alarm_level`='".$params_alarm_level."', `params_alarm_mail`='".$params_alarm_mail."', `params_alarm_list`='".$params_alarm_list."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_alarm_zone($device, $params_alarm_zone=''){
        if(!$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $alarm_zone = $this->params_alarm_transform($params_alarm_zone);
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_zone`='".$alarm_zone."', `params_alarm_zone`='".$params_alarm_zone."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    function sync_ai_face_detect_zone($device, $params_ai_face_detect_zone=''){
        if(!$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $ai_face_detect_zone = $this->params_alarm_transform($params_ai_face_detect_zone);
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_face_detect_zone`='".$ai_face_detect_zone."', `params_ai_face_detect_zone`='".$params_ai_face_detect_zone."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_ai_face($device, $params_face){
        if(!$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $params_face_detect = substr($params_face, 0, 2);
        $params_face_blur = substr($params_face, 2, 2);
        $params_body_count = substr($params_face, 4);
        $ai_count_direction = substr($params_body_count, 2, 2);
        // if($ai_count_direction == "03"){
        //     $ai_count_direction = "11";
        // }
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_face_detect`='".$params_face_detect."', `ai_face_blur`='".$params_face_blur."', `ai_count_direction`='".$ai_count_direction."', `ai_body_count`='".$params_body_count."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function sync_pano($client, $device, $params_pano) {
        if(!$client || !$device || !$device['deviceid'] || !$params_pano)
            return false;

        $deviceid = $device['deviceid'];
        $config = strval($params_pano);

        $n = 2;
        while($n < 10) {
            $command = '{"main_cmd":74,"sub_cmd":94,"param_len":4,"params":"0000000'.$n.'"}';
            $ret = $client->device_usercmd($device, $command, 1);
            if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
                return false;
            
            $params = $this->_cmd_params($ret['data'][1]['userData']);
            if(!$params)
                break;
            
            $config .= strval($params);
            $n++;
        }

        if(!$config)
            return false;
        
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `pano_config`='".$config."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function sync_server($device, $params_server){
        if(!$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $api_server_host = substr($params_server, 0, 8);
        $api_server_host = $this->_hex2bin($api_server_host, 0, 8);
        $api_server_host = inet_ntop($api_server_host);
        $api_server_port = base_convert(substr($params_server, 8, 4), 16, 10);
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `api_server_host`='".$api_server_host."', `api_server_port`='".$api_server_port."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function sync_set_upload($device, $params_set_upload){
        //@todo 字段数据库添加修改
        if(!$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $params_upload_port = base_convert(substr($params_set_upload, 0, 4), 16, 10);
        $params_group_type = base_convert(substr($params_set_upload, 4, 2), 16, 10);
        $params_upload_host = substr($params_set_upload, 6, 122);
        //协议修改
        $params_ai_face_port = base_convert(substr($params_set_upload, 128, 4), 16, 10);
        $params_ai_face_host = substr($params_set_upload, 132, 128);
        $params_face_group_id = substr($params_set_upload, 260, 200);
        // $preg = '/[0]*/';
        // $params_upload_host = preg_replace($preg, '', $params_upload_host, 1);
        $params_upload_host = $this->_hex2bin($params_upload_host, 0, strlen($params_upload_host));
        $params_ai_face_host = $this->_hex2bin($params_ai_face_host, 0, strlen($params_ai_face_host));
        $params_face_group_id = $this->_hex2bin($params_face_group_id, 0, strlen($params_face_group_id));
        if($params_upload_port == 0)
            $params_upload_port = "";
        if($params_ai_face_port == 0)
            $params_ai_face_port = "";
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_upload_port`='".$params_upload_port."', `ai_upload_host`='".$params_upload_host."', `ai_face_host`='".$params_ai_face_host."', `ai_face_port`='".$params_ai_face_port."', `ai_face_group_id`='".$params_face_group_id."', `ai_face_group_type`='".$params_group_type."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }
    function sync_set_face($device, $params_set_face){
        if(!$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $ai_api_key = $this->_hex2bin($params_set_face, 0, 64);
        $ai_secret_key = $this->_hex2bin($params_set_face, 64, 64);
        $ai_face_ori = base_convert(substr($params_set_face, 128, 2), 16, 10);
        $ai_face_pps = base_convert(substr($params_set_face, 130, 2), 16, 10); //每秒多少张人脸
        $ai_face_position = base_convert(substr($params_set_face, 132, 2), 16, 10); //上传部位
        $ai_face_frame = base_convert(substr($params_set_face, 136, 4), 16, 10); //显示框
        $ai_face_min_width = base_convert(substr($params_set_face, 140, 4), 16, 10);
        $ai_face_min_height = base_convert(substr($params_set_face, 144, 4), 16, 10);
        $ai_face_reliability = base_convert(substr($params_set_face, 148, 4), 16, 10);
        $ai_face_retention = base_convert(substr($params_set_face, 152, 4), 16, 10);
        // $ai_face_group_id = $this->_hex2bin(substr($params_set_face, 156), 0, 8);
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_api_key`='".$ai_api_key."', `ai_secret_key`='".$ai_secret_key."', `ai_face_ori`='".$ai_face_ori."', `ai_face_pps`='".$ai_face_pps."', `ai_face_position`='".$ai_face_position."', `ai_face_frame`='".$ai_face_frame."', `ai_face_min_width`='".$ai_face_min_width."', `ai_face_min_height`='".$ai_face_min_height."', `ai_face_reliability`='".$ai_face_reliability."', `ai_face_retention`='".$ai_face_retention."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function sync_set_ai_item($device, $params_set_item){
        if(!$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $ai_param = base_convert(substr($params_set_item, 0, 2), 16, 10);
        $ai_lan = intval($ai_param)>>7 & 1 ? 1 : 0;
        $ai_face_storage = intval($ai_param)>>5 & 1 ? 1 : 0; //是否本地存储
        $ai_face_type = base_convert(substr($params_set_item, 2, 2), 16, 10);

        
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_lan`='".$ai_lan."', `ai_face_storage` = '".$ai_face_storage."', `ai_face_type`='".$ai_face_type."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function params_alarm_transform($params){
        $alarm_zone = '';
        $type = '01';
        $width = '0016'; //十六进制数 22
        $heigh = '0012'; //十六进制数 18
        if($params ==  ''){
            return '-1';
        }
        $arr = str_split($params,8);
        if(count($arr) > 0){
            foreach ($arr as $value) {
                $dd = base_convert( substr(strval($value), 2, 6), 16, 2);
                if(strlen($dd) != 22){
                    //位数补全
                    $dd = str_pad($dd, 22, '0', STR_PAD_LEFT);
                }
                $alarm_zone .= $dd;
            }   
        }
        if(strlen($alarm_zone) !== 396)
            return false;
        $alarm_zone = $type.$width.$heigh.$alarm_zone;
        return $alarm_zone;
    }
    function alarm_params_transform($alarm_zone){
        $params_alarm_zone = '';
        if($alarm_zone ==  '-1'){
            return '';
        }
        $str = substr(strval($alarm_zone), 10);
        $arr = str_split($str,22);
        if(count($arr) > 0){
            foreach ($arr as $value) {
                $dd = base_convert( strval($value), 2, 16);
                if(strlen($dd) != 8){
                    //位数补全
                    $dd = str_pad($dd, 8, '0', STR_PAD_LEFT);
                }
                $params_alarm_zone .= $dd;
            }   
        }
        if(strlen($params_alarm_zone) !== 144)
            return false;
        
        return $params_alarm_zone;
    }

    function sync_capsule($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];

        $temp = unpack('n', hex2bin(substr($params, 0, 4)));
        $temperature = ($temp[1] > pow(2, 15) ? $temp[1] - pow(2, 16) : $temp[1]) / 100;

        $humidity = intval(substr($params, 4, 4), 16) / 100;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `temperature`='".$temperature."', `humidity`='".$humidity."', `params_capsule`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_storage($device, $params) {
        if (!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];
        $timezone = $device['timezone'];

        $storage_start_time = $this->_packtime_to_timestamp(intval(substr($params, 0, 8), 16), $timezone);
        $storage_end_time = $this->_packtime_to_timestamp(intval(substr($params, 8, 8), 16), $timezone);
        $storage_read_write = intval(substr($params, 28, 2), 16) ? 0 : 1;

        $nas = 1; // 是否支持NAS
        $nas_status = 0; // 是否接有NAS
        $sdcard = 1; // 是否支持SD卡
        $sdcard_status = 0; // 是否接有SD卡
        $storage_bitmode = 0; // 本地存储码率

        // 新固件协议更新NAS和SD卡(-1需格式化,0未接入,1准备中,2准备完成)
        $firmware = $this->_get_current_firmware($device);
        if ($firmware >= 7125010) {
            $p = intval(substr($params, 30, 2), 16);

            $storage_bitmode = ($p >> 2) & 0x1;

            $nas = ($p >> 4) & 0x1;
            $sdcard = ($p >> 5) & 0x1;

            $status = ($p & 0x40) ? 1 : 2;
            switch ($p & 0x3) {
                case 1: $nas_status = $status; break;
                case 2: $sdcard_status = $status; break;
                case 3: $nas_status = $status; $sdcard_status = 1; break;
            }

            if ($p & 0x8) {
                $sdcard_status = -1;
            }
        }

        $storage_total_size = intval(substr($params, 40, 8), 16).'0000000';
        $storage_free_size = intval(substr($params, 48, 8), 16).'0000000';

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `nas`='".$nas."',`nas_status`='".$nas_status."',`sdcard`='".$sdcard."',`sdcard_status`='".$sdcard_status."',`storage_total_size`='".$storage_total_size."',`storage_free_size`='".$storage_free_size."',`storage_read_write`='".$storage_read_write."',`storage_start_time`='".$storage_start_time."',`storage_end_time`='".$storage_end_time."',`storage_bitmode`='".$storage_bitmode."',`params_storage`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    
    function sync_timezone($device, $params) {
        if (!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];
        
        $ntp = 0; // 是否启用ntp
        $ntp_server = ''; // ntp服务器信息
        $dst_offset = 0; // 夏令时offset
        $timezone = 8; // 时区
        $dst_start = 0; // 夏令时开始时间
        $dst_end = 0; // 夏令时结束时间

        $ntp = intval(substr($params, 0, 2), 16);
        $ntp_server = $this->_hex2bin($params, 4, 64);
        $dst_offset = intval(substr($params, 68, 4), 16);
        $timezone = intval(substr($params, 72, 8), 16);
        $dst_start = intval(substr($params, 80, 8), 16);
        $dst_end = intval(substr($params, 88, 8), 16);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `timezone`='".$timezone."', `ntp`='".$ntp."', `ntp_server`='".$ntp_server."', `dst_offset`='".$dst_offset."', `dst_start`='".$dst_start."', `dst_end`='".$dst_end."', `params_timezone`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    
    function sync_cloud_publish($device, $params) {
        if (!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];
        
        $timezone = intval(substr($params, 2, 2), 16);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `timezone`='".$timezone."', `params_cloud_publish`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_plat($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];

        $plat = intval(substr($params, 0, 2), 16);
        $plat_move = intval(substr($params, 2, 2), 16);
        $plat_type = intval(substr($params, 4, 2), 16);
        $plat_rotate = intval(substr($params, 6, 2), 16);
        $plat_rotate_status = intval(substr($params, 8, 2), 16);
        $plat_track_status = intval(substr($params, 10, 2), 16);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `plat`='".$plat."', `plat_move`='".$plat_move."', `plat_type`='".$plat_type."', `plat_rotate`='".$plat_rotate."', `plat_rotate_status`='".$plat_rotate_status."', `plat_track_status`='".$plat_track_status."', `params_plat`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    
    function sync_volume($device, $params_volume_talk, $params_volume_media) {
        if(!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        
        $sql = "";
        if($params_volume_talk) {
            $volume_talk = intval(substr($params_volume_talk, 0, 2), 16);
            if($volume_talk == 0) $volume_talk = 50;
            $sql .= "`volume_talk`='$volume_talk', `params_volume_talk`='$params_volume_talk'";
        }
        
        if($params_volume_media) {
            $volume_media = intval(substr($params_volume_media, 0, 2), 16);
            if($volume_media == 0) $volume_media = 50;
            if($sql) $sql .= ", ";
            $sql .= "`volume_media`='$volume_media', `params_volume_media`='$params_volume_media'";
        }
        
        // update db
        if($sql) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET $sql WHERE `deviceid`='".$deviceid."'");
        }
        
        return true;
    }

    function sync_init($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if (!$settings)
            return false;

        $storage_bitmode = $settings['storage_bitmode'];

        // 设备型号
        $model = $this->_hex2bin($params, 0, 32);

        // 设备版本
        $firmware = intval(substr($params, 32, 8), 16) / 1000;

        // 版本日期
        $p = intval(substr($params, 40, 8), 16);
        $firmdate = ((($p >> 26) & 0x3F) + 1984).'-'.(($p >> 22) & 0xF).'-'.(($p >> 17) & 0x1F);
        // IP
        $p = intval(substr($params, 48, 8), 16);
        $ip = (($p >> 24) & 0xFF).'.'.(($p >> 16) & 0xFF).'.'.(($p >> 8) & 0xFF).'.'.($p & 0xFF);

        // mac
        $p = substr($params, 56, 12);
        $mac = substr($p, 0, 2);
        for($i=2; $i<strlen($p); $i+=2) {
            $mac .= ':'.substr($p, $i, 2);
        }

        // 云服务器: 0百度,1羚羊,50安徽广行
        $cloudserver = intval(substr($params, 68, 2), 16);

        // 分辨率
        $resolution = intval(substr($params, 70, 2), 16) === 1 ? 720 : 1080;

        // sn
        $sn = $this->_hex2bin($params, 72, 64);

        // 设备版本(重复)
        // $firmware = intval(substr($params, 136, 4), 16) / 1000;

        // 平台版本
        $p = intval(substr($params, 140, 4), 16);
        $platform = ($p >> 8) & 0xFF;

        $device_ability_data = '';
        //设备能力项处理
        if($firmware == 7.125 && intval($p) >= 32){
            $da = intval(substr($params, 306, 2), 16);
            // 是否支持人脸识别
            $da_ai = (intval($da&2) == 2)?1:0;
            // 是否支持音量调节
            $da_volume = (intval($da&8) == 8)?1:0;
            // 喜马拉雅
            $da_media = (intval($da&16) == 16)?1:0;
            // 双码流
            $da_storage_bitmode = (intval($da&32) == 32)?1:0;
            // NAS
            $da_nas = (intval($da&64) == 64)?1:0;
            // 卡录能力
            $da_sdcard = (intval($da&128) == 128)?1:0;
            
            $device_ability_data .= ", `ai`='".$da_ai."', `volume`='".$da_volume."', `media`='".$da_media."', `storage_bitmode`='".$da_storage_bitmode."', `nas`='".$da_nas."', `sdcard`='".$da_sdcard."'";
            // 是否支持降噪 //不支持同步  支持不予处理
            if(!(intval($da&4) == 4)){
                $da_nc = -1;
                $device_ability_data .= ", `nc`='".$da_nc."'";
            }

        }

        // 全景
        $pano = ($platform == 202)?1:0;

        // 铭牌
        switch ($platform) {
            case 0:
            case 1: $nameplate = 'HDB'; break;
            case 2: $nameplate = 'HDSM'; break;
            case 4: $nameplate = 'HDW'; break;
            case 5: $nameplate = 'HDM'; break;
            case 6: $nameplate = 'HR101'; break;
            case 16: $nameplate = 'Z1'; break;
            case 7:
            case 80:
            case 81: $nameplate = 'HDP'; break;
            case 86: $nameplate = 'Z1H'; break;
            case 100:
            case 180: $nameplate = 'HDQ'; break;
            default: $nameplate = 'HD'; break;
        }

        // 调试版本
        $firmware .= '_'.$platform.'_'.($p & 0xFF);

        // wifi信号强度
        $p = intval(substr($params, 144, 8), 16);
        $sig = ($p >> 16) & 0xFF;

        // wifi ssid
        $wifi = $this->_hex2bin($params, 152, 64);

        // 温度
        $p = unpack('n', hex2bin(substr($params, 216, 4)));
        $temperature = ($p[1] > pow(2, 15) ? $p[1] - pow(2, 16) : $p[1]) / 100;

        // 湿度
        $humidity = intval(substr($params, 220, 4), 16) / 100;

        // 是否接入云台: 1是,0否
        $plat = $params[225];

        // 是否支持位移: 1是,0否
        $plat_move = $params[227];

        // 云台类型: 0未知,1枪机,2iermu,3摇头机
        $plat_type = $params[229];

        // 是否支持平扫: 1是,0否
        $plat_rotate = $params[231];

        // 是否正在平扫: 1是,0否
        $plat_rotate_status = $params[233];

        // 是否正在巡航: 1是,0否
        $plat_track_status = $params[235];

        // 报警推送通知+移动侦测报警: 最高位推送开关位 / 最低位移动侦测位
        $p = intval(substr($params, 240, 2), 16);
        $alarm_push = $p & 0x80 ? 1 : 0;
        $alarm_move = $p & 0x1;

        // 指示灯: 0为关闭，1为开启
        $light = $params[243] === '0' ? 1 : 0;

        // 画面倒置: 0为正常,1为180°翻转
        $invert = $params[245];

        // 云录制: 0为关闭,1为开启
        $cvr = $params[247] === '0' ? 1 : 0;
        
        // 云帧率
        $framerate = intval(substr($params, 248, 4), 16);

        // 云码率
        $bitrate = intval(substr($params, 256, 4), 16);

        // 云清晰度: 0为流畅,1为高清,2为超清
        $bitlevel = $this->_bitrate_to_bitlevel($bitrate, $framerate, $cloudserver, $platform);

        // 音频状态: 0为关闭,1为开启
        $audio = $params[261];

        // 时区
        $timezone = intval(substr($params, 262, 2), 16);
        
        // 本地帧率
        $storage_framerate = 0;

        // 本地码率
        $storage_bitrate = 0;

        // 本地清晰度
        $storage_bitlevel = 0;

        if ($storage_bitmode) {
            $storage_framerate = $framerate;
            $storage_bitrate = $bitrate;
            $storage_bitlevel = $bitlevel;
            
            $framerate = intval(substr($params, 264, 4), 16);
            $bitrate = intval(substr($params, 272, 4), 16);
            $bitlevel = $this->_bitrate_to_bitlevel($bitrate, $framerate, $cloudserver, $platform);
            $audio |= $params[277];
        }

        // 带宽
        $maxspeed = intval(substr($params, 280, 8), 16);

        // 场景状态+夜视模式: 0为室内,1为室外 / 0为自动,1为日间,2为夜间
        $scene = $params[289] < 3 ? 0 : 1;
        $nightmode = $params[289] % 3;

        // 曝光模式: 0为自动,1为高光优先,2为低光优先
        switch ($params[291]) {
            case '2': $exposemode = 0; break;
            case '0': $exposemode = 1; break;
            case '1': $exposemode = 2; break;
        }

        // 声音报警开关
        $alarm_audio = $params[297];

        // 声音报警灵敏度: 0低,1中,2高
        $alarm_audio_level = intval(substr($params, 298, 2), 16);
        $alarm_audio_level = $alarm_audio_level > 3 ? 2 : ($alarm_audio_level > 1 ? 1 : 0);

        // 移动侦测报警
        $alarm_move_level = intval(substr($params, 300, 2), 16);
        $alarm_move_level = $alarm_move_level > 3 ? 2 : ($alarm_move_level > 1 ? 1 : 0);

        // 移动侦测报警(老协议)
        // $alarm_move_level = intval(substr($params, 302, 2), 16);
        // $alarm_move = $alarm_move_level === 127 ? 0 : 1;
        // $alarm_move_level = $alarm_move_level > 3 ? 2 : ($alarm_move_level > 1 ? 1 : 0);
        
        if($connect_type != API_LINGYANG_CONNECT_TYPE) {
            // 邮件通知
            $alarm_mail = intval(substr($params, 304, 2), 16) & 0x1;

            // 发件人
            $mail_from = $this->_hex2bin($params, 312, 64);

            // 收件人
            $mail_to = $this->_hex2bin($params, 376, 64);

            // 抄送
            $mail_cc = $this->_hex2bin($params, 440, 64);

            // 邮件服务器
            $mail_server = $this->_hex2bin($params, 504, 64);

            // 发件用户名
            $mail_user = $this->_hex2bin($params, 568, 64);

            // 发件密码
            $mail_passwd = $this->_hex2bin($params, 632, 32);

            // 邮件服务器端口
            $mail_port = intval(substr($params, 664, 4), 16);
            
            // update db
            $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `timezone`='".$timezone."', `model`='".$model."', `firmware`='".$firmware."', `firmdate`='".$firmdate."', `ip`='".$ip."', `mac`='".$mac."', `cloudserver`='".$cloudserver."', `resolution`='".$resolution."', `sn`='".$sn."', `platform`='".$platform."', `nameplate`='".$nameplate."', `sig`='".$sig."', `wifi`='".$wifi."', `temperature`='".$temperature."', `humidity`='".$humidity."', `plat`='".$plat."', `plat_move`='".$plat_move."', `plat_type`='".$plat_type."', `plat_rotate`='".$plat_rotate."', `plat_rotate_status`='".$plat_rotate_status."', `plat_track_status`='".$plat_track_status."', `alarm_push`='".$alarm_push."', `alarm_move`='".$alarm_move."', `light`='".$light."', `invert`='".$invert."', `cvr`='".$cvr."', `bitrate`='".$bitrate."', `bitlevel`='".$bitlevel."', `storage_bitrate`='".$storage_bitrate."', `storage_bitlevel`='".$storage_bitlevel."', `audio`='".$audio."', `maxspeed`='".$maxspeed."', `scene`='".$scene."', `nightmode`='".$nightmode."', `exposemode`='".$exposemode."', `alarm_audio`='".$alarm_audio."', `alarm_audio_level`='".$alarm_audio_level."', `alarm_move_level`='".$alarm_move_level."', `alarm_mail`='".$alarm_mail."', `mail_from`='".$mail_from."', `mail_to`='".$mail_to."', `mail_cc`='".$mail_cc."', `mail_server`='".$mail_server."', `mail_user`='".$mail_user."', `mail_passwd`='".$mail_passwd."', `mail_port`='".$mail_port."', `pano`='".$pano."', `params_init`='".$params."'".$device_ability_data." WHERE `deviceid`='".$deviceid."'");
        } else {
            // update db
            $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `timezone`='".$timezone."', `model`='".$model."', `firmware`='".$firmware."', `firmdate`='".$firmdate."', `ip`='".$ip."', `mac`='".$mac."', `cloudserver`='".$cloudserver."', `resolution`='".$resolution."', `sn`='".$sn."', `platform`='".$platform."', `nameplate`='".$nameplate."', `sig`='".$sig."', `wifi`='".$wifi."', `temperature`='".$temperature."', `humidity`='".$humidity."', `plat`='".$plat."', `plat_move`='".$plat_move."', `plat_type`='".$plat_type."', `plat_rotate`='".$plat_rotate."', `plat_rotate_status`='".$plat_rotate_status."', `plat_track_status`='".$plat_track_status."', `alarm_push`='".$alarm_push."', `alarm_move`='".$alarm_move."', `light`='".$light."', `invert`='".$invert."', `cvr`='".$cvr."', `bitrate`='".$bitrate."', `bitlevel`='".$bitlevel."', `storage_bitrate`='".$storage_bitrate."', `storage_bitlevel`='".$storage_bitlevel."', `audio`='".$audio."', `maxspeed`='".$maxspeed."', `scene`='".$scene."', `nightmode`='".$nightmode."', `exposemode`='".$exposemode."', `alarm_audio`='".$alarm_audio."', `alarm_audio_level`='".$alarm_audio_level."', `alarm_move_level`='".$alarm_move_level."', `pano`='".$pano."', `params_init`='".$params."'".$device_ability_data." WHERE `deviceid`='".$deviceid."'");
        }
        
        // 摄像机类型：商铺
        switch ($platform) {
            case 10:
            case 11: $device_type = '2'; break;
            default: $device_type = '1'; break;
        }
        
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `device_type`='".$device_type."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 检查推送机制
    function _check_push_version($device) {
        $firmware = $this->_get_current_firmware($device);
        if ($firmware > 7123021) {
            return true;
        }
        
        if($device['connect_type'] == 1) {
            return false;
        } else {
            return true;
        }
    }

    function updatesetting($device, $params, $platform) {
        if(!$device || !$params)
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $result = array();

        if(isset($params['light']) || isset($params['invert']) || isset($params['cvr']) || isset($params['alarm_push']) || isset($params['storage_type']) || isset($params['nc'])) {

            // 设置指示灯状态
            $light = $params['light'];
            if(!($light === NULL)) {
                if(!$this->set_light($client, $device, $light))
                    return false;
                $result['light'] = $light;
            }

            // 设置画面翻转
            $invert = $params['invert'];
            if(!($invert === NULL)) {
                if(!$this->set_invert($client, $device, $invert)) 
                    return false;
                $result['invert'] = $invert;
            }
            
            // 设置降噪
            $nc = $params['nc'];
            if(!($nc === NULL)) {
                if(!$this->set_nc($client, $device, $nc)) 
                    return false;
                $result['nc'] = $nc;
            }

            // 设置本地存储类型
            $storage_type = $params['storage_type'];
            if(!($storage_type === NULL)) {
                if(!$this->set_storage_type($client, $device, $storage_type)) 
                    return false;
                $result['storage_type'] = $storage_type;
            }

            // 设置云录制
            $cvr = $params['cvr'];
            if(!($cvr === NULL)) {
                if(!$this->set_cvr($client, $device, $cvr)) 
                    return false;
                $result['cvr'] = $cvr;

                // 2016年09月09日兼容固件cvr关闭仍然有定时录像的问题
                if (!$cvr && isset($params['cvr_cron'])) {
                    $params['cvr_cron'] = 0;
                    $params['cvr_start'] = '000000';
                    $params['cvr_end'] = '235959';
                    $params['cvr_repeat'] = '1111111';
                }
            }

            // 设置报警通知
            $alarm_push = $params['alarm_push'];
            if(!($alarm_push === NULL)) {
                if(!$this->set_alarm_push($client, $device, $alarm_push, $platform)) 
                    return false;
                $result['alarm_push'] = $alarm_push;
            }
        }

        if(isset($params['maxspeed']) || isset($params['minspeed'])) {
            // 设置最大限速、最小限速
            $maxspeed = $params['maxspeed'];
            $minspeed = $params['minspeed'];
            if(!($maxspeed === NULL || $minspeed === NULL)) {
                if(!$this->set_speed($client, $device, $maxspeed, $minspeed)) 
                    return false;
                $result['maxspeed'] = $maxspeed;
                $result['minspeed'] = $minspeed;
            }
        }

        if(isset($params['audio']) || isset($params['bitlevel']) || isset($params['bitrate']) || isset($params['framerate'])) {
            if(!$this->sync_setting($device, 'bits', 1))
                return false;

            // 设置音频状态
            $audio = $params['audio'];
            if(!($audio === NULL)) {
                if(!$this->set_audio($client, $device, $audio)) 
                    return false;
                $result['audio'] = $audio;
            }

            // 设置清晰度
            $bitlevel = $params['bitlevel'];
            if(!($bitlevel === NULL)) {
                if(!$this->set_bitlevel($client, $device, $bitlevel)) 
                    return false;
                $result['bitlevel'] = $bitlevel;
            }

            // 设置码率
            $bitrate = $params['bitrate'];
            if(!($bitrate === NULL)) {
                if(!$this->set_bitrate($client, $device, $bitrate)) 
                    return false;
                $result['bitrate'] = $bitrate;
            }
            
            // 设置帧率
            $framerate = $params['framerate'];
            if(!($framerate === NULL)) {
                if(!$this->set_framerate($client, $device, $framerate)) 
                    return false;
                $result['framerate'] = $framerate;
            }
        }

        if(isset($params['scene']) || isset($params['nightmode']) || isset($params['exposemode'])) {
            if(!$this->sync_setting($device, 'night', 1))
                return false;

            // 设置拍摄场景
            $scene = $params['scene'];
            if(!($scene === NULL)) {
                if(!$this->set_scene($client, $device, $scene))
                    return false;
                $result['scene'] = $scene;
            }

            // 设置夜视场景
            $nightmode = $params['nightmode'];
            if(!($nightmode === NULL)) {
                if(!$this->set_nightmode($client, $device, $nightmode)) 
                    return false;
                $result['nightmode'] = $nightmode;
            }

            // 设置曝光模式
            $exposemode = $params['exposemode'];
            if(!($exposemode === NULL)) {
                if(!$this->set_exposemode($client, $device, $exposemode)) 
                    return false;
                $result['exposemode'] = $exposemode;
            }
        }

        if(isset($params['power_cron']) || isset($params['cvr_cron']) || isset($params['alarm_cron'])) {
            if(!$this->sync_setting($device, 'cron', 1))
                return false;

            // 设置开机定时任务
            $power_cron = $params['power_cron'];
            $power_start = $params['power_start'];
            $power_end = $params['power_end'];
            $power_repeat = $params['power_repeat'];
            if(!($power_cron === NULL || $power_start === NULL || $power_end === NULL || $power_repeat === NULL)) {
                if(!$this->set_power_cron($client, $device, $power_cron, $power_start, $power_end, $power_repeat)) 
                    return false;
                $result['power_cron'] = $power_cron;
                $result['power_start'] = $power_start;
                $result['power_end'] = $power_end;
                $result['power_repeat'] = $power_repeat;
            }

            // 设置云录制定时任务
            $cvr_cron = $params['cvr_cron'];
            $cvr_start = $params['cvr_start'];
            $cvr_end = $params['cvr_end'];
            $cvr_repeat = $params['cvr_repeat'];
            if (!($cvr_cron === NULL || $cvr_start === NULL || $cvr_end === NULL || $cvr_repeat === NULL)) {
                // 2016年09月09日兼容固件cvr关闭仍然有定时录像的问题
                if ($params['cvr_start'] == '000000' && $params['cvr_end'] == '235959' && $params['cvr_repeat'] == '1111111') {
                    $cvr_cron = 0;
                }
                
                if (!$this->set_cvr_cron($client, $device, $cvr_cron, $cvr_start, $cvr_end, $cvr_repeat)) 
                    return false;

                $result['cvr_cron'] = $cvr_cron;
                $result['cvr_start'] = $cvr_start;
                $result['cvr_end'] = $cvr_end;
                $result['cvr_repeat'] = $cvr_repeat;
            }

            // 设置报警定时任务
            $alarm_cron = $params['alarm_cron'];
            $alarm_start = $params['alarm_start'];
            $alarm_end = $params['alarm_end'];
            $alarm_repeat = $params['alarm_repeat'];
            if(!($alarm_cron === NULL || $alarm_start === NULL || $alarm_end === NULL || $alarm_repeat === NULL)) {
                if(!$this->set_alarm_cron($client, $device, $alarm_cron, $alarm_start, $alarm_end, $alarm_repeat)) 
                    return false;
                $result['alarm_cron'] = $alarm_cron;
                $result['alarm_start'] = $alarm_start;
                $result['alarm_end'] = $alarm_end;
                $result['alarm_repeat'] = $alarm_repeat;
            }
        }

        if(isset($params['mail_to']) || isset($params['mail_cc']) || isset($params['mail_server'])) {
            if(!$this->sync_setting($device, 'mail', 1))
                return false;

            // 设置email邮箱
            $mail_to = $params['mail_to'];
            $mail_cc = $params['mail_cc'];
            $mail_server = $params['mail_server'];
            $mail_port = $params['mail_port'];
            $mail_from = $params['mail_from'];
            $mail_user = $params['mail_user'];
            $mail_passwd = $params['mail_passwd'];
            if(!($mail_to === NULL || $mail_cc === NULL || $mail_server === NULL || $mail_port === NULL || $mail_from === NULL || $mail_user === NULL || $mail_passwd === NULL)) {
                if(!$this->set_mail($client, $device, $mail_to, $mail_cc, $mail_server, $mail_port, $mail_from, $mail_user, $mail_passwd)) 
                    return false;
                $result['mail_to'] = $mail_to;
                $result['mail_cc'] = $mail_cc;
                $result['mail_server'] = $mail_server;
                $result['mail_port'] = $mail_port;
                $result['mail_from'] = $mail_from;
                $result['mail_user'] = $mail_user;
                $result['mail_passwd'] = $mail_passwd;
            }
        }

        if(isset($params['alarm_mail']) || isset($params['alarm_audio']) || isset($params['alarm_move']) || isset($params['alarm_zone'])) {
            if(!$this->sync_setting($device, 'alarm', 1))
                return false;

            // 设置报警邮件
            $alarm_mail = $params['alarm_mail'];
            if(!($alarm_mail === NULL)) {
                if(!$this->set_alarm_mail($client, $device, $alarm_mail)) 
                    return false;
                $result['alarm_mail'] = $alarm_mail;
            }

            // 声音报警
            $alarm_audio = $params['alarm_audio'];
            $alarm_audio_level = $params['alarm_audio_level'];
            if(!($alarm_audio === NULL || $alarm_audio_level === NULL)) {
                if(!$this->set_alarm_audio($client, $device, $alarm_audio, $alarm_audio_level)) 
                    return false;
                $result['alarm_audio'] = $alarm_audio;
                $result['alarm_audio_level'] = $alarm_audio_level;
            }

            // 移动报警
            $alarm_move = $params['alarm_move'];
            $alarm_move_level = $params['alarm_move_level'];
            if(!($alarm_move === NULL || $alarm_move_level === NULL)) {
                if(!$this->set_alarm_move($client, $device, $alarm_move, $alarm_move_level)) 
                    return false;
                $result['alarm_move'] = $alarm_move;
                $result['alarm_move_level'] = $alarm_move_level;
            }

            // 报警区域
            $alarm_zone = $params['alarm_zone'];
            if(!($alarm_zone === NULL)) {
                if(!$this->set_alarm_zone($client, $device, $alarm_zone)) 
                    return false;
                $result['alarm_zone'] = $alarm_zone;
            }
        }

        //人脸识别
        if(isset($params['ai_face_detect']) || isset($params['ai_face_blur']) || isset($params['ai_body_count'])) {
            if(!$this->sync_setting($device, 'ai', 1))
                return false;

            $ai_face_detect = $params['ai_face_detect'];
            if(!($ai_face_detect === NULL || intval($ai_face_detect) < 0)){
                if(!$this->set_ai_face_detect($client, $device, $ai_face_detect))
                    return false;
                $result['ai_face_detect'] = $ai_face_detect;
            }

            $ai_face_blur = $params['ai_face_blur'];
            if(!($ai_face_blur === NULL || intval($ai_face_blur) < 0)){
                if(!$this->set_ai_face_blur($client, $device, $ai_face_blur))
                    return false;
                $result['ai_face_blur'] = $ai_face_blur;
            }

            $ai_body_count = $params['ai_body_count'];
            if(!($ai_body_count === NULL || intval($ai_body_count) < 0)){
                if(!$this->set_ai_body_count($client, $device, $ai_body_count))
                    return false;
                $result['ai_body_count'] = $ai_body_count;
            }
            
        }

        //上传图片服务器 人脸设置
        if(isset($params['ai_upload_host']) || isset($params['ai_upload_port']) || isset($params['ai_face_host']) || isset($params['ai_face_port']) || isset($params['ai_api_key']) || isset($params['ai_secret_key']) || isset($params['ai_face_ori']) || isset($params['ai_face_pps']) || isset($params['ai_face_min_width']) || isset($params['ai_face_min_height']) || isset($params['ai_face_reliability']) || isset($params['ai_face_retention']) || isset($params['ai_face_group_id']) || isset($params['ai_face_position']) || isset($params['ai_face_frame']) || isset($params['ai_face_group_type']) || isset($params['ai_face_detect_zone'])) {
            if(!$this->sync_setting($device, 'ai_set', 1))
                return false;

            //ai_face_detect_zone
            $ai_face_detect_zone = $params['ai_face_detect_zone'];
            if(!($ai_face_detect_zone === NULL)){
                $result['ai_face_detect_zone'] = $ai_face_detect_zone;
            }
            if(!($ai_face_detect_zone === NULL)){
                if(!$this->set_ai_face_detect_zone($client, $device, $ai_face_detect_zone))
                    return false;
            }
            //upload
            $ai_upload_host = $params['ai_upload_host'];
            if(!($ai_upload_host === NULL)){
                $result['ai_upload_host'] = $ai_upload_host;
            }
            $ai_upload_port = $params['ai_upload_port'];
            if(!($ai_upload_port === NULL)){
                $result['ai_upload_port'] = $ai_upload_port;
            }
            $ai_face_host = $params['ai_face_host'];
            if(!($ai_face_host === NULL)){
                $result['ai_face_host'] = $ai_face_host;
            }
            $ai_face_port = $params['ai_face_port'];
            if(!($ai_face_port === NULL)){
                $result['ai_face_port'] = $ai_face_port;
            }
            $ai_face_group_id = $params['ai_face_group_id'];
            if(!($ai_face_group_id === NULL)){
                $result['ai_face_group_id'] = $ai_face_group_id;
            }
            $ai_face_group_type = $params['ai_face_group_type'];
            if(!($ai_face_group_type === NULL)){
                $result['ai_face_group_type'] = $ai_face_group_type;
            }
            if(!($ai_upload_host === NULL) || !($ai_upload_port === NULL) || !($ai_face_host === NULL) || !($ai_face_port === NULL) || !($ai_face_group_id === NULL) || !($ai_face_group_type === NULL)){
                if(!$this->set_ai_upload($client, $device, $ai_upload_host, $ai_upload_port, $ai_face_host, $ai_face_port, $ai_face_group_id, $ai_face_group_type))
                    return false;
            }

            //face
            $ai_api_key = $params['ai_api_key'];
            if(!($ai_api_key === NULL)){
                $result['ai_api_key'] = $ai_api_key;
            }
            $ai_secret_key = $params['ai_secret_key'];
            if(!($ai_secret_key === NULL)){
                $result['ai_secret_key'] = $ai_secret_key;
            }

            $ai_face_ori = $params['ai_face_ori'];
            if(!($ai_face_ori === NULL)){
                $result['ai_face_ori'] = $ai_face_ori;
            }
            $ai_face_pps = $params['ai_face_pps'];
            if(!($ai_face_pps === NULL)){
                $result['ai_face_pps'] = $ai_face_pps;
            }

            $ai_face_min_width = $params['ai_face_min_width'];
            if(!($ai_face_min_width === NULL)){
                $result['ai_face_min_width'] = $ai_face_min_width;
            }
            $ai_face_min_height = $params['ai_face_min_height'];
            if(!($ai_face_min_height === NULL)){
                $result['ai_face_min_height'] = $ai_face_min_height;
            }

            $ai_face_reliability = $params['ai_face_reliability'];
            if(!($ai_face_reliability === NULL)){
                $result['ai_face_reliability'] = $ai_face_reliability;
            }
            $ai_face_retention = $params['ai_face_retention'];
            if(!($ai_face_retention === NULL)){
                $result['ai_face_retention'] = $ai_face_retention;
            }

            $ai_face_position = $params['ai_face_position'];
            if(!($ai_face_position === NULL)){
                $result['ai_face_position'] = $ai_face_position;
            }
            $ai_face_frame = $params['ai_face_frame'];
            if(!($ai_face_frame === NULL)){
                $result['ai_face_frame'] = $ai_face_frame;
            }

            if($ai_api_key !==NULL || $ai_secret_key !==NULL || $ai_face_ori !==NULL || $ai_face_pps !==NULL || $ai_face_min_width !==NULL || $ai_face_min_height !==NULL || $ai_face_reliability !==NULL || $ai_face_retention !==NULL || $ai_face_position !==NULL || $ai_face_frame !==NULL){
                if(!$this->set_ai_face($client, $device, $ai_api_key, $ai_secret_key, $ai_face_ori, $ai_face_pps, $ai_face_min_width, $ai_face_min_height, $ai_face_reliability, $ai_face_retention, $ai_face_position, $ai_face_frame))
                    return false;
            }
        }

        //server
        if(isset($params['api_server_host']) || isset($params['api_server_port'])) {
            if(!$this->sync_setting($device, 'server', 1))
                return false;

            $api_server_host = $params['api_server_host'];
            if(!($api_server_host === NULL)){
                $result['api_server_host'] = $api_server_host;
            }
            $api_server_port = $params['api_server_port'];
            if(!($api_server_port === NULL)){
                $result['api_server_port'] = $api_server_port;
            }
            if($api_server_host !==NULL || $api_server_port !==NULL){
                if(!$this->set_server($client, $device, $api_server_host, $api_server_port))
                    return false;
            }
            //重启设备
            $params['restart'] = 1;
        }

        // 保存设置，以上函数为设置，以下函数为操作        
        if(!empty($result)) {
            if(!$this->save_settings($client, $device))
                return false;
        }

        //ai功能配置项设置
        if(isset($params['ai_lan']) || isset($params['ai_face_type']) || isset($params['ai_face_storage'])) {
            $ai_lan = $params['ai_lan'];
            if(!($ai_lan === NULL)){
                $result['ai_lan'] = $ai_lan;
            }
            $ai_face_storage = $params['ai_face_storage'];
            if(!($ai_face_storage === NULL)){
                $result['ai_face_storage'] = $ai_face_storage;
            }
            $ai_face_type = $params['ai_face_type'];
            if(!($ai_face_type === NULL)){
                $result['ai_face_type'] = $ai_face_type;
            }
            if(!$this->set_ai_item($client, $device, $ai_lan, $ai_face_type, $ai_face_storage))
                return false;
        }
        
        if(isset($params['cvr'])) {
            $cvr = $params['cvr'];
            if(!($cvr === NULL) && $client->need_cvr_after_save()) {
                if(!$client->set_cvr($device, $cvr))
                    return false;
            }
        }
        
        // 清除报警状态
        $clearalarm = $params['clearalarm'];
        if(!($clearalarm === NULL)) {
            if(!$this->set_clearalarm($client, $device, $clearalarm)) 
                return false;
            $result['clearalarm'] = intval($clearalarm);
        }

        // 设置简介
        $intro = $params['intro'];
        if(!($intro === NULL)) {
            if(!$this->set_intro($client, $device, $intro)) 
                return false;
            $result['intro'] = $intro;
        }

        // 设置局域网播放
        $localplay = $params['localplay'];
        if(!($localplay === NULL)) {
            if(!$this->set_localplay($client, $device, $localplay)) 
                return false;
            $result['localplay'] = $localplay;
        }
        
        // init
        $init = $params['init'];
        if(!($init === NULL)) {
            if(!$this->set_init($client, $device, $init)) 
                return false;
            $result['init'] = $init;
        }
        
        if(isset($params['volume_talk']) || isset($params['volume_media'])) {
            // 设置对讲音量
            $volume_talk = $params['volume_talk'];
            if(!($volume_talk === NULL)) {
                if(!$this->set_volume_talk($client, $device, $volume_talk)) 
                    return false;
                $result['volume_talk'] = $volume_talk;
            }

            // 设置媒体播放音量
            $volume_media = $params['volume_media'];
            if(!($volume_media === NULL)) {
                if(!$this->set_volume_media($client, $device, $volume_media)) 
                    return false;
                $result['volume_media'] = $volume_media;
            }
        }

        // 设置报警
        if(isset($params['alarm_count']) || isset($params['alarm_interval'])) {
            $alarm_count = $params['alarm_count'];
            $alarm_interval = $params['alarm_interval'];
            if(!($alarm_count === NULL) && !($alarm_interval === NULL)) {
                if(!$this->set_alarm_count($client, $device, $alarm_count, $alarm_interval)) 
                    return false;
                $result['alarm_count'] = $alarm_count;
                $result['alarm_interval'] = $alarm_interval;
            }
        }

        // 设置开机状态
        $power = $params['power'];
        if(!($power === NULL)) {
            if(!$this->set_power($client, $device, $power)) 
                return false;
            $result['power'] = $power;
        }

        // 重启设备
        $restart = $params['restart'];
        if(!($restart === NULL)) {
            if(!$this->set_restart($client, $device, $restart)) 
                return false;
            $result['restart'] = $restart;
        }

        if(empty($result)) 
            return false;

        return $result;
    }

    // 开关机
    function set_power($client, $device, $power) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $power = $power ? 1 : 0;

        if($power) {
            $command = '{"main_cmd":72,"sub_cmd":13,"param_len":0,"params":0}';
        } else {
            $command = '{"main_cmd":72,"sub_cmd":12,"param_len":0,"params":0}';
        }
        
        $this->base->log('lingyang _request', 'command='.$command);

        // api request
        if(!$client->device_usercmd($device, $command, $power?1:0))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power`='".$power."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function set_alarm_count($client, $device, $alarm_count, $alarm_interval) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];

        $alarm_count = intval($alarm_count);
        $alarm_interval = intval($alarm_interval);
        if($alarm_count<=0 || $alarm_interval<=0)
            return false;

        $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"03000000"}';

        // api request
        if(!$client->device_usercmd($device, $command, 0))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_count`='".$alarm_count."', `alarm_interval`='".$alarm_interval."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 开关机定时任务
    function set_power_cron($client, $device, $power_cron, $power_start, $power_end, $power_repeat) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        if (!preg_match('/^\d{6}$/', $power_start) || !preg_match('/^\d{6}$/', $power_end) || !preg_match('/^[01]{7}$/', $power_repeat))
            return false;

        if (intval($power_start) === intval($power_end))
            return false;

        $deviceid = $device['deviceid'];
        $power_cron = $power_cron ? 1 : 0;
        $start_hour = abs(intval(substr($power_start, 0, 2)));
        $start_minute = abs(intval(substr($power_start, 2, 2)));
        $start_second = abs(intval(substr($power_start, 4, 2)));
        $end_hour = abs(intval(substr($power_end, 0, 2)));
        $end_minute = abs(intval(substr($power_end, 2, 2)));
        $end_second = abs(intval(substr($power_end, 4, 2)));
        $power_repeat = $this->_parse_hex(decbin(bindec($power_repeat) & 0x7F), 7);
        if($start_hour > 23 || $start_minute > 59 || $start_second > 59 || $end_hour > 23 || $end_minute > 59 || $end_second > 59)
            return false;

        $params_cron = $this->db->result_first('SELECT params_cron FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_cron)
            return false;

        // 定时任务总数
        $cron_count = 0;

        // 工作日
        $work_day = substr($params_cron, 3, 1);
        for ($i = 2; $i < 8; $i++) { 
            $work_day .= substr($params_cron, $i * 2 + 1, 1);
        }

        // 请求参数
        $params = substr($params_cron, 2, 14);
        for ($i = 0, $n = intval(substr($params_cron, 0, 2), 16); $i < $n; $i++) { 
            $cron_item = substr($params_cron, $i * 24 + 16, 24);

        // 排除开关机定时任务
            if(intval(substr($cron_item, 4, 4), 16) === 2)
                continue;

            $params .= $cron_item;
            $cron_count++;
        }

        // 解析开关机定时任务
        $params_cron_type = '0002';
        $params_cron_status = $power_cron ? '05' : '01';
        if (intval($power_start) > intval($power_end)) {
            $cron_parts = array(
                array(
                    'start' => '00'.$this->_parse_hex(dechex($start_hour), 2).$this->_parse_hex(dechex($start_minute), 2).$this->_parse_hex(dechex($start_second), 2),
                    'end' => '00'.$this->_parse_hex(dechex(23), 2).$this->_parse_hex(dechex(59), 2).$this->_parse_hex(dechex(59), 2),
                    'repeat' => $power_repeat
                    ),
                array(
                    'start' => '00'.$this->_parse_hex(dechex(0), 2).$this->_parse_hex(dechex(0), 2).$this->_parse_hex(dechex(0), 2),
                    'end' => '00'.$this->_parse_hex(dechex($end_hour), 2).$this->_parse_hex(dechex($end_minute), 2).$this->_parse_hex(dechex($end_second), 2),
                    'repeat' => substr($power_repeat, 6, 1) . substr($power_repeat, 0, 6)
                    )
                );
        } else {
            $cron_parts = array(
                array(
                    'start' => '00'.$this->_parse_hex(dechex($start_hour), 2).$this->_parse_hex(dechex($start_minute), 2).$this->_parse_hex(dechex($start_second), 2),
                    'end' => '00'.$this->_parse_hex(dechex($end_hour), 2).$this->_parse_hex(dechex($end_minute), 2).$this->_parse_hex(dechex($end_second), 2),
                    'repeat' => $power_repeat
                    )
                );
        }
        for ($j = 0, $m = count($cron_parts); $j < $m; $j++) { 
            $params_cron_start = $cron_parts[$j]['start'];
            $params_cron_end = $cron_parts[$j]['end'];
            $cron_repeat = $cron_parts[$j]['repeat'];

            // 判断需要的定时任务
            $repeat = array();
            $n = bindec($cron_repeat);
            if((~$n & 0x7F) === 0) {
                $repeat[] = '09';
            } elseif ((~$n & bindec($work_day)) === 0) {
                $repeat[] = '07';
                $other = $this->_parse_hex(decbin($n ^ bindec($work_day)), 7);
                for ($i = 0; $i < 7; $i++) { 
                    if($other[$i] === '1') {
                        $repeat[] = $this->_parse_hex(dechex($i), 2);
                    }
                }
            } elseif ((~$n & (bindec($work_day) ^ 0x7F)) === 0) {
                $repeat[] = '08';
                $other = $this->_parse_hex(decbin($n ^ (bindec($work_day) ^ 0x7F)), 7);
                for ($i = 0; $i < 7; $i++) { 
                    if($other[$i] === '1') {
                        $repeat[] = $this->_parse_hex(dechex($i), 2);
                    }
                }
            } else {
                for ($i = 0; $i < 7; $i++) { 
                    if($cron_repeat[$i] === '1') {
                        $repeat[] = $this->_parse_hex(dechex($i), 2);
                    }
                }
            }
            $n = count($repeat);
            $cron_count += $n;
            for ($i = 0; $i < $n; $i++) { 
                $params .= $params_cron_status.$repeat[$i].$params_cron_type.$params_cron_start.$params_cron_end;
            }
        }

        $params = $this->_parse_hex(dechex($cron_count), 2) . $params;
        $command = '{"main_cmd":75,"sub_cmd":51,"param_len":' . (strlen($params) / 2) . ',"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power_cron`='".$power_cron."', `power_start`='".$power_start."', `power_end`='".$power_end."', `power_repeat`='".$power_repeat."', `params_cron`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 简介
    function set_intro($client, $device, $intro) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `intro`='".$intro."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 局域网播放
    function set_localplay($client, $device, $localplay) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $localplay = $localplay ? 1 : 0;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `localplay`='".$localplay."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 指示灯
    function set_light($client, $device, $light) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $light = $light ? 1 : 0;
        $params_status = 'FFFFFFFFFFFFFFFF';

        // request params
        $params_light = $light ? '00' : '01';
        $params = substr_replace($params_status, $params_light, 2, 2);

        $command = '{"main_cmd":75,"sub_cmd":43,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `light`='".$light."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 画面倒置
    function set_invert($client, $device, $invert) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $invert = $invert ? 1 : 0;
        $params_status = 'FFFFFFFFFFFFFFFF';

        // request params
        $params_invert = $invert ? '01' : '00';
        $params = substr_replace($params_status, $params_invert, 4, 2);
        $command = '{"main_cmd":75,"sub_cmd":43,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `invert`='".$invert."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    
    // 降噪
    function set_nc($client, $device, $nc) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $nc = $nc ? 1 : 0;
        $params_status = 'FFFFFFFFFFFFFFFF';

        // request params
        $params_nc = $nc ? '01' : '00';
        $params = substr_replace($params_status, $params_nc, 14, 2);
        $command = '{"main_cmd":75,"sub_cmd":43,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `nc`='".$nc."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 本地存储类型
    function set_storage_type($client, $device, $storage_type) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $storage_type = intval($storage_type);
        if(!in_array($storage_type, array(0,1,2)))
            return false;

        $params_status = 'FFFFFFFFFFFFFFFF';

        // request params
        if($storage_type == 0) {
            $params_storage_type = '01';
        } else if($storage_type == 1) {
            $params_storage_type = '80';
        } else {
            $params_storage_type = '00';
        }
        $params = substr_replace($params_status, $storage_type, 10, 2);
        $command = '{"main_cmd":75,"sub_cmd":43,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `storage_type`='".$storage_type."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 音频
    function set_audio($client, $device, $audio) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $audio = $audio ? 1 : 0;

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings || !$settings['params_bit'])
            return false;

        $params_bit = $settings['params_bit'];
        $storage_bitmode = $settings['storage_bitmode'];

        // request params
        $params_audio = $audio ? '1' : '0';

        $params = substr_replace($params_bit, $params_audio, 27, 1);
        if ($storage_bitmode) {
            $params = substr_replace($params, $params_audio, 51, 1);
        }
        
        $command = '{"main_cmd":75,"sub_cmd":5,"param_len":82,"params":"'.$params.'"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1)) {
            return false;
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `audio`='".$audio."', `params_bit`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 拍摄场景
    function set_scene($client, $device, $scene) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $scene = $scene ? 1 : 0;

        $params_night = $this->db->result_first('SELECT params_night FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_night)
            return false;

        // request params
        $params_scene = intval($params_night[9]);
        if($params_scene < 3) {
            if($scene)
                $params_scene += 3;
        } else {
            if(!$scene)
                $params_scene -= 3;
        }
        $params = substr_replace($params_night, $params_scene, 9, 1);
        $command = '{"main_cmd":75,"sub_cmd":41,"param_len":24,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `scene`='".$scene."', `params_night`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 夜视模式
    function set_nightmode($client, $device, $nightmode) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $nightmode = $nightmode ? ($nightmode > 1 ? 2 : 1) : 0;

        $params_night = $this->db->result_first('SELECT params_night FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_night)
            return false;

        // request params
        $params_nightmode = intval($params_night[9]);
        if($params_nightmode < 3) {
            $params_nightmode = $nightmode;
        } else {
            $params_nightmode = $nightmode + 3;
        }
        $params = substr_replace($params_night, $params_nightmode, 9, 1);
        $command = '{"main_cmd":75,"sub_cmd":41,"param_len":24,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `nightmode`='".$nightmode."', `params_night`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 曝光模式
    function set_exposemode($client, $device, $exposemode) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $exposemode = $exposemode ? ($exposemode > 1 ? 2 : 1) : 0;

        $params_night = $this->db->result_first('SELECT params_night FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_night)
            return false;

        // request params
        switch ($exposemode) {
            case 0: $params_exposemode = '2';break;
            case 1: $params_exposemode = '0';break;
            case 2: $params_exposemode = '1';break;
        }
        $params = substr_replace($params_night, $params_exposemode, 11, 1);
        $command = '{"main_cmd":75,"sub_cmd":41,"param_len":24,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `exposemode`='".$exposemode."', `params_night`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 清晰度
    function set_bitlevel($client, $device, $bitlevel) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $bitlevel = $bitlevel ? ($bitlevel > 1 ? 2 : 1) : 0;

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings || !$settings['params_bit'])
            return false;

        $params_bit = $settings['params_bit'];
        $cloudserver = $settings['cloudserver'] ? $settings['cloudserver'] : ($connect_type - 1);
        $platform = $settings['platform'];
        $storage_bitmode = $settings['storage_bitmode'];
        $framerate = $settings['framerate'];

        $bitrate = $this->_bitlevel_to_bitrate($bitlevel, $framerate, $cloudserver, $platform);

        $offset = $storage_bitmode ? 44 : 20; // 设置云码流协议偏移位
        $params_bitrate = $this->_parse_hex(dechex($bitrate), 4);
        $params_framerate = $this->_parse_hex(dechex($framerate), 4);
        $params = substr_replace($params_bit, $params_bitrate, $offset, 4);

        // 设置动态码率
        $command = '{"main_cmd":75,"sub_cmd":45,"param_len":6,"params":"0000' . $params_bitrate . $params_framerate . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $command = '{"main_cmd":75,"sub_cmd":5,"param_len":82,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $sqladd = "";
        if(!$storage_bitmode) {
            $sqladd = ", `storage_bitlevel`='".$bitlevel."', `storage_bitrate`='".$bitrate."'";
        }
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `bitlevel`='".$bitlevel."', `bitrate`='".$bitrate."', `params_bit`='".$params."' $sqladd WHERE `deviceid`='".$deviceid."'");
        
        /*
        $maxspeed = 2 * $bitrate;
        $minspeed = 50;
        $this->set_speed($client, $device, $maxspeed, $minspeed);
        */

        return true;
    }

    function _bitlevel_to_bitrate($bitlevel, $framerate, $cloudserver = 0, $platform = 0) {
        $bitrate = 0;

        switch ($cloudserver) {
            case 1:
                switch ($platform) {
                    case 52:
                    case 80:
                    case 81:
                    case 84:
                    case 86:
                    case 90:
                    case 91:
                    case 92:
                    case 94:
                    case 95:
                    case 96:
                    case 202:
                    case 88:
                    case 180:
                        switch ($bitlevel) {
                            case 0: $bitrate = LINGYANG_1080P_DEVICE_BITLEVEL_0;break;
                            case 1: $bitrate = LINGYANG_1080P_DEVICE_BITLEVEL_1;break;
                            case 2: $bitrate = LINGYANG_1080P_DEVICE_BITLEVEL_2;break;
                        }
                        break;

                    default:
                        switch ($bitlevel) {
                            case 0: $bitrate = LINGYANG_720P_DEVICE_BITLEVEL_0;break;
                            case 1: $bitrate = LINGYANG_720P_DEVICE_BITLEVEL_1;break;
                            case 2: $bitrate = LINGYANG_720P_DEVICE_BITLEVEL_2;break;
                        }
                        break;
                }
                if($framerate>=250) {
                    $bitrate = $bitrate*1.5;
                }
                break;
            
            default:
                switch ($platform) {
                    case 52:
                    case 80:
                    case 81:
                    case 84:
                    case 86:
                    case 90:
                    case 91:
                    case 92:
                    case 94:
                    case 95:
                    case 96:
                    case 202:
                    case 88:
                    case 180:
                        switch ($bitlevel) {
                            case 0: $bitrate = BAIDU_1080P_DEVICE_BITLEVEL_0;break;
                            case 1: $bitrate = BAIDU_1080P_DEVICE_BITLEVEL_1;break;
                            case 2: $bitrate = BAIDU_1080P_DEVICE_BITLEVEL_2;break;
                        }
                        break;

                    default:
                        switch ($bitlevel) {
                            case 0: $bitrate = BAIDU_720P_DEVICE_BITLEVEL_0;break;
                            case 1: $bitrate = BAIDU_720P_DEVICE_BITLEVEL_1;break;
                            case 2: $bitrate = BAIDU_720P_DEVICE_BITLEVEL_2;break;
                        }
                        break;
                }
                break;
        }

        return $bitrate;
    }

    // 码率
    function set_bitrate($client, $device, $bitrate) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if(!is_numeric($bitrate) || $bitrate < 50)
            return false;

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings || !$settings['params_bit'])
            return false;

        $params_bit = $settings['params_bit'];
        $cloudserver = $settings['cloudserver'] ? $settings['cloudserver'] : ($connect_type - 1);
        $platform = $settings['platform'];
        $storage_bitmode = $settings['storage_bitmode'];
        $framerate = $settings['framerate'];

        // 设置清晰度
        $bitlevel = $this->_bitrate_to_bitlevel($bitrate, $framerate, $cloudserver, $platform);

        $offset = $storage_bitmode ? 44 : 20; // 设置云码流协议偏移位
        $params_bitrate = $this->_parse_hex(dechex($bitrate), 4);
        $params_framerate = $this->_parse_hex(dechex($framerate), 4);
        $params = substr_replace($params_bit, $params_bitrate, $offset, 4);

        // 设置动态码率
        $command = '{"main_cmd":75,"sub_cmd":45,"param_len":6,"params":"0000' . $params_bitrate . $params_framerate.'"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $command = '{"main_cmd":75,"sub_cmd":5,"param_len":82,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $sqladd = "";
        if(!$storage_bitmode) {
            $sqladd = ", `storage_bitlevel`='".$bitlevel."', `storage_bitrate`='".$bitrate."'";
        }
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `bitlevel`='".$bitlevel."', `bitrate`='".$bitrate."', `params_bit`='".$params."' $sqladd WHERE `deviceid`='".$deviceid."'");
        
        /*
        $maxspeed = 2 * $bitrate;
        $minspeed = 50;
        $this->set_speed($client, $device, $maxspeed, $minspeed);
        */

        return true;
    }
    
    function set_framerate($client, $device, $framerate) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if(!is_numeric($framerate) || $framerate < 0)
            return false;

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings || !$settings['params_bit'])
            return false;

        $params_bit = $settings['params_bit'];
        $cloudserver = $settings['cloudserver'] ? $settings['cloudserver'] : ($connect_type - 1);
        $platform = $settings['platform'];
        $storage_bitmode = $settings['storage_bitmode'];
        $bitlevel = $settings['bitlevel'];
        
        $bitrate = $this->_bitlevel_to_bitrate($bitlevel, $framerate, $cloudserver, $platform);

        $params_bitrate = $this->_parse_hex(dechex($bitrate), 4);
        $params_framerate = $this->_parse_hex(dechex($framerate), 4);
        
        $offset = $storage_bitmode ? 36 : 12; // 设置云帧率协议偏移位
        $params_bit = substr_replace($params_bit, $params_framerate, $offset, 4);
        
        $offset_bitrate = $storage_bitmode ? 44 : 20; // 设置云码率协议偏移位
        $params_bit = substr_replace($params_bit, $params_bitrate, $offset_bitrate, 4);

        // 设置动态码率
        $command = '{"main_cmd":75,"sub_cmd":45,"param_len":6,"params":"0000' . $params_bitrate . $params_framerate . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $command = '{"main_cmd":75,"sub_cmd":5,"param_len":82,"params":"' . $params_bit . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $sqladd = "";
        if(!$storage_bitmode) {
            $sqladd = ", `storage_framerate`='".$framerate."', `storage_bitrate`='".$bitrate."'";
        }
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `framerate`='".$framerate."', `bitrate`='".$bitrate."', `params_bit`='".$params_bit."' $sqladd WHERE `deviceid`='".$deviceid."'");
        
        /*
        $maxspeed = 2 * $bitrate;
        $minspeed = 50;
        $this->set_speed($client, $device, $maxspeed, $minspeed);
        */

        return true;
    }

    function _bitrate_to_bitlevel($bitrate, $framerate, $cloudserver = 0, $platform = 0) {
        $bitlevel = 0;
        
        $bitlevel_2 = $bitlevel_1 = 0;

        switch ($cloudserver) {
            case 1:
                switch ($platform) {
                    case 52:
                    case 80:
                    case 81:
                    case 84:
                    case 86:
                    case 90:
                    case 91:
                    case 92:
                    case 94:
                    case 95:
                    case 96:
                    case 202:
                    case 88:
                    case 180:
                        $bitlevel_2 = LINGYANG_1080P_DEVICE_BITLEVEL_2;
                        $bitlevel_1 = LINGYANG_1080P_DEVICE_BITLEVEL_1;
                        break;
                    default:
                        $bitlevel_2 = LINGYANG_720P_DEVICE_BITLEVEL_2;
                        $bitlevel_1 = LINGYANG_720P_DEVICE_BITLEVEL_1;
                        break;
                }
                if($framerate >= 250) {
                    $bitlevel_2 = $bitlevel_2*1.5;
                    $bitlevel_1 = $bitlevel_1*1.5;
                }
                break;
            
            default:
                switch ($platform) {
                    case 52:
                    case 80:
                    case 81:
                    case 84:
                    case 86:
                    case 90:
                    case 91:
                    case 92:
                    case 94:
                    case 95:
                    case 96:
                    case 202:
                    case 88:
                    case 180:
                        $bitlevel_2 = BAIDU_1080P_DEVICE_BITLEVEL_2;
                        $bitlevel_1 = BAIDU_1080P_DEVICE_BITLEVEL_1;
                        break;
                    default:
                        $bitlevel_2 = BAIDU_720P_DEVICE_BITLEVEL_2;
                        $bitlevel_1 = BAIDU_720P_DEVICE_BITLEVEL_1;
                        break;
                }
                break;
        }
        
        if($bitrate >= $bitlevel_2) {
            $bitlevel = 2;
        } elseif ($bitrate >= $bitlevel_1) {
            $bitlevel = 1;
        } else {
           $bitlevel = 0; 
        }

        return $bitlevel;
    }

    // 最大限速、最小限速
    function set_speed($client, $device, $maxspeed, $minspeed) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        
        if(!is_numeric($maxspeed) || !is_numeric($minspeed) || $maxspeed < 50 || $minspeed < 50 || $maxspeed > 4000 || $maxspeed < $minspeed)
            return false;

        /* 该设置不需要取
        $params_bw = $this->db->result_first('SELECT params_bw FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_bw)
            return false;
        */

        $params_maxspeed = $this->_parse_hex(dechex($maxspeed), 8);

        $command = '{"main_cmd":75,"sub_cmd":49,"param_len":4,"params":"' . $params_maxspeed . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `maxspeed`='".$maxspeed."', `minspeed`='".$minspeed."', `params_bw`='".$params_maxspeed."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 发件邮箱设置
    function set_mail($client, $device, $mail_to, $mail_cc, $mail_server, $mail_port, $mail_from, $mail_user, $mail_passwd) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        if(!is_numeric($mail_port) || $mail_port < 0 || $mail_port > 65535)
            return false;

        $params_mail_port = $this->_parse_hex(dechex($mail_port), 4);

        $params = $this->_bin2hex($mail_from, 64).$this->_bin2hex($mail_to, 64).$this->_bin2hex($mail_cc, 64).$this->_bin2hex($mail_server, 64).$this->_bin2hex($mail_user, 64).$this->_bin2hex($mail_passwd, 32).$params_mail_port;
        $command = '{"main_cmd":75,"sub_cmd":12,"param_len":178,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `mail_to`='".$mail_to."', `mail_cc`='".$mail_cc."', `mail_server`='".$mail_server."', `mail_port`='".$mail_port."', `mail_from`='".$mail_from."', `mail_user`='".$mail_user."', `mail_passwd`='".$mail_passwd."', `params_email`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    
    function set_timezone($client, $device, $timezone) {
        if(!$client || !$device || !$device['deviceid'] || !$timezone)
            return false;

        $deviceid = $device['deviceid'];
        
        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings)
            return false;
        
        $firmware = $this->_get_current_firmware($device);
        
        $this->base->log('timezone_log', 'firmware='.$firmware);
        if ($firmware > 7125020) {
            $params_timezone = $settings['params_timezone'];
            if(!$params_timezone)
                return false;
            
            $tz_rule = $this->base->get_timezone_rule_from_timezone_id($timezone);
            if(!$tz_rule)
                return false;
        
            $this->base->log('timezone_log', 'tz_rule='.json_encode($tz_rule));
            
            if(intval($tz_rule['timezone']) == intval($settings['timezone']) && intval($tz_rule['dst_offset']) == intval($settings['dst_offset']) && intval($tz_rule['dst_start']) == intval($settings['dst_start']) && intval($tz_rule['dst_end']) == intval($settings['dst_end']))
                return true;
            
            $ps_dst_offset = $this->_parse_hex(dechex(intval($tz_rule['dst_offset'])), 4);
            $ps_timezone = $this->_parse_hex(dechex(intval($tz_rule['timezone'])), 8);
            $ps_dst_start = $this->_parse_hex(dechex(intval($tz_rule['dst_start'])), 8);
            $ps_dst_end = $this->_parse_hex(dechex(intval($tz_rule['dst_end'])), 8);
            
            $params_timezone = substr_replace($params_timezone, $ps_dst_offset, 68, 4);
            $params_timezone = substr_replace($params_timezone, $ps_timezone, 72, 8);
            $params_timezone = substr_replace($params_timezone, $ps_dst_start, 80, 8);
            $params_timezone = substr_replace($params_timezone, $ps_dst_end, 88, 8);
            
            $command = '{"main_cmd":75,"sub_cmd":9,"param_len":48,"params":"' . $params_timezone . '"}';
        } else {
            $params_cloud_publish = $settings['params_cloud_publish'];
            if(!$params_cloud_publish)
                return false;
            
            $tz_rule = $this->base->get_timezone_rule_from_timezone_id($timezone, true);
            if(!$tz_rule)
                return false;
        
            $this->base->log('timezone_log', 'tz_rule='.json_encode($tz_rule));
            
            if(intval($tz_rule['timezone'] & 0x3F) > 25)
                return false;
            
            if(intval($tz_rule['timezone']) == intval($settings['timezone']))
                return true;
            
            $ps_timezone = $this->_parse_hex(dechex(intval($tz_rule['timezone'])), 2);
            
            $params_cloud_publish = substr_replace($params_cloud_publish, $ps_timezone, 2, 2);
            
            $command = '{"main_cmd":75,"sub_cmd":40,"param_len":4,"params":"' . $params_cloud_publish . '"}';
        }
        
        $this->base->log('timezone_log', 'command='.$command);
        
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;
        
        // 保存设置
        if(!$this->save_settings($client, $device))
            return false;
        
        // 设备重启
        // 新UTC版本固件不需要重启设备
        if ($firmware < 7125023) {
            $this->set_restart($client, $device, 1);
        }
        
        // update db
        if($firmware > 7125020) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `dst_offset`='".intval($tz_rule['dst_offset'])."', `timezone`='".intval($tz_rule['timezone'])."', `dst_start`='".intval($tz_rule['dst_start'])."', `dst_end`='".intval($tz_rule['dst_end'])."', `params_timezone`='".$params_timezone."' WHERE `deviceid`='".$deviceid."'");
        } else {
            $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `timezone`='".intval($tz_rule['timezone'])."', `params_cloud_publish`='".$params_cloud_publish."' WHERE `deviceid`='".$deviceid."'");
        }
        
        return true;
    }

    // 云录制
    function set_cvr($client, $device, $cvr) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $cvr = $cvr ? 1 : 0;
        $params_status = 'FFFFFFFFFFFFFFFF';

        // request params
        $params_cvr = $cvr ? '00' : '01';
        
        $params = substr_replace($params_status, $params_cvr, 6, 2);
        $command = '{"main_cmd":75,"sub_cmd":43,"param_len":8,"params":"' . $params . '"}';
        
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `cvr`='".$cvr."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 云录制定时任务
    function set_cvr_cron($client, $device, $cvr_cron, $cvr_start, $cvr_end, $cvr_repeat) {
        if (!$client || !$device)
            return false;

        if (!preg_match('/^\d{6}$/', $cvr_start) || !preg_match('/^\d{6}$/', $cvr_end) || !preg_match('/^[01]{7}$/', $cvr_repeat))
            return false;

        if (intval($cvr_start) === intval($cvr_end))
            return false;

        $deviceid = $device['deviceid'];
        $cvr_type = $device['cvr_type'];

        $cvr_cron = $cvr_cron ? 1 : 0;
        $start_hour = abs(intval(substr($cvr_start, 0, 2)));
        $start_minute = abs(intval(substr($cvr_start, 2, 2)));
        $start_second = abs(intval(substr($cvr_start, 4, 2)));
        $end_hour = abs(intval(substr($cvr_end, 0, 2)));
        $end_minute = abs(intval(substr($cvr_end, 2, 2)));
        $end_second = abs(intval(substr($cvr_end, 4, 2)));
        $cvr_repeat = $this->_parse_hex(decbin(bindec($cvr_repeat) & 0x7F), 7);
        if($start_hour > 23 || $start_minute > 59 || $start_second > 59 || $end_hour > 23 || $end_minute > 59 || $end_second > 59)
            return false;

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings || !$settings['params_cron'] || !$settings['params_cvr_cron'])
            return false;

        $p_cron = $settings['params_cron'];
        $p_cvr_cron = $settings['params_cvr_cron'];

        // 是否是更新后的版本
        $need_cron_set_power = $this->_need_cron_set_power($device);

        // 解析云录制组成
        $params_cron_status = $cvr_cron ? '05' : '01';

        if (intval($cvr_start) > intval($cvr_end)) {
            $cron_parts = array(
                array(
                    'start' => '00'.$this->_parse_hex(dechex($start_hour), 2).$this->_parse_hex(dechex($start_minute), 2).$this->_parse_hex(dechex($start_second), 2),
                    'end' => '00'.$this->_parse_hex(dechex(23), 2).$this->_parse_hex(dechex(59), 2).$this->_parse_hex(dechex(59), 2),
                    'repeat' => $cvr_repeat
                ),
                array(
                    'start' => '00'.$this->_parse_hex(dechex(0), 2).$this->_parse_hex(dechex(0), 2).$this->_parse_hex(dechex(0), 2),
                    'end' => '00'.$this->_parse_hex(dechex($end_hour), 2).$this->_parse_hex(dechex($end_minute), 2).$this->_parse_hex(dechex($end_second), 2),
                    'repeat' => substr($cvr_repeat, 6, 1) . substr($cvr_repeat, 0, 6)
                )
            );
        } else {
            $cron_parts = array(
                array(
                    'start' => '00'.$this->_parse_hex(dechex($start_hour), 2).$this->_parse_hex(dechex($start_minute), 2).$this->_parse_hex(dechex($start_second), 2),
                    'end' => '00'.$this->_parse_hex(dechex($end_hour), 2).$this->_parse_hex(dechex($end_minute), 2).$this->_parse_hex(dechex($end_second), 2),
                    'repeat' => $cvr_repeat
                )
            );
        }

        $sql = '';

        // ###################################更新普通云录制定时任务#########################################
        // 定时任务总数
        $cron_count = 0;

        $params_cron = $req_params_cron = substr($p_cron, 2, 14);
        for ($i = 0, $n = intval(substr($p_cron, 0, 2), 16); $i < $n; $i++) { 
            $cron_item = substr($p_cron, $i * 24 + 16, 24);

            // 定时任务类型
            $cron_type = intval(substr($cron_item, 4, 4), 16);

            // 排除云录制定时任务
            if($cron_type === 16)
                continue;

            $params_cron .= $cron_item;

            // 处理新版本定时开关机状态
            if($cron_type === 2 && $need_cron_set_power && substr($cron_item, 0, 2) === '05')
                $cron_item = substr_replace($cron_item, '07', 0, 2);

            $req_params_cron .= $cron_item;
            $cron_count++;
        }
        
        // 2016年09月09日兼容固件cvr关闭仍然有定时录像的问题
        if($cvr_type != 2 || ($cvr_cron && (strlen($p_cron) - strlen($params_cron) != 2))) {
            // 定时云录制任务参数
            $params = '';

            // 持续云录制情况下将丢掉云录制定时任务
            if ($cvr_type != 2) {
                $params_cron_type = '0010';

                // 工作日
                $work_day = substr($p_cron, 3, 1);
                for ($i = 2; $i < 8; $i++) { 
                    $work_day .= substr($p_cron, $i * 2 + 1, 1);
                }

                for ($j = 0, $m = count($cron_parts); $j < $m; $j++) { 
                    $params_cron_start = $cron_parts[$j]['start'];
                    $params_cron_end = $cron_parts[$j]['end'];
                    $cron_repeat = $cron_parts[$j]['repeat'];

                    // 判断需要的定时任务
                    $repeat = array();
                    $n = bindec($cron_repeat);
                    if((~$n & 0x7F) === 0) {
                        $repeat[] = '09';
                    } elseif ((~$n & bindec($work_day)) === 0) {
                        $repeat[] = '07';
                        $other = $this->_parse_hex(decbin($n ^ bindec($work_day)), 7);
                        for ($i = 0; $i < 7; $i++) { 
                            if($other[$i] === '1') {
                                $repeat[] = $this->_parse_hex(dechex($i), 2);
                            }
                        }
                    } elseif ((~$n & (bindec($work_day) ^ 0x7F)) === 0) {
                        $repeat[] = '08';
                        $other = $this->_parse_hex(decbin($n ^ (bindec($work_day) ^ 0x7F)), 7);
                        for ($i = 0; $i < 7; $i++) { 
                            if($other[$i] === '1') {
                                $repeat[] = $this->_parse_hex(dechex($i), 2);
                            }
                        }
                    } else {
                        for ($i = 0; $i < 7; $i++) { 
                            if($cron_repeat[$i] === '1') {
                                $repeat[] = $this->_parse_hex(dechex($i), 2);
                            }
                        }
                    }
                    $n = count($repeat);
                    $cron_count += $n;
                    for ($i = 0; $i < $n; $i++) { 
                        $params .= $params_cron_status.$repeat[$i].$params_cron_type.$params_cron_start.$params_cron_end;
                    }
                }
            }
            
            $params_cron_count = $this->_parse_hex(dechex($cron_count), 2);

            $req_params_cron = $params_cron_count.$req_params_cron.$params;
            $command = '{"main_cmd":75,"sub_cmd":51,"param_len":'.(strlen($req_params_cron)/2).',"params":"'.$req_params_cron.'"}';

            // api request
            if(!$client->device_usercmd($device, $command, 1))
                return false;

            $sql .= ',`params_cron`="'.$params_cron_count.$params_cron.$params.'"';
        }
       
        // ###################################更新持续云录制定时任务#########################################
        $params_cvr_cron = substr($p_cvr_cron, 0, 24);

        // 2016年09月09日兼容固件cvr关闭仍然有定时录像的问题
        if($cvr_type == 2 || ($cvr_cron && strlen($p_cvr_cron) != strlen($params_cvr_cron))) {
            
            // 定时云录制任务参数
            $params = '';

            // 普通云录制情况下将丢掉云录制定时任务
            if ($cvr_type == 2) {
                // 工作日
                $work_day = substr($p_cvr_cron, 3, 1);
                for ($i = 2; $i < 8; $i++) { 
                    $work_day .= substr($p_cvr_cron, $i * 2 + 1, 1);
                }

                for ($j = 0, $m = count($cron_parts); $j < $m; $j++) { 
                    $params_cron_start = $cron_parts[$j]['start'];
                    $params_cron_end = $cron_parts[$j]['end'];
                    $cron_repeat = $cron_parts[$j]['repeat'];

                    // 判断需要的定时任务
                    $repeat = array();
                    $n = bindec($cron_repeat);
                    if((~$n & 0x7F) === 0) {
                        $repeat[] = '09';
                    } elseif ((~$n & bindec($work_day)) === 0) {
                        $repeat[] = '07';
                        $other = $this->_parse_hex(decbin($n ^ bindec($work_day)), 7);
                        for ($i = 0; $i < 7; $i++) { 
                            if($other[$i] === '1') {
                                $repeat[] = $this->_parse_hex(dechex($i), 2);
                            }
                        }
                    } elseif ((~$n & (bindec($work_day) ^ 0x7F)) === 0) {
                        $repeat[] = '08';
                        $other = $this->_parse_hex(decbin($n ^ (bindec($work_day) ^ 0x7F)), 7);
                        for ($i = 0; $i < 7; $i++) { 
                            if($other[$i] === '1') {
                                $repeat[] = $this->_parse_hex(dechex($i), 2);
                            }
                        }
                    } else {
                        for ($i = 0; $i < 7; $i++) { 
                            if($cron_repeat[$i] === '1') {
                                $repeat[] = $this->_parse_hex(dechex($i), 2);
                            }
                        }
                    }
                    for ($i = 0, $n = count($repeat); $i < $n; $i++) { 
                        $params .= '00'.$params_cron_status.$repeat[$i].'02'.$params_cron_start.$params_cron_end;
                    }
                }
            }

            $params_cvr_cron .= $params;
            $command = '{"main_cmd":75,"sub_cmd":6,"param_len":'.(strlen($params_cvr_cron)/2).',"params":"'.$params_cvr_cron.'"}';

            // api request
            if(!$client->device_usercmd($device, $command, 1))
                return false;

            $sql .= ',`params_cvr_cron`="'.$params_cvr_cron.'"';
        }

        // update db
        $this->db->query('UPDATE '.API_DBTABLEPRE.'devicefileds SET `cvr_cron`="'.$cvr_cron.'",`cvr_start`="'.$cvr_start.'",`cvr_end`="'.$cvr_end.'",`cvr_repeat`="'.$cvr_repeat.'"'.$sql.' WHERE `deviceid`="'.$deviceid.'"');

        return true;
    }

    // 报警定时任务
    function set_alarm_cron($client, $device, $alarm_cron, $alarm_start, $alarm_end, $alarm_repeat) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        if (!preg_match('/^\d{6}$/', $alarm_start) || !preg_match('/^\d{6}$/', $alarm_end) || !preg_match('/^[01]{7}$/', $alarm_repeat))
            return false;

        if (intval($alarm_start) === intval($alarm_end))
            return false;

        $deviceid = $device['deviceid'];
        $alarm_cron = $alarm_cron ? 1 : 0;
        $start_hour = abs(intval(substr($alarm_start, 0, 2)));
        $start_minute = abs(intval(substr($alarm_start, 2, 2)));
        $start_second = abs(intval(substr($alarm_start, 4, 2)));
        $end_hour = abs(intval(substr($alarm_end, 0, 2)));
        $end_minute = abs(intval(substr($alarm_end, 2, 2)));
        $end_second = abs(intval(substr($alarm_end, 4, 2)));
        $alarm_repeat = $this->_parse_hex(decbin(bindec($alarm_repeat) & 0x7F), 7);
        if($start_hour > 23 || $start_minute > 59 || $start_second > 59 || $end_hour > 23 || $end_minute > 59 || $end_second > 59)
            return false;

        $params_cron = $this->db->result_first('SELECT params_cron FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_cron)
            return false;

        // 是否是更新后的版本
        $need_cron_set_power = $this->_need_cron_set_power($device);

        // 定时任务总数
        $cron_count = 0;

        // 工作日
        $work_day = substr($params_cron, 3, 1);
        for ($i = 2; $i < 8; $i++) { 
            $work_day .= substr($params_cron, $i * 2 + 1, 1);
        }
        // 请求参数
        $params = $req_params = substr($params_cron, 2, 14);
        for ($i = 0, $n = intval(substr($params_cron, 0, 2), 16); $i < $n; $i++) { 
            $cron_item = substr($params_cron, $i * 24 + 16, 24);

            // 定时任务类型
            $cron_type = intval(substr($cron_item, 4, 4), 16);

            // 排除报警定时任务
            if($cron_type === 12)
                continue;

            $params .= $cron_item;

            // 处理新版本定时开关机状态
            if($cron_type === 2 && $need_cron_set_power && substr($cron_item, 0, 2) === '05')
                $cron_item = substr_replace($cron_item, '07', 0, 2);

            $req_params .= $cron_item;
            $cron_count++;
        }
        // 解析报警定时任务
        $params_cron_type = '000c';
        $params_cron_status = $alarm_cron ? '05' : '01';
        if (intval($alarm_start) > intval($alarm_end)) {
            $cron_parts = array(
                array(
                    'start' => '00'.$this->_parse_hex(dechex($start_hour), 2).$this->_parse_hex(dechex($start_minute), 2).$this->_parse_hex(dechex($start_second), 2),
                    'end' => '00'.$this->_parse_hex(dechex(23), 2).$this->_parse_hex(dechex(59), 2).$this->_parse_hex(dechex(59), 2),
                    'repeat' => $alarm_repeat
                    ),
                array(
                    'start' => '00'.$this->_parse_hex(dechex(0), 2).$this->_parse_hex(dechex(0), 2).$this->_parse_hex(dechex(0), 2),
                    'end' => '00'.$this->_parse_hex(dechex($end_hour), 2).$this->_parse_hex(dechex($end_minute), 2).$this->_parse_hex(dechex($end_second), 2),
                    'repeat' => substr($alarm_repeat, 6, 1) . substr($alarm_repeat, 0, 6)
                    )
                );
        } else {
            $cron_parts = array(
                array(
                    'start' => '00'.$this->_parse_hex(dechex($start_hour), 2).$this->_parse_hex(dechex($start_minute), 2).$this->_parse_hex(dechex($start_second), 2),
                    'end' => '00'.$this->_parse_hex(dechex($end_hour), 2).$this->_parse_hex(dechex($end_minute), 2).$this->_parse_hex(dechex($end_second), 2),
                    'repeat' => $alarm_repeat
                    )
                );
        }
        // 定时报警任务参数
        $params_alarm_cron = '';
        for ($j = 0, $m = count($cron_parts); $j < $m; $j++) { 
            $params_cron_start = $cron_parts[$j]['start'];
            $params_cron_end = $cron_parts[$j]['end'];
            $cron_repeat = $cron_parts[$j]['repeat'];

            // 判断需要的定时任务
            $repeat = array();
            $n = bindec($cron_repeat);
            if((~$n & 0x7F) === 0) {
                $repeat[] = '09';
            } elseif ((~$n & bindec($work_day)) === 0) {
                $repeat[] = '07';
                $other = $this->_parse_hex(decbin($n ^ bindec($work_day)), 7);
                for ($i = 0; $i < 7; $i++) { 
                    if($other[$i] === '1') {
                        $repeat[] = $this->_parse_hex(dechex($i), 2);
                    }
                }
            } elseif ((~$n & (bindec($work_day) ^ 0x7F)) === 0) {
                $repeat[] = '08';
                $other = $this->_parse_hex(decbin($n ^ (bindec($work_day) ^ 0x7F)), 7);
                for ($i = 0; $i < 7; $i++) { 
                    if($other[$i] === '1') {
                        $repeat[] = $this->_parse_hex(dechex($i), 2);
                    }
                }
            } else {
                for ($i = 0; $i < 7; $i++) { 
                    if($cron_repeat[$i] === '1') {
                        $repeat[] = $this->_parse_hex(dechex($i), 2);
                    }
                }
            }
            $n = count($repeat);
            $cron_count += $n;
            for ($i = 0; $i < $n; $i++) { 
                $params_alarm_cron .= $params_cron_status.$repeat[$i].$params_cron_type.$params_cron_start.$params_cron_end;
            }
        }

        $params_cron_count = $this->_parse_hex(dechex($cron_count), 2);

        $req_params = $params_cron_count . $req_params . $params_alarm_cron;
        $command = '{"main_cmd":75,"sub_cmd":51,"param_len":' . (strlen($req_params) / 2) . ',"params":"' . $req_params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $params = $params_cron_count . $params .$params_alarm_cron;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_cron`='".$alarm_cron."', `alarm_start`='".$alarm_start."', `alarm_end`='".$alarm_end."', `alarm_repeat`='".$alarm_repeat."', `params_cron`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    
    function check_partner_push($deviceid) {
        if(!$deviceid)
            return false;
        
        // 第三方允许推送
        if($this->base->partner_id && $this->base->partner && $this->base->partner['allow_alarm_push']) 
            return true;
        
        $push_service = $this->db->fetch_first('SELECT s.* FROM '.API_DBTABLEPRE.'device_partner d LEFT JOIN '.API_DBTABLEPRE.'partner p ON d.partner_id=p.partner_id LEFT JOIN '.API_DBTABLEPRE.'push_service s ON p.pushid=s.pushid WHERE d.deviceid="'.$deviceid.'" AND p.pushid>0 AND s.status>0');
        return $push_service?true:false;
    }
    
    function check_push_client() {
        $this->base->load('oauth2');
        $uid = $this->base->uid;
        $access_token = $_ENV['oauth2']->access_token;
        if($uid && $access_token) {
            $udid = $this->db->result_first("SELECT udid FROM ".API_DBTABLEPRE."oauth_access_token WHERE oauth_token='$access_token'");
            if($udid) {
                $push_client = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'push_client WHERE `udid`="'.$udid.'" AND `uid`="'.$uid.'" AND status>0 AND active>0');
                if($push_client) {
                    return true;
                }
            }
        }
        
        return false;
    }

    // 报警通知
    function set_alarm_push($client, $device, $alarm_push, $platform) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $alarm_push = $alarm_push ? 1 : 0;
        $params_status = 'FFFFFFFFFFFFFFFF';

        $p_alarm_push = $alarm_push ? $this->_parse_hex(dechex(0x81), 2) : $this->_parse_hex(dechex(0), 2);
        $params = substr_replace($params_status, $p_alarm_push, 0, 2);
        $command = '{"main_cmd":75,"sub_cmd":43,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // 老版本推送兼容
        if(!$this->_check_push_version($device)) {
            $push_client = array();

            $this->base->load('oauth2');
            $uid = $this->base->uid;
            $access_token = $_ENV['oauth2']->access_token;
            if($uid && $access_token) {
                $udid = $this->db->result_first("SELECT udid FROM ".API_DBTABLEPRE."oauth_access_token WHERE oauth_token='$access_token'");
                if($udid) {
                    $services = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."push_service WHERE `push_type`='baidu' AND `status`>0");
                    if($services) {
                        foreach($services as $s) {
                            $pushid = $s['pushid'];
                            $push_client = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'push_client WHERE `udid`="'.$udid.'" AND `uid`="'.$uid.'" AND `pushid`="'.$pushid.'"');
                            if($push_client) {
                                $service = $s;
                                break;
                            }
                        }
                    }
                }
            }

            $this->base->log('set alarm push', 'uid='.$uid.',access_token='.$access_token.',uuid='.$udid.',push_client='.json_encode($push_client));

            if(!$push_client)
                return false;

            $config = unserialize($push_client['config']);
            if(!$config || !$config['user_id'] || !$config['channel_id']) 
                return false;

            if(!$this->set_alarmlist($client, $device, $alarm_push, $udid, $config['channel_id'], $config['user_id'], $platform))
                return false;

            $check = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_alarmlist WHERE `deviceid`="'.$device['deviceid'].'" AND `cid`="'.$push_client['cid'].'"');
            if($alarm_push && !$check) {
                $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_alarmlist SET `deviceid`="'.$device['deviceid'].'", `cid`="'.$push_client['cid'].'"');
            } else if(!$alarm_push && $check) {
                $this->db->query('DELETE FROM '.API_DBTABLEPRE.'device_alarmlist WHERE `deviceid`="'.$device['deviceid'].'" AND `cid`="'.$push_client['cid'].'"');
            }
        }

        $sqladd = "";
        if($alarm_push) {
            $params_alarm_level = $this->db->result_first('SELECT params_alarm_level FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
            if($params_alarm_level) {
                if(intval(substr($params_alarm_level, 4, 2), 16) != intval(substr($params_alarm_level, 6, 2), 16)) {
                    $params = substr_replace($params_alarm_level, substr($params_alarm_level, 4, 2), 6, 2);
                    $command = '{"main_cmd":75,"sub_cmd":60,"param_len":8,"params":"' . $params . '"}';
                    // api request
                    if(!$client->device_usercmd($device, $command, 1))
                        return false;

                    $sqladd .= ", `alarm_record_level`=`alarm_move_level`, `params_alarm_level`='".$params."'";
                }
            }
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_push`='".$alarm_push."'".$sqladd." WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 报警邮件
    function set_alarm_mail($client, $device, $alarm_mail) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $alarm_mail = $alarm_mail ? 1 : 0;

        $params_alarm_mail = $this->db->result_first('SELECT params_alarm_mail FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_alarm_mail)
            return false;

        $p = intval(substr($params_alarm_mail, 54, 2), 16);
        if ($alarm_mail) {
            $p_alarm_mail = $this->_parse_hex(dechex($p | 0x1), 2);
        } else {
            $p_alarm_mail = $this->_parse_hex(dechex($p & ~0x1), 2);
        }

        $params = substr_replace($params_alarm_mail, $p_alarm_mail, 54, 2);
        $command = '{"main_cmd":75,"sub_cmd":16,"param_len":120,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_mail`='".$alarm_mail."', `params_alarm_mail`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 声音报警
    function set_alarm_audio($client, $device, $alarm_audio, $alarm_audio_level) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $alarm_audio = $alarm_audio ? 1 : 0;
        $alarm_audio_level = $alarm_audio_level ? ($alarm_audio_level > 1 ? 2 : 1) : 0;

        $params_alarm_level = $this->db->result_first('SELECT params_alarm_level FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_alarm_level)
            return false;

        // request params
        $params_alarm_audio = $alarm_audio ? '1' : '0';
        switch ($alarm_audio_level) {
            case 0: $params_alarm_audio .= '00';break;
            case 1: $params_alarm_audio .= '02';break;
            case 2: $params_alarm_audio .= '04';break;
        }

        $sqladd = "";
        $params = substr_replace($params_alarm_level, $params_alarm_audio, 1, 3);
        if(intval(substr($params, 4, 2), 16) != intval(substr($params, 6, 2), 16)) {
            $params = substr_replace($params, substr($params, 4, 2), 6, 2);
            $sqladd .= ", `alarm_record_level`=`alarm_move_level`";
        }

        $command = '{"main_cmd":75,"sub_cmd":60,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_audio`='".$alarm_audio."', `alarm_audio_level`='".$alarm_audio_level."', `params_alarm_level`='".$params."'".$sqladd." WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 移动报警
    function set_alarm_move($client, $device, $alarm_move, $alarm_move_level) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $alarm_move = $alarm_move ? 1 : 0;
        $alarm_move_level = $alarm_move_level ? ($alarm_move_level > 1 ? 2 : 1) : 0;

        $params_alarm_level = $this->db->result_first('SELECT params_alarm_level FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_alarm_level)
            return false;

        // request params
        $params_alarm_move = '7f';
        if($alarm_move) {
            switch ($alarm_move_level) {
                case 0: $params_alarm_move = '00';break;
                case 1: $params_alarm_move = '02';break;
                case 2: $params_alarm_move = '04';break;
            }
        }
        $params = substr_replace($params_alarm_level, $params_alarm_move, 4, 2);

        // 录像灵敏度和报警灵敏度相同
        if($params_alarm_move == '7f') {
            $params = substr_replace($params, '04', 6, 2);
        } else {
            $params = substr_replace($params, $params_alarm_move, 6, 2);
        }

        $command = '{"main_cmd":75,"sub_cmd":60,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_move`='".$alarm_move."', `alarm_move_level`='".$alarm_move_level."', `alarm_record_level`='".$alarm_record_level."', `params_alarm_level`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function set_alarm_zone($client, $device, $alarm_zone){
        if(!$client || !$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $params_alarm_zone = $this->alarm_params_transform($alarm_zone);
        $command = '{"main_cmd":75,"sub_cmd":88,"param_len":72,"params":"' . $params_alarm_zone . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_zone`='".$alarm_zone."', `params_alarm_zone`='".$params_alarm_zone."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    function set_ai_face_detect_zone($client, $device, $ai_face_detect_zone){
        if(!$client || !$device || !$device['deviceid'])
            return false;
        $deviceid = $device['deviceid'];
        $params_ai_face_detect_zone = $this->alarm_params_transform($ai_face_detect_zone);
        $command = '{"main_cmd":75,"sub_cmd":93,"param_len":72,"params":"' . $params_ai_face_detect_zone . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_face_detect_zone`='".$ai_face_detect_zone."', `params_ai_face_detect_zone`='".$params_ai_face_detect_zone."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function set_ai_face_detect($client, $device, $ai_face_detect){
        if(!$client || !$device || !$device['deviceid'] || $ai_face_detect===NULL)
            return false;
        $deviceid = $device['deviceid'];
        $ai_face_blur = 'ff';
        $ai_body_count = "ffffffffffffffffffffffffffff";
        $params = $this->_parse_hex($ai_face_detect, 2).$ai_face_blur.$ai_body_count;

        $command = '{"main_cmd":75,"sub_cmd":89,"param_len":16,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_face_detect`='".$ai_face_detect."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function set_server($client, $device, $api_server_host, $api_server_port){
        if(!$client || !$device || !$device['deviceid'] || ($api_server_host===NULL && $api_server_port===NULL))
            return false;
        $deviceid = $device['deviceid'];
        $data = "";
        if(!($api_server_host === NULL)){
            $data  .=" `api_server_host`='$api_server_host',";
            $api_server_host = inet_pton($api_server_host);
            $api_server_host = $this->_bin2hex($api_server_host, 8);
        }else{
            $api_server_host = str_pad("ffff",8,"f");
        }
        if(!($api_server_port === NULL)){
            if($api_server_port==0)
                return false;
            $data  .=" `api_server_port`='$api_server_port',";
            $api_server_port = base_convert($api_server_port, 10, 16);
            $api_server_port = str_pad($api_server_port, 4, '0', STR_PAD_LEFT);
        }else{
            $api_server_port = 'ffff';
        }
        $temp = str_pad("0",52,"0");
        $data = substr($data, 0, strlen($data)-1);
        $params = $api_server_host.$api_server_port.$temp;
        $command = '{"main_cmd":75,"sub_cmd":95,"param_len":32,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET $data WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function set_ai_face_blur($client, $device, $ai_face_blur){
        if(!$client || !$device || !$device['deviceid'] || $ai_face_blur===NULL)
            return false;
        $deviceid = $device['deviceid'];
        $ai_face_detect = 'ff';
        $ai_body_count = "ffffffffffffffffffffffffffff";
        $params = $ai_face_detect.$this->_parse_hex($ai_face_blur, 2).$ai_body_count;
        $command = '{"main_cmd":75,"sub_cmd":89,"param_len":16,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_face_blur`='".$ai_face_blur."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function set_ai_body_count($client, $device, $ai_body_count){
        if(!$client || !$device || !$device['deviceid'] || $ai_body_count===NULL)
            return false;
        $deviceid = $device['deviceid'];
        $ai_face_detect = 'ff';
        $ai_face_blur = 'ff';

        if(strlen($ai_body_count) !== 36)
            return false;
        $ai_count_direction = substr($ai_body_count, 2, 2);
        $ai_body_count_param = substr($ai_body_count, 0, 28);
        // if($ai_count_direction == "11"){
        //     $ai_body_count_param = substr($ai_body_count, 0, 2)."03".substr($ai_body_count, 4, 24);
        // }
        $params = $ai_face_detect.$ai_face_blur.$ai_body_count_param;
        $command = '{"main_cmd":75,"sub_cmd":89,"param_len":16,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `ai_count_direction`='".$ai_count_direction."', `ai_body_count`='".$ai_body_count."' WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function set_ai_upload($client, $device, $ai_upload_host, $ai_upload_port, $ai_face_host, $ai_face_port, $ai_face_group_id, $ai_face_group_type){
        if(!$client || !$device || !$device['deviceid'] || ($ai_upload_host===NULL && $ai_upload_port===NULL && $ai_face_host===NULL && $ai_face_port===NULL && $ai_face_group_id===NULL && $ai_face_group_type===NULL) )
            return false;
        $deviceid = $device['deviceid'];

        $data = "";
        if(!($ai_upload_host === NULL)){
            $data  .=" `ai_upload_host`='$ai_upload_host',";
            $ai_upload_host = $this->_bin2hex($ai_upload_host, 122);
        }else{
            $ai_upload_host = str_pad("ffff",122,"0");
        }
        if(!($ai_upload_port === NULL)){
            if($ai_upload_port==0)
                return false;
            $data  .=" `ai_upload_port`='$ai_upload_port',";
            $ai_upload_port = base_convert($ai_upload_port, 10, 16);
            $ai_upload_port = str_pad($ai_upload_port, 4, '0', STR_PAD_LEFT);
        }else{
            $ai_upload_port = 'ffff';
        }
        if(!($ai_face_host === NULL)){
            if($ai_face_port === NULL || $ai_face_group_id === NULL)
                return false;
            $data  .=" `ai_face_host`='$ai_face_host',";
            $ai_face_host = $this->_bin2hex($ai_face_host, 128);
        }else{
            $ai_face_host = str_pad("ffff",128,"0");
        }
        if(!($ai_face_port === NULL)){
            if($ai_face_host === NULL || $ai_face_group_id === NULL)
                return false;
            $data  .=" `ai_face_port`='$ai_face_port',";
            $ai_face_port = base_convert($ai_face_port, 10, 16);
            $ai_face_port = str_pad($ai_face_port, 4, '0', STR_PAD_LEFT);
        }else{
            $ai_face_port = 'ffff';
        }
        if(!($ai_face_group_id === NULL)){
            if($ai_face_host === NULL || $ai_face_port === NULL)
                return false;
            $data  .=" `ai_face_group_id`='$ai_face_group_id',";
            $ai_face_group_id = $this->_bin2hex($ai_face_group_id, 200);
        }else{
            $ai_face_group_id = str_pad("ffff",200,"0");
        }
        if(!($ai_face_group_type === NULL)){
            $data  .=" `ai_face_group_type`='$ai_face_group_type',";
            $ai_face_group_type = base_convert($ai_face_group_type, 10, 16);
            $ai_face_group_type = str_pad($ai_face_group_type, 2, '0', STR_PAD_LEFT);
        }else{
            $ai_face_group_type = 'ff';
        }
        $data = substr($data, 0, strlen($data)-1);
        $params = $ai_upload_port.$ai_face_group_type.$ai_upload_host.$ai_face_port.$ai_face_host.$ai_face_group_id;
        $command = '{"main_cmd":75,"sub_cmd":91,"param_len":230,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET $data WHERE `deviceid`='".$deviceid."'");
        return true;
    }

    function set_ai_face($client, $device, $ai_api_key, $ai_secret_key, $ai_face_ori, $ai_face_pps, $ai_face_min_width, $ai_face_min_height, $ai_face_reliability, $ai_face_retention, $ai_face_position, $ai_face_frame){
        if(!$client || !$device || !$device['deviceid'] )
            return false;
        $deviceid = $device['deviceid'];

        $data = '';
        if(!($ai_api_key === NULL)){
            $data  .=" `ai_api_key`='$ai_api_key',";
            $ai_api_key = $this->_bin2hex($ai_api_key, 64);
        }else{
            $ai_api_key = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
        }
        if(!($ai_secret_key === NULL)){
            $data  .=" `ai_secret_key`='$ai_secret_key',";
            $ai_secret_key = $this->_bin2hex($ai_secret_key, 64);
        }else{
            $ai_secret_key = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
        }

        if(!($ai_face_ori === NULL)){
            $data  .=" `ai_face_ori`='$ai_face_ori',";
            $ai_face_ori = base_convert($ai_face_ori, 10, 16);
            $ai_face_ori = $this->_parse_hex($ai_face_ori, 2);
            // $ai_face_ori = $this->_bin2hex($ai_face_ori, 2);
        }else{
            $ai_face_ori = 'ff';
        }
        if(!($ai_face_pps === NULL)){
            $data  .=" `ai_face_pps`='$ai_face_pps',";
            $ai_face_pps = base_convert($ai_face_pps, 10, 16);
            $ai_face_pps = $this->_parse_hex($ai_face_pps, 2);
            // $ai_face_pps = $this->_bin2hex($ai_face_pps, 2);
        }else{
            $ai_face_pps = 'ff';
        }

        if(!($ai_face_position === NULL)){
            $data  .=" `ai_face_position`='$ai_face_position',";
            $ai_face_position = base_convert($ai_face_position, 10, 16);
            $ai_face_position = $this->_parse_hex($ai_face_position, 2);
            // $ai_face_pps = $this->_bin2hex($ai_face_pps, 2);
        }else{
            $ai_face_position = 'ff';
        }
        if(!($ai_face_frame === NULL)){
            $data  .=" `ai_face_frame`='$ai_face_frame',";
            $ai_face_frame = base_convert($ai_face_frame, 10, 16);
            $ai_face_frame = $this->_parse_hex($ai_face_frame, 4);
            // $ai_face_pps = $this->_bin2hex($ai_face_pps, 2);
        }else{
            $ai_face_frame = 'ffff';
        }

        if(!($ai_face_min_width === NULL)){
            $data  .=" `ai_face_min_width`='$ai_face_min_width',";
            $ai_face_min_width = base_convert($ai_face_min_width, 10, 16);
            $ai_face_min_width = $this->_parse_hex($ai_face_min_width, 4);
            // $ai_face_min_width = $this->_bin2hex($ai_face_min_width, 4);
        }else{
            $ai_face_min_width = 'ffff';
        }
        if(!($ai_face_min_height === NULL)){
            $data  .=" `ai_face_min_height`='$ai_face_min_height',";
            $ai_face_min_height = base_convert($ai_face_min_height, 10, 16);
            $ai_face_min_height = $this->_parse_hex($ai_face_min_height, 4);
            // $ai_face_min_height = $this->_bin2hex($ai_face_min_height, 4);
        }else{
            $ai_face_min_height = 'ffff';
        }

        if(!($ai_face_reliability === NULL)){
            $data  .=" `ai_face_reliability`='$ai_face_reliability',";
            $ai_face_reliability = base_convert($ai_face_reliability, 10, 16);
            $ai_face_reliability = $this->_parse_hex($ai_face_reliability, 4);
            // $ai_face_reliability = $this->_bin2hex($ai_face_reliability, 4);
        }else{
            $ai_face_reliability = 'ffff';
        }
        if(!($ai_face_retention === NULL)){
            $data  .=" `ai_face_retention`='$ai_face_retention',";
            $ai_face_retention = base_convert($ai_face_retention, 10, 16);
            $ai_face_retention = $this->_parse_hex($ai_face_retention, 4);
            // $ai_face_retention = $this->_bin2hex($ai_face_retention, 4);
        }else{
            $ai_face_retention = 'ffff';
        }
        //保留
        $ai_face_group_id = 'ffffffff';

        $data = substr($data, 0, strlen($data)-1);
       
        $params = $ai_api_key.$ai_secret_key.$ai_face_ori.$ai_face_pps.$ai_face_position."00".$ai_face_frame.$ai_face_min_width.$ai_face_min_height.$ai_face_reliability.$ai_face_retention.$ai_face_group_id;
        $command = '{"main_cmd":75,"sub_cmd":90,"param_len":82,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;
        if($data){
            $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET $data WHERE `deviceid`='".$deviceid."'");
            return true;
        }
        return false;
    }

    function set_ai_item($client, $device, $ai_lan, $ai_face_type, $ai_face_storage){
        if(!$client || !$device || !$device['deviceid'] )
            return false;
        $deviceid = $device['deviceid'];

        $data = '';
        if(!($ai_lan === NULL)){
            $data  .=" `ai_lan`='$ai_lan',";
            if(intval($ai_lan) == 1){
                $ai_lan = 128;
            }
        }else{
            $ai_lan = $this->db->result_first("SELECT ai_lan FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='".$deviceid."'");
            if($ai_lan){
                $ai_lan = 128;
            }else{
                $ai_lan = 0;
            }
        }
        if(!($ai_face_storage === NULL)){
            $data  .=" `ai_face_storage`='$ai_face_storage',";
            if(intval($ai_face_storage) == 1){
                $ai_face_storage = 16;
            }
        }else{
            $ai_face_storage = $this->db->result_first("SELECT ai_face_storage FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='".$deviceid."'");
            if($ai_face_storage){
                $ai_face_storage = 16;
            }else{
                $ai_face_storage = 0;
            }
        }
        $ai_param = intval($ai_lan)+intval($ai_face_storage);
        $ai_param = str_pad(base_convert($ai_param, 10, 16), 2, '0', STR_PAD_LEFT);

        if(!($ai_face_type === NULL)){
            $data  .=" `ai_face_type`='$ai_face_type',";
            $ai_face_type = str_pad(base_convert($ai_face_type, 10, 16), 2, '0', STR_PAD_LEFT);
        }else{
            $ai_face_type = 'ff';
        }

        //保留
        $ai_param_header = '722a3c6e';
        
        $data = substr($data, 0, strlen($data)-1);
       
        $params = $ai_param_header.$ai_param.$ai_face_type;
        $command = '{"main_cmd":75,"sub_cmd":92,"param_len":6,"params":"' . $params . '"}';
        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;
        if($data){
            $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET $data WHERE `deviceid`='".$deviceid."'");
            return true;
        }
        return false;
    }

    // 设置手机报警推送
    function set_alarmlist($client, $device, $alarm_push, $udid, $channel_id, $user_id, $platform) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        if(!$udid || !$channel_id || !$user_id)
            return false;

        $deviceid = $device['deviceid'];
        $alarm_push = $alarm_push ? 1 : 0;

        if ($alarm_push) {
            $params = $this->_bin2hex($udid, 64).$this->_bin2hex($user_id, 64).$this->_bin2hex($channel_id, 64);
            if($platform == 4) $params = substr_replace($params, '0004', 60, 4);
            $command = '{"main_cmd":72,"sub_cmd":5,"param_len":96,"params":"' . $params . '"}';
        } else {
            $params = $this->_bin2hex($udid, 64);
            if($platform == 4) $params = substr_replace($params, '0004', 60, 4);
            $command = '{"main_cmd":72,"sub_cmd":6,"param_len":32,"params":"' . $params . '"}';
        }

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        return true;
    }

    // 保存设置
    function save_settings($client, $device) {
        if(!$client || !$device)
            return false;

        $command = '{"main_cmd":65,"sub_cmd":3,"param_len":0,"params":0}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        return true;
    }

    // 重启设备
    function set_restart($client, $device, $restart) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $restart = $restart ? 1 : 0;

        if(!$restart)
            return false;

        $command = '{"main_cmd":65,"sub_cmd":1,"param_len":0,"params":0}';

        // api request
        if(!$client->device_usercmd($device, $command, 0))
            return false;

        return true;
    }
    
    // 清除报警
    function set_clearalarm($client, $device, $clearalarm) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $clearalarm = $clearalarm ? 1 : 0;

        if(!$clearalarm)
            return false;

        $command = '{"main_cmd":65,"sub_cmd":10,"param_len":0,"params":0}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        return true;
    }
    
    function set_volume_talk($client, $device, $volume_talk) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $volume_talk = intval($volume_talk);
        
        $command = '{"main_cmd":75,"sub_cmd":87,"param_len":4,"params":"' . $this->_parse_hex(dechex($volume_talk), 2) . '010000"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `volume_talk`='".$volume_talk."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }
    
    function set_volume_media($client, $device, $volume_media) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $volume_media = intval($volume_media);
        
        $command = '{"main_cmd":75,"sub_cmd":86,"param_len":4,"params":"' . $this->_parse_hex(dechex($volume_media), 2) . '010000"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `volume_media`='".$volume_media."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function _parse_hex($hex, $byte) {
        $len = strlen($hex);
        if ($len > $byte) {
            $hex = substr($hex, 0, $byte);
        } else {
            for ($i = $byte - $len; $i > 0; $i--) { 
                $hex = '0'.$hex;
            }
        }
        return $hex;
    }

    // 16进制转字符串
    function _hex2bin($hex, $start, $num) {
        $sub = substr($hex, $start, $num);
        $str = '';
        for ($i = 0, $n = strlen($sub); $i < $n; $i += 2) {
            $temp = substr($sub, $i, 2);
            if ($temp === '00') break;
            $str .= chr(intval($temp, 16));
        }
        return addslashes($str);
    }

    // 字符串转16进制
    function _bin2hex($str, $byte) {
        $hex = bin2hex($str);
        $len = strlen($hex);
        if ($len > $byte) {
            $hex = substr($hex, 0, $byte);
        } else {
            for ($i = $byte - $len; $i > 0; $i--) { 
                $hex .= '0';
            }
        }
        return $hex;
    }

    function _main_cmd($command) {
        if(!$command)
            return false;
        $data = json_decode($command, true);
        $main_cmd = $data['main_cmd'];
        return $main_cmd;
    }

    function _sub_cmd($command) {
        if(!$command)
            return false;
        $data = json_decode($command, true);
        $sub_cmd = $data['sub_cmd'];
        return $sub_cmd;
    }

    function _cmd_params_length($command) {
        if(!$command)
            return false;
        $data = json_decode($command, true);
        $param_len = $data['param_len'];
        return $param_len;
    }

    function _cmd_params($command) {
        if(!$command)
            return false;
        $data = json_decode($command, true);
        $params = $data['params'];
        return $params;
    }
    
    function _format_settings($device, $allows, $type, $settings) {
        $result = array();

        //power处理
        if(isset($settings['power'])) $settings['power'] = (intval($device['status']&0x4) == 0)?'0':'1';

        // 老版本推送兼容
        if($type == 'alarm' || $type == 'all') {
            if(!$this->_check_push_version($device) && $settings['alarm_push']) {
                $settings['alarm_push'] = $this->_check_alarmlist($device) ? '1' : '0';
            }
        }

        if($type == 'status') {
            foreach($allows['status'] as $key) {
                $result[$key] = $settings[$key];
            }
            foreach($allows['email'] as $key) {
                $result['email'][$key] = $settings[$key];
            }
        } else if($type == 'volume') {
            $result['volume'] = $settings['volume'];
            if($settings['volume']) {
                $result['volume_talk'] = $settings['volume_talk'];
                if($settings['media']) {
                    $result['volume_media'] = $settings['volume_media'];
                }
            }
        } else if($type == 'alarm') {
            foreach($allows['alarm'] as $key) {
                if($key == 'alarm_count' && !$settings[$key]) $settings[$key] = '1';
                if($key == 'alarm_interval' && !$settings[$key]) $settings[$key] = '30';
                $result[$key] = $settings[$key];
            }
        } else if($type == 'all') {
            foreach($allows['info'] as $key) {
                $result['info'][$key] = $settings[$key];
            }
            foreach($allows['status'] as $key) {
                $result['status'][$key] = $settings[$key];
            }
            foreach($allows['email'] as $key) {
                $result['status']['email'][$key] = $settings[$key];
            }
            foreach($allows['power'] as $key) {
                $result['power'][$key] = $settings[$key];
            }
            foreach($allows['cvr'] as $key) {
                $result['cvr'][$key] = $settings[$key];
            }
            foreach($allows['alarm'] as $key) {
                if($key == 'alarm_count' && !$settings[$key]) $settings[$key] = '1';
                if($key == 'alarm_interval' && !$settings[$key]) $settings[$key] = '30';
                $result['alarm'][$key] = $settings[$key];
            }
        } else {
            $result = $settings;
        }

        if($type == 'info') {
            $result = array_merge(array('deviceid' => $device['deviceid']), $result);
        } else if($type == 'all') {
            $result['info'] = array_merge(array('deviceid' => $device['deviceid']), $result['info']);
        }
        
        $platform = $settings['platform'];
        if(!$platform) {
            $platform = $this->db->result_first("SELECT platform FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='".$device['deviceid']."'");
        }
        
        // 小球处理
        if($platform && ($platform == 100 || $platform == 180)) {
            if($type == 'alarm') {
                $result['alarm_audio'] = "-1";
                $result['alarm_audio_level'] = "-1";
            } else if($type == 'all') {
                $result['alarm']['alarm_audio'] = "-1";
                $result['alarm']['alarm_audio_level'] = "-1";
            }
        }
        //人脸设置处理
        if($type == 'ai_set'){
            $result["ai_upload_port"]=strval($settings['ai_upload_port']);
            $result["ai_upload_host"]=strval($settings['ai_upload_host']);
            $result["ai_face_port"]=strval($settings['ai_face_port']);
            $result["ai_face_host"]=strval($settings['ai_face_host']);
            $result["ai_api_key"]=strval($settings['ai_api_key']);
            $result["ai_secret_key"]=strval($settings['ai_secret_key']);
            $result["ai_face_ori"]=intval($settings['ai_face_ori']);
            $result["ai_face_pps"]=intval($settings['ai_face_pps']);
            $result["ai_face_position"]=intval($settings['ai_face_position']);
            $result["ai_face_frame"]=intval($settings['ai_face_frame']);
            $result["ai_face_min_width"]=intval($settings['ai_face_min_width']);
            $result["ai_face_min_height"]=intval($settings['ai_face_min_height']);
            $result["ai_face_reliability"]=intval($settings['ai_face_reliability']);
            $result["ai_face_retention"]=intval($settings['ai_face_retention']);
            $result["ai_face_group_id"]=strval($settings['ai_face_group_id']);
            $result["ai_lan"]=intval($settings['ai_lan']);
            $result["ai_face_type"]=intval($settings['ai_face_type']);
            $result["ai_face_group_type"]=intval($settings['ai_face_group_type']);
            $result["ai_face_storage"]=intval($settings['ai_face_storage']);
        }
        //server处理
        if($type == 'server'){
            $result["api_server_host"]=strval($settings['api_server_host']);
            $result["api_server_port"]=strval($settings['api_server_port']);
        }
        return $result;
    }

    function _check_alarmlist($device) {
        $this->base->load('oauth2');
        $uid = $_ENV['oauth2']->uid;
        $access_token = $_ENV['oauth2']->access_token;
        if($uid && $access_token) {
            $udid = $this->db->result_first("SELECT udid FROM ".API_DBTABLEPRE."oauth_access_token WHERE oauth_token='$access_token'");
            if($udid) {
                $clients = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'push_client WHERE `udid`="'.$udid.'" AND `uid`="'.$uid.'" AND `status`>0');
                foreach($clients as $push_client) {
                    $service = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."push_service WHERE pushid='".$push_client['pushid']."'");
                    if(!$service)
                        return true;

                    if($service['push_type'] == 'baidu') {
                        $check = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_alarmlist WHERE `deviceid`="'.$device['deviceid'].'" AND `cid`="'.$push_client['cid'].'"');
                        if($check) return true;
                    } else {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    function _get_api_client($connect_type) {
        if($connect_type == 0) {
            return $this->base->load('server');
        } else {
            return $this->base->load_connect($connect_type);
        }
    }

    function device_register($uid, $deviceid, $connect_type, $desc, $appid, $client_id, $timezone, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude) {
        if (!$uid || !$deviceid || !$desc)
            return false;

        $stream_id = '';
        $connect_cid = '';

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);

            if(!$client)
                return false;

            $ret = $client->device_register($uid, $deviceid, $desc);

            if(!$ret)
                return false;

            $this->base->log('device register', 'connect ret='.json_encode($ret));

            if(is_array($ret)) {
                if(isset($ret['stream_id']) && $ret['stream_id'])
                    $stream_id = $ret['stream_id'];
                if(isset($ret['connect_cid']) && $ret['connect_cid'])
                    $connect_cid = $ret['connect_cid'];
            }   
        }

        $device = $this->get_device_by_did($deviceid);
        if($device) {
            $device = $this->update_device($uid, $deviceid, $connect_type, $connect_cid, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude);
        } else {
            $regip = $this->base->onlineip;
            $device = $this->add_device($uid, $deviceid, $connect_type, $connect_cid, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location, $location_type, $location_name, $location_address, $location_latitude, $location_longitude);
        }
        
        // 设备注册活动
        $this->business_activity_record($device);

        return $device;
    }

    function business_activity_record($device) {
        $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'device_business_activity WHERE status=1 AND starttime<'.$this->base->time.' AND endtime>'.$this->base->time);
        if (!$arr)
            return false;

        foreach ($arr as $activity) {
            // 筛选出一个未使用的校验码
            $record = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_business_record WHERE aid='.$activity['aid'].' AND deviceid="'.$device['deviceid'].'" AND uid=0 LIMIT 1');
            if ($record) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_business_record SET uid='.$device['uid'].',status='.(2-$activity['notification']).',dateline='.$this->base->time.',lastupdate='.$this->base->time.' WHERE rid='.$record['rid']);
            }
        }

        return true;
    }

    function business_activity_message($device) {
        $uid = $device['uid'];
        $appid = $device['appid'];
        
        $arr = $this->db->fetch_all('SELECT a.rid,a.sn,b.notification,b.notification_type,b.notification_templateid FROM '.API_DBTABLEPRE.'device_business_record a LEFT JOIN '.API_DBTABLEPRE.'device_business_activity b ON a.aid=b.aid WHERE a.deviceid="'.$device['deviceid'].'" AND a.uid='.$device['uid'].' AND a.status=1 AND b.status=1 AND b.starttime<'.$this->base->time.' AND b.endtime>'.$this->base->time);
        if (!$arr)
            return false;

        // 加入测试功能
        /*
        $this->base->load('user');
        $dev = $_ENV['user']->get_dev_by_uid($uid);
        $debug = !$dev || !$dev['message'] ? 0 : 1;
        */
        $debug = 0;
        

        $this->base->load('service');
        $msg_header = $_ENV['service']->service_message_header('10000001', $uid);
        $thistime = $this->base->time;

        foreach ($arr as $record) {
            $msgid = date('YmdHis', $thistime++);
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'service_message SET msgid="'.$msgid.'",ptype=3,pid='.$device['uid'].',msgtype='.$record['notification_type'].',sid="'.$msg_header['fromid'].'",mtype=2,mid="'.$record['rid'].'",status=2,push_msg=1,push_alert='.$record['notification'].',dateline='.$thistime.',lastupdate='.$thistime);

            if ($record['notification']) {
                switch ($record['notification_type']) {
                    case '10': // 图文通知
                        $msg_body = $this->db->fetch_first('SELECT title,intro FROM '.API_DBTABLEPRE.'notification_template WHERE tid="'.$record['notification_templateid'].'"');
                        if ($msg_body) {
                            $temp = $this->db->fetch_first('SELECT pathname,filename FROM '.API_DBTABLEPRE.'notification_template_file WHERE tid="'.$record['notification_templateid'].'" AND type="template_thumb" LIMIT 1');
                            $msg_body['msgid'] = $msgid;
                            $msg_body['msg_type'] = 10;
                            $msg_body['time'] = $thistime;
                            $msg_body['image'] = $temp ? 'http://7xoa8w.com2.z0.glb.qiniucdn.com/'.$temp['pathname'].$temp['filename'] : '';
                            $msg_body['url'] = 'https://www.iermu.com/business/activity/'.$record['rid'].'/'.$record['sn'].'/'.md5($device['deviceid'].$device['uid']);
                            $message = array_merge($msg_header, $msg_body);
                        } else {
                            $message = array();
                        }
                        break;
                    
                    default:
                        $message = array();
                        break;
                }
                
                $option = array('push_alert'=>1, 'uids'=>array($uid), 'dev'=>$debug);

                // $this->send_message($message, $option);
                // POST http://115.28.56.133:6001/service/message {message: message, option: option}
                
                // 推送
                $push_server = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid='$appid' AND server_type='2' AND status>0");
                if($push_server && $push_server['api']) {
                    $url = $push_server['api'].'service/message';
                    $params = array(
                        'message' => json_encode($message),
                        'option' => json_encode($option)
                    );
                    
                    $this->base->log('business_activity_message push', 'url='.$url.', parmas='.json_encode($params));
                    $ret = $this->_request($url, $params, 'POST');
                    $this->base->log('business_activity_message push', 'ret='.json_encode($ret));
        
                    if(!$ret || $ret['http_code'] != 200) {
                        $this->base->log('business_activity_message push failed.');
                        //return false;
                    } else {
                        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_business_record SET status=2, lastupdate='.$this->base->time.' WHERE rid='.$record['rid']);
                    }
                }
                
                // 发送短信
                $notify_service = $this->base->load_notify(0, 1);
                if($notify_service) {
                    $this->base->load('web');
                    $user = $_ENV['user']->get_user_by_uid($uid);
                    if($user && $user['mobile']) {
                        $link = $_ENV['web']->shortLink('https://www.wangxinlicai.com/user/register?type=h5&client_id=db6c30dddd42e4343c82713e&redirect_uri=http%3A%2F%2Fiermu.wangxinlicai.com%2Faccount%2Flogin%3Ffrom_register%3D1&euid='.$record['sn']);
                        $link = str_replace("http://", "", $link);
                        
                        $vars = json_encode(array(
                            "%link%" => $link
                        ));
                        $template = '5645';
                        
                        $notify_service->_send_template_sms_by_service($user['countrycode'], $user['mobile'], $template, $vars);
                    }
                }
            }
        }
        
        return true;
    }
    
    function _request($url, $params = array(), $httpMethod = 'GET') {
        $ch = curl_init();
        
        $headers = array();
        if (isset($params['host'])) {
            $headers[] = 'X-FORWARDED-FOR: ' . $params['host'] . '; CLIENT-IP: ' . $params['host'];
            unset($params['host']);
        }

        $curl_opts = array(
            CURLOPT_CONNECTTIMEOUT  => 5,
            CURLOPT_TIMEOUT         => 10,
            CURLOPT_USERAGENT       => 'iermu api server/1.0',
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_FOLLOWLOCATION  => false,
        );

        if (stripos($url, 'https://') === 0) {
            $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
        }
    
        if (strtoupper($httpMethod) === 'GET') {
            $query = http_build_query($params, '', '&');
            $delimiter = strpos($url, '?') === false ? '?' : '&';
            $curl_opts[CURLOPT_URL] = $url . $delimiter . $query;
            $curl_opts[CURLOPT_POST] = false;
        } else {
            $body = http_build_query($params, '', '&');
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_POSTFIELDS] = $body;
        }

        if (!empty($headers)) {
            $curl_opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $curl_opts);
        $result = curl_exec($ch);
        
        $info = curl_getinfo($ch);
        $this->base->log('push _request info', 'errorno='.curl_errno($ch).', errormsg='.curl_error($ch).', url='.$info['url'].',total_time='.$info['total_time'].', namelookup_time='.$info['namelookup_time'].', connect_time='.$info['connect_time'].', pretransfer_time='.$info['pretransfer_time'].',starttransfer_time='.$info['starttransfer_time']);
        
        if ($result === false) {
            $this->base->log('device _request error', 'result false');
            curl_close($ch);
            return false;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->base->log('device _request finished', 'http_code='.$http_code.',result='.$result);
        
        return array('http_code' => $http_code, 'data' => json_decode($result, true)); 
    }

    function device_init($device) {
        $this->base->log('device register', 'init start');
        if(!$device || !$device['deviceid']) 
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $info = $client->device_init_info($device);
        $this->base->log('device register', 'init info ret='.json_encode($info));
        if($info && is_array($info)) {
            if(isset($info['cvr']) && isset($info['cvr']['cvr_day']) && isset($info['cvr']['cvr_end_time'])) {
                $this->update_cvr($deviceid, $info['cvr']['cvr_type'], $info['cvr']['cvr_day'], $info['cvr']['cvr_end_time']);
            }
        }
        
        // 设置时区
        //if($device['timezone']) {
        //    $this->update_timezone($device, $device['timezone']);
        //}

        return true;
    }
    
    function alarmtime($device) {
        if(!$device)
            return false;
        
        $cvr_day = intval($device['cvr_day']);
        $cvr_day = $cvr_day?$cvr_day:7;
        $end = $this->base->time;
        $start = $this->base->time - $cvr_day*24*3600;
        $start = $this->base->day_start_time($start, $device['timezone']);
        return array('start'=>$start, 'end'=>$end);
    }
    
    function listalarm($device, $type, $sensorid, $sensortype, $actionid, $actiontype, $starttime=0, $endtime=0, $page=1, $count=10) {
        if(!$device || !$device['deviceid'])
            return false;
        
        if($starttime>$endtime)
            return false;
        
        // TODO: 清理过期图片
        
        $deviceid = $device['deviceid'];
        $uid = $device['uid'];
        $connect_type = $device['connect_type'];
        $cvr_day = $device['cvr_day'];
        $cvr_end_time = $device['cvr_end_time'];
        $timezone = $device['timezone'];
        
        $alarm_tableid = $device['alarm_tableid'];
        if(!$alarm_tableid) {
            $alarm_tableid = $this->gen_alarm_tableid($deviceid);
            if(!$alarm_tableid)
                return false;
            
            $device['alarm_tableid'] = $alarm_tableid;
        }
        
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            if($client->_need_sync_alarm()) {
                $client->sync_alarm($device, $this->alarmtime($device));
                $device = $this->get_device_by_did($deviceid);
            }
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $list = array();
        
        $alarm_index_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid;
        $where = "WHERE deviceid='$deviceid' AND uid='$uid'";
        $orderby = "ORDER BY time DESC";
        
        $sqladd = '';
        
        if($type !== NULL) {
            $type = intval($type);
            $sqladd .= " AND type='$type'";
            
            if($sensorid !== NULL) {
                $sqladd .= " AND sensorid='$sensorid'";
            }
            if($sensortype !== NULL) {
                $sensortype = intval($sensortype);
                $sqladd .= " AND sensortype='$sensortype'";
            }
            if($actionid !== NULL) {
                $sqladd .= " AND actionid='$actionid'";
            }
            if($actiontype !== NULL) {
                $actiontype = intval($actiontype);
                $sqladd .= " AND actiontype='$actiontype'";
            }
        }
        
        if($starttime || $endtime) {
            $sqladd .= " AND (time BETWEEN $starttime AND $endtime)";
        }
        
        $where .= $sqladd;
        
        $total = $this->db->result_first("SELECT count(*) FROM $alarm_index_table $where");
        
        // 纠正统计错误
        if($sqladd == '' && $total != $device['alarmnum']) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET alarmnum='$total' WHERE deviceid='$deviceid'");
            $device['alarmnum'] = $total;
        }
        
        $pages = $this->base->page_get_page($page, $count, $total);
        $limit = ' LIMIT '.$pages['start'].', '.$count;
        
        if($total) {
            $data = $this->db->fetch_all("SELECT * FROM $alarm_index_table $where $orderby $limit");
            foreach($data as $value) {
                if($value['table'] && $value['aid']) {
                    $alarm = $this->get_alarm_by_aid($alarm_tableid, $value['table'], $value['aid']);
                    if($alarm) {
                        $url = $this->get_alarm_temp_url($device, $alarm);
                        if($url) {
                            $data = array(
                                'time' => intval($alarm['time']),
                                'type' => intval($alarm['type']),
                                'url' => $url
                            );
                            
                            if($alarm['expiretime'])
                                $data['expiretime'] = intval($alarm['expiretime']);
                            
                            // 传感器
                            if($alarm['type'] == 0) {
                                $data['sensorid'] = strval($alarm['sensorid']);
                                $data['sensortype'] = intval($alarm['sensortype']);
                                $data['actionid'] = strval($alarm['actionid']);
                                $data['actiontype'] = intval($alarm['actiontype']);
                            }
                            $list[] = $data;
                        }
                    }
                }
            }
        }
        
        $result = array();
        $sum = $this->get_alarm_sum($device);
        if($sum) {
            $result['sum'] = $sum;
        }
        $result['page'] = $pages['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    function dropalarm($device, $list, $time) {
        if(!$device || !$device['deviceid'])
            return false;
        
        if(!$list && !$time)
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $alarm_tableid = $device['alarm_tableid'];
        if(!$alarm_tableid)
            return false;
        
        $alarm_index_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid;
        
        $del = 0;
        $del_types = array();
        $del_sensortypes = array();
        
        if($list) {
            $del_types = array();
            $del_sensortypes = array();
            
            foreach($list as $data) {
                $type = intval($data['type']);
                $time = intval($data['time']);
                
                $wheresql = "";
                $wheresql .= "WHERE `deviceid`='$deviceid' AND `type`='$type' AND `time`='$time'";
                
                if($type == 0) {
                    $sensorid = strval($data['sensorid']);
                    $sensortype = intval($data['sensortype']);
                    $actionid = strval($data['actionid']);
                    $actiontype = intval($data['actiontype']);
                    
                    $wheresql .= " AND `sensorid`='$sensorid' AND `sensortype`='$sensortype' AND `actionid`='$actionid' AND `actiontype`='$actiontype'";
                }
                
                $pic = $this->db->fetch_first("SELECT * FROM $alarm_index_table $wheresql");
                if($pic) {
                    $alarm_file_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid.'_'.$pic['table'];
                    $this->db->query("UPDATE $alarm_file_table SET `delstatus`=1 WHERE `aid`='".$pic['aid']."'");
                    
                    if(isset($del_types[$pic['type']])) {
                        $del_types[$pic['type']] += 1;
                    } else {
                        $del_types[$pic['type']] = 1;
                    }
            
                    if($pic['type'] == 0) {
                        if(isset($del_sensortypes[$pic['sensortype']])) {
                            $del_sensortypes[$pic['sensortype']] += 1;
                        } else {
                            $del_sensortypes[$pic['sensortype']] = 1;
                        }
                    }
                    
                    $this->db->query("DELETE FROM $alarm_index_table $wheresql");
                    $del++;
                }
            }
        } else {
            $times = "'";
            $times .= join("', '", $time);
            $times .= "'";
            
            $data = $this->db->fetch_all("SELECT * FROM $alarm_index_table WHERE `deviceid`='$deviceid' AND `uid`='$uid' AND `time` in ($times)");
            foreach($data as $pic) {
                $alarm_file_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid.'_'.$pic['table'];
                $this->db->query("UPDATE $alarm_file_table SET `delstatus`=1 WHERE `aid`='".$pic['aid']."'");
            
                if(isset($del_types[$pic['type']])) {
                    $del_types[$pic['type']] += 1;
                } else {
                    $del_types[$pic['type']] = 1;
                }
            
                if($pic['type'] == 0) {
                    if(isset($del_sensortypes[$pic['sensortype']])) {
                        $del_sensortypes[$pic['sensortype']] += 1;
                    } else {
                        $del_sensortypes[$pic['sensortype']] = 1;
                    }
                }
            }
        
            $this->db->query("DELETE FROM $alarm_index_table WHERE `deviceid`='$deviceid' AND `uid`='$uid' AND `time` in ($times)");
            $del = $this->db->affected_rows();
        }
        
        // update alarm count
        if($del) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET alarmnum=alarmnum-$del WHERE `deviceid`='$deviceid'");
    
            foreach($del_types as $type => $d) {
                $type_count = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type='$type' AND sensortype='-1' AND actiontype='-1'");
                if($type_count) {
                    if($type_count['count'] > $d) {
                        $this->db->query("UPDATE ".API_DBTABLEPRE."device_alarm_count SET count=count-$d WHERE deviceid='$deviceid' AND type='$type' AND sensortype='-1' AND actiontype='-1'");
                    } else {
                        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type='$type'");
                    }
                }
            }
    
            foreach($del_sensortypes as $sensortype => $d) {
                $sensortype_count = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type='0' AND sensortype='$sensortype' AND actiontype='-1'");
                if($sensortype_count) {
                    if($sensortype_count['count'] > $d) {
                        $this->db->query("UPDATE ".API_DBTABLEPRE."device_alarm_count SET count=count-$d WHERE deviceid='$deviceid' AND type='0' AND sensortype='$sensortype' AND actiontype='-1'");
                    } else {
                        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type='0' AND sensortype='$sensortype'");
                    }
                }
            }
        }
        
        return array('deviceid' => $deviceid);
    }
    
    function alarminfo($device, $time, $type=NULL, $sensorid=NULL, $sensortype=NULL, $actionid=NULL, $actiontype=NULL, $download=0) {
        if(!$device || !$device['deviceid'] || !$time)
            return false;

        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $alarm_tableid = $device['alarm_tableid'];
        if(!$alarm_tableid)
            return false;
        
        $sqladd = "";
        if($type !== NULL) {
            $sqladd .= " AND `type`='$type'";
        }
        if($sensorid !== NULL) {
            $sqladd .= " AND `sensorid`='$sensorid'";
        }
        if($sensortype !== NULL) {
            $sqladd .= " AND `sensortype`='$sensortype'";
        }
        if($actionid !== NULL) {
            $sqladd .= " AND `actionid`='$actionid'";
        }
        if($actiontype !== NULL) {
            $sqladd .= " AND `actiontype`='$actiontype'";
        }
        
        $alarm_index_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid;
        $index = $this->db->fetch_first("SELECT * FROM $alarm_index_table WHERE `deviceid`='$deviceid' AND `uid`='$uid' AND `time`='$time' $sqladd");
        if(!$index) {
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    return false;

                if($client->_need_sync_alarm()) {
                    $client->sync_alarm($device, $this->alarmtime($device));
                }
                
                $index = $this->db->fetch_first("SELECT * FROM $alarm_index_table WHERE `deviceid`='$deviceid' AND `uid`='$uid' AND `time`='$time' $sqladd");
                if(!$index)
                    return false;
            } else {
                return false;
            }
        }
        
        $alarm = $this->get_alarm_by_aid($alarm_tableid, $index['table'], $index['aid']);
        if(!$alarm)
            return false;
        
        $url = $this->get_alarm_temp_url($device, $alarm);
        if(!$url)
            return false;
        
        if($download)
            $this->base->redirect($url);
        
        $info = array(
            'time' => intval($time),
            'type' => intval($alarm['type']),
            'url' => $url
        );
        
        if($alarm['expiretime'])
            $info['expiretime'] = intval($alarm['expiretime']);
        
        // 传感器
        if($alarm['type'] == 0) {
            $info['sensorid'] = strval($alarm['sensorid']);
            $info['sensortype'] = intval($alarm['sensortype']);
            $info['actionid'] = strval($alarm['actionid']);
            $info['actiontype'] = intval($alarm['actiontype']);
        }
        
        return $info;
    }

    function meta($device, $params) {
        $meta = array();
 
        if($device && $device['deviceid']) {
            $deviceid = $device['deviceid'];
            $connect_type = $device['connect_type'];
            $appid = $device['appid'];
            $uid = $params['uid'];
        } else {
            $deviceid = $appid = $uid = 0;
            $connect_type = API_BAIDU_CONNECT_TYPE;
        }
        
        if ($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if (!$client)
                return false;

            if ($client->_connect_meta()) {
                $meta = $client->device_meta($device, $params);
                if (!$meta)
                    return false;

                if (!$device)
                    return $meta;
            }
        }

        $grant_device = $device['grant_device'] ? 1 : 0;
        
        $device = $this->get_device_by_did($deviceid);

        $sub_list = $params['uid'] ? $this->get_subscribe_by_uid($params['uid'], $device['appid']) : array();
        $check_sub = !empty($sub_list);

        // 更新设备云录制信息
        $device = $this->update_cvr_info($device);

        if($device['laststatusupdate'] + 60 < $this->base->time) {
            if($device['connect_type'] > 0 && $device['connect_online'] == 0 && $device['status'] > 0) {
                $device['status'] = '0';
            }
        }

        $share = $this->get_share_by_deviceid($device['deviceid']);
        if (!$share) {
            $share = array();
        }

        $user = $_ENV['user']->_format_user($device['uid']);
        $meta = array(
            'deviceid' => $device['deviceid'],
            'data_type' => ($params['auth_type'] == 'share') ? 2 : $grant_device,
            'device_type' => intval($device['device_type']),
            'connect_type' => $device['connect_type'],
            'connect_cid' => $device['connect_cid'],
            'stream_id' => $device['stream_id'],
            'status' => $device['status'],
            'description' => ($params['auth_type'] == 'share' && $share['title']) ? $share['title'] : $device['desc'],
            'cvr_type' => intval($device['cvr_type']),
            'cvr_day' => $device['cvr_day'],
            'cvr_end_time' => $device['cvr_end_time'],
            'share' => strval(intval($share['share_type'])),
            'shareid' => strval($share['shareid']),
            'uk' => strval(intval($share['uid'] ? $share['uid'] : $share['connect_uid'])),
            'uid' => $user['uid'],
            'username' => $user['username'],
            'avatar' => $user['avatar'],
            'intro' => strval($share['intro']),
            'thumbnail' => $this->get_device_thumbnail($device),
            'timezone' => strval($device['timezone']),
            'subscribe' => ($check_sub && in_array($device['deviceid'], $sub_list)) ? 1 : 0,
            'viewnum' => $device['viewnum'],
            'approvenum' => $device['approvenum'],
            'commentnum' => $device['commentnum']
        );

        if (isset($share['password']) && $share['password'] !== '') {
            $meta['needpassword'] = 1;
        }

        if ($share['expires']) {
            $meta['share_end_time'] = $share['dateline']+$share['expires'];
            $meta['share_expires_in'] = $meta['share_end_time']-$this->base->time;
        }

        if ($share['share_type']) {
            $meta['showlocation'] = !$share['showlocation'] || !$device['location'] ? 0 : 1;
        }

        if ($params['auth_type'] == 'token' && $device['location']) {
            $meta['location'] = array(
                'type' => $device['location_type'],
                'name' => $device['location_name'],
                'address' => $device['location_address'],
                'latitude' => floatval($device['location_latitude']),
                'longitude' => floatval($device['location_longitude'])
            );
        } elseif ($meta['showlocation']) {
            $meta['location'] = array(
                'type' => $device['location_type'],
                'name' => $device['location_name'],
                'address' => $device['location_address']
            );
        }
        
        if ($device['cvr_free']) {
            $meta['cvr_free'] = 1;
        }
        
        if($device['reportstatus'])
            $meta['reportstatus'] = intval($device['reportstatus']);

        return $meta;
    }
    
    function batch_meta($devices, $params) {
        $connect_type = $params['connect_type'];
        if ($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if (!$client)
                return false;

            if ($client->_connect_meta()) {
                $metas = $client->device_batch_meta($devices, $params);
                if (!$metas) {
                    return false;
                }
            }
        }

        return true;
    }

    // 保存设备分类
    function add_category($dev_list, $cid) {
        $num = 0;
        $list = array();
        foreach ($dev_list as $value) {
            if($value) {
                if(!$this->add_category_by_pk($value, $cid))
                    return false;

                $num++;
                $list[] = $value;
            }
        }

        $result = array();
        $result['count'] = $num;
        $result['list'] = $list;
        return $result;
    }

    // 更新设备分类
    function update_category($dev_list, $cid) {
        if (!$this->drop_category_by_cid($cid))
            return false;

        return $this->add_category($dev_list, $cid);
    }

    function get_cid_by_pk($uid, $deviceid) {
        $ret = array();
        $arr = $this->db->fetch_all('SELECT a.cid FROM '.API_DBTABLEPRE.'category a LEFT JOIN '.API_DBTABLEPRE.'device_category b ON a.cid=b.cid WHERE a.uid="'.$uid.'" AND b.deviceid="'.$deviceid.'"');
        foreach ($arr as $key => $value) {
            $ret[] = $value['cid'];
        }

        return $ret;;
    }

    // 添加设备分类
    function add_category_by_pk($deviceid, $cid) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_category SET deviceid="'.$deviceid.'", cid="'.$cid.'", dateline="'.$this->base->time.'"');

        return true;
    }

    // 删除设备分类
    function drop_category_by_cid($cid) {
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'device_category WHERE cid="'.$cid.'"');
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'multiscreen_display_category WHERE cid="'.$cid.'"');
        return true;
    }

    // 获取观看记录
    function list_view($uid, $appid, $page, $count) {
        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'device_view a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.uid="'.$uid.'" AND a.delstatus=0 AND b.reportstatus=0');
        $pages = $this->base->page_get_page($page, $count, $total);

        $list = array();
        if($total) {
            $sub_list = $uid ? $this->get_subscribe_by_uid($uid, $appid) : array();
            $check_sub = !empty($sub_list);

            $this->base->load('user');
            if($this->base->connect_domain) {
                $data = $this->db->fetch_all('SELECT a.deviceid,b.connect_type,m.connect_cid,b.desc,b.status,m.connect_thumbnail,b.cvr_thumbnail,b.uid,IFNULL(c.share_type,0) AS share_type,IFNULL(c.shareid,"") AS shareid,IFNULL(c.uid,"") AS uk,IFNULL(c.connect_uid,"") AS connect_uid,b.viewnum,b.approvenum,b.commentnum,a.lastupdate FROM '.API_DBTABLEPRE.'device_view a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid LEFT JOIN '.API_DBTABLEPRE.'device_share c ON a.deviceid=c.deviceid  LEFT JOIN '.API_DBTABLEPRE.'device_connect_domain m ON m.deviceid=b.deviceid WHERE a.uid="'.$uid.'" AND a.delstatus=0 AND b.reportstatus=0  AND m.connect_type=b.connect_type AND m.connect_domain="'.$this->base->connect_domain.'" ORDER BY a.lastupdate DESC LIMIT '.$pages['start'].', '.$count);
            } else {
                $data = $this->db->fetch_all('SELECT a.deviceid,b.connect_type,b.connect_cid,b.desc,b.status,b.connect_thumbnail,b.cvr_thumbnail,b.uid,IFNULL(c.share_type,0) AS share_type,IFNULL(c.shareid,"") AS shareid,IFNULL(c.uid,"") AS uk,IFNULL(c.connect_uid,"") AS connect_uid,b.viewnum,b.approvenum,b.commentnum,a.lastupdate FROM '.API_DBTABLEPRE.'device_view a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid LEFT JOIN '.API_DBTABLEPRE.'device_share c ON a.deviceid=c.deviceid WHERE a.uid="'.$uid.'" AND a.delstatus=0 AND b.reportstatus=0 ORDER BY a.lastupdate DESC LIMIT '.$pages['start'].', '.$count);
            }
            foreach($data as $value) {
                $user = $_ENV['user']->_format_user($value['uid']);
                $share = array(
                    'deviceid' => $value['deviceid'],
                    'connect_type' => $value['connect_type'],
                    'connect_cid' => $value['connect_cid'],
                    'description' => $value['desc'],
                    'status' => $value['status'],
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'uid' => $user['uid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'share' => $value['share_type'],
                    'shareid' => $value['shareid'],
                    'uk' => $value['uk'] ? $value['uk'] : $value['connect_uid'],
                    'subscribe' => ($check_sub && in_array($value['deviceid'], $sub_list)) ? 1 : 0,
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum'],
                    'lastupdate' => $value['lastupdate']
                );
                $list[] = $share;
            }
        }

        $result = array();
        $result['page'] = $pages['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    // 获取设备评论列表
    function list_comment($device, $list_type, $page, $count, $st, $et, $reply_type, $reply_cid) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $result = array();

        $table = API_DBTABLEPRE.'device_comment';
        $where = ' WHERE delstatus=0 AND deviceid="'.$deviceid.'"';
        
        $commentnum = $this->db->result_first('SELECT count(*) FROM '.$table.$where);
        if($commentnum != $device['commentnum']) {
            // 更新设备评论数
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET commentnum="'.$commentnum.'" WHERE deviceid="'.$deviceid.'"');
        }
        $result['commentnum'] = $commentnum;
        
        if($reply_type == 'reply') {
            $where .= ' AND reply_cid=0';
        } else {
            if($reply_cid) $where .= ' AND reply_cid="'.$reply_cid.'"';
        }

        if($list_type === 'timeline') {
            if($st) $where .= ' AND dateline>'.$st;
            if($et) $where .= ' AND dateline<'.$et;
        }
        
        if(!$reply_type && !$reply_cid && !$st && !$et) {
            $total = $commentnum;
        } else {
            $total = $this->db->result_first('SELECT count(*) FROM '.$table.$where);
        }

        if($list_type === 'page') {
            $pages = $this->base->page_get_page($page, $count, $total);
            $start = $pages['start'];
            $result['page'] = $pages['page'];
        } else {
            $start = 0;
        }

        $limit = ' LIMIT '.$start.','.$count;
        $orderbysql = ' ORDER BY dateline DESC';

        $list = array();
        if($total) {
            $query = $this->db->query('SELECT * FROM '.$table.$where.$orderbysql.$limit);
            while($data = $this->db->fetch_array($query)) {
                $comment = $this->_format_comment($data);
                
                if($reply_type === 'reply') {
                    $replynum = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_comment WHERE reply_cid='".$comment['cid']."'");
                    $reply_list = array();
                    
                    if($replynum) {
                        $reply_query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_comment WHERE reply_cid='".$comment['cid']."' $orderbysql LIMIT 5");
                        foreach($reply_query as $value) {
                            $reply = $this->_format_comment($value);
                            $reply_list[] = $reply;
                        } 
                    }
                    
                    $comment['replynum'] = $replynum;
                    $comment['reply_list'] = $reply_list;
                }

                $list[] = $comment;
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }
    
    function _format_comment($comment) {
        $this->base->load('user');
        
        $parent_username = '';
        if($comment['parent_cid']) {
            $parent_comment = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_comment WHERE cid='".$comment['parent_cid']."'");
            $parent_user = $this->_comment_user($parent_comment);
            if($parent_user) $parent_username = $parent_user['username'];
        }
        
        $result = array(
            'cid' => $comment['cid'],
            'reply_cid' => $comment['reply_cid'],
            'parent_cid' => $comment['parent_cid'],
            'parent_username' => $parent_username,
            'uid' => $comment['uid']
        );
        
        $comment_user = $this->_comment_user($comment);
        $result['username'] = $comment_user['username'];
        $result['avatar'] = $comment_user['avatar'];

        $result['comment'] = $this->base->userTextDecode($comment['comment']);
        $result['ip'] = $comment['ip'];
        $result['dateline'] = $comment['dateline'];
        return $result;
    }
    
    function _comment_user($comment) {
        $result = array();
        if(!$comment['uid'] && $comment['connect_type'] && $comment['connect_uid']) {
            $connect = $this->base->load_connect($comment['connect_type']);
            $connect_user = $connect->get_connect_user($comment['connect_uid']);
            $result['username'] = $connect_user['username'];
            $result['avatar'] = $connect_user['avatar'];
        } else {
            $user = $_ENV['user']->_format_user($comment['uid']);
            $result['username'] = $user['username'];
            $result['avatar'] = $user['avatar'];
        }
        return $result;
    }

    // 保存设备评论
    function save_comment($uid, $connect_type, $connect_uid, $deviceid, $comment, $parent_cid, $client_id, $appid) {
        if(!$uid && !($connect_type && $connect_uid))
            return false;
        
        $regip = $this->base->onlineip;
        $regtime = $this->base->time;
        
        $reply_cid = 0;
        if($parent_cid) {
            $reply_cid = $this->db->result_first("SELECT reply_cid FROM ".API_DBTABLEPRE."device_comment WHERE cid='$parent_cid'");
            if(!$reply_cid) $reply_cid = $parent_cid;
        }
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_comment SET uid="'.$uid.'", connect_type="'.$connect_type.'", connect_uid="'.$connect_uid.'", deviceid="'.$deviceid.'", comment="'.$this->base->userTextEncode($comment).'", reply_cid="'.$reply_cid.'", parent_cid="'.$parent_cid.'", ip="'.$regip.'", dateline="'.$regtime.'", lastupdate="'.$regtime.'", client_id="'.$client_id.'", appid="'.$appid.'"');
        $cid = $this->db->insert_id();
        $comment_my = $this->db->result_first("SELECT comment FROM ".API_DBTABLEPRE."device_comment WHERE cid='".$cid."'");
        $comment = array(
            'cid' => strval($cid),
            'reply_cid' => strval($reply_cid),
            'parent_cid' => strval($parent_cid),
            'ip' => $regip,
            'comment' => $comment_my,
            'dateline' => $regtime
        );
        $result = array();
        $result['comment'] = $this->_format_comment($comment);
        $result['commentnum'] = $this->add_comment($deviceid);
        return $result;
    }

    // 增加设备评论数
    function add_comment($deviceid) {
        $commentnum = $this->db->result_first('SELECT commentnum FROM '.API_DBTABLEPRE.'device WHERE deviceid="'.$deviceid.'"');

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET commentnum='.(++$commentnum).' WHERE deviceid="'.$deviceid.'"');

        return $commentnum;
    }

    // 保存观看记录
    function save_view($uid, $deviceid, $client_id, $appid) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_view SET uid="'.$uid.'",deviceid="'.$deviceid.'",num=1,delstatus=0,ip="'.$this->base->onlineip.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'",client_id="'.$client_id.'",appid="'.$appid.'" ON DUPLICATE KEY UPDATE num=num+1,delstatus=0,ip="'.$this->base->onlineip.'",lastupdate="'.$this->base->time.'",client_id="'.$client_id.'",appid="'.$appid.'"');

        return true;
    }

    // 增加设备点击数
    function add_view($deviceid) {
        $viewnum = $this->db->result_first('SELECT viewnum FROM '.API_DBTABLEPRE.'device WHERE deviceid="'.$deviceid.'"');

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET viewnum='.(++$viewnum).' WHERE deviceid="'.$deviceid.'"');

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['viewnum'] = $viewnum;

        return $result;
    }

    // 获取点赞记录
    function get_approve_by_pk($uid, $deviceid, $fileds='*') {
        return $this->db->fetch_first('SELECT '.$fileds.' FROM '.API_DBTABLEPRE.'device_approve WHERE uid="'.$uid.'" AND deviceid="'.$deviceid.'"');
    }

    // 保存用户点赞记录
    function save_approve($uid, $deviceid, $client_id, $appid) {
        $result = $this->get_approve_by_pk($uid, $deviceid);
        if($result) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_approve SET num=num+1, ip="'.$this->base->onlineip.'", lastupdate="'.$this->base->time.'",  client_id="'.$client_id.'", appid="'.$appid.'" WHERE uid="'.$uid.'" AND deviceid="'.$deviceid.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_approve SET uid="'.$uid.'", deviceid="'.$deviceid.'", num=1, ip="'.$this->base->onlineip.'", dateline="'.$this->base->time.'", lastupdate="'.$this->base->time.'", client_id="'.$client_id.'", appid="'.$appid.'"');
        }
    }

    // 增加设备点赞数
    function add_approve($deviceid) {
        $approvenum = $this->db->result_first('SELECT approvenum FROM '.API_DBTABLEPRE.'device WHERE deviceid="'.$deviceid.'"');

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET approvenum='.(++$approvenum).' WHERE deviceid="'.$deviceid.'"');

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['approvenum'] = $approvenum;

        return $result;
    }

    // 保存用户举报
    function add_report($uid, $username, $device, $type, $reason, $client_id, $appid) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $reportnum = $device['reportnum'];
        
        $isadmin = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."admins WHERE uid='$uid' AND allowadminreport='1'");
        if ($isadmin) {
            // 取消设备分享
            $this->cancel_share($device);
            
            $admin_name = $isadmin['name']?$isadmin['name']:$username;
            
            // 增加举报记录
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_report SET uid="'.$uid.'", username="'.$username.'", deviceid="'.$deviceid.'", type="'.$type.'", reason="'.$reason.'", admin_type="1", admin_id="'.$uid.'", admin_name="'.$admin_name.'",  ip="'.$this->base->onlineip.'", dateline="'.$this->base->time.'", lastupdate="'.$this->base->time.'", client_id="'.$client_id.'", appid="'.$appid.'"');
            
            // 设置举报下线状态
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET reportnum='.(++$reportnum).', reportstatus="1" WHERE deviceid="'.$deviceid.'"');
        } else {
            // 增加举报记录
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_report SET uid="'.$uid.'", username="'.$username.'", deviceid="'.$deviceid.'", type="'.$type.'", reason="'.$reason.'", ip="'.$this->base->onlineip.'", dateline="'.$this->base->time.'", lastupdate="'.$this->base->time.'", client_id="'.$client_id.'", appid="'.$appid.'"');
            
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET reportnum='.(++$reportnum).' WHERE deviceid="'.$deviceid.'"');
        }
        
        // 发送通知邮件
        $notify_service = $this->base->load_notify(1);
        if($notify_service) {
            $admins = array();
            
            $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."admins WHERE allowadminreport='1'");
            foreach($data as $admin) {
                $name = $admin['name']?$admin['name']:$admin['username'];
                $email = $admin['email'];
                if($name && $email) {
                    $admins[] = array(
                        'name' => $name,
                        'email' => $email
                    );
                }
            }
            
            if($admins) {
                if($isadmin) {
                    $params = array(
                        'deviceid' => $deviceid,
                        'title' => $device['desc'],
                        'admin' => $isadmin['name']?$isadmin['name']:$isadmin['username'],
                        'thumbnail' => $this->get_device_thumbnail($device)
                    );
                    $notify_service->sendreportoffline($admins, $params);
                } else {
                    $num = $this->get_report_in_hour($deviceid);
                    if($num > 20) {
                        $share = $this->get_share_by_deviceid($deviceid);
                        if($share) {
                            $params = array(
                                'deviceid' => $deviceid,
                                'title' => $device['desc'],
                                'admin' => $isadmin['name']?$isadmin['name']:$isadmin['username'],
                                'thumbnail' => $this->get_device_thumbnail($device),
                                'url' => 'http://www.iermu.com/video/'.$share['shareid'].'/'.($share['uid']?$share['uid']:$share['connect_uid'])
                            );
                            $notify_service->sendreportnotify($admins, $params);
                        }
                    }
                }
            }
        }

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['reportnum'] = $reportnum;

        return $result;
    }

    // 获取一个小时以内新增的设备举报人数
    function get_report_in_hour($deviceid) {
        return $this->db->result_first('SELECT COUNT(*) AS total FROM '.API_DBTABLEPRE.'device_report WHERE deviceid="'.$deviceid.'" AND lastupdate>'.($this->base->time - 3600));
    }

    // 获取首页图
    function list_ad($type, $page, $count) {
        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'ad a LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid JOIN '.API_DBTABLEPRE.'device c ON a.deviceid=c.deviceid WHERE a.type="'.$type.'" AND b.share_type&0x1!=0 AND c.status&0x4!=0 AND c.reportstatus=0');
        $pages = $this->base->page_get_page($page, $count, $total);

        $list = array();
        if($total)
            $list = $this->db->fetch_all('SELECT a.aid,a.deviceid,c.desc AS description,c.connect_thumbnail AS thumbnail,b.shareid,b.connect_uid AS uk,b.uid,b.share_type AS share,c.viewnum,c.approvenum,c.commentnum FROM '.API_DBTABLEPRE.'ad a LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid JOIN '.API_DBTABLEPRE.'device c ON a.deviceid=c.deviceid WHERE a.type="'.$type.'" AND b.share_type&0x1!=0 AND c.status&0x4!=0 AND c.reportstatus=0 LIMIT '.$pages['start'].','.$count);

        $result = array();
        $result['page'] = $pages['page'];
        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    // 获取设备授权码
    function grantcode($uid, $grant_type, $dev_list, $client_id, $appid) {
        $deviceid = implode(',', $dev_list);
        while (true) {
            $code = $this->create_grantcode($grant_type);
            $grantcode = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_grant_code WHERE code="'.$code.'"');
            if (!$grantcode) {
                $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_grant_code SET code="'.$code.'", type="'.$grant_type.'", deviceid="'.$deviceid.'", uid="'.$uid.'", dateline="'.$this->base->time.'", useid="0", usedate="0", status="0", client_id="'.$client_id.'", appid="'.$appid.'"');
                break;
            } elseif ($grantcode['useid'] || ($this->base->time - $grantcode['dateline']) > 3600) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_grant_code SET type="'.$grant_type.'", deviceid="'.$deviceid.'", uid="'.$uid.'", dateline="'.$this->base->time.'", useid="0", usedate="0", status="0", client_id="'.$client_id.'", appid="'.$appid.'" WHERE code="'.$code.'"');
                break;
            }
        }

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['code'] = $code;
        $result['expires'] = $this->base->time + 3600;

        return $result;
    }

    // 创建授权码
    function create_grantcode($grant_type) {
        switch ($grant_type) {
            case 1:
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $rand = mt_rand(65, 90);
                    if ($i < 4) {
                        $code .= $rand % 10;
                    } else {
                        $code .= chr($rand);
                    }
                }
                break;
            default:
                $str = $this->base->time.mt_rand();
                $code = md5($str);
                break;
        }

        return $code;
    }

    // 获取授权码信息
    function get_grant_by_code($code) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_grant_code WHERE code="'.$code.'"');
    }

    // 获取授权信息
    function grantinfo($grant) {
        $this->base->load('user');
        $user = $_ENV['user']->get_user_by_uid($grant['uid']);

        $arr = explode(',', $grant['deviceid']);
        $count = count($arr);
        $list = array();
        for ($i = 0; $i < $count; $i++) { 
            $device = $this->get_device_by_did($arr[$i]);

            $list[] = array('deviceid' => $device['deviceid'], 'description' => $device['desc'], 'thumbnail' => $this->get_device_thumbnail($device));
        }

        $result = array(
            'uid' => $user['uid'],
            'username' => $user['username'],
            'count' => $count,
            'list' => $list
        );

        return $result;
    }

    // 获取m3u8录像片段列表
    function vodlist($device, $starttime, $endtime) {
        if(!$device || !$starttime || !$endtime)
            return false;

        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->vodlist($device, $starttime, $endtime);
        } else {
            return false;
        }
    }

    // 添加幼儿云设备
    function addyoueryundevice($deviceid) {
        if(!$deviceid)
            return false;

        $share = $this->get_share($deviceid);
        if($share)
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_share SET status=0 WHERE shareid="'.$share['shareid'].'"');

        $device = $this->db->result_first('SELECT deviceid FROM '.API_DBTABLEPRE.'device_youeryun WHERE deviceid="'.$deviceid.'"');
        if(!$device)
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_youeryun SET deviceid="'.$deviceid.'"');

        return array('deviceid' => $deviceid);
    }

    // 删除幼儿云设备
    function dropyoueryundevice($deviceid) {
        if (!$this->checkyoueryundevice($deviceid))
            return false;

        $share = $this->get_share($deviceid);
        if($share)
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_share SET status=1 WHERE shareid="'.$share['shareid'].'"');

        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'device_youeryun WHERE deviceid="'.$deviceid.'"');

        return array('deviceid' => $deviceid);
    }

    // 判断幼儿云设备
    function checkyoueryundevice($deviceid) {
        if(!$deviceid)
            return false;

        $device = $this->db->result_first('SELECT deviceid FROM '.API_DBTABLEPRE.'device_youeryun WHERE deviceid="'.$deviceid.'"');
        if(!$device)
            return false;

        return true;
    }

    // 猜你喜欢接口
    function guess_share($uid, $support_type, $page, $count) {
        if($support_type) {
            $support = '0,'.implode(',', $support_type);
        } else {
            $support = '0';
        }

        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'device_share a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.share_type in (1,3) AND a.connect_type in ('.$support.') AND b.status&0x4!=0 AND b.reportstatus=0');
        $offset = rand(1, floor($total/$count));

        return $this->list_share($uid, 0, $support_type, 0, 0, 0, $offset, $count);
    }

    // 获取用户的分享摄像机
    function get_share_by_uid($uid, $support_type, $device) {
        if($support_type) {
            $support_type = '0,'.implode(',', $support_type);
        } else {
            $support_type = '0';
        }

        $count = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'device_share a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.uid="'.$uid.'" AND a.share_type in (1,3) AND a.connect_type in ('.$support_type.') AND b.status&0x4!=0 AND b.reportstatus=0');

        if($device) {
            $share_list = $this->db->fetch_all('SELECT a.shareid,a.connect_type,a.connect_uid,a.uid,a.deviceid,a.share_type,b.connect_cid,b.status,b.connect_thumbnail,b.cvr_thumbnail,b.viewnum,b.approvenum,b.commentnum FROM '.API_DBTABLEPRE.'device_share a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.uid="'.$uid.'" AND a.share_type in (1,3) AND a.connect_type in ('.$support_type.') AND b.status&0x4!=0 AND b.reportstatus=0');
            if(!$share_list) $share_list = array();
        }

        $result = array();
        $result['sharenum'] = !$count ? 0 : $count;
        $result['share_list'] = !$device ? array() : $share_list;
        return $result;
    }

    // 删除观看记录列表
    function drop_view($uid, $list) {
        $num = 0;
        $dev_list = array();
        foreach ($list as $value) {
            if($value) {
                if(!$this->drop_view_by_pk($uid, $value))
                    return false;

                $num++;
                $dev_list[] = $value;
            }
        }

        $result = array();
        $result['count'] = $num;
        $result['list'] = $dev_list;
        return $result;
    }

    // 删除观看记录
    function drop_view_by_pk($uid, $deviceid) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_view SET delstatus=1 WHERE `uid`="'.$uid.'" AND `deviceid`="'.$deviceid.'"');
        return true;
    }

    // 搜索分享设备列表
    function search_share($uid, $uk, $keyword, $device_type, $category, $orderby, $page, $count, $appid) {
        $this->base->load('search');
        $table = API_DBTABLEPRE.'device_share a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid';
        $where = 'WHERE a.share_type&0x1!=0 AND b.status&0x4!=0 AND b.reportstatus=0 AND b.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
        
        if($this->base->connect_domain) {
            $where .= 'AND m.connect_online=1';
        } else {
            $where .= 'AND b.connect_online=1';
        }
        
        if ($device_type === 'my') {
            if ($uk) {
                $where .= ' AND a.uid="'.$uk.'"';
            } elseif ($uid) {
                $where .= ' AND a.uid="'.$uid.'"';
            }
        }
        switch ($orderby) {
            case 'all': $orderbysql=' ORDER BY b.viewnum DESC,b.approvenum DESC,b.commentnum DESC,a.dateline DESC'; break;
            case 'view': $orderbysql=' ORDER BY b.viewnum DESC,a.dateline DESC'; break;
            case 'approve': $orderbysql=' ORDER BY b.approvenum DESC,a.dateline DESC'; break;
            case 'comment': $orderbysql=' ORDER BY b.commentnum DESC,a.dateline DESC'; break;
            case 'recommend': $orderbysql=' ORDER BY a.recommend DESC,a.dateline DESC'; $where.=' AND a.recommend>0'; break;
            default: $orderbysql=' ORDER BY a.dateline DESC'; break;;
        }

        if ($category) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON a.deviceid=c.deviceid';
            $where .= ' AND c.cid='.$category;
        }
        
        if($this->base->connect_domain) {
            $table .= " LEFT JOIN ".API_DBTABLEPRE."device_connect_domain m ON m.deviceid=b.deviceid";
            $where .= " AND m.connect_type=b.connect_type AND m.connect_domain='".$this->base->connect_domain."'";
        }
        
        if ($appid) $where .= ' AND b.appid='.$appid;

        $result = $list = array();
        $total = $this->db->result_first("SELECT count(*) FROM $table $where");
        $pages = $this->base->page_get_page($page, $count, $total);
        $limit = 'LIMIT '.$pages['start'].', '.$count;

        $count = 0;
        if($total) {
            $sub_list = $uid ? $this->get_subscribe_by_uid($uid, $appid) : array();
            $check_sub = !empty($sub_list);

            $this->base->load('user');
            $data = $this->db->fetch_all('SELECT a.shareid,a.connect_type,a.connect_uid,a.uid,a.deviceid,a.share_type,b.connect_did,REPLACE(b.desc,"'.$keyword.'","<span class=hl_keywords>'.$keyword.'</span>") AS description,b.status,b.connect_thumbnail,b.cvr_thumbnail,b.viewnum,b.approvenum,b.commentnum FROM '."$table $where $orderbysql $limit");
            foreach($data as $value) {
                $user = $_ENV['user']->_format_user($value['uid']);
                $share = array(
                    'shareid' => $value['shareid'],
                    'connect_type' => $value['connect_type'],
                    'connect_did' => $value['connect_did'],
                    'description' => $value['description'],
                    'deviceid' => $value['deviceid'],
                    'uk' => $value['connect_uid'],
                    'share' => $value['share_type'],
                    'status' => $value['status'],
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'uid' => $value['uid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'subscribe' => ($check_sub && in_array($value['deviceid'], $sub_list)) ? 1 : 0,
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum']
                    );

                $list[] = $share;
                $count++;
            }
        }

        $result = array(
            'page' => $pages['page'],
            'count' => $count,
            'device_list' => $list
        );

        return $result;
    }

    // 通过方向和步距云台位移
    function move_by_direction($device, $direction, $step) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        switch ($direction) {
            case 'up': $sub_cmd = 1; break;
            case 'down': $sub_cmd = 2; break;
            case 'left': $sub_cmd = 3; break;
            case 'right': $sub_cmd = 4; break;
            case 'leftup': $sub_cmd = 22; break;
            case 'rightup': $sub_cmd = 23; break;
            case 'leftdown': $sub_cmd = 24; break;
            case 'rightdown': $sub_cmd = 25; break;
        }

        $params = '01'.$this->_parse_hex(dechex($step), 2).'01';
        $command = '{"main_cmd":71,"sub_cmd":' . $sub_cmd . ',"param_len":3,"params":"' . $params . '"}';

        // api request
        /*
        $ret = $client->device_usercmd($device, $command, 1);
        $this->base->log('data', json_encode($ret));
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;
        
        $result = $this->_cmd_params($ret['data'][1]['userData']);
        */

        $client->device_usercmd($device, $command, 0);
        $result = "ffff0000";

        return $result;
    }

    // 设置云台延迟时长
    function move_delay($device, $delay) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $delay = intval($delay);

        $fileds = $this->_check_fileds($deviceid);
        if(!$fileds)
            return false;

        if(intval($fileds['plat_move_delay']) == $delay && $this->base->time - $fileds['plat_move_delay_lastupdate'] < 3600) {
            return true;
        }

        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $params = $this->_parse_hex(dechex($delay), 2);
        $command = '{"main_cmd":71,"sub_cmd":20,"param_len":1,"params":"' . $params . '"}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        $this->base->log('data', json_encode($ret));
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `plat_move_delay`='".$delay."', `plat_move_delay_lastupdate`='".$this->base->time."' WHERE `deviceid`='".$deviceid."'");

        $result = $this->_cmd_params($ret['data'][1]['userData']);
        return $result;
    }

    // 通过坐标点云台位移
    function move_by_point($device, $x1, $y1, $x2, $y2) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $params = $this->_parse_hex(dechex($x1), 8).$this->_parse_hex(dechex($y1), 8).$this->_parse_hex(dechex($x2), 8).$this->_parse_hex(dechex($y2), 8);
        $command = '{"main_cmd":75,"sub_cmd":66,"param_len":16,"params":"' . $params . '"}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        $this->base->log('data', json_encode($ret));
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;

        $result = $this->_cmd_params($ret['data'][1]['userData']);

        return $result;
    }

    // 通过预置点云台位移
    function move_by_preset($device, $preset) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":71,"sub_cmd":19,"param_len":3,"params":"01' . $this->_parse_hex(dechex($preset), 2) . '00"}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;

        $result = $this->_cmd_params($ret['data'][1]['userData']);

        return $result;
    }

    // 云台平扫设置
    function rotate($device, $rotate) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        switch ($rotate) {
            case 'auto': $command = '{"main_cmd":71,"sub_cmd":11,"param_len":3,"params":"010400"}'; break;
            case 'stop': $command = '{"main_cmd":71,"sub_cmd":12,"param_len":3,"params":"010400"}'; break;      
            default: return false;
        }

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        return true;
    }

    // 保存云台预置点
    function save_preset($device, $preset, $title, $reset = true) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $plat_preset = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_preset WHERE deviceid="'.$deviceid.'" AND preset="'.$preset.'"');
        if($plat_preset) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_preset SET title="'.$title.'" WHERE deviceid="'.$deviceid.'" AND preset="'.$preset.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_preset SET deviceid="'.$deviceid.'",preset="'.$preset.'",title="'.$title.'"');
        }

        if ($reset) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_preset SET pathname="",filename="",storageid="0",status="0",dateline="'.$this->base->time.'" WHERE deviceid="'.$deviceid.'" AND preset="'.$preset.'"');

            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $command = '{"main_cmd":71,"sub_cmd":18,"param_len":3,"params":"01' . $this->_parse_hex(dechex($preset), 2) . '00"}';

            // api request
            if(!$client->device_usercmd($device, $command, 1) || !$this->save_settings($client, $device))
                return false; 
        }

        return true;
    }

    // 删除云台预置点
    function drop_preset($deviceid, $preset) {
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'device_preset WHERE deviceid="'.$deviceid.'" AND preset="'.$preset.'"');

        return true;
    }

    // 获取云台预置点列表
    function list_preset($deviceid) {
        $storage = $this->base->load_storage('11'); //qiniu
        if(!$storage)
            return false;

        $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'device_preset WHERE deviceid="'.$deviceid.'" AND status="1" ORDER BY dateline');

        $list = array();
        for ($i = 0, $n = count($arr); $i < $n; $i++) { 
            $filepath = $arr[$i]['pathname'].$arr[$i]['filename'];
            
            $list[] = array(
                'preset' => $arr[$i]['preset'],
                'title' => $arr[$i]['title'],
                'thumbnail' => $storage->preset_image($filepath)
            );
        }

        $result = array(
            'count' => count($list),
            'list' => $list
        );

        return $result;
    }

    // 获取预置点上传token
    function get_preset_token($device, $preset, $time, $client_id, $sign) {
        if(!$device || !$preset || !$time || !$client_id || !$sign)
            return false;

        $deviceid = $device['deviceid'];

        $storageid = 11; //qiniu
        $storage = $this->base->load_storage($storageid);
        if(!$storage)
            return false;

        $ret = $storage->preset_info($device, $preset, $time, $client_id, $sign);
        if(!$ret)
            return false;

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_preset SET pathname="'.$ret['pathname'].'",filename="'.$ret['filename'].'",storageid="'.$storageid.'" WHERE deviceid="'.$deviceid.'" AND preset="'.$preset.'"');

        $result = array(
            'storageid' => $storageid,
            'filepath' => $ret['filepath'],
            'upload_token' => $ret['upload_token']
        );
        
        return $result;
    }

    function preset_qiniu_notify($device, $preset) {
        if(!$device || !$preset)
            return false;

        $deviceid = $device['deviceid'];
        
        $arr = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_preset WHERE deviceid="'.$deviceid.'" AND preset="'.$preset.'"');
        if(!$arr)
            return false;

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_preset SET status="1" WHERE deviceid="'.$deviceid.'" AND preset="'.$preset.'"');

        $result = array(
            'storageid' => $arr['storageid'],
            'status' => '1'
        );
        
        return $result;
    }

    // upgrade
    function upgrade($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        //判断是否需要升级
        $firmware_info = $this->_check_need_upgrade($device);
        if(!$firmware_info)
            return true;
        
        /*
        #define CMSUPDATE_NO        0
        #define CMSUPDATE_WAIT      1
        #define CMSUPDATE_OK        2
        #define CMSUPDATE_DOWN      3
        #define CMSUPDATE_START 4
        #define CMSUPDATE_CANCEL    5
        */
        if($firmware_info['confirm_upgrade']) {
            $status = 0;
            $retry = 5;
            while($retry > 0) {
                $ret = $this->_check_upgrade_status($device);
                if(!$ret)
                    return false;
                
                $status = $ret['status'];
                if($status == 0) {
                    $retry--;
                    usleep(2000000);
                    continue;
                } else if($status == 1) {
                    $ret = $this->_set_upgrade_status($device);
                    if(!$ret)
                        return false;
                    break;
                } else {
                    break;
                }
            }
            
            if($status == 0)
                return false;
        } else {
            $ret = $this->_check_upgrade_status($device);
            if(!$ret)
                return false;
        }
        
        $this->_check_device_status_exist($deviceid);
        $firmware_latest = $firmware_info['firmware'] ? $firmware_info['firmware'] : -1;
        $this->db->query('UPDATE '.API_DBTABLEPRE."device_status SET current_upgrade_status=1, current_upgrade_firmware=$firmware_latest, current_upgrade_starttime=".$this->base->time.', current_upgrade_lastupdate='.$this->base->time.' WHERE deviceid="'.$deviceid.'"');
        
        return true;
    }
    
    function _check_upgrade_status($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":74,"sub_cmd":47,"param_len":0,"params":0}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;

        $params = $this->_cmd_params($ret['data'][1]['userData']);
        if(!$params)
            return false;
        
        // 设备型号
        $status = intval(substr($params, 0, 2), 16);
        return array('status' => $status);
    }
    
    function _set_upgrade_status($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":75,"sub_cmd":47,"param_len":4,"params":"02ffffff"}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        if(!$ret)
            return false;
        
        return true;
    }

    // control
    function control($device, $command) {
        if (!$device || !$device['deviceid'] || !$command)
            return false;

        $client = $this->_get_api_client($device['connect_type']);
        if(!$client)
            return false;

        $ret = $client->device_usercmd($device, $command, 1);

        return $ret;
    }

    function get_sensor_info($uid, $param) {
        if (!$uid || !$param)
            return false;

        $result = array();
        $info = $this->_get_433_info($param);
        if (!$info)
            return false;
        
        $type = intval($info['type']);
        $result['type'] = $type;
        $name = '';
        switch ($type) {
            case 1:
            $name = $this->base->lang['sensor_door_name'];
            break;
            case 2:
            $name = $this->base->lang['sensor_body_name'];
            break;
            case 3:
            $name = $this->base->lang['sensor_button_name'];
            break;
            case 4:
            $name = $this->base->lang['sensor_smoke_name'];
            break;
            default:
            return false;
        }
        
        $sensorid = $info['sensorid'];
        $result['sensorid'] = strval($sensorid);

        $sensor = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE uid!=0 AND sensorid='$sensorid'");
        if (!$sensor) {
            $exist = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE uid=$uid AND name='$name' AND sensorid!='$sensorid'");
            if ($exist) {
                $postfix = 1;
                $max_loop = 100;
                while ($max_loop > 0) {
                    $recommendation = $name.$postfix;
                    $exist = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE uid=$uid AND name='$recommendation' AND sensorid!='$sensorid'");
                    if (!$exist) {
                        $result['name'] = $recommendation;
                        break;
                    }
                    $postfix++;
                    $max_loop--;
                }
                if ($max_loop == 0) {
                    $result['name'] = $name.rand(100,999);
                }
            } else {
                $result['name'] = $name;
            }
            $result['uid'] = 0;
            $result['username'] = '';
        } else {
            if (intval($uid) == intval($sensor['uid'])) {
                $devicelist = $this->db->fetch_all('SELECT A.*,B.desc FROM '.API_DBTABLEPRE.'device_sensor AS A left join '.API_DBTABLEPRE."device AS B ON A.deviceid=B.deviceid WHERE A.uid=$uid AND A.sensorid='$sensorid'");
                $list = array();
                foreach ($devicelist as $item) {
                    $device = array();
                    $device['deviceid'] = $item['deviceid'];
                    $device['desc'] = $item['desc'];
                    $list[] = $device;
                }                
                $result['count'] = count($list);
                $result['list'] = $list;
            }

            $result['name'] = $sensor['name'];
            $result['uid'] = intval($sensor['uid']);
            $result['username'] = $this->db->result_first('SELECT username FROM '.API_DBTABLEPRE.'members WHERE uid='.$sensor['uid']);
        }

        return $result;
    }

    function check_sensor_binded($device, $param) {
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        if (!$uid || !$deviceid || !$param)
            return false;

        $info = $this->_get_433_info($param);
        if (!$info)
            return false;
        $sensorid = $info['sensorid'];
        $sensor = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE uid!=0 AND uid!=$uid AND sensorid='$sensorid'");
        if ($sensor)
            return intval($sensor['uid']);

        return false;
    }

    function recommendation_sensor_name($device, $name, $param) {
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        if (!$uid || !$deviceid || !isset($name) || $name === '' || !$param)
            return false;

        $info = $this->_get_433_info($param);
        if (!$info)
            return false;
        $sensorid = $info['sensorid'];
        
        $exist = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE uid=$uid AND name='$name' AND sensorid!='$sensorid'");
        if ($exist) {
            $postfix = 1;
            $max_loop = 100;
            while ($max_loop > 0) {
                $recommendation = $name.$postfix;
                $exist = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE uid=$uid AND name='$recommendation' AND sensorid!='$sensorid'");
                if (!$exist) {
                    return $recommendation;
                }
                $postfix++;
                $max_loop--;
            }
            return $name.rand(100,999);
        }
        
        return false;
    }

    function check_sensor_name_conflict($uid, $sensorid, $name) {
        if (!$uid || !$sensorid || !isset($name) || $name === '')
            return false;
        $exist = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE uid=$uid AND name='$name' AND sensorid!='$sensorid'");
        if ($exist)
            return false;

        return true;
    }

    function check_sensor_name($name) {
        if(strtolower(API_CHARSET) != 'utf-8')
            return false;
    
        $len = $this->base->dstrlen($name);
        if($len > 25 || $len < 1) {
            return false;
        } else {
            return true;
        }
    }

    function addsensor_433($device, $param, $name) {
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];

        if (!$deviceid || !$uid || !$param || !isset($name) || $name === '')
            return false;

        $params = '';
        $count = 0;
        
        $info = $this->_get_433_info($param);
        if (!$info)
            return false;

        $sensorid = $info['sensorid'];
        $type = intval($info['type']);
        $desc = strval($sensorid);
        foreach ($info['param'] as $k => $v) {
            $actiontype = intval($k);
            $actionid = intval($v);

            $this->db->query('INSERT INTO '.API_DBTABLEPRE."sensor_action SET sensorid='$sensorid', actiontype=$actiontype, actionid=$actionid", 'SILENT');

            $params .= (($actiontype == SENSOR_ACTION_SOS_TYPE || $actiontype == SENSOR_ACTION_SMOKE_TYPE) ? 'c1' : '81').$this->_parse_hex(dechex($actiontype), 2).$this->_parse_hex(dechex($sensorid), 8).$this->_parse_hex(dechex($actionid), 8).$this->_bin2hex($name, 32);
            $count++;
        }

        $params = $this->_parse_hex(dechex($count), 2).$params;

        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $commands = array();
        $commands[] = array('type' => '433_set', 'command' => '{"main_cmd":75,"sub_cmd":77,"param_len":' . (strlen($params) / 2) . ',"params":"' . $params . '"}', 'response' => 1);
        $commands[] = array('type' => 'savemenu', 'command' => '{"main_cmd":65,"sub_cmd":3,"param_len":0,"params":0}', 'response' => 1);
        
        $ret = $client->device_batch_usercmd($device, $commands);
        if (!$ret)
            return false;
        
        // 判断返回状态
        foreach($ret as $value) {
            if($value['type'] == '433_set') {
                $data = json_decode($value['data'], true);
                if(!$data || $data['param_len'] != 1) {
                    return false;
                }
            }
        }

        if ($this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."sensor WHERE sensorid='$sensorid'")) {
            $this->db->query('UPDATE '.API_DBTABLEPRE."sensor SET uid=$uid, name='$name', lastupdate=".$this->base->time." WHERE sensorid='$sensorid'");
        } else {
            $setarr = array();
            $setarr[] = 'sensorid="'.$sensorid.'"';
            $setarr[] = 'uid='.$uid;
            $setarr[] = 'type='.$type;
            $setarr[] = 'firm="'.$info['factory'].'"';
            $setarr[] = 'model="'.$info['model'].'"';
            $setarr[] = 'name="'.$name.'"';
            $setarr[] = 'appid='.$this->base->appid;
            $setarr[] = 'client_id="'.$this->base->client_id.'"';
            $setarr[] = 'dateline='.$this->base->time;
            $setarr[] = 'lastupdate='.$this->base->time;
            $sets = implode(',', $setarr);
            $this->db->query('INSERT INTO '.API_DBTABLEPRE."sensor SET $sets");
        }

        $status = 1;
        if ($sensor = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."device_sensor WHERE deviceid='$deviceid' AND uid=$uid AND sensorid='$sensorid'")) {
            $status = intval($sensor['status']);
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_sensor SET lastupdate='.$this->base->time." WHERE deviceid='$deviceid' AND uid=$uid AND sensorid='$sensorid'");
        } else {
            $setarr = array();
            $setarr[] = 'sensorid="'.$sensorid.'"';
            $setarr[] = 'deviceid="'.$deviceid.'"';
            $setarr[] = 'uid='.$uid;
            $setarr[] = 'type='.$type;
            $setarr[] = 'appid='.$this->base->appid;
            $setarr[] = 'client_id="'.$this->base->client_id.'"';
            $setarr[] = 'dateline='.$this->base->time;
            $setarr[] = 'lastupdate='.$this->base->time;
            $sets = implode(',', $setarr);
            $this->db->query('INSERT INTO '.API_DBTABLEPRE."device_sensor SET $sets");
        }   

        $result = array();
        $result['name'] = $name;
        $result['deviceid'] = $deviceid;
        $result['sensorid'] = strval($sensorid);
        $result['type'] = $type;
        $result['status'] = $status;
        return $result;
    }

    function listsensor_433($device) {
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];

        if (!$deviceid || !$uid)
            return false;

        $sensorlist = $this->db->fetch_all('SELECT A.*, B.name FROM '.API_DBTABLEPRE.'device_sensor AS A left join '.API_DBTABLEPRE."sensor AS B ON A.sensorid=B.sensorid WHERE A.uid=$uid AND A.deviceid='$deviceid'");
        if ($sensorlist === false)
            return false;

        $result = array();
        $list = array();
        foreach ($sensorlist as $item) {
            $sensor = array();
            $sensor['sensorid'] = $item['sensorid'];
            $sensor['type'] = intval($item['type']);
            $sensor['status'] = intval($item['status']);
            $sensor['deviceid'] = $item['deviceid'];
            $sensor['name'] = $item['name'];
            $list[] = $sensor;
        }
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    function update_sensor($device, $sensorid, $ps) {
        if (!$device || !$sensorid || !$ps)
            return false;

        $uid = $device['uid'];
        $deviceid = $device['deviceid'];

        $sensor = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."device_sensor WHERE uid=$uid AND sensorid='$sensorid' AND deviceid='$deviceid'");
        if (!$sensor)
            return false;

        $result = array();
        $result['sensorid'] = strval($sensorid);
        $result['deviceid'] = strval($deviceid);

        if (isset($ps['name'])) {
            $name = $ps['name'];
            $this->db->query('UPDATE '.API_DBTABLEPRE."sensor SET name='$name' WHERE uid=$uid AND sensorid='$sensorid'");
            $result['name'] = strval($ps['name']);
        }
        if (isset($ps['status'])) {
            $status = intval($ps['status']) == 0 ? 0 : 1;
            $this->db->query('UPDATE '.API_DBTABLEPRE."device_sensor SET status=$status WHERE uid=$uid AND sensorid='$sensorid' AND deviceid='$deviceid'");
            $result['status'] = $status;
        }

        return $result;
    }

    function dropsensor_433($device, $sensorid) {
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];

        if (!$uid || !$deviceid || !$sensorid)
            return false;

        $actionids = $this->db->fetch_all('SELECT B.actionid FROM '.API_DBTABLEPRE.'device_sensor AS A left join '.API_DBTABLEPRE."sensor_action AS B ON A.sensorid=B.sensorid WHERE A.uid=$uid AND A.deviceid='$deviceid' AND A.sensorid='$sensorid'");
        if (!$actionids)
            return false;

        $params = '';
        $count = 0;
        foreach ($actionids as $item) {
            $actionid = intval($item['actionid']);

            $params .= "00"."00"."00000000".$this->_parse_hex(dechex($actionid), 8).$this->_bin2hex("", 32);
            $count++;
        }

        $params = $this->_parse_hex(dechex($count), 2).$params;

        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $commands = array();
        $commands[] = array('type' => '433_drop', 'command' => '{"main_cmd":75,"sub_cmd":77,"param_len":' . (strlen($params) / 2) . ',"params":"' . $params . '"}', 'response' => 1);
        $commands[] = array('type' => 'savemenu', 'command' => '{"main_cmd":65,"sub_cmd":3,"param_len":0,"params":0}', 'response' => 1);

        if (!$client->device_batch_usercmd($device, $commands))
            return false;

        $this->db->query('DELETE FROM '.API_DBTABLEPRE."device_sensor WHERE uid=$uid AND deviceid='$deviceid' AND sensorid='$sensorid'");

        $binded_device = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."device_sensor WHERE uid=$uid AND sensorid='$sensorid'");
        if (!$binded_device) {
            $this->db->query('UPDATE '.API_DBTABLEPRE."sensor SET uid=0 WHERE uid=$uid AND sensorid='$sensorid'");
        }

        $result = array();
        $result['deviceid'] = $deviceid;
        $result['sensorid'] = strval($sensorid);
        return $result;
    }

    function _get_433_info($param) {
        if (!is_numeric($param))
            return false;

        $param = intval($param);
        
        if($param < 1 || $param > 4294967295)
            return false;

        $factory = ($param >> 28) & 0xf;
        $type = ($param >> 24) & 0xf;
        $model = ($param >> 20) & 0xf;
        $sensorid = strval($param & 0xfffff);
        
        if ($factory < 1 || $factory > 1 || $type < 1 || $type > 4 || $model < 1 || $model > 15 || !$sensorid)
            return false;

        $list = $this->_generate_433_param($sensorid, $type, $factory, $model); 

        $info = array(
            'factory' => $factory,
            'model' => $model,
            'type' => $type,
            'param' => $list,
            'sensorid' => $sensorid
        );
        return $info;
    }
    
    function get_sensor_action_by_actionid($actionid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."sensor_action WHERE actionid='$actionid'");
    }
    
    function get_sensor_by_sensorid($sensorid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."sensor WHERE sensorid='$sensorid'");
    }
    
    function get_device_sensor_by_sensorid($deviceid, $sensorid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_sensor WHERE deviceid='$deviceid' AND sensorid='$sensorid'");
    }
    
    function get_partner_device_by_did($partner_id, $deviceid) {
        if(!$partner_id || !$deviceid)
            return false;
        
        $check = $this->db->fetch_first("SELECT deviceid FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id' AND deviceid='$deviceid'");
        if(!$check)
            return false;
        
        return $this->get_device_by_did($deviceid);
    }
    
    function check_partner_device($partner_id, $deviceid) {
        if(!$partner_id || !$deviceid)
            return false;
        
        $check = $this->db->fetch_first("SELECT deviceid FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id' AND deviceid='$deviceid'");
        if(!$check)
            return false;
        
        return $deviceid;
    }
    
    function get_partner_share($device, $share_type, $title, $intro, $appid, $client_id) {
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        $share = $this->get_share_by_deviceid($deviceid);
        if($share && $share['share_type'] == $share_type) {
            $sql = "";

            if ($title != $share['title']) {
                $sql .= "title='$title',";
            }

            if ($intro != $share['intro']) {
                $sql .= "intro='$intro',";
            }
            
            if ($sql) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_share SET $sql appid='$appid',client_id='$client_id',lastupdate='".$this->base->time."' WHERE shareid='".$share['shareid']."'");
            }

            $share['title'] = $title;
            $share['intro'] = $intro;
            $share['appid'] = $appid;
            $share['client_id'] = $client_id;
            $share['lastupdate'] = $this->base->time;
            return $share;
        } else {
            return $this->create_share($uid, $device, $title, $intro, $share_type, $appid, $client_id);
        }
    }
    
    function set_device_auth($device, $controls='') {
        if(!$device || !$device['deviceid'] || !$device['uid'])
            return false;
        
        $cookie = $this->_device_auth_cookie($device['deviceid']);
        if($controls) {
            $auth = rawurlencode($this->base->authcode($device['uid'].'|'.$device['deviceid'].'|'.md5($_SERVER['HTTP_USER_AGENT']).'|'.$controls, 'ENCODE', API_KEY));
        } else {
            $auth = rawurlencode($this->base->authcode($device['uid'].'|'.$device['deviceid'].'|'.md5($_SERVER['HTTP_USER_AGENT']), 'ENCODE', API_KEY));
        }
        $this->base->setcookie($cookie, $auth, 86400);
        return true;
    }
    
    function check_device_auth($deviceid) {
        if(!$deviceid)
            return false;
        
        $cookie = $this->_device_auth_cookie($deviceid);
        if (!isset($_COOKIE[$cookie]))
            return false;

        @list($uid, $did, $agent, $controls) = explode('|', $this->base->authcode(rawurldecode($_COOKIE[$cookie]), 'DECODE', API_KEY));
        if ($deviceid != $did || $agent != md5($_SERVER['HTTP_USER_AGENT'])) {
            $this->base->setcookie($cookie, '');
            return false;
        }

        // log记录 ----------- init params
        log::$uid = $uid;

        $auth = array(
            'uid' => $uid
        );

        $controls = json_decode($controls, true);
        if($controls && is_array($controls)) {
            $auth['controls'] = $controls;
        }

        return $auth;
    }
    
    function _device_auth_cookie($deviceid) {
        return 'ida_'.md5($deviceid.API_KEY);
    }
    
    function get_upgrade_status($device) {
        $result = array();

        $timeout = 300;/*second*/
        $deviceid = $device['deviceid'];
        $status = $this->db->fetch_first('SELECT firmware, current_upgrade_status, current_upgrade_starttime, current_upgrade_lastupdate FROM '.API_DBTABLEPRE.'device_status WHERE deviceid="'.$deviceid.'"');
        if (!$status) {
            $result['status'] = 0;
            $result['deviceid'] = $device['deviceid'];
            return $result;
        }

        $cloud = $this->_connect_type_to_cloud($device['connect_type']);
        
        $platform = -1;
        $current_upgrade_status = intval($status['current_upgrade_status']);
        
        $firmware_latest = intval($status['current_upgrade_firmware']);
        if(!$firmware_latest || $firmware_latest == -1) {
            $firmware_latest = $this->db->result_first('SELECT firmware FROM '.API_DBTABLEPRE."device_firmware WHERE cloud=$cloud and platform=$platform order by firmware desc");
        }
        
        $device_firmware = $this->_get_device_firmware($deviceid);
        if($status['firmware'] > $device_firmware) {
            $device_firmware = $status['firmware'];
        }
        
        $inprocessing = 0;
        if ((intval($status['current_upgrade_status']) >= 1 && intval($status['current_upgrade_status']) <= 4) || (intval($status['current_upgrade_status'])) == -1) {
            $inprocessing = 1;
        }
        
        $up_status = 0;
        if ($inprocessing && ($device_firmware >= $firmware_latest)) {
            $up_status = 5;        
        } else if ($inprocessing && ($this->base->time - intval($status['current_upgrade_lastupdate']) > $timeout)) {
            $up_status = -2;     
        }
        
        if($up_status) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_status SET current_upgrade_status='.$current_upgrade_status.', current_upgrade_lastupdate='.$this->base->time.' WHERE deviceid="'.$deviceid.'"');
            $current_upgrade_status = $up_status;
        }

        $result['status'] = $current_upgrade_status;
        $result['deviceid'] = $device['deviceid'];
        return $result;
    }

    function get_upgrade_info($device) {
        $result = array();
        
        $firmware_info = $this->_check_need_upgrade($device);
        $result['need_upgrade'] = $firmware_info ? 1 : 0;
        if ($firmware_info) {
            $version = (intval(substr($firmware_info['firmware'], 0, -3))/1000).'_'.(intval(substr($firmware_info['firmware'], -3)));
            $result['version'] = $version;
            $result['desc'] = $firmware_info['description'];
            $result['date'] = $firmware_info['firmdate'];
            $result['force_upgrade'] = intval($firmware_info['force_upgrade']);
        }        

        return $result;
    }

    function getuploadtoken($device, $request_no) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $device = $this->_on_connect($device);
        if(!$device)
            return false;
        
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;
            
            return $client->device_uploadtoken($device, $request_no);
        }
        
        return false;
    }
    
    function _on_connect($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        
        // 记录lastconnect
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET lastconnectip='".$this->base->onlineip."', lastconnectdate='".$this->base->time."' WHERE deviceid='$deviceid'");
        
        // 沧州联通测试
        // zfT67AHcDdvXZ3By8MGt
        if($this->check_partner_device('zfT67AHcDdvXZ3By8MGt', $deviceid) && !$this->is_czlz_valid_ip($this->base->onlineip))
            return array('error'=>-1);
        
        // 初始化
        if(!$device['isinit']) {
            $this->device_init($device);
        }
        
        // 更新设备云录制信息
        $device = $this->update_cvr_info($device);

        // 设备活动
        $this->business_activity_message($device);
        
        return $device;
    }
    
    function get_connect_token($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        // 更新设备云录制信息
        $device = $this->update_cvr_info($device);
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;
            
            return $client->device_connect_token($device);
        }
        
        return false;
    }
    
    function str_starts_with($haystack, $needle) {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }

    function is_czlz_valid_ip($ip) {
        if(!$ip) return false;
    
        if($this->str_starts_with($ip, '110.244.') || $this->str_starts_with($ip, '110.252.') || $this->str_starts_with($ip, '120.10.') 
            || $this->str_starts_with($ip, '120.11.') || $this->str_starts_with($ip, '121.16.') || $this->str_starts_with($ip, '121.16.')
            || $this->str_starts_with($ip, '61.55.') || $this->str_starts_with($ip, '101.31.') || $this->str_starts_with($ip, '119.251.')
            || $this->str_starts_with($ip, '221.193.') || $this->str_starts_with($ip, '221.195.') || $this->str_starts_with($ip, '202.99.176.')
            || $this->str_starts_with($ip, '218.12.192.') || $this->str_starts_with($ip, '218.12.193.') || $this->str_starts_with($ip, '218.12.194.')
            || $this->str_starts_with($ip, '218.12.195.') || $this->str_starts_with($ip, '218.12.196.') || $this->str_starts_with($ip, '218.12.197.')
            || $this->str_starts_with($ip, '218.12.198.') || $this->str_starts_with($ip, '218.12.199.'))
            return true;
    
        return false;
    }
    
    function updatestatus($device, $status, $time) {
        if(!$device || !$device['deviceid'] || !$time)
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        if($time < $device['laststatusupdate'])
            return false;
        
        //$this->update_status($deviceid, $this->get_status($status));
        //$this->db->query("UPDATE ".API_DBTABLEPRE."device SET laststatusupdate='".$time."' WHERE deviceid='$deviceid'");
        $data = $this->get_status($status);
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `isonline`='".$data['isonline']."', `isalert`='".$data['isalert']."', `isrecord`='".$data['isrecord']."', `status`='".$data['status']."', `laststatusupdate`='$time', `lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
        
        // 设备上线
        if(($status == 1 || $status == 65)) {
            // 等待200ms
            usleep(200);
            
            return $this->on_device_online($device);
        }
        
        return true;
    }
    
    function on_device_online($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $client = $this->_get_api_client($connect_type);
        if($client) {
            // 上线处理
            $client->on_device_online($device);
            
            if (!$this->sync_setting($device, 'timezone', 1))
                return false;
            
            // 时区处理
            $this->set_timezone($client, $device, $device['timezone']);
        }
        
        return true;
    }

    /**
     * [_check_need_upgrade 检测某设备是否需要升级]
     * @param  [type] $device [设备信息]
     * @return [type]         [true代表需要升级，false代表不需要升级]
     */
    function _check_need_upgrade($device) {
        if (!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        
        $firmware = 0;
        
        $status = $this->db->fetch_first('SELECT firmware, lastonline FROM '.API_DBTABLEPRE.'device_status WHERE deviceid="'.$deviceid.'"');
        $factory = $this->db->fetch_first('SELECT firmware, lastupdate FROM '.API_DBTABLEPRE.'device_factory WHERE deviceid="'.$deviceid.'"');
        
        if($status && $status['firmware'] != -1) {
            $firmware = $status['firmware'];
        }

        $setting_firmware = $this->_get_device_firmware($deviceid);
        if($setting_firmware && $setting_firmware > $firmware) {
            $firmware = $setting_firmware;
        }
        
        if(!$firmware && $factory && $factory['firmware'] != -1) {
            $firmware = $factory['firmware'];
        }
        
        if(!$firmware)
            return false;

        $firmware_info = $this->_get_latest_firmware($device, $firmware);
        if (!$firmware_info)
            return false;

        if (intval($firmware) < intval($firmware_info['firmware'])) {
            return $firmware_info;
        }

        return false;
    }

    /**
     * [_get_latest_firmware 获取某设备对应的最新固件版本信息]
     * @param  [type] $device [设备信息]
     * @return [type]         [false代表查询失败，非false代表信息]
     */
    function _get_latest_firmware($device, $firmware) {
        if (!$device || !$device['deviceid'] || !$firmware)
            return false;
        
        $deviceid = $device['deviceid'];
        
        $cloud = $this->_connect_type_to_cloud($device['connect_type']);
        $platform = -1;//-1为全平台
        
        // 测试设备
        $isdev = 0;
        $check = $this->db->result_first("SELECT firmware FROM ".API_DBTABLEPRE."device_dev WHERE deviceid='$deviceid'");
        if($check) {
            $isdev = 1;
        }
        
        $sqladd = '';
        if(!$isdev) $sqladd = " AND isdev='0'";
        
        $count = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_firmware WHERE cloud='$cloud' AND platform='$platform' AND firmware>'$firmware'$sqladd");
        if(!$count)
            return false;
        
        $fid = 0;
        $list = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_firmware WHERE cloud='$cloud' AND platform='$platform' AND firmware>'$firmware'$sqladd ORDER BY firmware ASC");
        foreach($list as $k => $v) {
            if($k == 0) {
                $confirm_upgrade = $v['confirm_upgrade'];
                $fid = $v['fid'];
            } else {
                if($v['confirm_upgrade'] != $confirm_upgrade) {
                    break;
                } else {
                    $fid = $v['fid'];
                }
            }
        }
        
        if(!$fid)
            return false;
        
        $firmware_info = $this->db->fetch_first('SELECT a.firmware,IFNULL(b.description,a.description) AS description,a.firmdate,a.force_upgrade,a.confirm_upgrade FROM '.API_DBTABLEPRE.'device_firmware a LEFT JOIN '.API_DBTABLEPRE.'device_firmware_lang b ON a.fid=b.fid AND b.lang="'.API_LANGUAGE.'" WHERE a.fid="'.$fid.'"');
        if (!$firmware_info)
            return false;

        return $firmware_info;
    }

    /**
     * [_check_device_status_exist 判断设备号在device_status表中是否存在，不存在即创建]
     * @param  [type] $deviceid [设备号]
     * @return [type]           [void]
     */
    function _check_device_status_exist($deviceid) {
        $status = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_status WHERE deviceid="'.$deviceid.'"');
        if ($status === false) {
            $setarr = array();
            $setarr[] = 'deviceid="'.$deviceid.'"';
            $setarr[] = 'firmware=0';
            $setarr[] = 'dateline='.$this->base->time;
            $sets = implode(',', $setarr);
            $this->db->query('INSERT INTO '.API_DBTABLEPRE."device_status SET $sets");
        }
    }
    
    function check_device_connect_type($deviceid) {
        if(!$deviceid) 
            return API_LINGYANG_CONNECT_TYPE;
        
        $type = 'none';
        $cloud = -1;
        $dateline = 0;
        
        $status = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_status WHERE deviceid='$deviceid'");
        $factory = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_factory WHERE deviceid='$deviceid'");
        if($status || $factory) {
            if($status && $status['cloud'] != -1) {
                $type = 'status';
                $cloud = $status['cloud'];
                $dateline = $status['lastonline'];
            }
            
            if($factory && $factory['cloud'] != -1 && (!$dateline || $factory['lastupdate'] > $dateline)) {
                $type = 'factory';
                $cloud = $factory['cloud'];
                $dateline = $factory['lastupdate'];
            }
        }
        
        if($type == 'none' || $type == 'factory') {
            $data = $this->db->fetch_first("SELECT platform,firmware FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
            if($data && $data['firmware']) {
                $cloud = $data['platform'];
            }
        }
        
        return $this->_cloud_to_connect_type($cloud);
    }
    
    function partner_update_devicelist($partner_id, $support_type, $appid) {
         if(!$partner_id || !$support_type || !$appid)
             return false;
         
         $members = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."member_partner WHERE partner_id='$partner_id'");
         if(!$members)
             return false;
         
         $dids = array();
         foreach($members as $member) {
             $devices = $this->listdevice($member['uid'], $support_type, -1, $appid, -1, -1, -1, '');
             if($devices && $devices['count']) {
                 foreach($devices['list'] as $value) {
                     $dids[] = $value['deviceid'];
                 }
             }
         }
         
         $partner_dids = $adds = $dels = array();
         
         $partner_devices = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id'");
         foreach($partner_devices as $k=>$v) {
             if(in_array($v['deviceid'], $dids)) {
                 $partner_dids[] = $v['deviceid'];
             } else {
                 $dels[] = $v['deviceid'];
             }
         }
         
         foreach($dids as $did) {
             if(!in_array($did, $partner_dids)) {
                 $adds[] = $did;
             }
         }
         
         if($adds) {
             $values = '';
             foreach($adds as $v) {
                 $values .= ($values?',':'')."('$partner_id', '$v')";
             }
             $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_partner (`partner_id`, `deviceid`) VALUES $values");
         }
         
         if($dels) {
             $values = '';
             foreach($dels as $v) {
                 $values .= ($values?',':'')."'$v'";
             }
             $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id' AND deviceid IN ($values)");
         }
         
         return true;
    }
    
    function partner_listdevice($partner_id, $page=1, $count=10) {
        if(!$partner_id)
            return false;
        
        $table = API_DBTABLEPRE."device_partner p LEFT JOIN ".API_DBTABLEPRE."device d ON p.deviceid=d.deviceid";
        $where = "WHERE p.partner_id='$partner_id'";
        $orderbysql = " ORDER BY d.dateline DESC";
        
        $result = $list = array();
        $total = $this->db->result_first("SELECT count(*) FROM $table $where");
        $pages = $this->base->page_get_page($page, $count, $total);
        $limit = " LIMIT ".$pages['start'].", ".$count;

        $result = $list = $uids = array();
        $count = 0;
        if($total) {
            $data = $this->db->fetch_all("SELECT d.* FROM $table $where $orderbysql $limit");
            foreach($data as $value) {
                $u = $value['uid'];
                $c = $value['connect_type'];
                if(!isset($uids[$u])) {
                    $uids[$u] = array();
                }
                if(!isset($uids[$u][$c])) {
                    $uids[$u][$c] = array();
                }
                
                $uids[$u][$c][] = $value;
            }
            
            foreach($uids as $uid=>$connects) {
                foreach($connects as $connect_type=>$devices) {
                    $params = array('uid'=>$uid, 'auth_type'=>'token', 'connect_type'=>$connect_type);
                    $this->batch_meta($devices, $params);
                }
            }
            
            $data = $this->db->fetch_all("SELECT d.deviceid FROM $table $where $orderbysql $limit");
            foreach($data as $value) {
                $deviceid = $value['deviceid'];

                $share = $this->get_share_by_deviceid($deviceid);
                if($share) {
                    $share_type = $share['share_type'];
                    $shareid = $share['shareid'];
                    $uk = $share['connect_uid'];
                    $title = $share['title'];
                    $intro = $share['intro'];
                } else {
                    $share_type = '0';
                    $shareid = '';
                    $uk = '';
                    $title = '';
                    $intro = '';
                }
        
                $device = $this->get_device_by_did($deviceid);

                // 更新设备云录制信息
                $device = $this->update_cvr_info($device);
                
                if($device['laststatusupdate'] + 60 < $this->base->time) {
                    if($device['connect_type'] > 0 && $device['connect_online'] == 0 && $device['status'] > 0) {
                        $device['status'] = 0;
                    }
                }

                $meta = array(
                    'deviceid' => $device['deviceid'],
                    'data_type' => 0,
                    'connect_type' => $device['connect_type'],
                    'connect_cid' => $device['connect_cid'],
                    'stream_id' => $device['stream_id'],
                    'status' => strval($device['status']),
                    'description' => $title,
                    'cvr_type' => intval($device['cvr_type']),
                    'cvr_day' => $device['cvr_day'],
                    'cvr_end_time' => $device['cvr_end_time'],
                    'share' => $share_type,
                    'shareid' => $shareid,
                    'uk' => $uk,
                    'intro' => $intro,
                    'thumbnail' => $this->get_device_thumbnail($device),
                    'subscribe' => 0,
                    'viewnum' => $device['viewnum'],
                    'approvenum' => $device['approvenum'],
                    'commentnum' => $device['commentnum']
                );
                
                if($device['cvr_free']) $meta['cvr_free'] = 1;
                
                if($device['reportstatus'])
                    $meta['reportstatus'] = intval($device['reportstatus']);
                
                $list[] = $meta;
                $count++;
            }
        }
        
        $result['page'] = $pages['page'];
        $result['count'] = $count;
        $result['list'] = $list;
        return $result;
    }
    
    function check_alarm_file_exist($deviceid, $uid, $alarm_tableid, $time) {
        if(!$deviceid || !$uid || !$alarm_tableid || !$time)
            return false;
        
        $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_alarm_$alarm_tableid WHERE deviceid='$deviceid' AND uid='$uid' AND time='$time'");
        if(!$check || !$check['table']  || !$check['aid'])
            return false;
        
        $table = $check['table'];
        $aid = $check['aid'];
        
        $file = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_alarm_".$alarm_tableid."_".$table." WHERE aid='$aid'");
        if(!$file)
            return false;
        
        return $file;
    }
    
    function add_alarm($deviceid, $alarm_tableid, $uid, $type, $sensorid, $sensortype, $actionid, $actiontype, $time, $expiretime, $pathname, $filename, $size, $storageid, $param, $client_id, $appid, $status) {
        if(!$deviceid || !$alarm_tableid || !$time)
            return false;
        
        $this->base->log('add alarm start', 'deviceid='.$deviceid.', time='.$time);
        
        $table = gmdate('Ymd', $time);
        $alarm_index_table = 'device_alarm_'.$alarm_tableid;
        $alarm_file_table = 'device_alarm_'.$alarm_tableid.'_'.$table;
        
        $table_check = $this->check_alarm_file_table($alarm_file_table);
        if(!$table_check)
            return false;
        
        $this->base->log('add alarm file table check', 'deviceid='.$deviceid.', alarm_file_table='.$alarm_file_table);
        
        $index_check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE.$alarm_index_table." WHERE deviceid='$deviceid' AND uid='$uid' AND type='$type' AND sensorid='$sensorid' AND sensortype='$sensortype' AND actionid='$actionid' AND actiontype='$actiontype' AND time='$time'");
        if($index_check)
            return false;
        
        $this->base->log('add alarm file index check', 'deviceid='.$deviceid.', alarm_index_table='.$alarm_index_table);
        
        if($param && is_array($param)) $param = serialize($param);
        $this->db->query("INSERT INTO ".API_DBTABLEPRE.$alarm_file_table." SET deviceid='$deviceid', uid='$uid', type='$type', sensorid='$sensorid', sensortype='$sensortype', actionid='$actionid', actiontype='$actiontype', time='$time', expiretime='$expiretime', pathname='$pathname', filename='$filename', size='$size', storageid='$storageid', param='$param', client_id='$client_id', appid='$appid', status='$status', dateline='".$this->base->time."', lastupdate='".$this->base->time."'");
        $aid = $this->db->insert_id();
        
        if(!$aid)
            return false;
        
        $this->db->query("INSERT INTO ".API_DBTABLEPRE.$alarm_index_table." SET deviceid='$deviceid', uid='$uid', type='$type', sensorid='$sensorid', sensortype='$sensortype', actionid='$actionid', actiontype='$actiontype', time='$time',expiretime='$expiretime',`table`='$table' ,aid='$aid'");
        
        // update alarm count
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET alarmnum=alarmnum+1 WHERE deviceid='$deviceid'");
        
        $type_count = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type='$type' AND sensortype='-1' AND actiontype='-1'");
        if($type_count) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_alarm_count SET count=count+1 WHERE deviceid='$deviceid' AND type='$type' AND sensortype='-1' AND actiontype='-1'");
        } else {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_alarm_count SET deviceid='$deviceid',type='$type',sensortype='-1',actiontype='-1',count='1'");
        }
        
        // 传感器
        if($type == 0) {
            $sensortype_count = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type='$type' AND sensortype='$sensortype' AND actiontype='-1'");
            if($sensortype_count) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_alarm_count SET count=count+1 WHERE deviceid='$deviceid' AND type='$type' AND sensortype='$sensortype' AND actiontype='-1'");
            } else {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_alarm_count SET deviceid='$deviceid',type='$type',sensortype='$sensortype',actiontype='-1',count='1'");
            }
        }
        
        return array(
            'deviceid' => $deviceid,
            'uid' => $uid,
            'type' => $type,
            'sensorid' => $sensorid,
            'sensortype' => $sensortype,
            'actionid' => $actionid,
            'actiontype' => $actiontype,
            'time' =>$time,
            'expiretime' => $expiretime,
            'index' => $alarm_tableid,
            'table' => $table,
            'aid' => $aid
        );
    }
    
    function check_alarm_file_table($alarm_file_table) {
        $sql = "CREATE TABLE IF NOT EXISTS ".API_DBTABLEPRE.$alarm_file_table." (
          `aid` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `deviceid` varchar(40) NOT NULL,
          `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
          `type` tinyint(3) NOT NULL DEFAULT '0',
          `sensorid` varchar(40) NOT NULL DEFAULT '' COMMENT '传感器ID',
          `sensortype` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '设备类型:1:门磁.2:PIR.3:SOS',
          `actionid` varchar(40) NOT NULL DEFAULT '' COMMENT '动作码',
          `actiontype` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '动作类型:1.低电（门磁，PIR），2.开门，3.关门，4.拆除（门磁，PIR)，5.PIR触发，6.SOS',
          `time` int(10) unsigned NOT NULL DEFAULT '0',
          `expiretime` int(10) unsigned NOT NULL DEFAULT '0',
          `pathname` varchar(100) NOT NULL DEFAULT '',
          `filename` varchar(100) NOT NULL DEFAULT '',
          `size` int(10) unsigned NOT NULL DEFAULT '0',
          `storageid` mediumint(8) unsigned NOT NULL DEFAULT '0',
          `param` text NOT NULL,
          `client_id` varchar(20) NOT NULL,
          `appid` mediumint(8) unsigned NOT NULL,
          `status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '报警状态，0待处理1正在推送2推送成功-1不需要推送-2推送失败',
          `delstatus` tinyint(1) NOT NULL DEFAULT '0' COMMENT '删除状态',
          `dateline` int(10) unsigned NOT NULL DEFAULT '0',
          `lastupdate` int(10) unsigned NOT NULL DEFAULT '0',
          PRIMARY KEY (`aid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $this->db->query($sql, 'SILENT');
        if($this->db->error()) return false;
        return true;
    }
    
    function gen_alarm_tableid($deviceid) {
        $this->base->log('alarmmmm', $deviceid);
        if(!$deviceid) return 0;
        
        $max = $this->db->fetch_first("SELECT tableid, devicenum FROM ".API_DBTABLEPRE."device_alarm_table WHERE tableid=(SELECT max(tableid) FROM ".API_DBTABLEPRE."device_alarm_table)");
        $this->base->log('alarmmmm', json_encode($max));
        if($max && $max['tableid']) {
            if($max['devicenum'] < 10000) {
                $tableid = $max['tableid'];
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_alarm_table SET `devicenum`=`devicenum`+1 WHERE tableid='$tableid'");
                $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `alarm_tableid`='$tableid' WHERE deviceid='$deviceid'");
                return $tableid;
            } else {
                $tableid = $max['tableid'] + 1;
            }
        } else {
            $tableid = 1;
        }
        
        $create_table_sql = "CREATE TABLE IF NOT EXISTS ".API_DBTABLEPRE."device_alarm_".$tableid." (
          `deviceid` varchar(40) NOT NULL,
          `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
          `type` tinyint(3) NOT NULL DEFAULT '0',
          `sensorid` varchar(40) NOT NULL DEFAULT '' COMMENT '传感器ID',
          `sensortype` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '设备类型:1:门磁.2:PIR.3:SOS',
          `actionid` varchar(40) NOT NULL DEFAULT '' COMMENT '动作码',
          `actiontype` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '动作类型:1.低电（门磁，PIR），2.开门，3.关门，4.拆除（门磁，PIR)，5.PIR触发，6.SOS',
          `time` int(10) unsigned NOT NULL DEFAULT '0',
          `expiretime` int(10) unsigned NOT NULL DEFAULT '0',
          `table` varchar(20) NOT NULL DEFAULT '',
          `aid` int(10) unsigned NOT NULL DEFAULT '0',
          UNIQUE KEY `alarm` (`deviceid`, `uid`, `type`, `sensorid`, `sensortype`, `actionid`, `actiontype`, `time`),
          KEY `idx_time` (`deviceid`, `uid`, `time`) USING BTREE,
          KEY `idx_expiretime` (`expiretime`) USING BTREE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;";
        
        $this->db->query($create_table_sql, 'SILENT');
        if($this->db->error()) return 0;
        
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_alarm_table SET tableid='$tableid', `devicenum`=1");
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `alarm_tableid`='$tableid' WHERE deviceid='$deviceid'");
        return $tableid;
    }
    
    function get_alarm_by_aid($alarm_tableid, $table, $aid) {
        if(!$alarm_tableid || !$table || !$aid)
            return false;
        
        $alarm_file_table = 'device_alarm_'.$alarm_tableid.'_'.$table;
        $pic = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE.$alarm_file_table." WHERE aid='$aid'");
        return $pic?$pic:false;
    }
    
    function get_alarm_temp_url($device, $alarm) {
        if(!$alarm || !$alarm['storageid'])
            return false;
        
        $params = array('uid'=>$alarm['uid'], 'type'=>'alarm', 'container'=>$alarm['pathname'], 'object'=>$alarm['filename'], 'device'=>$device);  
        return $this->base->storage_temp_url($alarm['storageid'], $params);
    }
    
    function _get_current_firmware($device) {
        if(!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        
        $firmware = 0;
        
        //$status = $this->db->fetch_first('SELECT firmware, lastonline FROM '.API_DBTABLEPRE.'device_status WHERE deviceid="'.$deviceid.'" AND lastonline>'.($this->base->time - 100));
        $status = $this->db->fetch_first('SELECT firmware, lastonline FROM '.API_DBTABLEPRE.'device_status WHERE deviceid="'.$deviceid.'" AND lastonline>'.($this->base->time - 3600*24));
        if($status && $status['firmware'] != -1) {
            $firmware = $status['firmware'];
        }

        if(!$firmware) {
            $firmware = $this->_get_device_firmware($deviceid);
        }

        if(!$firmware) {
            $this->sync_setting($device, 'firmware');
            $firmware = $this->_get_device_firmware($deviceid);
        }

        return $firmware;
    }
    
    function _need_cron_set_power($device) {
        $current_firmware = $this->_get_current_firmware($device);
        if(!$current_firmware)
            return false;
        
        if($current_firmware < 7123018)
            return false;
        
        return true;
    }
    
    function _connect_type_to_cloud($connect_type) {
        switch($connect_type) {
            case API_LINGYANG_CONNECT_TYPE:
                return 1;
            case API_IERMU_CONNECT_TYPE:
                return 50;
            default:
                return 0;
        }
    }
    
    function _cloud_to_connect_type($cloud) {
        switch($cloud) {
            case 0:
                return API_BAIDU_CONNECT_TYPE;
            case 1:
                return API_LINGYANG_CONNECT_TYPE;
            case 50:
                return API_IERMU_CONNECT_TYPE;
            default:
                return API_LINGYANG_CONNECT_TYPE;
        }
    }

    function _packtime_to_timestamp($packtime, $timezone = 'Asia/Shanghai') {
        $timestamp = 0;

        if ($packtime) {
            date_default_timezone_set($timezone);
            $datetime = ((($packtime>>26)&0x3F)+1984).'-'.(($packtime>>22)&0xF).'-'.(($packtime>>17)&0x1F).' '.(($packtime>>12)&0x1F).':'.(($packtime>>6)&0x3F).':'.(($packtime)&0x3F);
            $timestamp = strtotime($datetime);
        }

        return $timestamp;
    }
    
    function sum($uid, $support_type, $appid) {
        if(!$uid || !$support_type || !$appid)
            return false;
        //判断企业用户 我的设备授权设备机制修改
        $isorg = $_ENV['org']->getorgidbyuid($uid);
        if($isorg){
            if($isorg['admin'] == 1){
                //管理员
                $devicenum = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device_org a LEFT JOIN ".API_DBTABLEPRE."member_org b ON b.org_id=a.org_id WHERE b.uid='$uid' AND b.admin=1");
                $deviceonline = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device a LEFT JOIN ".API_DBTABLEPRE."device_org b ON b.deviceid=a.deviceid LEFT JOIN ".API_DBTABLEPRE."member_org c ON c.org_id=b.org_id WHERE c.uid='$uid' AND a.connect_online=1 AND a.status&4!=0");
            }else{
                $devicenum = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device_grant WHERE uk='$uid' AND auth_type=1");
                $deviceonline = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device_grant a LEFT JOIN ".API_DBTABLEPRE."device b ON b.deviceid=a.deviceid WHERE a.uk='$uid' AND b.connect_online=1 AND b.status&4!=0");
            }
        }else{
            $devicenum = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device WHERE uid='$uid'");
            $deviceonline = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device WHERE uid='$uid' AND connect_online=1 AND status&4!=0");
        }
        $grantnum = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device_grant WHERE uk='$uid' AND auth_type=0");
        $grantonline = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device_grant a LEFT JOIN ".API_DBTABLEPRE."device b ON a.deviceid=b.deviceid WHERE a.uk='$uid' AND b.connect_online=1 AND b.status&4!=0 AND a.auth_type=0");
        $subnum = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device_subscribe WHERE uid='$uid'");
        $subonline = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."device_subscribe a LEFT JOIN ".API_DBTABLEPRE."device b ON a.deviceid=b.deviceid WHERE a.uid='$uid' AND b.connect_online=1 AND b.status&4!=0");
        
        $sum = array(
            'devicenum' => intval($devicenum),
            'grantnum' => intval($grantnum),
            'subnum' => intval($subnum),
            'status' => array(
                'online' => intval($deviceonline),
                'offline' => $devicenum - $deviceonline
            ),
            'grantstatus' => array(
                'online' => intval($grantonline),
                'offline' => $grantnum - $grantonline
            ),
            'substatus' => array(
                'online' => intval($subonline),
                'offline' => $subnum - $subonline
            )
        );
       
        return $sum;
    }
    
    function listalarmdevice($uid, $page, $count, $appid) {
        if(!$uid)
            return false;
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        $list = array();
        
        //判断企业用户 我的设备机制修改
        $isorg = $_ENV['org']->getorgidbyuid($uid);
        if($isorg){
            if($isorg['admin'] == 1){
                //管理员
                $table = API_DBTABLEPRE."device a LEFT JOIN ".API_DBTABLEPRE."device_org b on b.deviceid=a.deviceid LEFT JOIN ".API_DBTABLEPRE."member_org c on c.org_id=b.org_id";
                $where = "WHERE c.uid='$uid' AND a.alarmnum>0 AND a.appid='$appid'";
                $total = $this->db->result_first("SELECT COUNT(*) FROM $table $where");
            }else{
                $table = API_DBTABLEPRE."device a LEFT JOIN ".API_DBTABLEPRE."device_grant b on b.deviceid=a.deviceid";
                $where = "WHERE b.uk='$uid' AND b.auth_type=1 AND a.alarmnum>0 AND a.appid='$appid'";
                $total = $this->db->result_first("SELECT COUNT(*) FROM $table $where");
            }
        }else{
            $table = API_DBTABLEPRE."device a";
            $where = "WHERE a.uid='$uid' AND a.alarmnum>0 AND a.appid='$appid'";
            $total = $this->db->result_first("SELECT COUNT(*) FROM $table $where");
        }
        

        $pages = $this->base->page_get_page($page, $count, $total);
        $limit = ' LIMIT '.$pages['start'].', '.$count;
        
        if($total) {
            $devices = $this->db->fetch_all("SELECT a.* FROM $table $where $limit");
            foreach($devices as $device) {
                $alarm = $this->get_lastest_alarm($device);
                if($alarm) {
                    $data = array(
                        'deviceid' => $device['deviceid'],
                        'description' => addslashes($device['desc']),
                        'alarmnum' => intval($device['alarmnum']),
                        'alarm' => $alarm
                    );
                    $sum = $this->get_alarm_sum($device);
                    $data['types'] = $sum['types'];
                    $list[] = $data;
                } else {
                    // 重新统计报警数据
                }
            }
        }
        
        $result = array();
        $result['page'] = $pages['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    
    function get_lastest_alarm($device) {
        $alarm = array();
        if($device && $device['deviceid'] && $device['alarm_tableid']) {
            $uid = $device['uid'];
            $deviceid = $device['deviceid'];
            $alarm_tableid = $device['alarm_tableid'];
            $alarm_index_table = API_DBTABLEPRE.'device_alarm_'.$alarm_tableid;
            
            $time = $this->db->result_first("SELECT max(time) FROM $alarm_index_table WHERE `deviceid`='$deviceid' AND `uid`='$uid'");
            if($time) {
                $info = $this->alarminfo($device, $time);
                if($info) $alarm = $info;
            }
        }
        return $alarm;
    }

    function generate_433_json($actionid, $type, $factory, $model) {
        if (!$actionid || !$type || !$factory || !$model)
            return false;

        $result = array();
        $result['f'] = intval($factory);
        $result['m'] = intval($model);
        $result['t'] = intval($type);
        $sensorid = $this->_get_433_sensorid($actionid, $factory, $model);
        $result['id'] = strval($sensorid);
        return json_encode($result, true);
    }
    
    function generate_433_code($actionid, $type, $factory, $model) {
        if (!$actionid || !$type || !$factory || !$model)
            return false;
        
        $code = (intval($factory) << 28) | (intval($type) << 24) | (intval($model) << 20) | ((intval($actionid) >> 4) & 0xfffff);
        return str_pad(intval($code), 10, '0', STR_PAD_LEFT);
    }

    function _get_433_sensorid($actionid, $factory, $model) {
        return strval((intval($actionid) >> 4) & 0xfffff);
    }

    function _generate_433_param($sensorid, $type, $factory, $model) {
        $result = array();
        switch (intval($type)) {
            case 1:
            $result[SENSOR_ACTION_LOW_POWER_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_LOW_POWER;
            $result[SENSOR_ACTION_BROKEN_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_BROKEN;
            $result[SENSOR_ACTION_DOOR_OPEN_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_DOOR_OPEN;
            $result[SENSOR_ACTION_DOOR_CLOSE_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_DOOR_CLOSE;
            break;
            case 2:
            $result[SENSOR_ACTION_LOW_POWER_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_LOW_POWER;
            $result[SENSOR_ACTION_BROKEN_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_BROKEN;
            $result[SENSOR_ACTION_PIR_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_PIR;
            break;
            case 3:
            $result[SENSOR_ACTION_SOS_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_SOS;
            break;
            case 4:
            $result[SENSOR_ACTION_LOW_POWER_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_LOW_POWER;
            $result[SENSOR_ACTION_SMOKE_TYPE] = (intval($sensorid) << 4) | KERUI_ACTION_SMOKE;
            break;
        }
        return $result;
    }
    
    function set_init($client, $device, $init) {
        if(!$device || !$init)
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $len = strlen($init);
        if(($connect_type == API_LINGYANG_CONNECT_TYPE && $len != 156*2) || ($connect_type != API_LINGYANG_CONNECT_TYPE && $len != 336*2))
            return false;
        
        // 重置设备cvr=1
        $init[247] = '0';
        
        $this->sync_init($device, $init);
        return true; 
    }
    
    function get_alarm_sum($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        
        $sum = $types = array();
        $sum['alarmnum'] = intval($device['alarmnum']);
        
        if($sum['alarmnum']) {
            $datas = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type<>'-1' AND sensortype='-1' AND actiontype='-1'");
            foreach($datas as $data) {
                if($data['count'] > 0) {
                    $type = array(
                        'type' => intval($data['type']),
                        'total' => intval($data['count'])
                    );
            
                    // 传感器处理
                    if($data['type'] == 0) {
                        $sensortypes = array();
                        $sens = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_alarm_count WHERE deviceid='$deviceid' AND type='0' AND sensortype<>'-1' AND actiontype='-1'");
                        foreach($sens as $sen) {
                            if($sen['count'] > 0) {
                                $sensortypes[] = array(
                                    'sensortype' => intval($sen['sensortype']),
                                    'total' => intval($sen['count'])
                                );
                            }
                        }
                        $type['sensortypes'] = $sensortypes;
                    }
                    $types[] = $type;
                }
            }
        }
        
        $sum['types'] = $types;
        return $sum;
    }
    
    // 添加截屏
    function snapshot($device, $notify, $uid=0) {
        $deviceid = $device['deviceid'];
        
        if($device['share_device']) {
            $uid = 0;
        } else {
            //判断企业用户
            $uid = $uid;
            // $uid = $device['grant_uid'] ? $device['grant_uid'] : $device['uid'];
        }
        
        // 限定两秒之内只能截图一次
        $snapshot = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_snapshot WHERE `deviceid`="'.$deviceid.'" AND `uid`="'.$uid.'" AND dateline>'.($this->base->time-2).' LIMIT 1');
        if ($snapshot)
            return false;

        $client = $this->_get_api_client($device['connect_type']);
        if (!$client)
            return false;

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_snapshot SET `deviceid`="'.$deviceid.'",`uid`="'.$uid.'",`notify`="'.$notify.'",`dateline`="'.$this->base->time.'",`lastupdate`="'.$this->base->time.'"');
        $sid = $this->db->insert_id();
        $command = '{"main_cmd":74,"sub_cmd":56,"param_len":64,"params":"00' . $this->_bin2hex(strval($sid), 126) . '"}';

        // api request
        if (!$client->device_usercmd($device, $command, 1))
            return false;

        $result = array('deviceid' => $deviceid, 'sid' => $sid);

        return $result;
    }

    // 检验截屏参数
    function serialize_notify($notify) {
        if (!$notify['url'] || !$notify['body'])
            return false;

        $url = (preg_match('/^https?:\/\//i', $notify['url']) ? '' : 'http://').$notify['url'];
        $result = array('url'=>$url, 'body'=>$notify['body']);

        if (preg_match_all('/(?<=\$\()\w+(?=\))/', $notify['body'], $match)) {
            foreach ($match[0] as $value) {
                if (!in_array($value, array('deviceid', 'sid', 'time', 'url'))) {
                    return false;
                }
            }

            $result['var'] = $match[0];
        }

        return serialize($result);
    }

    function get_snapshot_by_sid($sid) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_snapshot WHERE `sid`="'.$sid.'" AND `delstatus`="0"');
    }

    // 删除截屏
    function drop_snapshot($list) {
        foreach ($list as $sid) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_snapshot WHERE `delstatus`="1" WHERE `sid`="'.$sid.'"');
        }

        return true;
    }

    // 截屏信息
    function get_snapshot_info($snapshot) {
        $storage = $this->base->load_storage($snapshot['storageid']);
        if (!$storage)
            return false;
        
        $params = array(
            'uid' => $snapshot['uid'],
            'type' => 'snapshot',
            'container' => $snapshot['pathname'],
            'object' => $snapshot['filename'],
            'filename' => $snapshot['filename'],
            'expires_in' => 0,
            'local' => 0
        );

        $result = array(
            'sid' => $snapshot['sid'],
            'deviceid' => $snapshot['deviceid'],
            'time' => $snapshot['time'],
            'url' => $storage->temp_url($params)
        );

        return $result;
    }

    // 获取截屏列表
    function list_snapshot($device, $list_type, $page, $size, $uid='') {
        $deviceid = $device['deviceid'];
        
        if($device['share_device']) {
            $uid = 0;
        } else {
            //企业用户判断
            $uid = $uid;
           // $uid = $device['grant_uid'] ? $device['grant_uid'] : $device['uid'];
        }

        if ($list_type == 'page') {
            $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.API_DBTABLEPRE.'device_snapshot WHERE deviceid="'.$deviceid.'" AND uid="'.$uid.'" AND status="1" AND delstatus="0"');
            $pages = $this->base->page_get_page($page, $count, $total);

            $limit = ' LIMIT '.$pages['start'].','.$count;
            $result = array('page' => $pages['page']);
        } else {
            $limit = '';
            $result = array();
        }

        $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'device_snapshot WHERE deviceid="'.$deviceid.'" AND uid="'.$uid.'" AND status="1" AND delstatus="0"'.$limit);

        $list = array();
        for ($i = 0, $n = count($arr); $i < $n; $i++) { 
            $list[] = $this->get_snapshot_info($arr[$i]);
        }

        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    // 获取截屏上传token
    function get_snapshot_token($device, $snapshot, $time, $client_id, $sign) {
        // 上传图片位置
        // 非百度设备、公开分享截图或设置回调截图均上传七牛
        $storageid = $device['connect_type'] == 1 && !$device['share_device'] && !$snapshot['notify'] && ($device['uid'] != 171923) ? 10 : 11;
        $storage = $this->base->load_storage($storageid);
        if (!$storage)
            return false;
    
        $ret = $storage->snapshot_info($device, $snapshot['sid'], $time, $client_id, $sign);
        if (!$ret)
            return false;

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_snapshot SET time="'.$time.'",pathname="'.$ret['pathname'].'",filename="'.$ret['filename'].'",storageid='.$storageid.',status='.($storageid == 10 ? 1 : 0).',lastupdate='.$this->base->time.' WHERE sid='.$snapshot['sid']);

        $result = array(
            'storageid' => $storageid,
            'filepath' => $ret['filepath'],
            'upload_token' => $ret['upload_token']
        );
        
        return $result;
    }

    function snapshot_qiniu_notify($snapshot) {
        if ($snapshot['notify']) {
            $notify = unserialize($snapshot['notify']);
            $url = $notify['url'];
            $body = $notify['body'];
            $params = $this->get_snapshot_info($snapshot);

            foreach ($notify['var'] as $value) {
                $body = str_replace('$('.$value.')', $params[$value], $body);
            }

            // 回调通知
            $this->_http_request($url, $body, 'POST');
        }

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_snapshot SET notify_status='.($snapshot['notify'] ? 1 : 0).',status=1,lastupdate='.$this->base->time.' WHERE sid="'.$snapshot['sid'].'"');

        $result = array(
            'storageid' => $snapshot['storageid'],
            'status' => '1'
        );
        
        return $result;
    }

    function _http_request($url, $body = '', $method = 'GET') {
        $ch = curl_init();
        
        $curl_opts = array(
            CURLOPT_CONNECTTIMEOUT  => 3,
            CURLOPT_TIMEOUT         => 20,
            CURLOPT_USERAGENT       => 'iermu api server/1.0',
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_FOLLOWLOCATION  => false
        );

        if (stripos($url, 'https://') === 0) {
            $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
        }

        if ($body && is_array($body)) {
            $body = http_build_query($body, '', '&');
        }

        if (strtoupper($method) === 'GET') {
            $curl_opts[CURLOPT_URL] = $url.(strpos($url, '?') === false ? '?' : '&').$body;
            $curl_opts[CURLOPT_POST] = false;
        } else {
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $curl_opts);
        $result = curl_exec($ch);

        if ($result === false) {
            $result = array();
        }

        curl_close($ch);

        return $result;
    }
    
    function alarmsetting($uid) {
        if(!$uid)
            return false;
        
        $weixin_push = -1;
        $wx_connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".API_WEIXIN_CONNECT_TYPE."' AND uid='$uid'");
        if($wx_connect && $wx_connect['connect_uid']) {
            $weixin_push = $this->db->result_first("SELECT alarm_push FROM ".API_DBTABLEPRE."member_weixin WHERE unionid='".$wx_connect['connect_uid']."'");
            $weixin_push = intval($weixin_push);
        }
        
        $result = array('weixin_push' => $weixin_push);
        return $result;
    }
    
    function updatealarmsetting($uid, $fileds) {
        if(!$uid || !$fileds)
            return false;
        
        $result = array();
        
        $weixin_push = $fileds['weixin_push'];
        if($weixin_push !== NULL) {
            $weixin_push = $weixin_push?1:0;
            $wx_connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".API_WEIXIN_CONNECT_TYPE."' AND uid='$uid'");
            if($wx_connect && $wx_connect['connect_uid']) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."member_weixin SET alarm_push='$weixin_push' WHERE unionid='".$wx_connect['connect_uid']."'");
                $result['weixin_push'] = $weixin_push;
            }
        }
        
        if(!$result)
            return false;
        
        return $result;
    }
    
    function add_cvr_plan($deviceid, $plan, $num) {
        if(!$deviceid || !$plan || !$num)
            return false;
        
        $device = $this->get_device_by_did($deviceid);
        if(!$device || $device['connect_type'] != $plan['connect_type'])
            return false;
        
        $connect_type = $device['connect_type'];
        $cvr_type = $plan['cvr_type'];
        $cvr_day = $plan['cvr_day'];
        
        $record = $this->get_last_cvr_record($device);
        $cvr_start_time = $record ? $record['cvr_end_time'] : $this->base->time;

        // 开始收费事件：2016-04-05 00:00:00
        if($cvr_start_time < 1491321600) $cvr_start_time = 1491321600;
        
        $cvr_end_time = strtotime('+'.($plan['plan_month'] * $num).' month', $cvr_start_time);
        
        // db
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_cvr_record SET deviceid='$deviceid', connect_type='$connect_type', cvr_type='$cvr_type', cvr_day='$cvr_day', cvr_start_time='$cvr_start_time', cvr_end_time='$cvr_end_time', dateline='".$this->base->time."'");
        
        // 相同类型更新数据
        if($device['cvr_type'] == $cvr_type && $device['cvr_day'] == $cvr_day) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET cvr_end_time='$cvr_end_time' WHERE deviceid='$deviceid'");
            $device['cvr_end_time'] = $cvr_end_time;
        }
        
        $this->update_cvr_info($device, true);
        
        // 状态通知设备
        $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"03000000"}';
        $client = $this->_get_api_client($connect_type);
        if($client) {
            $client->device_usercmd($device, $command, 0);
        }
        
        $result = array(
            'deviceid' => $deviceid,
            'connect_type' => $connect_type,
            'cvr_type' => $cvr_type,
            'cvr_day' => $cvr_day,
            'cvr_start_time' => $cvr_start_time,
            'cvr_end_time' => $cvr_end_time
        );
        return $result;
    }

    function get_cvr_record_by_payment($deviceid, $connect_type) {
        switch ($connect_type) {
            case '2':
                $temp = $this->db->fetch_all('SELECT connect_type,cvr_type,cvr_day,cvr_start_time,cvr_end_time FROM '.API_DBTABLEPRE.'device_cvr_record WHERE deviceid="'.$deviceid.'" AND connect_type="'.$connect_type.'" AND cvr_end_time>"'.$this->base->time.'"');
                
                // 合并羚羊购买云服务
                $list = array();
                for ($i = 0, $n = count($temp), $temp_key = 0; $i < $n; $i++) {
                    
                    if ($i > $temp_key) {
                        if ($temp[$i]['connect_type'] == $temp[$temp_key]['connect_type'] && $temp[$i]['cvr_type'] == $temp[$temp_key]['cvr_type'] && $temp[$i]['cvr_day'] == $temp[$temp_key]['cvr_day'] && $temp[$i]['cvr_start_time'] <= $temp[$temp_key]['cvr_end_time'] && $temp[$i]['cvr_end_time'] > $temp[$temp_key]['cvr_end_time']) {
                            $temp[$temp_key]['cvr_end_time'] = $temp[$i]['cvr_end_time'];
                        } else {
                            $list[] = $temp[$temp_key];
                            $temp_key = $i;
                        }
                    }
                    
                    if ($i == $n-1) $list[] = $temp[$temp_key];
                    
                }
                break;
            
            default:
                $list = $this->db->fetch_all('SELECT connect_type,1 AS cvr_type,cvr_day,'.$this->base->time.' AS cvr_start_time,cvr_end_time FROM '.API_DBTABLEPRE.'device WHERE deviceid="'.$deviceid.'" AND connect_type="'.$connect_type.'" AND cvr_end_time>"'.$this->base->time.'"');
                break;
        }

        return $list;
    }

    function get_current_cvr_record($device) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $record = $this->get_cvr_record_by_payment($deviceid, $connect_type);
        
        return current($record);
    }
    
    function get_last_cvr_record($device) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $record = $this->get_cvr_record_by_payment($deviceid, $connect_type);

        return end($record);
    }

    function get_cvr_record_by_default($deviceid, $connect_type) {
        switch ($connect_type) {
            case '2':
                $default_cvr = array(
                    'connect_type' => $connect_type,
                    'cvr_type' => '0',
                    'cvr_day' => '7',
                    'cvr_start_time' => strval($this->base->time),
                    'cvr_end_time' => '2145888000',
                    'cvr_free' => '1'
                );

                // 特殊羚羊默认云服务
                $cvr_status = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_cvr_status WHERE deviceid="'.$deviceid.'" AND connect_type="'.$connect_type.'"');
                if ($cvr_status) {
                    $default_cvr['cvr_type'] = $cvr_status['cvr_type'];
                    $default_cvr['cvr_day'] = $cvr_status['cvr_day'];
                }
                break;
            
            default:
                $default_cvr = array(
                    'connect_type' => $connect_type,
                    'cvr_type' => '0',
                    'cvr_day' => '0',
                    'cvr_start_time' => '0',
                    'cvr_end_time' => '0'
                );
                break;
        }

        return $default_cvr;
    }
    
    function merge_cvr_info($device, $cvr) {
        $device = array_merge($device, $cvr);
        if($device['cvr_type'] == 0) {
            switch ($device['connect_type']) {
                case '2':
                    $device['cvr_day'] = '7';
                    $device['cvr_start_time'] = strval($this->base->time);
                    $device['cvr_end_time'] = '2145888000';
                    $device['cvr_free'] = '1';
                    break;
                default:
                    $device['cvr_day'] = '0';
                    $device['cvr_start_time'] = '0';
                    $device['cvr_end_time'] = '0';
                    break;
            }
        }
        return $device;
    }
    
    function update_cvr_info($device, $force = false) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        // 添加测试设备处理
        $dev = $this->db->fetch_first('SELECT cvr_type,cvr_day,cvr_end_time,cvr_free,init_cvr_cron FROM '.API_DBTABLEPRE.'device_dev WHERE deviceid="'.$deviceid.'" AND cvr=1 AND cvr_connect_type="'.$connect_type.'"');
        if ($dev) {
            $device['dev_cvr'] = 1;
            return $this->merge_cvr_info($device, $dev);
        }
        
        // 添加partner设备处理
        $partner_device = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_partner WHERE deviceid="'.$deviceid.'"');
        if ($partner_device) {
            $partner_cvr = array();
            if($partner_device['cvr'] && $partner_device['cvr_connect_type'] == $connect_type) {
                $partner_cvr = array(
                    'cvr_type' => strval($partner_device['cvr_type']),
                    'cvr_day' => strval($partner_device['cvr_day']),
                    'cvr_end_time' => strval($partner_device['cvr_end_time']),
                    'cvr_free' => strval($partner_device['cvr_free']),
                    'init_cvr_cron' => strval($partner_device['init_cvr_cron'])
                );
            } else {
                $partner = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'partner WHERE partner_id="'.$partner_device['partner_id'].'"');
                if($partner && $partner['config']) {
                    $config = json_decode($partner['config'], true);
                    if($config && $config['cvr'] && $config['cvr_connect_type'] == $connect_type) {
                        $partner_cvr = array(
                            'cvr_type' => strval($config['cvr_type']),
                            'cvr_day' => strval($config['cvr_day']),
                            'cvr_end_time' => strval($config['cvr_end_time']),
                            'cvr_free' => strval($config['cvr_free']),
                            'init_cvr_cron' => strval($config['init_cvr_cron'])
                        );
                    }
                }
            }

            if($partner_cvr) {
                $device['partner_cvr'] = 1;
                return array_merge($device, $partner_cvr);
            }
        }

        if (!$force && $device['cvr_end_time'] >= time())
            return $device;

        // 必须实时获取第一段生效的云录制，否则云录制到期时间不会累加
        $record = $this->get_current_cvr_record($device);
        if ($record) {
            if ($device['cvr_type'] != $record['cvr_type'] || $device['cvr_day'] != $record['cvr_day'] || $device['cvr_end_time'] != $record['cvr_end_time']) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET cvr_type="'.$record['cvr_type'].'",cvr_day="'.$record['cvr_day'].'",cvr_end_time="'.$record['cvr_end_time'].'",init_cvr_cron='.($device['cvr_type'] != $record['cvr_type'] ? 1 : 0).' WHERE deviceid="'.$deviceid.'" AND connect_type="'.$connect_type.'"');
            }
        } else {
            $record = $this->get_cvr_record_by_default($deviceid, $connect_type);
        }

        return $this->merge_cvr_info($device, $record);
    }
    
    function list_cvr_record($device) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $record = array();
        
        // 添加测试设备处理
        $dev = $this->db->fetch_first("SELECT cvr_type,cvr_day,cvr_end_time,cvr_free FROM ".API_DBTABLEPRE."device_dev WHERE deviceid='".$device['deviceid']."' AND cvr=1 AND cvr_connect_type='".$device['connect_type']."'");
        if ($dev) {
            $device['dev_cvr'] = 1;
            $record[] = array(
                'connect_type' => $connect_type,
                'cvr_type' => $dev['cvr_type'],
                'cvr_day' => $dev['cvr_day'],
                'cvr_start_time' => '0',
                'cvr_end_time' => $dev['cvr_end_time'],
                'cvr_free' => $dev['cvr_free']
            );
        }
        
        // 添加partner设备处理
        if (!$record) {
            $partner_device = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_partner WHERE deviceid="'.$deviceid.'"');
            if ($partner_device) {
                $partner_cvr = array();
                if($partner_device['cvr'] && $partner_device['cvr_connect_type'] == $connect_type) {
                    $record[] = array(
                        'connect_type' => $connect_type,
                        'cvr_type' => strval($partner_device['cvr_type']),
                        'cvr_day' => strval($partner_device['cvr_day']),
                        'cvr_start_time' => '0',
                        'cvr_end_time' => strval($partner_device['cvr_end_time']),
                        'cvr_free' => strval($partner_device['cvr_free'])
                    );
                } else {
                    $partner = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'partner WHERE partner_id="'.$partner_device['partner_id'].'"');
                    if($partner && $partner['config']) {
                        $config = json_decode($partner['config'], true);
                        if($config && $config['cvr'] && $config['cvr_connect_type'] == $connect_type) {
                            $record[] = array(
                                'connect_type' => $connect_type,
                                'cvr_type' => strval($config['cvr_type']),
                                'cvr_day' => strval($config['cvr_day']),
                                'cvr_start_time' => '0',
                                'cvr_end_time' => strval($config['cvr_end_time']),
                                'cvr_free' => strval($config['cvr_free'])
                            );
                        }
                    }
                }
            }
        }
        
        if (!$record) {
            $record = $this->get_cvr_record_by_payment($deviceid, $connect_type);
        }
        
        if (!$record) {
            $record[] = $this->get_cvr_record_by_default($deviceid, $connect_type);
        }

        $result = array(
            'count' => count($record),
            'list' => $record
        );

        return $result;
    }
    
    function gen_cvr_alarm($device, $update=true) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        
        $cvr_alarm = 0;
        
        $daystart = $this->base->day_start_time($this->base->time, $device['timezone']);
        if($device['lastcvralarm'] < $daystart) {
            if($update) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device SET cvralarmnum='1', lastcvralarm='".$this->base->time."' WHERE deviceid='$deviceid'");
            }
            $cvr_alarm = 1;
        } else {
            $cvr_alarm = $this->get_cvr_alarm($device);
            if($update && $cvr_alarm) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device SET cvralarmnum=cvralarmnum+1, lastcvralarm='".$this->base->time."' WHERE deviceid='$deviceid'");
            }
        }
         
        return $cvr_alarm;
    }
    
    function get_cvr_alarm($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        
        $cvr_alarm = 0;
        
        if($device['cvr_type'] == 0) {
            // 免费每天30条报警录像
            if($device['cvralarmnum'] < 29) {
                $cvr_alarm = 1;
            }
        } else {
            $cvr_alarm = 1;
        }
         
        return $cvr_alarm;
    }

    function list_contact($deviceid) {
        $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'device_contact WHERE deviceid="'.$deviceid.'"');

        $list = array();
        foreach ($arr as $value) {
            $list[] = array(
                'id' => intval($value['id']),
                'name' => $value['name'],
                'phone' => $value['phone']
            );
        }

        return $list;
    }

    function get_contact_by_id($id) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_contact WHERE id="'.$id.'"');
    }

    function add_contact($deviceid, $name, $phone) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_contact SET deviceid="'.$deviceid.'",name="'.$name.'",phone="'.$phone.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');

        return $this->db->insert_id();
    }

    function update_contact($id, $name, $phone) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_contact SET name="'.$name.'",phone="'.$phone.'",lastupdate="'.$this->base->time.'" WHERE id="'.$id.'"');

        return true;
    }

    function drop_contact($id) {
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'device_contact WHERE id="'.$id.'"');

        return true;
    }
    
    function authcode($uid, $operation, $param, $client_id, $appid) {
        if(!$uid || !$operation)
            return false;
        
        // 清理过期数据
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_authcode_register WHERE codeid IN ( SELECT codeid FROM ".API_DBTABLEPRE."device_authcode WHERE (expiredate=0 AND usedate>0 AND usedate<'".($this->base->time - 24*3600)."') OR (expiredate>0 AND expiredate<'".($this->base->time - 24*3600)."'))");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_authcode WHERE (expiredate=0 AND usedate>0 AND usedate<'".($this->base->time - 24*3600)."') OR (expiredate>0 AND expiredate<'".($this->base->time - 24*3600)."')");
        
        $retry = 3;
        while($retry > 0) {
            $code = $this->base->n16to32(md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid()))));
            if($this->get_authcode_by_code($code)) {
                $retry--;
            } else {
                break;
            }
        }
        
        if(!$code)
            return false;
        
        $expires_in = 5*60;
        $expiredate = $this->base->time + $expires_in;
        
        $param = $param?serialize($param):"";
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_authcode SET code='$code', operation='$operation', param='$param', uid='$uid', expiredate='$expiredate', dateline='".$this->base->time."', client_id='$client_id', appid='$appid', status='0'");
        
        return $this->get_authcode_by_code($code);
    }
    
    function get_authcode_by_code($code) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_authcode WHERE code='$code'");
    }
    
    function get_authcode_by_codeid($codeid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_authcode WHERE codeid='$codeid'");
    }
    
    function update_authcode_status_by_codeid($codeid, $status) {
        return $this->db->fetch_first("UPDATE ".API_DBTABLEPRE."device_authcode SET status='$status',lastupdate='".$this->base->time."' WHERE codeid='$codeid'");
    }
    
    function authstatus($code) {
        $ret = array('status' => -2);
        if(!$code)
            return $ret;
        
        $authcode = $this->get_authcode_by_code($code);
        if(!$authcode || $authcode['status'] < 0)
            return $ret;
        
        $codeid = $authcode['codeid'];
        
        if($authcode['expiredate'] > 0 && $authcode['expiredate'] < $this->base->time) {
            $this->update_authcode_status_by_codeid($codeid, -2);
            return $ret;
        }
        
        $ret['status'] = intval($authcode['status']);
        $ret['operation'] = $authcode['operation'];
        if($authcode['operation'] == 'register' && $authcode['status'] == 1) {
            $list = array();
            $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_authcode_register WHERE codeid='$codeid'");
            foreach($query as $value) {
                $data = array(
                    'deviceid' => $value['deviceid'],
                    'status' => intval($value['status'])
                );
                
                if($value['status'] == 2 && $value['result']) {
                    $result = unserialize($value['result']);
                    $data['result'] = $result;
                }
                
                $list[] = $data;
            }
            $ret['count'] = count($list);
            $ret['list'] = $list;
        }
        return $ret;
    } 
    
    function update_authcode_register($authcode, $deviceid, $error=NULL, $extras=NULL) {
        if(!$authcode || !$authcode['codeid'] || $authcode['operation'] != 'register' || $authcode['status'] < 0 || !$deviceid)
            return false;
        
        $codeid = $authcode['codeid'];
        
        $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_authcode_register WHERE codeid='$codeid' AND deviceid='$deviceid'");
        if(!$check)
            return false;
        
        if($error === NULL) {
            $error_code = 0;
            $error_msg = 'success';
        } else {
            if($error && $p = strpos($error, ':')) {
                $error_code = intval(substr($error, 0, $p));
                $error_msg = substr($error, $p+1);
            } else {
                $error_code = 400;
                $error_msg = 'error';
            }
        }
        
        $result = array(
            'error_code' => $error_code,
            'error_msg' => $error_msg
        );
        
        if($extras && is_array($extras)) $result = array_merge($result, $extras);
        $result = serialize($result);
        
        $this->db->query("UPDATE ".API_DBTABLEPRE."device_authcode_register SET status='2', result='$result', lastupdate='".$this->base->time."' WHERE codeid='$codeid' AND deviceid='$deviceid'");
        
        return true;
    }
    
    function updateauth($code, $operation, $params) {
        if(!$code || !$operation || !in_array($operation, array('register')))
            return false;
        
        $authcode = $this->get_authcode_by_code($code);
        if(!$authcode)
            return false;
        
        if($operation != $authcode['operation'])
            return false;
        
        $codeid = $authcode['codeid'];
        
        if($operation == 'register') {
            $deviceid = $params['deviceid'];
            $type = $params['type'];
            
            if(!$deviceid || !$type)
                return false;
            
            if($authcode['status'] == 0 && $authcode['expiredate'] > 0 && $authcode['expiredate'] < $this->base->time) {
                $this->update_authcode_status_by_codeid($codeid, -2);
                $authcode['status'] = -2;
            }
            
            $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_authcode_register WHERE codeid='$codeid' AND deviceid='$deviceid'");
            
            if($authcode['status'] < 0 && !$check)
                return array('status' => intval($authcode['status']));
            
            if($authcode['status'] == 0) {
                $this->update_authcode_status_by_codeid($codeid, 1);
                $authcode['status'] = 1;
            }
            
            $lang = API_LANGUAGE;
            $timezone = 'Asia/Shanghai';
            $desc = '';
        
            $config = array();
            if($authcode['param']) {
                $config = unserialize($authcode['param']);
            }
        
            if($config) {
                if($config['lang']) $lang = $config['lang'];
                if($config['timezone']) $timezone = $config['timezone'];
                if($config['desc']) $desc = $config['desc'];
            }
            
            $sqladd = "";
            if($lang) $sqladd .= ", `lang`='$lang'";
            if($timezone) $sqladd .= ", `timezone`='$timezone'";
            if($desc) $sqladd .= ", `desc`='$desc'";
            
            if(!$check) {
                $status = 0;
                if($type == 'qrcode') $status = 1;
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_authcode_register SET codeid='$codeid', deviceid='$deviceid', uid='".$authcode['uid']."', type='$type', status='$status', client_id='".$authcode['client_id']."', appid='".$authcode['appid']."', dateline='".$this->base->time."'$sqladd");
            } else {
                if(!$check['type'] || $check['type'] != $type) {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_authcode_register SET type='$type', lastupdate='".$this->base->time."'$sqladd WHERE codeid='$codeid' AND deviceid='$deviceid'");
                }
                $status = $check['status'];
            }
            
            return array('status' => intval($status));
        }
        
        return false;
    }
    
    function grantauth($code, $operation, $param) {
        if(!$code || !$operation || !in_array($operation, array('register')))
            return false;
        
        $authcode = $this->get_authcode_by_code($code);
        if(!$authcode)
            return false;
        
        if($operation != $authcode['operation'])
            return false;
        
        $codeid = $authcode['codeid'];
        
        if($operation == 'register') {
            if(!$param || !is_array($param) || !$param['list'])
                return false;
            
            if($authcode['status'] == 0 && $authcode['expiredate'] > 0 && $authcode['expiredate'] < $this->base->time) {
                $this->update_authcode_status_by_codeid($codeid, -2);
                $authcode['status'] = -2;
            }
            
            if($authcode['status'] < 0)
                return false;
            
            $list = array();
            
            foreach($param['list'] as $value) {
                $deviceid = $value['deviceid'];
                $status = $value['status']?1:0;
                
                if($status) {
                    $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_authcode_register WHERE codeid='$codeid' AND deviceid='$deviceid'");
                    if(!$check)
                        return false;
                    
                    $status = $check['status'];
                    if($status == 0) {
                        $this->db->query("UPDATE ".API_DBTABLEPRE."device_authcode_register SET status='1', lastupdate='".$this->base->time."' WHERE codeid='$codeid' AND deviceid='$deviceid'");
                        $status = 1;
                    }
                    
                    $data = array(
                        'deviceid' => $deviceid,
                        'status' => $status
                    );
                    $list[] = $data;
                }
            }
            
            $result = array(
                'count' => count($list),
                'list' => $list
            );
            return $result;
        }
        
        return false;
    }
    
    function get_authcode_register_by_codeid($codeid, $deviceid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_authcode_register WHERE codeid='$codeid' AND deviceid='$deviceid'");
    }
    
    function alarmspace($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;
        
        return $client->alarmspace($device);
    }
    
    function mediastatus($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        
        $data = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_mediaplay WHERE deviceid='$deviceid'");
        if(!$data) {
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_mediaplay SET deviceid='$deviceid', status='0', dateline='".$this->base->time."', lastupdate='".$this->base->time."'");
            return array(
                'deviceid' => strval($deviceid),
                'status' => 0,
                'interval' => 5
            ); 
        }
        
        $status = array(
            'deviceid' => strval($data['deviceid']),
            'status' => intval($data['status']),
            'volume' => $this->get_media_volume($device),
            'type' => strval($data['type']),
            'albumid' => strval($data['albumid']),
            'trackid' => strval($data['trackid']),
            'mode' => intval($data['mode']),
            'size' => intval($data['size']),
            'offset' => intval($data['offset']),
            'interval' => 5
        );
        
        return $status;
    }
    
    function mediaplay($device, $type, $action, $params, $cmd=TRUE) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        if($type == 'audio') {
            $status = $this->mediastatus($device);
            if(!$status || $status['type'] != $type) {
                $status = array(
                    'deviceid' => $deviceid,
                    'status' => 0
                );
            }
            
            $this->base->load('audio');
            
            $control = 0;
            switch($action) {
                case 'stop':
                    if($status['status'] == 0) {
                        return $status;
                    }
                    
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET status='0', lastupdate='".$this->base->time."' WHERE deviceid='$deviceid'");
                    
                    $control = 1;
                    break;
                case 'pause':
                    if($status['status'] != 1) {
                        return $status;
                    }
                    
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET status='2', lastupdate='".$this->base->time."' WHERE deviceid='$deviceid'");
                    
                    $control = 3; 
                    break;
                case 'start':
                    $album = $params['album'];
                    $track = $params['track'];
                    if(!$album || !$track)
                        return false;
        
                    $albumid = $album['albumid'];
                    $trackid = $track['trackid'];
                    $duration = $track['duration'];
                    
                    $sqladd = '';
                    if(isset($params['mode'])) $sqladd .= ", mode='".$params['mode']."'";
                    
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET type='$type', albumid='$albumid', trackid='$trackid', status='1', duration='$duration', size='0', offset='0', lastupdate='".$this->base->time."', laststarttime='".$this->base->time."'$sqladd WHERE deviceid='$deviceid'");
                    
                    $control = 2; 
                    break;
                case 'continue': 
                    if($status['status'] != 2) {
                        return $status;
                    }

                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET status='1', lastupdate='".$this->base->time."', laststarttime='".$this->base->time."' WHERE deviceid='$deviceid'");
                    
                    $control = 4; 
                    break;
                case 'next':
                    $albumid = $status['albumid'];
                    $trackid = $status['trackid'];
                    if(!$albumid || !$trackid)
                        return false;
                    
                    if($params['auto'] && $status['mode'] == 0)
                        return false;
                    
                    $nextid = 0;
                    switch($status['mode']) {
                        case 0:
                        case 1:
                            $nextid = $trackid;
                            break;
                        case 2:
                            $order_num = $this->db->result_first("SELECT order_num FROM ".API_DBTABLEPRE."audio_album_track WHERE albumid='$albumid' AND trackid='$trackid'");
                            $nextid = $this->db->result_first("SELECT trackid FROM ".API_DBTABLEPRE."audio_album_track WHERE albumid='$albumid' AND order_num>'$order_num' ORDER BY order_num ASC LIMIT 1");
                            if(!$nextid) {
                                $nextid = $this->db->result_first("SELECT trackid FROM ".API_DBTABLEPRE."audio_album_track WHERE albumid='$albumid' ORDER BY order_num ASC LIMIT 1");
                            }
                            break;
                        case 3:
                            $nextid = $this->db->result_first("SELECT trackid FROM ".API_DBTABLEPRE."audio_album_track WHERE albumid='$albumid' ORDER BY rand() LIMIT 1");
                            break;
                    }
                    
                    if($nextid) {
                        $track = $_ENV['audio']->get_track_by_id($nextid);
                        if(!$track)
                            return false;
                        
                        $duration = $track['duration'];
                        $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET type='$type', albumid='$albumid', trackid='$nextid', status='1', duration='$duration', size='0', offset='0', lastupdate='".$this->base->time."', laststarttime='".$this->base->time."' WHERE deviceid='$deviceid'");
                    }   
                
                    $control = 2; 
                    break;
                case 'prev':
                    $albumid = $status['albumid'];
                    $trackid = $status['trackid'];
                    if(!$albumid || !$trackid)
                        return false;
                    
                    $previd = 0;
                    switch($status['mode']) {
                        case 0:
                        case 1:
                            $previd = $trackid;
                            break;
                        case 2:
                            $order_num = $this->db->result_first("SELECT order_num FROM ".API_DBTABLEPRE."audio_album_track WHERE albumid='$albumid' AND trackid='$trackid'");
                            $previd = $this->db->result_first("SELECT trackid FROM ".API_DBTABLEPRE."audio_album_track WHERE albumid='$albumid' AND order_num<'$order_num' ORDER BY order_num DESC LIMIT 1");
                            break;
                        case 3:
                            $previd = $this->db->result_first("SELECT trackid FROM ".API_DBTABLEPRE."audio_album_track WHERE albumid='$albumid' ORDER BY rand() LIMIT 1");
                            break;
                    }
                    
                    if($previd) {
                        $track = $_ENV['audio']->get_track_by_id($previd);
                        if(!$track)
                            return false;
                        
                        $duration = $track['duration'];
                        $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET type='$type', albumid='$albumid', trackid='$previd', status='1', duration='$duration', size='0', offset='0', lastupdate='".$this->base->time."', laststarttime='".$this->base->time."' WHERE deviceid='$deviceid'");
                    }
                
                    $control = 2; 
                    break;
                case 'set':
                    $mode = $params['mode'];
                
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET mode='$mode', lastupdate='".$this->base->time."' WHERE deviceid='$deviceid'");
                
                    $control = 2;
                    $cmd = false;
                    break;
            }
            
            if(!$control)
                return false;
            
            if($cmd) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    return false;
            
                $command = '{"main_cmd":75,"sub_cmd":85,"param_len":4,"params":"' . $this->_parse_hex(dechex($control), 2) . '000000"}';

                $ret = $client->device_usercmd($device, $command, 1);
                if(!$ret)
                    return false;
            }
            
            return $this->mediastatus($device);
        }
        
        return false;
    }
    
    function updatemediastatus($device, $type, $status, $time, $params) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $status = intval($status);
        if(!in_array($status, array(0,1,2)))
            return false;
        
        if($type == 'audio') {
            $media_status = $this->mediastatus($device);
            if(!$media_status || $media_status['type'] != $type) {
                return false;
            }
            
            $albumid = $params['albumid'];
            $trackid = $params['trackid'];
            $size = intval($params['size']);
            $offset = intval($params['offset']);
            
            // 5秒种内有开始播放操作丢弃0状态上报
            if($status == 0) {
                $laststarttime = $this->db->result_first("SELECT laststarttime FROM ".API_DBTABLEPRE."device_mediaplay WHERE deviceid='$deviceid'");
                if($laststarttime && $laststarttime > ($this->base->time - 5))
                    return false;
            }

            if($media_status['albumid'] == $albumid && $media_status['trackid'] == $trackid && $time > $media_status['updatetime']) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_mediaplay SET status='$status', updatetime='$time', size='$size', offset='$offset', lastupdate='".$this->base->time."' WHERE deviceid='$deviceid'");
            }
            
            return $this->mediastatus($device);
        }
        
        return false;
    }
    
    function notify($device, $request_no, $data) {
        if (!$device || !$device['deviceid'])
            return false;

        $connect_type = $device['connect_type'];

        $client = $this->_get_api_client($connect_type);
        if (!$client)
            return false;

        $notify_key = $client->_notify_key($request_no);
        $notify = $this->base->redis->hGetAll($notify_key);
        if(!$notify || $notify['response'] != 2) 
            return false;

        $params = json_decode($notify['data'], true);
        if (!$params)
            return false;

        $params['param_len'] = strlen($data) / 2;
        $params['params'] = $data;

        $this->base->redis->hMset($notify_key, array('data' => json_encode($params), 'status' => 1));

        return true;
    }

    function dvrlist($device, $type, $starttime, $endtime) {
        if (!$device || !$device['deviceid'])
            return false;

        $connect_type = $device['connect_type'];

        $client = $this->_get_api_client($connect_type);
        if (!$client)
            return false;

        switch ($type) {
            case 'sum':
                $result = $this->get_sum_dvrlist($client, $device, $starttime, $endtime);
                break;
            
            default:
                $result = $this->get_daily_dvrlist($client, $device, $starttime, $endtime);
                break;
        }

        return $result;
    }

    function get_sum_dvrlist($client, $device, $starttime, $endtime) {
        if (!$client || !$device)
            return false;

        $deviceid = $device['deviceid'];
        $request_no = $client->_request_no($deviceid, 2, array('data' => '{"main_cmd":66,"sub_cmd":25,"param_len":0,"params":""}'));
        if (!$request_no)
            return false;

        $params = $request_no.$this->_parse_hex(dechex($starttime), 8).$this->_parse_hex(dechex($endtime), 8).'000000ff0000';
        $command = '{"main_cmd":66,"sub_cmd":25,"param_len":22,"params":"'.$params.'"}';
        
        $ret = $client->device_usercmd($device, $command, 2, array(), $request_no);
        if (!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;

        return $this->_parse_sum_dvrlist($this->_cmd_params($ret['data'][1]['userData']));
    }

    function _parse_sum_dvrlist($params) {
        $count = 0;
        $list = array();

        if ($params) {
            $p = unpack('V*', hex2bin($params));
            $count = intval($p[1]);
            if ($count) {
                $temp = array();
                for ($i = 2, $n = $count + 1; $i <= $n; $i++) {
                    if (!$temp || $p[$i] - end($temp) <= 86400) {
                        $temp[] = $p[$i];
                    } else {
                        $list[] = array($temp[0], end($temp) + 86399);
                        $temp = array($p[$i]);
                    }
                }
                $list[] = array($temp[0], end($temp) + 86399);
            }
        }

        return array('count' => count($list), 'list' => $list);
    }

    function get_daily_dvrlist($client, $device, $starttime, $endtime) {
        if (!$client || !$device)
            return false;

        $deviceid = $device['deviceid'];
        $request_no = $client->_request_no($deviceid, 2, array('data' => '{"main_cmd":66,"sub_cmd":26,"param_len":0,"params":""}'));
        if (!$request_no)
            return false;

        $params = $request_no.$this->_parse_hex(dechex($starttime), 8).'0000000200000001'.$this->_parse_hex(dechex($endtime), 8);
        $command = '{"main_cmd":66,"sub_cmd":26,"param_len":24,"params":"'.$params.'"}';
        
        // api request
        $ret = $client->device_usercmd($device, $command, 2, array(), $request_no);
        if (!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;

        return $this->_parse_daily_dvrlist($this->_cmd_params($ret['data'][1]['userData']));
    }

    function _parse_daily_dvrlist($params) {
        $count = 0;
        $list = array();

        if ($params) {
            $p = unpack('V', hex2bin(substr($params, 0, 8)));
            $count = intval($p[1]);
            if ($count) {
                $t = unpack('C*', hex2bin(substr($params, 8, 2*$count)));
                $p = unpack('V*', hex2bin(substr($params, 8+2*$count, 16*$count)));
                $temp = array();
                for ($i = 1; $i <= $count; $i++) {
                    if (!$temp) {
                        $temp = array($p[$i], $p[$i]+$p[$count+$i], $t[$i]);
                    } else {
                        if ($t[$i] == $temp[2] && $p[$i] - $temp[1] <= 1) {
                            $temp[1] = $p[$i]+$p[$count+$i];
                        } else {
                            $list[] = $temp;
                            $temp = array($p[$i], $p[$i]+$p[$count+$i], $t[$i]);
                        }
                    }
                }
                $list[] = $temp;
            }
        }

        return array('count' => count($list), 'list' => $list);
    }

    function get_dvrplay($device) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $dvrplay = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_dvrplay WHERE deviceid="'.$deviceid.'"');

        if ($dvrplay && $dvrplay['status']) {
            // 更新远程播放状态标志
            $flag = true;

            $client = $this->_get_api_client($connect_type);

            if ($client) {
                $ret = $client->device_usercmd($device, '{"main_cmd":74,"sub_cmd":64,"param_len":4,"params":"01000000"}', 1);

                if ($ret && $ret['data'] && $ret['data'][1] && $ret['data'][1]['userData'] && ($params = $this->_cmd_params($ret['data'][1]['userData']))) {
                    $playstatus = intval(substr($params, 6, 2), 16); // 0停止,1准备,2播放,3等待

                    switch ($playstatus) {
                        case 1:
                        case 2: $flag = false; break;
                        case 3: $flag = $this->_stop_dvrplay($client, $device); break;
                    }
                }
            }

            if ($flag) {
                $this->update_dvrplay_status($deviceid, 0);
                $dvrplay['status'] = '0';
            }
        }

        return $dvrplay;
    }

    function dvrplay($device, $udid, $action, $status, $starttime, $endtime) {
        if (!$device || !$device['deviceid'] || !$udid || !$action)
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $client = $this->_get_api_client($connect_type);
        if (!$client)
            return false;

        switch ($action) {
            case 'start':
                $ret = $this->_start_dvrplay($client, $device, $status, $starttime, $endtime);
                break;
            
            default:
                $ret = $this->_stop_dvrplay($client, $device);
                break;
        }
        
        if(!$ret)
            return false;
        
        return $_ENV['device']->save_dvrplay($deviceid, $udid, $action==='start'?1:0, $starttime, $endtime);
    }

    function _start_dvrplay($client, $device, $status, $starttime, $endtime) {
        if (!$client || !$device)
            return false;

        $deviceid = $device['deviceid'];

        $params = $this->_parse_hex(dechex($status+1), 8).$this->_bin2hex($deviceid, 64).'01000000'.$this->_parse_hex(dechex($starttime), 8).$this->_parse_hex(dechex($endtime), 8);
        $command = '{"main_cmd":75,"sub_cmd":64,"param_len":48,"params":"'.$params.'"}';

        $ret = $client->device_usercmd($device, $command, 1);
        if (!$ret)
            return false;

        return true;
    }

    function _stop_dvrplay($client, $device) {
        if (!$client || !$device)
            return false;

        $deviceid = $device['deviceid'];

        $params = $this->_parse_hex(dechex(3), 8).$this->_bin2hex($deviceid, 64).'01000000';
        $command = '{"main_cmd":75,"sub_cmd":64,"param_len":40,"params":"'.$params.'"}';

        $ret = $client->device_usercmd($device, $command, 1);
        if (!$ret)
            return false;

        return true;
    }

    function save_dvrplay($deviceid, $udid, $status, $starttime, $endtime) {
        $keeplive = $status ? DVR_KEEPLIVE_INTERVAL*2+$this->base->time : 0;

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_dvrplay SET deviceid="'.$deviceid.'",udid="'.$udid.'",status='.$status.',starttime='.$starttime.',endtime='.$endtime.',keeplive='.$keeplive.',dateline='.$this->base->time.',lastupdate='.$this->base->time.' ON DUPLICATE KEY UPDATE udid="'.$udid.'",status='.$status.',starttime='.$starttime.',endtime='.$endtime.',keeplive='.$keeplive.',lastupdate='.$this->base->time);

        return true;
    }

    function update_dvrplay_status($deviceid, $status) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_dvrplay SET status='.$status.',lastupdate='.$this->base->time.' WHERE deviceid="'.$deviceid.'"');

        return true;
    }

    function update_dvrplay_keeplive($deviceid, $keeplive) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_dvrplay SET keeplive='.$keeplive.',lastupdate='.$this->base->time.' WHERE deviceid="'.$deviceid.'"');

        return true;
    }
    
    function update_device_connect_by_cid($connect_type, $connect_cid, $connect_online=NULL, $connect_thumbnail=NULL) {
        if(!$connect_type || !$connect_cid || $connect_online === NULL)
            return false;
        
        $connect_online = $connect_online?1:0;
        $sqladd = "connect_online='$connect_online'";
        if($connect_thumbnail !== NULL) $sqladd .= ", connect_thumbnail='$connect_thumbnail'";
        
        if($this->base->connect_domain) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_connect_domain SET $sqladd WHERE connect_cid='$connect_cid' AND connect_type='$connect_type' AND connect_domain='".$this->base->connect_domain."'");
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET $sqladd WHERE connect_cid='$connect_cid' AND connect_type='$connect_type'");
        } else {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET $sqladd WHERE connect_cid='$connect_cid' AND connect_type='$connect_type'");
        }
        
        return true;
    }
    
    function update_device_connect_cid($deviceid, $connect_type, $connect_cid) {
        if(!$deviceid) return false;
        if($this->base->connect_domain) {
            $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_connect_domain WHERE deviceid='$deviceid' AND connect_type='$connect_type' AND connect_domain='".$this->base->connect_domain."'");
            if($check) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_connect_domain SET connect_cid='$connect_cid', lastupdate='".$this->base->time."' WHERE deviceid='$deviceid' AND connect_type='$connect_type' AND connect_domain='".$this->base->connect_domain."'");
            } else {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_connect_domain SET deviceid='$deviceid', connect_type='$connect_type', connect_domain='".$this->base->connect_domain."', connect_cid='$connect_cid', dateline='".$this->base->time."', lastupdate='".$this->base->time."'");
            }
        } else {
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_cid='$connect_cid' WHERE deviceid='$deviceid' AND connect_type='$connect_type'");
        }
    }
    
    function init_vars_by_deviceid($deviceid) {
        if(!$deviceid) return false;
        $partner = $this->db->fetch_first("SELECT p.* FROM ".API_DBTABLEPRE."device_partner d LEFT JOIN ".API_DBTABLEPRE."partner p ON d.partner_id=p.partner_id WHERE d.deviceid='$deviceid'");
        if($partner && $partner['partner_id']) {
            $this->base->partner_id = $partner['partner_id'];
            $this->base->partner = $partner;
            $this->base->connect_domain = $partner['connect_domain'];
        }
    }
    
    function update_device_cvr_by_partner($device, $partner, $cvr_type, $cvr_day, $cvr_end_time, $cvr_free) {
        if(!$device || !$device['deviceid'] || !$partner || !$partner['partner_id'])
            return false;
        
        $deviceid = $device['deviceid'];
        $partner_id = $partner['partner_id'];
        
        if(!$this->check_partner_device($partner_id, $deviceid))
            return false;
        
        $connect_type = $device['connect_type'];
        
        $this->db->query("UPDATE ".API_DBTABLEPRE."device_partner SET cvr='1', cvr_connect_type='$connect_type', cvr_type='$cvr_type', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', cvr_free='$cvr_free' WHERE deviceid='$deviceid' AND partner_id='$partner_id'");
        
        $this->update_cvr_info($device, true);
        
        // 状态通知设备
        $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"03000000"}';
        $client = $this->_get_api_client($connect_type);
        if($client) {
            $client->device_usercmd($device, $command, 0);
        }
        
        return true;
    }
    
    function check_blacklist($deviceid) {
        if(!$deviceid) return false;
        $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_blacklist WHERE deviceid='$deviceid'");
        return $check?true:false;
    }
    
    function check_partner_client($deviceid, $client_id) {
        $pcheck = $this->db->fetch_first("SELECT d.partner_id FROM ".API_DBTABLEPRE."device_partner d LEFT JOIN ".API_DBTABLEPRE."partner p on d.partner_id=p.partner_id WHERE d.deviceid='$deviceid' AND p.check_partner_client>0");
        if(!$pcheck)
            return true;
        
        $ccheck = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id' AND partner_id='".$pcheck['partner_id']."'");
        if(!$ccheck)
            return false;
        
        return true;
    }
    
    function check_cvr_free($deviceid, $connect_type, $cvr_type, $cvr_day) {
        // 测试设备
        $check = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_dev WHERE deviceid="'.$deviceid.'" AND cvr=1 AND cvr_connect_type="'.$connect_type.'" AND cvr_type="'.$cvr_type.'" AND cvr_day="'.$cvr_day.'" AND cvr_free=1');
        if($check)
            return true;
        
        // partner设备
        $check = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_partner WHERE deviceid="'.$deviceid.'" AND cvr=1 AND cvr_connect_type="'.$connect_type.'" AND cvr_type="'.$cvr_type.'" AND cvr_day="'.$cvr_day.'" AND cvr_free=1');
        if($check)
            return true;
        
        // 默认免费
        $check = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_cvr_status WHERE deviceid="'.$deviceid.'" AND connect_type="'.$connect_type.'" AND cvr_type="'.$cvr_type.'" AND cvr_day="'.$cvr_day.'"');
        if($check)
            return true;
        
        return false;
    }
    
    function is_support_media($device) {
        if(!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $fileds = $this->_check_fileds($deviceid);
        if(!$fileds)
            return false;
        
        if(!$fileds['params_info']) {
            $this->sync_setting($device, 'info');
        }
        
        $check = $this->db->result_first("SELECT media FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        return $check?true:false;
    }
    
    function is_support_volume($device) {
        if(!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];

        $fileds = $this->_check_fileds($deviceid);
        if(!$fileds)
            return false;
        
        if(!$fileds['params_info']) {
            $this->sync_setting($device, 'info');
        }
        
        $check = $this->db->result_first("SELECT volume FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        return $check?true:false;
    }
    
    function get_media_volume($device) {
        if(!$device || !$device['deviceid'])
            return -1;

        $deviceid = $device['deviceid'];

        $fileds = $this->_check_fileds($deviceid);
        if(!$fileds)
            return -1;
        
        if(!$fileds['params_info']) {
            $this->sync_setting($device, 'info');
        }
        
        $check = $this->db->result_first("SELECT volume FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        if(!$check)
            return -1;
        
        if(!$fileds['params_volume_media']) {
            $this->sync_setting($device, 'volume');
        }
        
        $volume = $this->db->result_first("SELECT volume_media FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        return intval($volume);
    }
    
    function get_logo($device) {
        if(!$device || !$device['deviceid'])
            return 1;
        
        $deviceid = $device['deviceid'];
        $data = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_logo WHERE deviceid='$deviceid'");
        if(!$data)
            return 1;
        
        return intval($data['logo']);
    }

    function listlogserver($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        
        $list = array();

        // 是否测试设备
        $default = 0;
        //$isdev = $this->isdev($deviceid);
        //$default = ($isdev && $isdev['relayserverid'])?$isdev['relayserverid']:$device['lastrelayserverid'];

        $this->base->load('server');
        $server = $default ? $_ENV['server']->get_server_by_id($default) : array();

        // 检查server状态
        if($server && $server['url'] && $server['status']>0) {
            $list[] = $server['url'];
        }
        
        // server_type=9 log server
        $appid = $device['appid'];
        $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid=".$appid." AND server_type=8 AND status>0 ORDER BY devicenum ASC");
        foreach($query as $v) {
            if($v['serverid'] == $default)
                continue;

            $value = $_ENV['server']->get_server_by_id($v['serverid']);
            if($value && $value['url']) {
                $list[] = $value['url'];
            }
        }

        return $list;
    }
    
    function listrelayserver($device) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        
        $list = array();

        // 是否测试设备
        $isdev = $this->isdev($deviceid);
        $default = ($isdev && $isdev['relayserverid'])?$isdev['relayserverid']:$device['lastrelayserverid'];

        $this->base->load('server');
        $server = $default ? $_ENV['server']->get_server_by_id($default) : array();

        // 检查server状态
        if($server && $server['url'] && $server['status']>0) {
            $list[] = $server['url'];
        }
        
        // server_type=5 relay server
        $appid = $device['appid'];
        $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid=".$appid." AND server_type=5 AND status>0 ORDER BY devicenum ASC");
        foreach($query as $v) {
            if($v['serverid'] == $default)
                continue;

            $value = $_ENV['server']->get_server_by_id($v['serverid']);
            if($value && $value['url']) {
                $list[] = $value['url'];
            }
        }

        return $list;
    }
    
    function getdevicesetting($device, $request_no) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        // 设备连接处理
        $device = $this->_on_connect($device);
        if(!$device)
            return false;
        
        // 设备配置
        $fileds = $this->_check_fileds($deviceid);
        if(!$fileds)
            return false;
        
        // 分享状态
        $share = $this->get_share_by_deviceid($deviceid);
        
        // 报警状态
        $cvr_alarm = $this->gen_cvr_alarm($device, false);
        
        // logo状态
        $logo = $this->get_logo($device);
        
        $config = array(
            'bitlevel' => intval($fileds['bitlevel']),
            'audio' => $fileds['audio']?1:0,
            'share' => ($share && ($share['share_type'] == 1 || $share['share_type'] == 3))?1:0,
            'cvr' => $fileds['cvr']?1:0,
            'cvr_type' => intval($device['cvr_type']),
            'cvr_alarm' => $cvr_alarm?1:0,
            'logo' => $logo
        );
        
        // 时区
        $tz_rule = $this->base->get_timezone_rule_from_timezone_id($device['timezone']);
        if($tz_rule['dst']) {
            $config['timezone'] = $tz_rule['timezone'];
            $config['dst'] = $tz_rule['dst'];
            $config['dst_offset'] = $tz_rule['dst_offset'];
            $config['dst_start'] = $tz_rule['dst_start'];
            $config['dst_end'] = $tz_rule['dst_end'];
        } else {
            $config['timezone'] = $tz_rule['timezone'];
            $config['dst'] = $tz_rule['dst'];
        }
        
        // 平台
        $client = $this->_get_api_client($connect_type);
        if($client) {
            $config = $client->device_config($device, $config, $request_no);
            if(!$config)
                return array('error' => -1);
        }
        
        $result = array(
            'deviceid' => $deviceid,
            'connect_type' => $device['connect_type'],
            'config' => $config,
            'servertime' => round(microtime(true)*1000)
        );
        return $result;
    }

    //云录制购买
    function cvr_buy_test($deviceid, $uid, $ismobile, $connect_type=1){
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;
        
        return $client->cvr_buy($deviceid, $uid, $ismobile);
    }
    
}
