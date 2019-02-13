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

    function list_devices($uid, $support_type, $device_type, $appid=0, $share, $online, $category, $orderby, $data_type='my', $list_type='all', $page=1, $count=10, $dids='') {
        if(!$uid)
            return false;
        
        if($dids) $data_type = 'my';

        $lists = array();
        $my_count = $grant_count = $sub_count = 0;
        $this->base->log('list device', 'my start');
        if(in_array($data_type, array('my', 'all'))) {
            $my_list = $this->listdevice($uid, $support_type, $device_type, $appid, $share, $online, $category, $orderby, $dids);
            if($my_list) {
                $my_count = $my_list['count'];
                $lists = array_merge($lists, $my_list['list']);
            }
        }
        $this->base->log('list device', 'grant start');
        if(in_array($data_type, array('grant', 'all'))) {
            $grant_list = $this->listgrantdevice($uid, $support_type, $appid, $share, $online, $category, $orderby);
            if($grant_list) {
                $grant_count = $grant_list['count'];
                $lists = array_merge($lists, $grant_list['list']);
            }
        }
        $this->base->log('list device', 'sub start');
        if(in_array($data_type, array('sub', 'all'))) {
            $sub_list = $this->listsubscribe($uid, $support_type, $appid, $share, $online, $category, $orderby);
            if($sub_list) {
                $sub_count = $sub_list['count'];
                $lists = array_merge($lists, $sub_list['device_list']);
            }
        }
        $this->base->log('list device', 'end');
        $result = array();
        if($list_type == 'page') {
            $page = $page > 0 ? $page : 1;
            $count = $count > 0 ? $count : 10;

            $total_count = $my_count + $grant_count + $sub_count;
            $page = $this->base->page_get_page($page, $count, $total_count);

            $lists = array_slice($lists, $page['start'], $count);
            $result['page'] = $page['page'];
        }

        $count = count($lists);
        $result['count'] = $count;
        $result['list'] = $lists;
        return $result;
    }

    function listdevice($uid, $support_type, $device_type, $appid=0, $share, $online, $category, $orderby, $dids='') {
        foreach($support_type as $connect_type) {
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    return false;

                if($client->_connect_list()) {
                    $my_list = $client->listdevice($uid);
                    if($my_list && is_array($my_list)) {
                        $this->sync_device($uid, $appid, $connect_type, $my_list);
                    }
                }
            }
        }

        $table = API_DBTABLEPRE.'device a';
        $where = 'WHERE a.uid='.$uid;

        if($appid > 0) $where .=' AND a.appid='.$appid;
        
        if($dids) $where .=' AND a.deviceid in ('.$dids.')';

        if($share > -1) {
            $table .= ' RIGHT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid';
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
            if($category > -1) {
                $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON a.deviceid=c.deviceid';
                $where .= ' AND c.cid="'.$category.'"';
                $category = -1;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND a.status&4=0'; break;
            case 1: $where .= ' AND a.status&4!=0'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON a.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY a.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: break;
        }

        $result = $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM $table $where");
        if($count) {
            $data = $this->db->fetch_all("SELECT a.deviceid,a.connect_type,a.connect_did,a.connect_thumbnail,a.cvr_thumbnail,a.stream_id,a.status,a.desc,a.cvr_day,a.cvr_end_time,a.cvr_tableid,a.uid,a.viewnum,a.approvenum,a.commentnum FROM $table $where $orderbysql");
            foreach($data as $value) {
                $shared = $this->get_share_by_deviceid($value['deviceid']);
                if($shared) {
                    $share_type = $shared['share_type'];
                    $shareid = $shared['shareid'];
                    $uk = $shared['connect_uid'];
                } else {
                    $share_type = '0';
                    $shareid = '';
                    $uk = '';
                }

                $expires_in = ($value['cvr_end_time'] > $this->base->time)?($value['cvr_end_time'] - $this->base->time):0;
                $device = array(
                    'deviceid' => $value['deviceid'],
                    'data_type' => 0,
                    'cid' => $this->get_cid_by_pk($uid, $value['deviceid']),
                    'connect_type' => $value['connect_type'],
                    'connect_did' => $value['connect_did'],
                    'stream_id' => $value['stream_id'],
                    'status' => $value['status'],
                    'description' => addslashes($value['desc']),
                    'cvr_day' => $value['cvr_day'],
                    'cvr_end_time' => $value['cvr_end_time'],
                    'cvr_expires_in' => $expires_in,
                    'share' => $share_type,
                    'shareid' => $shareid,
                    'uk' => $uk,
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum'],
                    'grantnum' => $this->get_grantnum($value['deviceid']),
                    'need_upgrade' => 0
                    );
                $list[] = $device;
            }
        }
        $result['count'] = $count;
        $result['list'] = $list;
        return $result;
    }

    function get_grantnum($deviceid) {
        $count = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid'");
        return $count?$count:'0';
    }

    function sync_device($uid, $appid, $connect_type, $lists) {
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
                $description = $device['description'];
                $cvr_day = $device['cvr_day'];
                $cvr_end_time = $device['cvr_end_time'];
                $thumbnail = $device['thumbnail'];
                $share_type = $device['share'];

                $device = $this->get_device_by_did($deviceid);
                if($device) {
                    if($device['uid'] && $device['connect_type'] != $connect_type)
                        continue;

                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid='$uid', connect_type='$connect_type', connect_thumbnail='$thumbnail', stream_id='$stream_id', status='$status', share_type='$share_type', `desc`='$desc', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync' WHERE deviceid='$deviceid'");
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET deviceid='$deviceid', uid='$uid', appid='$appid', connect_type='$connect_type', connect_thumbnail='$thumbnail', stream_id='$stream_id', status='$status', share_type='$share_type', `desc`='$desc', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync', dateline='$lastsync'");
                }

                // $this->update_status($deviceid, $this->get_status($status));
            }
        }

        // 处理未更新数据
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid='0', isonline='0', isalert='0', isrecord='0', status='0' WHERE uid='$uid' AND connect_type='$connect_type' AND lastupdate<'$lastsync'");

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
                $connect_thumbnail = $device['thumbnail'];
                $cvr_day = $device['cvr_day'];
                $cvr_end_time = $device['cvr_end_time'];

                $device = $this->get_device_by_did($deviceid);
                if($device) {
                    if($device['uid'] && $device['connect_type'] != $connect_type)
                        continue;

                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_type='$connect_type', connect_thumbnail='$connect_thumbnail', stream_id='$stream_id', status='$status', `desc`='$desc', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync' WHERE deviceid='$deviceid'");
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET deviceid='$deviceid', connect_type='$connect_type', appid='$appid', connect_thumbnail='$connect_thumbnail', stream_id='$stream_id', status='$status', `desc`='$desc', cvr_day='$cvr_day', cvr_end_time='$cvr_end_time', lastupdate='$lastsync', dateline='$lastsync'");
                }

                // $this->update_status($deviceid, $this->get_status($status));

                $grantid = $this->db->result_first("SELECT grantid FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' and uk='$uk' AND connect_type='$connect_type'");
                if(!$grantid) {
                    $uid = intval($device['uid']);
                    $name = $this->db->result_first("SELECT username FROM ".API_DBTABLEPRE."members WHERE uid='$uk'");
                    $name = $name?$name:'';
                    $connect_uid = $this->db->result_first("SELECT connect_uid FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='$connect_type' AND uid='$uk'");
                    $connect_uid = intval($connect_uid);

                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_grant SET deviceid='$deviceid', connect_type='$connect_type', `connect_uid`='$connect_uid', uid='$uid', uk='$uk', `name`='$name', appid='$appid', lastupdate='$lastsync', dateline='$lastsync'");
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device_grant SET lastupdate='$lastsync' WHERE grantid='$grantid'");
                }
            }
        }

        // 处理未更新数据
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE uk='$uk' AND connect_type='$connect_type' AND lastupdate<'$lastsync'");

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
                $thumbnail = $device['thumbnail'];
                $shareid = $device['shareid'];
                $connect_uid = $device['uk'];
                $share_type = $device['share'];

                $device = $this->get_device_by_did($deviceid);
                if($device) {
                    if($device['uid'] && $device['connect_type'] != $connect_type)
                        continue;

                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_type='$connect_type', connect_thumbnail='$thumbnail', status='$status', `desc`='$desc', lastupdate='$lastsync' WHERE deviceid='$deviceid'");
                } else {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET deviceid='$deviceid', appid='$appid', connect_type='$connect_type', connect_thumbnail='$thumbnail', status='$status', `desc`='$desc', lastupdate='$lastsync', dateline='$lastsync'");
                }

                // $this->update_status($deviceid, $this->get_status($status));

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

        return $this->base->storage_temp_url($thumbnail['storageid'], $thumbnail['pathname'], $thumbnail['filename']);
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
        return $arr;
    }

    function get_device_by_connect_did($connect_did, $fileds='*', $extend=false) {
        if($extend) {
            $arr = $this->db->fetch_first("SELECT $fileds FROM ".API_DBTABLEPRE."device d LEFT JOIN ".API_DBTABLEPRE."devicefileds df ON df.deviceid=d.deviceid WHERE d.connect_did='$connect_did'");
        } else {
            $arr = $this->db->fetch_first("SELECT $fileds FROM ".API_DBTABLEPRE."device WHERE connect_did='$connect_did'");
        }
        return $arr;
    }

    function add_device($uid, $deviceid, $connect_type, $connect_did, $connect_token, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location_latitude, $location_longitude) {
        $stream_id = $stream_id?$stream_id:$this->_stream_id($uid, $deviceid);
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device SET uid='$uid', deviceid='$deviceid', connect_type='$connect_type', connect_did='$connect_did', connect_token='$connect_token', appid='$appid', client_id='$client_id', device_type='1', `desc`='$desc', stream_id='$stream_id', status='0', dateline='".$this->base->time."', regip='$regip'");
        return $this->get_device_by_did($deviceid);
    }

    function update_device($uid, $deviceid, $connect_type, $connect_did, $connect_token, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location_latitude, $location_longitude) {
        $stream_id = $stream_id?$stream_id:$this->_stream_id($uid, $deviceid);
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid='$uid', connect_type='$connect_type', connect_did='$connect_did', connect_token='$connect_token', appid='$appid', client_id='$client_id', `desc`='$desc', stream_id='$stream_id', status='0', dateline='".$this->base->time."', regip='$regip' WHERE deviceid='$deviceid'");
        return $this->get_device_by_did($deviceid);
    }

    function get_desc($deviceid) {
        $desc = $this->db->result_first("SELECT `desc` FROM ".API_DBTABLEPRE."device WHERE deviceid='$deviceid'");
        return $desc?$desc:'';
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
        //$this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power`='".$status['isonline']."' WHERE deviceid='$deviceid'");
        return TRUE;
    }

    function update_status_by_status($deviceid, $status) {
        //$power = ($status&0x4) !== 0 ? 1 : 0;
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `status`='".$status."', `lastupdate`='".$this->base->time."' WHERE deviceid='$deviceid'");
        //$this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power`='".$power."' WHERE deviceid='$deviceid'");
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

    function listsubscribe($uid, $support_type, $appid=0, $share, $online, $category, $orderby) {
        foreach($support_type as $connect_type) {
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    return false;

                if($client->_connect_list()) {
                    $sub_list = $client->listsubscribe($uid);
                    if($sub_list && is_array($sub_list)) {
                        $this->sync_sub_device($uid, $appid, $connect_type, $sub_list);
                    }
                }
            }
        }

        $table = API_DBTABLEPRE.'device_subscribe d LEFT JOIN '.API_DBTABLEPRE.'device a ON d.deviceid=a.deviceid';
        $where = 'WHERE d.uid='.$uid;

        if($appid > 0) $where .=' AND d.appid='.$appid;

        if($share > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_share b ON d.deviceid=b.deviceid';
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND a.status&4=0'; break;
            case 1: $where .= ' AND a.status&4!=0'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON d.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY a.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: break;
        }

        $result = $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM $table $where");
        if($count) {
            $this->base->load('user');
            $data = $this->db->fetch_all("SELECT a.uid,a.deviceid,a.connect_type,a.connect_did,a.connect_thumbnail,a.cvr_thumbnail,a.stream_id,a.status,a.desc,a.cvr_day,a.cvr_end_time,d.share_type,d.shareid,d.uk,d.connect_uid,a.viewnum,a.approvenum,a.commentnum FROM $table $where $orderbysql");
            foreach($data as $value) {
                $user = $_ENV['user']->_format_user($value['uid']);
                $share = array(
                    'shareid' => $value['shareid'],
                    'deviceid' => $value['deviceid'],
                    'data_type' => 2,
                    'cid' => $this->get_cid_by_pk($uid, $value['deviceid']),
                    'connect_type' => $value['connect_type'],
                    'connect_did' => $value['connect_did'],
                    'uk' => $value['connect_uid'],
                    'description' => $value['desc'],
                    'share' => $value['share_type'],
                    'status' => $value['status'],
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'uid' => $user['uid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'subscribe' => 1,
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum']
                    );
                $list[] = $share;
            }
        }
        $result['count'] = $count;
        $result['device_list'] = $list;
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

    function check_user_grant($deviceid, $uk) {
        if(!$deviceid || !$uk) return FALSE;
        $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk'");
        return $check?TRUE:FALSE;
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
            $grant = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uk'");
            if(!$grant) {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_grant SET uid='$uid', deviceid='$deviceid', connect_type='$connect_type', connect_uid='".$connect_arr['connect_uid']."', uk='$uk', name='$name', auth_code='$auth_code', appid='$appid', client_id='$client_id', dateline='".$this->base->time."'");
            } else {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device_grant SET auth_code='$auth_code', appid='$appid', client_id='$client_id', dateline='".$this->base->time."' WHERE grantid='".$grant['grantid']."'");
            }

            if($code) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_grant_code SET useid="'.$uk.'", usedate="'.$this->base->time.'" WHERE code="'.$code.'"');
            }
        }

        return array('uid' => $uk, 'username' => $name);
    }

    function listgrantuser($uid, $deviceid, $connect_type, $appid=0, $client_id) {
        if(!$uid || !$deviceid)
            return FALSE;

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return FALSE;

            if ($client->_connect_list()) {
                $grant_list = $client->listgrantuser($uid, $deviceid);
                if ($grant_list && is_array($grant_list)) {
                    $this->sync_grant_user($uid, $deviceid, $connect_type, $appid, $client_id, $grant_list);
                }
            }
        }

        $where = "WHERE deviceid='".$deviceid."'";
        if($appid > 0) $where .= ' AND appid='.$appid;
        $orderby = ' ORDER BY dateline DESC';

        $result = $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."device_grant $where");
        if($count) {
            $datas = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device_grant $where $orderby");
            foreach($datas as $data) {
                $grant = array(
                    'uk' => $data['uk'],
                    'name' => $data['name'],
                    'auth_code' => $data['auth_code'],
                    'time' => $data['dateline']
                    );
                $list[] = $grant;
            }
        }
        $result['count'] = $count;
        $result['list'] = $list;
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

                $grantid = $this->db->result_first("SELECT grantid FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND connect_uid='$connect_uid' AND connect_type='$connect_type'");
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
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND connect_type='$connect_type' AND lastupdate<'$lastsync'");
    }

    function listgrantdevice($uid, $support_type, $appid=0, $share, $online, $category, $orderby) {
        foreach($support_type as $connect_type) {
            if($connect_type > 0) {
                $client = $this->_get_api_client($connect_type);
                if(!$client)
                    return false;

                if($client->_connect_list()) {
                    $grant_list = $client->listgrantdevice($uid);
                    if($grant_list && is_array($grant_list)) {
                        $this->sync_grant_device($uid, $appid, $connect_type, $grant_list);
                    }
                }
            }
        }

        $table = API_DBTABLEPRE.'device_grant d LEFT JOIN '.API_DBTABLEPRE.'device a ON d.deviceid=a.deviceid';
        $where = 'WHERE d.uk='.$uid;

        if($appid > 0) $where .=' AND d.appid='.$appid;

        if($share > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_share b ON d.deviceid=b.deviceid';
            switch ($share) {
                case 0: $where .= ' AND ISNULL(b.share_type)'; break;
                case 1: $where .= ' AND b.share_type in (1,3)'; break;
                case 2: $where .= ' AND b.share_type in (2,4)'; break;
                default: break;
            }
        }

        switch ($online) {
            case 0: $where .= ' AND a.status&4=0'; break;
            case 1: $where .= ' AND a.status&4!=0'; break;
            default: break;
        }

        if($category > -1) {
            $table .= ' JOIN '.API_DBTABLEPRE.'device_category c ON d.deviceid=c.deviceid';
            $where .= ' AND c.cid="'.$category.'"';
        }

        switch ($orderby) {
            case 'view': $orderbysql=' ORDER BY a.viewnum DESC'; break;
            case 'approve': $orderbysql=' ORDER BY a.approvenum DESC'; break;
            case 'comment': $orderbysql=' ORDER BY a.commentnum DESC'; break;
            default: break;
        }

        $result = $list = array();
        $count = $this->db->result_first("SELECT count(*) FROM $table $where");
        if($count) {
            $data = $this->db->fetch_all("SELECT a.deviceid,a.connect_type,a.connect_did,a.connect_thumbnail,a.cvr_thumbnail,a.stream_id,a.status,a.desc,a.cvr_day,a.cvr_end_time,a.viewnum,a.approvenum,a.commentnum FROM $table $where $orderbysql");
            foreach($data as $value) {
                $shared = $this->get_share_by_deviceid($value['deviceid']);
                if($shared) {
                    $share_type = $shared['share_type'];
                    $shareid = $shared['shareid'];
                    $uk = $shared['connect_uid'];
                } else {
                    $share_type = '0';
                    $shareid = '';
                    $uk = '';
                }
                $device = array(
                    'deviceid' => $value['deviceid'],
                    'data_type' => 1,
                    'cid' => $this->get_cid_by_pk($uid, $value['deviceid']),
                    'connect_type' => $value['connect_type'],
                    'connect_did' => $value['connect_did'],
                    'stream_id' => $value['stream_id'],
                    'status' => $value['status'],
                    'description' => $value['desc'],
                    'cvr_day' => $value['cvr_day'],
                    'cvr_end_time' => $value['cvr_end_time'],
                    'share' => $share_type,
                    'shareid' => $shareid,
                    'uk' => $uk,
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'viewnum' => $value['viewnum'],
                    'approvenum' => $value['approvenum'],
                    'commentnum' => $value['commentnum']
                    );
                $list[] = $device;
            }
        }
        $result['count'] = $count;
        $result['list'] = $list;
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
                    $grant = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk='$uid'");
                    if($grant && $grant['connect_type'] == $connect_type) {
                        $ret = $client->dropgrantuser($device, $grant['connect_uid']);
                        if(!$ret)
                            return false;
                    }
                }
            }

            $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_grant WHERE deviceid='$deviceid' AND uk in ($uk)");
            return true;
        }

        return false;
    }

    function create_share($uid, $device, $title, $intro, $share_type, $appid, $client_id) {
        if(!$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $shareid = '';
        $connect_uid = $uid;
        $status = 1;
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $ret = $client->createshare($device, $share_type);
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
            if($youeryun_did) $status = 0;
        } 

        $share = $this->get_share($deviceid);
        if($share) {
            $shareid = $shareid?$shareid:$share['shareid'];
            if($share_type == 2 || $share_type == 4) $password = $this->_share_password();
            $this->db->query("UPDATE ".API_DBTABLEPRE."device_share SET shareid='$shareid', title='$title', intro='$intro', connect_type='$connect_type', connect_uid='$connect_uid', uid='$uid', share_type='$share_type', password='$password', lastupdate='".$this->base->time."', appid='$appid', client_id='$client_id', status='$status' WHERE shareid='".$share['shareid']."'");
            // 删除收藏
            $this->db->query("DELETE FROM ".API_DBTABLEPRE."device_subscribe WHERE shareid='".$share['shareid']."'");
        } else {
            $shareid = $shareid?$shareid:$this->_shareid();
            if($share_type == 2 || $share_type == 4) $password = $this->_share_password();
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_share SET shareid='$shareid', title='$title', intro='$intro',  connect_type='$connect_type', connect_uid='$connect_uid', uid='$uid', deviceid='$deviceid', share_type='$share_type', password='$password', dateline='".$this->base->time."', appid='$appid', client_id='$client_id', status='$status'");
        }

        return true;
    }

    function get_share($deviceid) {
        $share = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");
        return $share?$share:NULL;
    }

    function get_share_by_shareid($shareid) {
        $share = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE shareid='$shareid'");
        return $share?$share:NULL;
    }

    function get_share_by_deviceid($deviceid) {
        $share = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");
        return $share?$share:NULL;
    }

    function cancel_share($device) {
        if(!$device || !$device['deviceid'])
            return false;

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
                $data['comment'] = $this->userTextDecode($data['comment']);

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
            $where .= ' AND c.status&0x4!=0 AND b.status=1';
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
            case 'all': $orderbysql=' ORDER BY c.viewnum DESC,c.approvenum DESC,c.commentnum DESC,b.dateline DESC'; break;
            case 'view': $orderbysql=' ORDER BY c.viewnum DESC,b.dateline DESC'; break;
            case 'approve': $orderbysql=' ORDER BY c.approvenum DESC,b.dateline DESC'; break;
            case 'comment': $orderbysql=' ORDER BY c.commentnum DESC,b.dateline DESC'; break;
            case 'recommend': $orderbysql=' ORDER BY b.recommend DESC,b.dateline DESC'; $where.=' AND b.recommend>0'; break;
            default: $orderbysql=' ORDER BY b.dateline DESC'; break;;
        }

        $result = $list = array();
        $total = $this->db->result_first("SELECT count(*) FROM $table $where");
        $page = $this->base->page_get_page($page, $count, $total);
        if($dids) {
            $limit = '';
        } else {
            $limit = ' LIMIT '.$page['start'].', '.$count;
        }

        $count = 0;
        if($total) {
            $sub_list = $uid ? $this->get_subscribe_by_uid($uid, $appid) : array();
            $check_sub = !empty($sub_list);

            $this->base->load('user');
            $data = $this->db->fetch_all("SELECT b.shareid,b.connect_type,b.connect_uid,b.uid,b.deviceid,b.share_type,c.connect_did,c.status,c.connect_thumbnail,c.cvr_thumbnail,c.viewnum,c.approvenum,c.commentnum FROM $table $where $orderbysql $limit");
            foreach($data as $value) {
                $user = $_ENV['user']->_format_user($value['uid']);
                $share = array(
                    'shareid' => $value['shareid'],
                    'connect_type' => $value['connect_type'],
                    'connect_did' => $value['connect_did'],
                    'description' => $this->get_desc($value['deviceid']),
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
                    'commentnum' => $value['commentnum'],
                    'comment_list' => $this->get_comment_by_did($value['deviceid'], $commentnum)
                    );

                $list[] = $share;
                $count++;
            }
        }

        $result['page'] = $page['page'];
        $result['count'] = $count;
        $result['device_list'] = $list;
        return $result;
    }

    function _shareid() {
        return md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
    }

    function _share_password() {
        return rand(1000,9999);
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

    function liveplay($device, $type, $params, $client_id) {
        if($device && $device['deviceid']) {
            $connect_type = $device['connect_type'];
            $device['client_id'] = $client_id;
        } else {
            $connect_type = API_BAIDU_CONNECT_TYPE;
        }

        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $ret = $client->liveplay($device, $type, $params);
        if(!$device)
            return $ret;

        if(!$ret)
            return false;

        if($connect_type > 0 && is_array($ret)) {
            if(isset($ret['status'])) {
                $this->update_status_by_status($device['deviceid'], $ret['status']);
            }
        }

        return $ret;
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
        $status = $this->_set_b(1, $isonline, $status);
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

        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET uid=0,isonline=0,isalert=0,isrecord=0,status=0,cvr_thumbnail='',alarmnum=0 WHERE deviceid='$deviceid'");
        
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

        // 删除433设备
        $sensors = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE."device_sensor WHERE uid=$uid AND deviceid='$deviceid'");
        $this->db->query('DELETE FROM '.API_DBTABLEPRE."device_sensor WHERE uid=$uid AND deviceid='$deviceid'");
        foreach ($sensors as $item) {
            $sensorid = $item['sensorid'];
            $binded_device = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE."device_sensor WHERE uid=$uid AND sensorid='$sensorid'");
            if (!$binded_device) {
                $this->db->query('UPDATE '.API_DBTABLEPRE."sensor SET uid=0 WHERE uid=$uid AND sensorid='$sensorid'");
            }
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

    function playlist($device, $params, $starttime, $endtime) {
        if(!$starttime || !$endtime)
            return false;

        if($device && $device['deviceid']) {
            $connect_type = $device['connect_type'];
        } else {
            $connect_type = API_BAIDU_CONNECT_TYPE;
        }

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->playlist($device, $params, $starttime, $endtime);
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
            return array();

        if($device && $device['deviceid']) {
            $connect_type = $device['connect_type'];
        } else {
            $connect_type = API_BAIDU_CONNECT_TYPE;
        }

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
                        $url = $this->base->storage_temp_url($record['storageid'], $record['pathname'], $record['filename'], $filename);
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
            return array();

        if($device && $device['deviceid']) {
            $connect_type = $device['connect_type'];
        } else {
            $connect_type = API_BAIDU_CONNECT_TYPE;
        }

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->vodseek($device, $params, $time);
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
        if(!$device || !$device['deviceid'])
            return false;

        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->thumbnail($device, $params, $starttime, $endtime, $latest);
        } else {
            $deviceid = $device['deviceid'];
            $uid = $device['uid'];

            // TODO: add thumbnail
            return false;

            $result = array();
            $result['count'] = 0;
            $result['list'] = array();
            return $result;
        }
    }

    function clip($device, $starttime, $endtime, $name) {
        if(!$device || !$device['deviceid'] || !$starttime || !$endtime || !$name)
            return false;

        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->clip($device, $starttime, $endtime, $name);
        } else {
            // TODO: add clip
            return false;
        }
    }

    function infoclip($device, $type) {
        if(!$device || !$type)
            return false;

        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->infoclip($device, $type);
        } else {
            // TODO: add clip
            return false;
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

    function listuserclip($device, $page = 1, $count = 10) {
        if (!$device)
            return false;

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $connect_type = $device['connect_type'];
        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->listuserclip($device['uid'], $page, $count);
        } else {
            // TODO: add clip
            return false;
        }
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

    function update_cvr($deviceid, $cvr_day, $cvr_end_time) {
        if(!$deviceid || !$cvr_day || !$cvr_end_time) return;
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET `cvr_day`='".$cvr_day."', `cvr_end_time`='".$cvr_end_time."' WHERE deviceid='$deviceid'");
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

    function get_thumbnail_info($deviceid, $cvrid, $fileid, $local=false) {
        if(!$deviceid || !$cvrid || !$fileid)
            return array();

        $cvr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_list WHERE deviceid='$deviceid' AND cvrid='$cvrid'");
        if(!$cvr)
            return array();

        $table = API_DBTABLEPRE."device_cvr_file_".$cvr['tableid'];
        $file = $this->db->fetch_first("SELECT * FROM ".$table." WHERE cvrid='$cvrid' AND fileid='$fileid'");
        if(!$file || !$file['thumbnail'])
            return array();

        $cvr_storage = $this->base->get_storage_service($file['storageid']);        
        $url = $this->base->storage_temp_url($file['storageid'], $file['pathname'], $file['filename'], '', 0, $local);
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
        $allows['info'] = array('intro', 'model', 'nameplate', 'platform', 'resolution', 'sn', 'mac', 'wifi', 'ip', 'sig', 'firmware', 'firmdate');

        // status
        $allows['status'] = array('power', 'light', 'invert', 'audio', 'localplay', 'scene', 'nightmode', 'exposemode', 'bitlevel', 'bitrate', 'maxspeed', 'minspeed');

        // email
        $allows['email'] = array('mail_to', 'mail_cc', 'mail_server', 'mail_port', 'mail_from', 'mail_user', 'mail_passwd');

        // power
        $allows['power'] = array('power_cron', 'power_start', 'power_end', 'power_repeat');

        // cvr
        $allows['cvr'] = array('cvr', 'cvr_cron', 'cvr_start', 'cvr_end', 'cvr_repeat');

        // alarm
        $allows['alarm'] = array('alarm_push', 'alarm_mail', 'alarm_audio', 'alarm_audio_level', 'alarm_move', 'alarm_move_level', 'alarm_cron', 'alarm_start', 'alarm_end', 'alarm_repeat');

        // capsule
        $allows['capsule'] = array('temperature', 'humidity');

        // plat
        $allows['plat'] = array('plat', 'plat_move', 'plat_type', 'plat_rotate', 'plat_rotate_status', 'plat_track_status');

        // bit
        $allows['bit'] = array('bitrate', 'bitlevel', 'audio');

        // bw
        $allows['bw'] = array('maxspeed', 'minspeed');

        // night
        $allows['night'] = array('scene', 'nightmode', 'exposemode');

        // init
        $allows['init'] = array('model', 'firmware', 'firmdate', 'ip', 'mac', 'cloudserver', 'resolution', 'sn', 'sig', 'wifi', 'temperature', 'humidity', 'plat', 'plat_move', 'plat_type', 'plat_rotate', 'plat_rotate_status', 'plat_track_status', 'alarm_push', 'light', 'invert', 'cvr', 'bitrate', 'audio', 'timezone', 'maxspeed', 'minspeed', 'nightmode', 'exposemode', 'alarm_audio', 'alarm_audio_level', 'alarm_move', 'alarm_move_level', 'alarm_mail', 'mail_from', 'mail_to', 'mail_cc', 'mail_server', 'mail_port', 'mail_user', 'mail_passwd');

        if(in_array($type, array('info', 'status', 'power', 'email', 'cvr', 'alarm', 'capsule', 'plat', 'bit', 'bw', 'night', 'init'))) {
            if($type == 'status') {
                $fileds = array_merge($allows['status'], $allows['email']);
            } else {
                $fileds = $allows[$type];
            }
        } else if($type == 'all') {
            $fileds = array_merge($allows['info'], $allows['status'], $allows['email'], $allows['power'], $allows['cvr'], $allows['alarm'], $allows['capsule'], $allows['plat'], $allows['init']);
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
        }
        else {
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
        if(in_array($type, array('info', 'all'))) {
            $commands[] = array('type' => 'info', 'command' => '{"main_cmd":74,"sub_cmd":3,"param_len":0,"params":0}', 'response' => 1);
            $commands[] = array('type' => 'debug', 'command' => '{"main_cmd":74,"sub_cmd":48,"param_len":0,"params":0}', 'response' => 1);
        }

        // sync_capsule
        if($type === 'capsule') {
            $commands[] = array('type' => 'capsule', 'command' => '{"main_cmd":74,"sub_cmd":74,"param_len":0,"params":0}', 'response' => 1);
        }

        // sync_plat
        if($type === 'plat') {
            $commands[] = array('type' => 'plat', 'command' => '{"main_cmd":74,"sub_cmd":66,"param_len":0,"params":0}', 'response' => 1);
        }

        // sync_plat
        if($type === 'init') {
            $commands[] = array('type' => 'init', 'command' => '{"main_cmd":74,"sub_cmd":76,"param_len":0,"params":0}', 'response' => 1);
        }

        $sync_all = false;
        $sync_config = false;

        /*废除同步时间内5分钟不再同步的机制*/
        //if($setting_sync != 0)
        {
            if($type == 'all') $sync_all = true;

            // bits
            if(in_array($type, array('bits'))) {
                if(!$fileds['params_debug']) {
                    $commands[] = array('type' => 'debug', 'command' => '{"main_cmd":74,"sub_cmd":48,"param_len":0,"params":0}', 'response' => 1);
                }
            }

            // sync_status
            if(in_array($type, array('cvr', 'status', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_status']) || ($set == 1 && !$fileds['params_status'])) {
                    $commands[] = array('type' => 'status', 'command' => '{"main_cmd":74,"sub_cmd":43,"param_len":0,"params":0}', 'response' => 1);
                }
                $sync_config = true;
            }

            // sync_bit
            if(in_array($type, array('status', 'bit', 'bits', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_bit']) || ($set == 1 && !$fileds['params_bit'])) {
                    $commands[] = array('type' => 'bit', 'command' => '{"main_cmd":74,"sub_cmd":5,"param_len":0,"params":0}', 'response' => 1);
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
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_cron']) || ($set == 1 && !$fileds['params_cron'])) {
                    $commands[] = array('type' => 'cron', 'command' => '{"main_cmd":74,"sub_cmd":51,"param_len":0,"params":0}', 'response' => 1);
                }
            }

            // sync_alarm
            if(in_array($type, array('alarm', 'all'))) {
                if (($set == 0 && $setting_sync != 1) || ($set == 0 && $setting_sync == 1 && !$fileds['params_alarm_level']) || ($set == 1 && !$fileds['params_alarm_level'])) {
                    $commands[] = array('type' => 'alarm_level', 'command' => '{"main_cmd":74,"sub_cmd":60,"param_len":0,"params":0}', 'response' => 1);
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
        }

        $this->base->log('sync setting', 'type='.$type.', count='.count($commands));

        if($commands) {
            // api request
            $ret = $client->device_batch_usercmd($device, $commands);
            if(!$ret)
                return false;

            foreach($ret as $value) {
                $params = $this->_cmd_params($value['data']);
                switch($value['type']) {
                    case 'info':
                    $params_info = $params;
                    break;
                    case 'debug':
                    $params_debug = $params;
                    break;
                    case 'status':
                    $params_status = $params;
                    break;
                    case 'bit':
                    $params_bit = $params;
                    break;
                    case 'bw':
                    $params_bw = $params;
                    break;
                    case 'night':
                    $params_night = $params;
                    break;
                    case 'email':
                    $params_email = $params;
                    break;
                    case 'cron':
                    $params_cron = $params;
                    break;
                    case 'alarm_level':
                    $params_alarm_level = $params;
                    break;
                    case 'alarm_mail':
                    $params_alarm_mail = $params;
                    break;
                    case 'alarm_list':
                    $params_alarm_list = $params;
                    break;
                    case 'capsule':
                    $params_capsule = $params;
                    break;
                    case 'plat':
                    $params_plat = $params;
                    break;
                    case 'init':
                    $params_init = $params;
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

            // sync_status
            if($params_status) {
                $this->base->log('sync setting', 'sync_status');
                $this->sync_status($device, $params_status);
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
                $this->sync_cron($device, $params_cron);
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

            if($sync_all) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `lastsettingsync`='".$this->base->time."' WHERE `deviceid`='".$deviceid."'");
            }
        }

        if($sync_config) {
            $config = $client->device_sync_config($device);
            if($config && is_array($config)) {
                $set = '';
                if(isset($config['cvr']))
                    $set .= "`cvr`='".$config['cvr']."'";
                if(isset($config['audio']))
                    $set .= ", `audio`='".$config['audio']."'";
                $this->base->log('sync setting', 'config='.$set);
                if($set) {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET $set WHERE `deviceid`='".$deviceid."'");
                } 
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

        // 设备版本
        $firmware = intval(substr($params_info, 64, 8), 16) / 1000;

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

        $p = intval(substr($params_debug, 4, 4), 16);

        // 平台版本
        $plat = ($p >> 8) & 0xFF;

        // 铭牌
        $resolution = 720;
        switch ($plat) {
            case 0:
            case 1: $nameplate = 'HDB'; break;
            case 2: $nameplate = 'HDSM'; break;
            case 4: $nameplate = 'HDW'; break;
            case 5: $nameplate = 'HDM'; break;
            case 7: $nameplate = 'HDP'; break;
            case 80:
            case 81: $nameplate = 'HDP'; $resolution = 1080; break;
            case 100: $nameplate = 'HDQ'; break;
            default: $nameplate = 'HD'; break;
        }

        // 调试版本
        $firmware .= '_'.$plat.'_'.($p & 0xFF);

        // sig
        $p = intval(substr($params_debug, 8, 8), 16);
        $sig = ($p >> 16) & 0xFF;

        // wifi
        $wifi = $this->_hex2bin($params_debug, 16, 64);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `firmware`='".$firmware."', `firmdate`='".$firmdate."', `model`='".$model."', `nameplate`='".$nameplate."', `ip`='".$ip."', `mac`='".$mac."', `sn`='".$sn."', `sig`='".$sig."', `platform`='".$plat."', `resolution`='".$resolution."', `wifi`='".$wifi."', `params_info`='".$params_info."', `params_debug`='".$params_debug."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_debug($device, $params_debug) {
        if(!$device || !$device['deviceid'] || !$params_debug)
            return false;

        $deviceid = $device['deviceid'];

        $p = intval(substr($params_debug, 4, 4), 16);

        // 平台版本
        $plat = ($p >> 8) & 0xFF;

        // 铭牌
        $resolution = 720;
        switch ($plat) {
            case 0:
            case 1: $nameplate = 'HDB'; break;
            case 2: $nameplate = 'HDSM'; break;
            case 4: $nameplate = 'HDW'; break;
            case 5: $nameplate = 'HDM'; break;
            case 7: $nameplate = 'HDP'; break;
            case 80:
            case 81: $nameplate = 'HDP'; $resolution = 1080; break;
            case 100: $nameplate = 'HDQ'; break;
            default: $nameplate = 'HD'; break;
        }

        // sig
        $p = intval(substr($params_debug, 8, 8), 16);
        $sig = ($p >> 16) & 0xFF;

        // wifi
        $wifi = $this->_hex2bin($params_debug, 16, 64);

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `sig`='".$sig."', `platform`='".$plat."', `resolution`='".$resolution."', `wifi`='".$wifi."', `params_debug`='".$params_debug."' WHERE `deviceid`='".$deviceid."'");

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

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_push`='".$alarm_push."', `light`='".$light."', `invert`='".$invert."', `cvr`='".$cvr."', `params_status`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    function sync_bit($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $allows['bit'] = array('bitrate', 'bitlevel', 'audio');

        $deviceid = $device['deviceid'];

        // 码率
        $bitrate = intval(substr($params, 21, 3), 16);

        $plat = $this->db->result_first('SELECT platform FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if($plat===NULL)
            return false;

        // 设置清晰度
        $bitlevel = 0;
        switch ($plat) {
            case 80:
            case 81:
            if($bitrate > (600 + 800) / 2) {
                $bitlevel = 2;
            } elseif ($bitrate > (400 + 600) / 2) {
                $bitlevel = 1;
            }
            break;

            default:
            if($bitrate > (300 + 400) / 2) {
                $bitlevel = 2;
            } elseif ($bitrate > (200 + 300) / 2) {
                $bitlevel = 1;
            }
            break;
        }

        // 音频状态：0为关闭，1为开启
        $audio = $params[27];

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `bitrate`='".$bitrate."', `bitlevel`='".$bitlevel."', `audio`='".$audio."', `params_bit`='".$params."' WHERE `deviceid`='".$deviceid."'");

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

    function sync_cron($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];

        // 工作日
        $work_day = substr($params, 3, 1);
        for ($i = 2; $i < 8; $i++) { 
            $work_day .= substr($params, $i * 2 + 1, 1);
        }

        // 解析定时任务
        $cron = array();
        for ($i = 0, $n = intval(substr($params, 0, 2), 16); $i < $n; $i++) { 
            $cron_item = substr($params, $i * 24 + 16, 24);

            $temp = array();

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
        $n = count($cron['power']);
        if ($n) {
            $multi = $n > 1 && intval($cron['power'][0]['endtime']) === 235959 && intval($cron['power'][$n - 1]['starttime']) === 0 ? 1 : 0;
            if ($multi) {
                $power_parts = array($cron['power'][0], $cron['power'][$n - 1]);
            } else {
                $power_parts = array($cron['power'][0]);
            }
            for ($i = 1, $j = 0; $i < $n - $multi; $i++) { 
                if($cron['power'][$i]['status'] === $power_parts[$j]['status'] && $cron['power'][$i]['starttime'] === $power_parts[$j]['starttime'] && $cron['power'][$i]['endtime'] === $power_parts[$j]['endtime']) {
                    $power_parts[$j]['work_day'] = $this->_parse_hex(decbin(bindec($power_parts[$j]['work_day']) | bindec($cron['power'][$i]['work_day'])), 7);
                } elseif ($j++ < $multi) {
                    $i--;
                } else {
                    break;
                }
            }
            $power_cron = $power_parts[0]['status'];
            $power_start = $power_parts[0]['starttime'];
            $power_end = $multi && substr($power_parts[1]['work_day'], 1).substr($power_parts[1]['work_day'], 0, 1) === $power_parts[0]['work_day'] ? $power_parts[1]['endtime'] : $power_parts[0]['endtime'];
            $power_repeat = $power_parts[0]['work_day'];
        } else {
            $power_cron = 0;
            $power_start = '000000';
            $power_end = '000000';
            $power_repeat = '0000000';
        }

        // 解析云录制定时任务
        $n = count($cron['cvr']);
        if ($n) {
            $multi = $n > 1 && intval($cron['cvr'][0]['endtime']) === 235959 && intval($cron['cvr'][$n - 1]['starttime']) === 0 ? 1 : 0;
            if ($multi) {
                $cvr_parts = array($cron['cvr'][0], $cron['cvr'][$n - 1]);
            } else {
                $cvr_parts = array($cron['cvr'][0]);
            }
            for ($i = 1, $j = 0; $i < $n - $multi; $i++) { 
                if($cron['cvr'][$i]['status'] === $cvr_parts[$j]['status'] && $cron['cvr'][$i]['starttime'] === $cvr_parts[$j]['starttime'] && $cron['cvr'][$i]['endtime'] === $cvr_parts[$j]['endtime']) {
                    $cvr_parts[$j]['work_day'] = $this->_parse_hex(decbin(bindec($cvr_parts[$j]['work_day']) | bindec($cron['cvr'][$i]['work_day'])), 7);
                } elseif ($j++ < $multi) {
                    $i--;
                } else {
                    break;
                }
            }
            $cvr_cron = $cvr_parts[0]['status'];
            $cvr_start = $cvr_parts[0]['starttime'];
            $cvr_end = $multi && substr($cvr_parts[1]['work_day'], 1).substr($cvr_parts[1]['work_day'], 0, 1) === $cvr_parts[0]['work_day'] ? $cvr_parts[1]['endtime'] : $cvr_parts[0]['endtime'];
            $cvr_repeat = $cvr_parts[0]['work_day'];
        } else {
            $cvr_cron = 0;
            $cvr_start = '000000';
            $cvr_end = '000000';
            $cvr_repeat = '0000000';
        }

        // 解析报警定时任务
        $n = count($cron['alarm']);
        if ($n) {
            $multi = $n > 1 && intval($cron['alarm'][0]['endtime']) === 235959 && intval($cron['alarm'][$n - 1]['starttime']) === 0 ? 1 : 0;
            if ($multi) {
                $alarm_parts = array($cron['alarm'][0], $cron['alarm'][$n - 1]);
            } else {
                $alarm_parts = array($cron['alarm'][0]);
            }
            for ($i = 1, $j = 0; $i < $n - $multi; $i++) { 
                if($cron['alarm'][$i]['status'] === $alarm_parts[$j]['status'] && $cron['alarm'][$i]['starttime'] === $alarm_parts[$j]['starttime'] && $cron['alarm'][$i]['endtime'] === $alarm_parts[$j]['endtime']) {
                    $alarm_parts[$j]['work_day'] = $this->_parse_hex(decbin(bindec($alarm_parts[$j]['work_day']) | bindec($cron['alarm'][$i]['work_day'])), 7);
                } elseif ($j++ < $multi) {
                    $i--;
                } else {
                    break;
                }
            }
            $alarm_cron = $alarm_parts[0]['status'];
            $alarm_start = $alarm_parts[0]['starttime'];
            $alarm_end = $multi && substr($alarm_parts[1]['work_day'], 1).substr($alarm_parts[1]['work_day'], 0, 1) === $alarm_parts[0]['work_day'] ? $alarm_parts[1]['endtime'] : $alarm_parts[0]['endtime'];
            $alarm_repeat = $alarm_parts[0]['work_day'];
        } else {
            $alarm_cron = 0;
            $alarm_start = '000000';
            $alarm_end = '000000';
            $alarm_repeat = '0000000';
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power_cron`='".$power_cron."', `power_start`='".$power_start."', `power_end`='".$power_end."', `power_repeat`='".$power_repeat."', `cvr_cron`='".$cvr_cron."', `cvr_start`='".$cvr_start."', `cvr_end`='".$cvr_end."', `cvr_repeat`='".$cvr_repeat."', `alarm_cron`='".$alarm_cron."', `alarm_start`='".$alarm_start."', `alarm_end`='".$alarm_end."', `alarm_repeat`='".$alarm_repeat."', `params_cron`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
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
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_mail`='".$alarm_mail."', `alarm_audio`='".$alarm_audio."', `alarm_audio_level`='".$alarm_audio_level."', `alarm_move`='".$alarm_move."', `alarm_move_level`='".$alarm_move_level."', `params_alarm_level`='".$params_alarm_level."', `params_alarm_mail`='".$params_alarm_mail."', `params_alarm_list`='".$params_alarm_list."' WHERE `deviceid`='".$deviceid."'");

        return true;
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

    function sync_init($device, $params) {
        if(!$device || !$device['deviceid'] || !$params)
            return false;

        $deviceid = $device['deviceid'];

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

        // 铭牌
        switch ($platform) {
            case 0:
            case 1: $nameplate = 'HDB'; break;
            case 2: $nameplate = 'HDSM'; break;
            case 4: $nameplate = 'HDW'; break;
            case 5: $nameplate = 'HDM'; break;
            case 7:
            case 80:
            case 81: $nameplate = 'HDP'; break;
            case 100: $nameplate = 'HDQ'; break;
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

        // 码率
        $bitrate = intval(substr($params, 256, 4), 16);

        // 清晰度: 0为流畅,1为高清,2为超清
        if ($resolution === 1080) {
            if($bitrate > (600 + 800) / 2) {
                $bitlevel = 2;
            } elseif ($bitrate > (400 + 600) / 2) {
                $bitlevel = 1;
            } else {
                $bitlevel = 0;
            }
        } else {
            if($bitrate > (300 + 400) / 2) {
                $bitlevel = 2;
            } elseif ($bitrate > (200 + 300) / 2) {
                $bitlevel = 1;
            } else {
                $bitlevel = 0;
            }
        }

        // 音频状态: 0为关闭,1为开启
        $audio = $params[261];

        // 时区
        $timezone = intval(substr($params, 262, 2), 16);

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
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `model`='".$model."', `firmware`='".$firmware."', `firmdate`='".$firmdate."', `ip`='".$ip."', `mac`='".$mac."', `cloudserver`='".$cloudserver."', `resolution`='".$resolution."', `sn`='".$sn."', `platform`='".$platform."', `sig`='".$sig."', `wifi`='".$wifi."', `temperature`='".$temperature."', `humidity`='".$humidity."', `plat`='".$plat."', `plat_move`='".$plat_move."', `plat_type`='".$plat_type."', `plat_rotate`='".$plat_rotate."', `plat_rotate_status`='".$plat_rotate_status."', `plat_track_status`='".$plat_track_status."', `alarm_push`='".$alarm_push."', `alarm_move`='".$alarm_move."', `light`='".$light."', `invert`='".$invert."', `cvr`='".$cvr."', `bitrate`='".$bitrate."', `bitlevel`='".$bitlevel."', `audio`='".$audio."', `maxspeed`='".$maxspeed."', `scene`='".$scene."', `nightmode`='".$nightmode."', `exposemode`='".$exposemode."', `alarm_audio`='".$alarm_audio."', `alarm_audio_level`='".$alarm_audio_level."', `alarm_move_level`='".$alarm_move_level."', `alarm_mail`='".$alarm_mail."', `mail_from`='".$mail_from."', `mail_to`='".$mail_to."', `mail_cc`='".$mail_cc."', `mail_server`='".$mail_server."', `mail_user`='".$mail_user."', `mail_passwd`='".$mail_passwd."', `mail_port`='".$mail_port."', `params_init`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 检查推送机制
    function _check_push_version($device) {
        // TODO: 根据版本规则判断
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

        if(isset($params['light']) || isset($params['invert']) || isset($params['cvr']) || isset($params['alarm_push'])) {

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

            // 设置云录制
            $cvr = $params['cvr'];
            if(!($cvr === NULL)) {
                if(!$this->set_cvr($client, $device, $cvr)) 
                    return false;
                $result['cvr'] = $cvr;
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

        if(isset($params['audio']) || isset($params['bitlevel']) || isset($params['bitrate'])) {
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
            if(!($cvr_cron === NULL || $cvr_start === NULL || $cvr_end === NULL || $cvr_repeat === NULL)) {
                if(!$this->set_cvr_cron($client, $device, $cvr_cron, $cvr_start, $cvr_end, $cvr_repeat)) 
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

        if(isset($params['alarm_mail']) || isset($params['alarm_audio']) || isset($params['alarm_move'])) {
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
        }

        // 保存设置，以上函数为设置，以下函数为操作        
        if (!empty($result) && $client->_check_need_savemenu($params)) {
            if (!$this->save_settings($client, $device))
                return false;
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

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `power`='".$power."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 开关机定时任务
    function set_power_cron($client, $device, $power_cron, $power_start, $power_end, $power_repeat) {
        if(!$client || !$device || !$device['deviceid'])
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

    // 音频
    function set_audio($client, $device, $audio) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $audio = $audio ? 1 : 0;

        $params_bit = $this->db->result_first('SELECT params_bit FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$params_bit)
            return false;

        // request params
        $params_audio = $audio ? '1' : '0';
        $params = substr_replace($params_bit, $params_audio, 27, 1);

        if($client->_set_config()) {
            if(!$client->set_audio($device, $audio))
                return false;
        } else {
            $command = '{"main_cmd":75,"sub_cmd":5,"param_len":82,"params":"' . $params . '"}';

        // api request
            if(!$client->device_usercmd($device, $command, 1))
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
        $bitlevel = $bitlevel ? ($bitlevel > 1 ? 2 : 1) : 0;

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings || !$settings['params_bit'] || !$settings['params_debug'])
            return false;

        $params_bit = $settings['params_bit'];
        $plat = $settings['platform'];

        // 设置码率
        switch ($plat) {
            case 80:
            case 81:
            switch ($bitlevel) {
                case 0: $bitrate = 400;break;
                case 1: $bitrate = 600;break;
                case 2: $bitrate = 800;break;
            }
            break;

            default:
            switch ($bitlevel) {
                case 0: $bitrate = 200;break;
                case 1: $bitrate = 300;break;
                case 2: $bitrate = 400;break;
            }
            break;
        }

        $params_bitrate = $this->_parse_hex(dechex($bitrate), 3);
        $params = substr_replace($params_bit, $params_bitrate, 21, 3);


        if ($client->_set_config()) {
            if(!$client->set_bitrate($device, $bitrate))
                return false;
        } else {
            // 设置动态码率
            $command = '{"main_cmd":75,"sub_cmd":45,"param_len":6,"params":"00000' . $params_bitrate . '0064"}';

            // api request
            if(!$client->device_usercmd($device, $command, 1))
                return false;

            $command = '{"main_cmd":75,"sub_cmd":5,"param_len":82,"params":"' . $params . '"}';
            // api request
            if(!$client->device_usercmd($device, $command, 1))
                return false;

            $maxspeed = 2 * $bitrate;
            $minspeed = 50;
            $this->set_speed($client, $device, $maxspeed, $minspeed);
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `bitlevel`='".$bitlevel."', `bitrate`='".$bitrate."', `params_bit`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 码率
    function set_bitrate($client, $device, $bitrate) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        if(!is_numeric($bitrate) || $bitrate < 50)
            return false;

        $settings = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'devicefileds WHERE `deviceid`="'.$deviceid.'"');
        if(!$settings || !$settings['params_bit'] || !$settings['params_debug'])
            return false;

        $params_bit = $settings['params_bit'];
        $plat = $settings['platform'];

        // 设置清晰度
        $bitlevel = 0;
        switch ($plat) {
            case 80:
            case 81:
            if($bitrate > (600 + 800) / 2) {
                $bitlevel = 2;
            } elseif ($bitrate > (400 + 600) / 2) {
                $bitlevel = 1;
            }
            break;

            default:
            if($bitrate > (300 + 400) / 2) {
                $bitlevel = 2;
            } elseif ($bitrate > (200 + 300) / 2) {
                $bitlevel = 1;
            }
            break;
        }

        $params_bitrate = $this->_parse_hex(dechex($bitrate), 3);
        $params = substr_replace($params_bit, $params_bitrate, 21, 3);

        if ($client->_set_config()) {
            if(!$client->set_bitrate($device, $bitrate))
                return false;
        } else {
            // 设置动态码率
            $command = '{"main_cmd":75,"sub_cmd":45,"param_len":6,"params":"00000' . $params_bitrate . '0064"}';

            // api request
            if(!$client->device_usercmd($device, $command, 1))
                return false;

            $command = '{"main_cmd":75,"sub_cmd":5,"param_len":82,"params":"' . $params . '"}';
            // api request
            if(!$client->device_usercmd($device, $command, 1))
                return false;
            
            $maxspeed = 2 * $bitrate;
            $minspeed = 50;
            $this->set_speed($client, $device, $maxspeed, $minspeed);
        }

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `bitlevel`='".$bitlevel."', `bitrate`='".$bitrate."', `params_bit`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
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

    // 云录制
    function set_cvr($client, $device, $cvr) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $cvr = $cvr ? 1 : 0;
        $params_status = 'FFFFFFFFFFFFFFFF';

        if ($client->_check_need_sendcvrcmd($deviceid, $cvr)) {
            // request params
            $params_cvr = $cvr ? '00' : '01';
            $params = substr_replace($params_status, $params_cvr, 6, 2);
            $command = '{"main_cmd":75,"sub_cmd":43,"param_len":8,"params":"' . $params . '"}';

            // api request
            if(!$client->device_usercmd($device, $command, 1))
                return false;
        }

        if(!$client->set_cvr($device, $cvr))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `cvr`='".$cvr."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 云录制定时任务
    function set_cvr_cron($client, $device, $cvr_cron, $cvr_start, $cvr_end, $cvr_repeat) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
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

            // 排除云录制定时任务
            if(intval(substr($cron_item, 4, 4), 16) === 16)
                continue;

            $params .= $cron_item;
            $cron_count++;
        }
        // 解析云录制定时任务
        $params_cron_type = '0010';
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
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `cvr_cron`='".$cvr_cron."', `cvr_start`='".$cvr_start."', `cvr_end`='".$cvr_end."', `cvr_repeat`='".$cvr_repeat."', `params_cron`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 报警定时任务
    function set_alarm_cron($client, $device, $alarm_cron, $alarm_start, $alarm_end, $alarm_repeat) {
        if(!$client || !$device || !$device['deviceid'])
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

        // 排除报警定时任务
            if(intval(substr($cron_item, 4, 4), 16) === 12)
                continue;

            $params .= $cron_item;
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
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_cron`='".$alarm_cron."', `alarm_start`='".$alarm_start."', `alarm_end`='".$alarm_end."', `alarm_repeat`='".$alarm_repeat."', `params_cron`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
    }

    // 报警通知
    function set_alarm_push($client, $device, $alarm_push, $platform) {
        if(!$client || !$device || !$device['deviceid'])
            return false;

        $deviceid = $device['deviceid'];
        $alarm_push = $alarm_push ? 1 : 0;
        $params_status = 'FFFFFFFFFFFFFFFF';

        $p_alarm_push = $alarm_push ? $this->_parse_hex(dechex(0x80), 2) : $this->_parse_hex(dechex(0), 2);
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
                            $push_client = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'push_client WHERE `udid`="'.$udid.'" AND `uid`="'.$uid.'" AND `pushid`="'.$pushid.'" AND status>0');
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

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_push`='".$alarm_push."' WHERE `deviceid`='".$deviceid."'");

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
        $params = substr_replace($params_alarm_level, $params_alarm_audio, 1, 3);

        $command = '{"main_cmd":75,"sub_cmd":60,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_audio`='".$alarm_audio."', `alarm_audio_level`='".$alarm_audio_level."', `params_alarm_level`='".$params."' WHERE `deviceid`='".$deviceid."'");

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

        $command = '{"main_cmd":75,"sub_cmd":60,"param_len":8,"params":"' . $params . '"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."devicefileds SET `alarm_move`='".$alarm_move."', `alarm_move_level`='".$alarm_move_level."', `params_alarm_level`='".$params."' WHERE `deviceid`='".$deviceid."'");

        return true;
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

    function _cmd_params($command) {
        if(!$command)
            return false;
        $data = json_decode($command, true);
        $params = $data['params'];
        return $params;
    }

    function _format_settings($device, $allows, $type, $settings) {
        $result = array();

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

    function device_register($uid, $deviceid, $connect_type, $desc, $appid, $client_id, $timezone, $location_latitude, $location_longitude) {
        if(!$uid || !$deviceid || !$desc)
            return false;

        $stream_id = '';
        $connect_did = '';
        $connect_token = '';

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
                if(isset($ret['connect_did']) && $ret['connect_did'])
                    $connect_did = $ret['connect_did'];
                if(isset($ret['connect_token']) && $ret['connect_token'])
                    $connect_token = $ret['connect_token'];
            }   
        }

        $device = $this->get_device_by_did($deviceid);
        if($device) {
        // check app
        // TODO: app config check mechanism
        /*
        if($device['appid'] != $appid) 
        return false;
        */

        $device = $this->update_device($uid, $deviceid, $connect_type, $connect_did, $connect_token, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location_latitude, $location_longitude);
        } else {
            $regip = $this->base->onlineip;
            $device = $this->add_device($uid, $deviceid, $connect_type, $connect_did, $connect_token, $stream_id, $appid, $client_id, $desc, $regip, $timezone, $location_latitude, $location_longitude);
        }

        return $device;
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
                $this->update_cvr($deviceid, $info['cvr']['cvr_day'], $info['cvr']['cvr_end_time']);
            }
        }

        return true;
    }

    function alarmpic($device, $page=1, $count=10) {
        if(!$device || !$device['deviceid'])
            return false;

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $list = array();

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $ret = $client->alarmpic($device, $page, $count);
            if($ret) 
                $list = $ret;
        }

        $result = array();
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    function dropalarmpic($device, $path) {
        if(!$device || !$device['deviceid'] || !$path)
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->dropalarmpic($device, $path);
        }

        // TODO: connect_type=0
        return false;
    }

    function downloadalarmpic($device, $path) {
        if(!$device || !$device['deviceid'] || !$path)
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            return $client->downloadalarmpic($device, $path);
        }

        // TODO: connect_type=0
        return false;
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

        if($connect_type > 0) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;
        }

        if($connect_type > 0 && $client->_connect_meta()) {
            $client = $this->_get_api_client($connect_type);
            if(!$client)
                return false;

            $meta = $client->device_meta($device, $params);
            if(!$device)
                return $meta;

            if($meta && is_array($meta) && $meta['deviceid']) {
                // $cvr_day = $meta['cvr_day'];
                // $cvr_end_time = $meta['expire_time'];
                // $this->update_cvr($deviceid, $cvr_day, $cvr_end_time);
                if(isset($meta['status'])) {
                    $this->update_status_by_status($deviceid, $meta['status']);
                    $device['status'] = $meta['status'];
                }
            }
        }

        $sub_list = $uid ? $this->get_subscribe_by_uid($uid, $appid) : array();
        $check_sub = !empty($sub_list);

        $share = $this->get_share_by_deviceid($device['deviceid']);
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

        $meta = array(
            'deviceid' => $device['deviceid'],
            'data_type' => ($params['auth_type'] == 'share') ? 2 : ($device['grant_device'] ? 1 : 0),
            'connect_type' => $device['connect_type'],
            'connect_did' => $device['connect_did'],
            'stream_id' => $device['stream_id'],
            'status' => $device['status'],
            'description' => ($params['auth_type'] == 'share' && $title) ? $title : $device['desc'],
            'cvr_day' => $device['cvr_day'],
            'cvr_end_time' => $device['cvr_end_time'],
            'share' => $share_type,
            'shareid' => $shareid,
            'uk' => $uk,
            'intro' => $intro,
            'thumbnail' => $this->get_device_thumbnail($device),
            'subscribe' => ($check_sub && in_array($device['deviceid'], $sub_list)) ? 1 : 0,
            'viewnum' => $device['viewnum'],
            'approvenum' => $device['approvenum'],
            'commentnum' => $device['commentnum'],
            'reportnum' => $device['reportnum']
        );

        return $meta;
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

        return true;
    }

    // 获取观看记录
    function list_view($uid, $appid, $page, $count) {
        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'device_view WHERE uid="'.$uid.'"');
        $page = $this->base->page_get_page($page, $count, $total);

        $list = array();
        if($total) {
            $sub_list = $uid ? $this->get_subscribe_by_uid($uid, $appid) : array();
            $check_sub = !empty($sub_list);

            $this->base->load('user');
            $data = $this->db->fetch_all('SELECT a.deviceid,b.connect_type,b.connect_did,b.desc,b.status,b.connect_thumbnail,b.cvr_thumbnail,b.uid,c.share_type,c.shareid,c.uid AS uk,c.connect_uid,b.viewnum,b.approvenum,b.commentnum,a.lastupdate FROM '.API_DBTABLEPRE.'device_view a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid JOIN '.API_DBTABLEPRE.'device_share c ON a.deviceid=c.deviceid WHERE a.uid="'.$uid.'" AND a.delstatus=0 AND b.reportstatus=0 ORDER BY a.lastupdate DESC LIMIT '.$page['start'].', '.$count);
            foreach($data as $value) {
                $user = $_ENV['user']->_format_user($value['uid']);
                $share = array(
                    'deviceid' => $value['deviceid'],
                    'connect_type' => $value['connect_type'],
                    'connect_did' => $value['connect_did'],
                    'description' => $value['desc'],
                    'status' => $value['status'],
                    'thumbnail' => $this->get_device_thumbnail($value),
                    'uid' => $user['uid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'share' => $value['share_type'],
                    'shareid' => $value['shareid'],
                    'uk' => $value['connect_uid'],
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
        $result['page'] = $page['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    // 获取设备评论列表
    function list_comment($deviceid, $list_type, $page, $count, $st, $et) {
        $result = array();

        $table = API_DBTABLEPRE.'device_comment a LEFT JOIN '.API_DBTABLEPRE.'members b ON a.uid=b.uid';
        $where = ' WHERE a.delstatus=0 AND a.deviceid="'.$deviceid.'"';

        if($list_type === 'timeline') {
            if($st) $where .= ' AND a.dateline>'.$st;
            if($et) $where .= ' AND a.dateline<'.$et;
        }

        $total = $this->db->result_first('SELECT count(*) FROM '.$table.$where);

        if($list_type === 'page') {
            $page = $this->base->page_get_page($page, $count, $total);

            $start = $page['start'];
            $result['page'] = $page['page'];
        } else {
            $start = 0;
        }

        $limit = ' LIMIT '.$start.','.$count;
        $orderbysql = ' ORDER BY a.dateline DESC';

        $list = array();
        if($total) {
            $this->base->load('user');
            $query = $this->db->query('SELECT a.cid,a.parent_cid,a.uid,a.ip,a.comment,a.dateline FROM '.$table.$where.$orderbysql.$limit);
            while($data = $this->db->fetch_array($query)) {
                $user = $_ENV['user']->_format_user($data['uid']);

                $data['username'] = $user['username'];
                $data['avatar'] = $user['avatar'];
                $data['comment'] = $this->userTextDecode($data['comment']);

                $list[] = $data;
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    // 保存设备评论
    function save_comment($uid, $deviceid, $comment, $parent_cid, $client_id, $appid) {
        $regip = $this->base->onlineip;
        $regtime = $this->base->time;
        
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_comment SET uid="'.$uid.'", deviceid="'.$deviceid.'", comment="'.$this->userTextEncode($comment).'", parent_cid="'.$parent_cid.'", ip="'.$regip.'", dateline="'.$regtime.'", lastupdate="'.$regtime.'", client_id="'.$client_id.'", appid="'.$appid.'"');

        $result = array();
        $result['comment'] = array(
            'cid' => $this->db->insert_id(),
            'parent_cid' => $parent_cid,
            'ip' => $regip,
            'comment' => $comment,
            'dateline' => $regtime
            );

        $result['commentnum'] = $this->add_comment($deviceid);

        return $result;
    }

    // 把用户输入的文本转义（主要针对特殊符号和emoji表情）
    function userTextEncode($str) {
        if (!is_string($str)) 
            return $str;

        if (!$str || $str === 'undefined')
            return '';

        // 暴露出unicode
        $text = json_encode($str);

        // 将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i", function($str) {
            return mysql_real_escape_string(addslashes($str[0]));
        }, $text); 

        return json_decode($text);
    }

    // 解码上面的转义
    function userTextDecode($str) {
        //暴露出unicode
        $text = json_encode($str);

        //将两条斜杠变成一条，其他不动
        $text = preg_replace_callback('/\\\\\\\\/i', function($str) {
            return '\\';
        }, $text);

        return json_decode($text);
    }

    // 增加设备评论数
    function add_comment($deviceid) {
        $commentnum = $this->db->result_first('SELECT commentnum FROM '.API_DBTABLEPRE.'device WHERE deviceid="'.$deviceid.'"');

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET commentnum='.(++$commentnum).' WHERE deviceid="'.$deviceid.'"');

        return $commentnum;
    }

    // 获取观看记录
    function get_view_by_pk($uid, $deviceid, $fileds='*') {
        return $this->db->fetch_first('SELECT '.$fileds.' FROM '.API_DBTABLEPRE.'device_view WHERE uid="'.$uid.'" AND deviceid="'.$deviceid.'"');
    }

    // 保存观看记录
    function save_view($uid, $deviceid, $client_id, $appid) {
        $result = $this->get_view_by_pk($uid, $deviceid);
        if($result) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_view SET num=num+1, delstatus=0, ip="'.$this->base->onlineip.'", lastupdate="'.$this->base->time.'",  client_id="'.$client_id.'", appid="'.$appid.'" WHERE uid="'.$uid.'" AND deviceid="'.$deviceid.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_view SET uid="'.$uid.'", deviceid="'.$deviceid.'", num=1, delstatus=0, ip="'.$this->base->onlineip.'", dateline="'.$this->base->time.'", lastupdate="'.$this->base->time.'", client_id="'.$client_id.'", appid="'.$appid.'"');
        }
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
    function save_report($uid, $deviceid, $type, $reason, $client_id, $appid) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_report SET uid="'.$uid.'", deviceid="'.$deviceid.'", type="'.$type.'", reason="'.$reason.'", ip="'.$this->base->onlineip.'", dateline="'.$this->base->time.'", lastupdate="'.$this->base->time.'", client_id="'.$client_id.'", appid="'.$appid.'"');
    }

    // 增加设备举报数
    function add_report($admin, $deviceid) {
        $reportnum = $this->db->result_first('SELECT reportnum FROM '.API_DBTABLEPRE.'device WHERE deviceid="'.$deviceid.'"');

        if ($admin) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET reportnum='.(++$reportnum).',reportstatus="1" WHERE deviceid="'.$deviceid.'"');
        } else {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET reportnum='.(++$reportnum).' WHERE deviceid="'.$deviceid.'"');
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

    // 发送邮件给管理员
    function send_email_to_admin($subject, $message) {
        $admin_email = array(
            '酆继明<feng@cms.com.cn>',
            '张杰<zhangjie@cms.com.cn>',
            '徐康<xukang@iermu.com>',
            '周维明<zhouweiming@iermu.com>',
            '李欣<lixin@cms.com.cn>',
            '何建敏<hejianmin@iermu.com>'
        );

        $this->base->load('mail');

        $mail = array(
            'appid' => '',
            'uids' => array(),
            'email_to' => implode(',', $admin_email),
            'subject' => $subject,
            'message' => $message,
            'charset' => 'utf-8',
            'htmlon' => 1,
            'level' => 1,
            'frommail' => '后台自动报警 <ops@cms.com.cn>',
            'dateline' => $this->base->time
        );
        
        return $_ENV['mail']->send_one_mail($mail);
    }

    // 获取首页图
    function list_ad($type, $page, $count) {
        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'ad a LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid JOIN '.API_DBTABLEPRE.'device c ON a.deviceid=c.deviceid WHERE a.type="'.$type.'" AND b.share_type&0x1!=0 AND c.status&0x4!=0 AND c.reportstatus=0');
        $page = $this->base->page_get_page($page, $count, $total);

        $list = array();
        if($total)
            $list = $this->db->fetch_all('SELECT a.aid,a.deviceid,c.desc AS description,c.connect_thumbnail AS thumbnail,b.shareid,b.connect_uid AS uk,b.uid,b.share_type AS share,c.viewnum,c.approvenum,c.commentnum FROM '.API_DBTABLEPRE.'ad a LEFT JOIN '.API_DBTABLEPRE.'device_share b ON a.deviceid=b.deviceid JOIN '.API_DBTABLEPRE.'device c ON a.deviceid=c.deviceid WHERE a.type="'.$type.'" AND b.share_type&0x1!=0 AND c.status&0x4!=0 AND c.reportstatus=0 LIMIT '.$page['start'].','.$count);

        $result = array();
        $result['page'] = $page['page'];
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

            $list[] = array('deviceid' => $device['deviceid'], 'description' => $device['desc']);
        }

        $result = array();
        $result['uid'] = $user['uid'];
        $result['username'] = $user['username'];
        $result['count'] = $count;
        $result['list'] = $list;

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
            $share_list = $this->db->fetch_all('SELECT a.shareid,a.connect_type,a.connect_uid,a.uid,a.deviceid,a.share_type,b.connect_did,b.status,b.connect_thumbnail,b.cvr_thumbnail,b.viewnum,b.approvenum,b.commentnum FROM '.API_DBTABLEPRE.'device_share a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.uid="'.$uid.'" AND a.share_type in (1,3) AND a.connect_type in ('.$support_type.') AND b.status&0x4!=0 AND b.reportstatus=0');
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

    // 搜索设备列表
    function search_share($uid, $uk, $keyword, $device_type, $category, $orderby, $page, $count, $appid) {
        $this->base->load('search');
        $table = API_DBTABLEPRE.'device_share a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid';
        $where = 'WHERE a.share_type&0x1!=0 AND b.status&0x4!=0 AND b.reportstatus=0 AND b.desc LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"';
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

        $result = $list = array();
        $total = $this->db->result_first("SELECT count(*) FROM $table $where");
        $page = $this->base->page_get_page($page, $count, $total);
        $limit = 'LIMIT '.$page['start'].', '.$count;

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

        $result['page'] = $page['page'];
        $result['count'] = $count;
        $result['device_list'] = $list;
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
    function add_preset($device, $preset, $title) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":71,"sub_cmd":18,"param_len":3,"params":"01' . $this->_parse_hex(dechex($preset), 2) . '00"}';

        // api request
        if(!$client->device_usercmd($device, $command, 1) || !$this->save_settings($client, $device))
            return false; 

        $plat_preset = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_preset WHERE `deviceid`="'.$deviceid.'" AND `preset`="'.$preset.'"');
        if($plat_preset) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_preset SET `title`="'.$title.'", `dateline`="'.$this->base->time.'" WHERE `deviceid`="'.$deviceid.'" AND `preset`="'.$preset.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'device_preset SET `deviceid`="'.$deviceid.'", `preset`="'.$preset.'", `title`="'.$title.'", `dateline`="'.$this->base->time.'"');
        }

        return true;
    }

    // 删除云台预置点
    function drop_preset($deviceid, $preset) {
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'device_preset WHERE `deviceid`="'.$deviceid.'" AND `preset`="'.$preset.'"');

        return true;
    }

    // 获取云台预置点列表
    function list_preset($deviceid) {
        $storage = $this->base->load_storage('11'); //qiniu
        if(!$storage)
            return false;

        $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'device_preset WHERE deviceid="'.$deviceid.'"');

        $list = array();
        for ($i = 0, $n = count($arr); $i < $n; $i++) { 
            $filepath = $arr[$i]['pathname'] . $arr[$i]['filename'];
            
            $list[] = array(
                'preset' => $arr[$i]['preset'],
                'title' => $arr[$i]['title'],
                'thumbnail' => $arr[$i]['status'] ? $storage->preset_image($filepath) : ''
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
        if(!$device || !$device['deviceid'] || !$preset || !$time || !$client_id || !$sign)
            return false;
        
        $device_preset = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_preset WHERE deviceid="'.$device['deviceid'].'" AND preset="'.$preset.'"');
        if(!$device_preset)
            return false;

        $storageid = 11; //qiniu
        $storage = $this->base->load_storage($storageid);
        if(!$storage)
            return false;

        $ret = $storage->preset_info($device, $preset, $time, $client_id, $sign);
        if(!$ret)
            return false;

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_preset SET pathname="'.$ret['pathname'].'",filename="'.$ret['filename'].'",storageid="'.$storageid.'",status="0" WHERE deviceid="'.$device['deviceid'].'" AND preset="'.$preset.'"');

        $result = array(
            'storageid' => $storageid,
            'filepath' => $ret['pathname'].$ret['filename'],
            'upload_token' => $ret['upload_token']
        );
        
        return $result;
    }

    function preset_qiniu_notify($device, $preset) {
        if(!$device || !$device['deviceid'] || !$preset)
            return false;
        
        $arr = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_preset WHERE deviceid="'.$device['deviceid'].'" AND preset="'.$preset.'"');
        if(!$arr)
            return false;
        
        $storageid = 11; //qiniu
        $storage = $this->base->load_storage($storageid);
        if(!$storage)
            return false;

        $this->db->query('UPDATE '.API_DBTABLEPRE.'device_preset SET status="1" WHERE deviceid="'.$device['deviceid'].'" AND preset="'.$preset.'"');

        $result = array(
            'storageid' => $storageid,
            'status' => '1',
            'thumbnail' => 'http://7xr64e.com2.z0.glb.qiniucdn.com/'.$arr['pathname'].$arr['filename']
        );
        
        return $result;
    }

    function device_update_cvr($device, $cvr_day, $cvr_end_time) {
        if(!$device || !$device['deviceid']) 
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $ret = $client->device_update_cvr($device, $cvr_day, $cvr_end_time);
        if(!$ret)
            return false;

        $cvr_day = $cvr_day?$cvr_day:$device['cvr_day'];
        $cvr_end_time = $cvr_end_time?$cvr_end_time:$device['cvr_end_time'];

        $this->update_cvr($deviceid, $cvr_day, $cvr_end_time);

        $result = array(
            'deviceid' => $deviceid,
            'cvr_day' => $cvr_day.'',
            'cvr_end_time' => $cvr_end_time.''
            );

        return $result;
    }

    // upgrade
    function upgrade($device) {
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":74,"sub_cmd":47,"param_len":0,"params":0}';

        // api request
        if(!$client->device_usercmd($device, $command, 1))
            return false;

        return true;
    }

    // repair
    function repair($device) {
        if(!$device || !$device['deviceid']) 
            return false;

        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];

        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $ret = $client->device_repair($device);
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

    // list 433alarm
    function list_433alarm($device) {
        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":74,"sub_cmd":77,"param_len":0,"params":0}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;

        $params = $this->_cmd_params($ret['data'][1]['userData']);

        $count = intval(substr($params, 0, 2), 16);

        $list = array();
        for ($i = 0; $i < $count; $i++) {
            $item = substr($params, $i * 52 + 2, 52);

            $temp = array();
            
            $temp['action'] = intval(substr($item, 2, 2), 16);
            $temp['udid'] = intval(substr($item, 4, 8), 16);
            $temp['key'] = intval(substr($item, 12, 8), 16);
            $temp['desc'] = $this->_hex2bin($item, 20, 32);

            $list[] = $temp;
        }

        $result = array();
        $result['count'] = $count;
        $result['list'] = $list;
        return $result;
    }

    // add 433alarm
    function add_433alarm($device, $fileds) {
        $params = '';
        $count = 0;
        foreach ($fileds as $k => $v) {
            if (!isset($v['action'], $v['udid'], $v['key'], $v['desc']))
                continue;
            
            $action = intval($v['action'], 16);
            $udid = intval($v['udid'], 16);
            $key = intval($v['key'], 16);
            $desc = $v['desc'] ? $v['desc'] : '';

            $params .= "81".$this->_parse_hex(dechex($action), 2).$this->_parse_hex(dechex($udid), 8).$this->_parse_hex(dechex($key), 8).$this->_bin2hex($desc, 32);
            $count++;
        }

        $params = $this->_parse_hex(dechex($count), 2).$params;

        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":75,"sub_cmd":77,"param_len":' . (strlen($params) / 2) . ',"params":"' . $params . '"}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;


        $result = array();
        $result['result'] = $this->_cmd_params($ret['data'][1]['userData']);
        return $result;
    }

    // delete 433alarm
    function delete_433alarm($device, $fileds) {
        $params = '';
        $count = 0;
        foreach ($fileds as $k => $v) {
            if (!isset($v['key']))
                continue;

            $key = intval($v['key'], 16);

            $params .= "00"."00"."00000000".$this->_parse_hex(dechex($key), 8).$this->_bin2hex("", 32);
            $count++;
        }

        $params = $this->_parse_hex(dechex($count), 2).$params;

        $connect_type = $device['connect_type'];
        $client = $this->_get_api_client($connect_type);
        if(!$client)
            return false;

        $command = '{"main_cmd":75,"sub_cmd":77,"param_len":' . (strlen($params) / 2) . ',"params":"' . $params . '"}';

        // api request
        $ret = $client->device_usercmd($device, $command, 1);
        if(!$ret || !$ret['data'] || !$ret['data'][1] || !$ret['data'][1]['userData'])
            return false;


        $result = array();
        $result['result'] = $this->_cmd_params($ret['data'][1]['userData']);
        return $result;
    }
    
    function get_partner_device_by_did($partner_id, $deviceid) {
        if(!$partner_id || !$deviceid)
            return false;
        
        $check = $this->db->fetch_first("SELECT deviceid FROM ".API_DBTABLEPRE."device_partner WHERE partner_id='$partner_id' AND deviceid='$deviceid'");
        if(!$check)
            return false;
        
        return $this->get_device_by_did($deviceid);
    }
    
    function set_device_auth($device) {
        if(!$device || !$device['deviceid'] || !$device['uid'])
            return false;
        
        $cookie = $this->_device_auth_cookie($device['deviceid']);
        $auth = rawurlencode($this->base->authcode($device['uid'].'|'.$device['deviceid'].'|'.md5($_SERVER['HTTP_USER_AGENT']), 'ENCODE', API_KEY));
        $this->base->setcookie($cookie, $auth, 86400);
        return true;
    }
    
    function check_device_auth($deviceid) {
        if(!$deviceid)
            return false;
        
        $cookie = $this->_device_auth_cookie($deviceid);
        if(isset($_COOKIE[$cookie])) {
            @list($uid, $did, $agent) = explode('|', $this->base->authcode(rawurldecode($_COOKIE[$cookie]), 'DECODE', API_KEY));
            if($deviceid != $did || $agent != md5($_SERVER['HTTP_USER_AGENT'])) {
                $this->base->setcookie($cookie, '');
            } else {
                return $uid;
            }
        }
        return false;
    }
    
    function _device_auth_cookie($deviceid) {
        return 'ida_'.md5($deviceid.API_KEY);
    }
    
    function get_upgrade_status($device) {
        $result = array();
        $result['status'] = 0;
        $result['deviceid'] = $device['deviceid'];
        return $result;
    }

    function get_upgrade_info($device) {
        $result = array();
        $needupgrade = false;
        if (!$needupgrade) {
            $result['version'] = 0;
            $result['desc'] = '';
        } else {
            $result['version'] = '6123-20';
            $result['desc'] = '1.xxxxx;\n2.xxxxx';
        }

        return $result;
    }
    
}
