<?php

!defined('IN_API') && exit('Access Denied');

class mapmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->mapmodel($base);
    }

    function mapmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function list_marker($uid) {
        // 地图标记
        $marker = $this->db->fetch_all('SELECT type,tid,location_type,location_name,location_address,location_latitude,location_longitude,lastupdate AS time FROM '.API_DBTABLEPRE.'map_marker WHERE uid="'.$uid.'"');
        for ($i = 0, $n = count($marker); $i < $n; $i++) {
            switch ($marker[$i]['type']) {
                case '1': $marker[$i]['marker'] = $this->get_building_by_bid($marker[$i]['tid']); break;
                
                default: $marker[$i]['marker'] = array(); break;
            }
        }

        // 个人和授权摄像机
        $devices = $this->list_marker_device($uid);

        return array_merge($marker, $devices);
    }

    function list_marker_device($uid) {
        // 排除楼宇摄像机
        $excludes = $this->db->result_first('SELECT GROUP_CONCAT(a.deviceid SEPARATOR "\",\"") AS deviceids FROM '.API_DBTABLEPRE.'building_device a LEFT JOIN '.API_DBTABLEPRE.'building_preview b ON a.pid=b.pid LEFT JOIN '.API_DBTABLEPRE.'map_marker c ON b.bid=c.tid WHERE c.uid="'.$uid.'" AND c.type="1" GROUP BY c.uid');

        // 个人摄像机地理位置
        $owner = $this->db->fetch_all('SELECT deviceid AS tid,location_type,location_name,location_address,location_latitude,location_longitude,lastupdate AS time FROM '.API_DBTABLEPRE.'device WHERE deviceid NOT IN ("'.$excludes.'") AND uid="'.$uid.'" AND location="1"');

        // 授权摄像机地理位置
        $grant = $this->db->fetch_all('SELECT a.deviceid AS tid,b.location_type,b.location_name,b.location_address,b.location_latitude,b.location_longitude,b.lastupdate AS time FROM '.API_DBTABLEPRE.'device_grant a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.deviceid NOT IN ("'.$excludes.'") AND a.uk="'.$uid.'" AND b.location="1"');

        $list = array_merge($owner, $grant);
        for ($i = 0, $n = count($list); $i < $n; $i++) {
            $list[$i]['type'] = '0';
            $list[$i]['marker'] = array('deviceid' => $list[$i]['tid']);
        }

        return $list;
    }

    function get_marker_by_mid($mid) {
        $marker = $this->db->fetch_first('SELECT type,tid,location_type,location_name,location_address,location_latitude,location_longitude,lastupdate AS time FROM '.API_DBTABLEPRE.'map_marker WHERE mid="'.$mid.'"');
        if ($marker) {
            switch ($marker['type']) {
                case '1': $marker['marker'] = $this->get_building_by_bid($marker['tid']); break;
                
                default: $marker['marker'] = array(); break;
            }
        }

        return $marker;
    }

    function get_marker_by_pk($uid, $type, $tid) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'map_marker WHERE uid="'.$uid.'" AND type="'.$type.'" AND tid="'.$tid.'"');
    }

    function add_marker($uid, $type, $tid, $location_type, $location_name, $location_address, $location_latitude, $location_longitude) {
        $marker = $this->get_marker_by_pk($uid, $type, $tid);
        if ($marker) {
            $mid = $marker['mid'];

            $this->db->query('UPDATE '.API_DBTABLEPRE.'map_marker SET location_type="'.$location_type.'",location_name="'.$location_name.'",location_address="'.$location_address.'",location_latitude="'.$location_latitude.'",location_longitude="'.$location_longitude.'",lastupdate="'.$this->base->time.'" WHERE mid="'.$mid.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'map_marker SET uid="'.$uid.'",type="'.$type.'",tid="'.$tid.'",location_type="'.$location_type.'",location_name="'.$location_name.'",location_address="'.$location_address.'",location_latitude="'.$location_latitude.'",location_longitude="'.$location_longitude.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');

            $mid = $this->db->insert_id();
        }

        return $this->get_marker_by_mid($mid);
    }

    function drop_marker($uid, $type, $tid) {
        switch ($type) {
            case '1': $this->drop_building($tid);

            default: $this->db->query('DELETE FROM '.API_DBTABLEPRE.'map_marker WHERE uid="'.$uid.'" AND type="'.$type.'" AND tid="'.$tid.'"'); break;
        }

        return true;
    }

    function get_building_by_bid($bid) {
        $building = $this->db->fetch_first('SELECT bid,name,intro,type,minfloor,maxfloor,lastupdate AS time FROM '.API_DBTABLEPRE.'map_building WHERE bid="'.$bid.'"');
        if ($building) {
            $building['preview'] = $this->get_building_preview_by_bid($building['bid']);
        }

        return $building;
    }

    function add_building($bid, $name, $intro, $type, $minfloor, $maxfloor) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'map_building SET bid="'.$bid.'",name="'.$name.'",intro="'.$intro.'",type="'.$type.'",minfloor="'.$minfloor.'",maxfloor="'.$maxfloor.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');

        if (!$bid)
            $bid = $this->db->insert_id();

        return $this->get_building_by_bid($bid);
    }

    function drop_building($bid) {
        $this->db->query('DELETE a,c FROM '.API_DBTABLEPRE.'map_building a LEFT JOIN '.API_DBTABLEPRE.'building_preview b ON a.bid=b.bid LEFT JOIN '.API_DBTABLEPRE.'building_device c ON b.pid=c.pid WHERE a.bid="'.$bid.'"');

        $this->drop_building_preview($bid);

        return true;
    }

    function get_building_preview_by_bid($bid) {
        $preview = $this->db->fetch_all('SELECT pid,floor,CONCAT(pathname,filename) AS image,lastupdate AS time FROM '.API_DBTABLEPRE.'building_preview WHERE bid="'.$bid.'" ORDER BY `order`');
        for ($i = 0, $n = count($preview); $i < $n; $i++) { 
            $preview[$i]['device'] = $this->get_building_device_by_pid($preview[$i]['pid']);
        }

        return $preview;
    }

    function get_building_preview_by_pid($pid) {
        $preview = $this->db->fetch_first('SELECT pid,floor,CONCAT(pathname,filename) AS image,lastupdate AS time FROM '.API_DBTABLEPRE.'building_preview WHERE pid="'.$pid.'"');
        if ($preview) {
            $preview['device'] = $this->get_building_device_by_pid($preview['pid']);
        }

        return $preview;
    }

    function add_building_preview($type, $storageid, $pathname, $filename, $uploadname, $size) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'building_preview SET type="'.$type.'",storageid="'.$storageid.'",pathname="'.$pathname.'",filename="'.$filename.'",uploadname="'.$uploadname.'",size="'.$size.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');

        $pid = $this->db->insert_id();

        return $this->get_building_preview_by_pid($pid);
    }

    function update_building_preview($pid, $bid, $floor, $order) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'building_preview SET bid="'.$bid.'",floor="'.$floor.'",`order`="'.$order.'",lastupdate="'.$this->base->time.'" WHERE pid="'.$pid.'"');

        return true;
    }

    function drop_building_preview($bid) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'building_preview SET bid="0",floor="0",`order`="0",lastupdate="'.$this->base->time.'" WHERE bid="'.$bid.'"');

        return true;
    }

    function get_building_device_by_pid($pid) {
        return $this->db->fetch_all('SELECT deviceid,`left`,top,lastupdate AS time FROM '.API_DBTABLEPRE.'building_device WHERE pid="'.$pid.'"');
    }

    function add_building_device($deviceid, $pid, $left, $top) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'building_device SET deviceid="'.$deviceid.'",pid="'.$pid.'",`left`="'.$left.'",top="'.$top.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'" ON DUPLICATE KEY UPDATE `left`="'.$left.'",top="'.$top.'",lastupdate="'.$this->base->time.'"');

        return true;
    }

    function drop_building_device($uid, $bid, $deviceid) {
        $this->db->query('DELETE a FROM '.API_DBTABLEPRE.'building_device a LEFT JOIN '.API_DBTABLEPRE.'building_preview b ON a.pid=b.pid LEFT JOIN '.API_DBTABLEPRE.'map_marker c ON b.bid=c.tid WHERE a.deviceid="'.$deviceid.'" AND c.uid="'.$uid.'" AND c.type="1" AND c.tid="'.$bid.'"');

        return true;
    }

    function clean_building_device($deviceid) {
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'building_device WHERE deviceid="'.$deviceid.'"');

        return true;
    }
}