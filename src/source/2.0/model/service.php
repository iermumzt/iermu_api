<?php

!defined('IN_API') && exit('Access Denied');

class servicemodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->servicemodel($base);
    }

    function servicemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function service_message_contact($uid, $client_id, $platform) {
        $this->base->load('user');
        $touser = $_ENV['user']->get_user_by_uid($uid);
        $dev = $_ENV['user']->get_dev_by_uid($uid);
        $debug = !$dev || !$dev['message'] ? 0 : 1;

        $list = array();
        $where = ' WHERE ((ptype=3 AND pid="'.$uid.'") OR (ptype=2 AND pid="'.$client_id.'") OR (ptype=1 AND pid="'.$platform.'")) AND status'.($debug ? '>=1' : '=2');
        $arr = $this->db->fetch_all('SELECT id,name,avatar FROM '.API_DBTABLEPRE.'service_info WHERE status=1');
        for ($i = 0, $n = count($arr); $i < $n; $i++) {
            $temp = $this->db->fetch_first('SELECT msgid,msgtype,mtype,mid,status,lastupdate FROM '.API_DBTABLEPRE.'service_message'.$where.' AND sid="'.$arr[$i]['id'].'" ORDER BY lastupdate DESC LIMIT 1');
            if ($temp) {
                $msg_body = $this->service_message_body($temp['msgtype'], $temp['mtype'], $temp['mid'], $temp['status']);
                if ($msg_body) {
                    $arr[$i]['type'] = 3;
                    $arr[$i]['message'] = array(
                        'fromid' => $arr[$i]['id'],
                        'fromname' => $arr[$i]['name'],
                        'fromtype' => 3,
                        'toid' => $touser['uid'],
                        'toname' => $touser['username'],
                        'totype' => 1,
                        'msgid'=>$temp['msgid'],
                        'msgtype'=>intval($temp['msgtype']),
                        'time'=>$temp['lastupdate'],
                        'title'=>$msg_body['title'],
                        'intro'=>$msg_body['intro'],
                        'image'=>$msg_body['image'],
                        'url'=>$msg_body['url']
                    );
                    $list[] = $arr[$i];
                }
            }
        }

        $result = array(
            'count' => count($list),
            'list' => $list
        );

        return $result;
    }

    function list_service_message($sid, $uid, $client_id, $platform, $list_type, $page, $count, $st, $et) {
        $this->base->load('user');
        $dev = $_ENV['user']->get_dev_by_uid($uid);
        $debug = !$dev || !$dev['message'] ? 0 : 1;

        $result = array();
        $where = ' WHERE ((ptype=3 AND pid="'.$uid.'") OR (ptype=2 AND pid="'.$client_id.'") OR (ptype=1 AND pid="'.$platform.'")) AND status'.($debug ? '>=1' : '=2').' AND sid="'.$sid.'"';

        if ($list_type === 'timeline') {
            $temp = $this->db->fetch_all('SELECT msgid,msgtype,mtype,mid,status,lastupdate FROM '.API_DBTABLEPRE.'service_message'.$where.' AND lastupdate>'.$st.' AND lastupdate<'.$et.' ORDER BY lastupdate DESC');
        } else {
            $total = $this->db->result_first('SELECT COUNT(*) FROM '.API_DBTABLEPRE.'service_message'.$where);
            $pages = $this->base->page_get_page($page, $count, $total);
            $result['page'] = $pages['page'];

            $temp = $this->db->fetch_all('SELECT msgid,msgtype,mtype,mid,status,lastupdate FROM '.API_DBTABLEPRE.'service_message'.$where.' ORDER BY lastupdate DESC LIMIT '.$pages['start'].','.$count);
        }

        $list = array();
        $header = $this->service_message_header($sid, $uid);

        foreach ($temp as $value) {
            $msg = $this->service_message_body($value['msgtype'], $value['mtype'], $value['mid'], $value['status']);
            if ($msg) {
                $body = array('msgid'=>$value['msgid'], 'msgtype'=>intval($value['msgtype']), 'time'=>$value['lastupdate'], 'title'=>$msg['title'], 'intro'=>$msg['intro'], 'image'=>$msg['image'], 'url'=>$msg['url']);
                $list[] = array_merge($header, $body);
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    function service_message_header($sid, $uid) {
        $this->base->load('user');
        $from = $this->db->fetch_first('SELECT id,name FROM '.API_DBTABLEPRE.'service_info WHERE id="'.$sid.'" AND status=1');
        $to = $_ENV['user']->get_user_by_uid($uid);

        $header = $from && $to ? array('fromid'=>$from['id'], 'fromname'=>$from['name'], 'fromtype'=>3, 'toid'=>$to['uid'], 'toname'=>$to['username'], 'totype'=>1) : array();

        return $header;
    }

    function service_message_body($msgtype, $mtype, $mid, $status) {
        $msg = array();
        
        switch ($msgtype) {
            case '10': // 图文消息
                switch ($mtype) {
                    case '2': // 商业消息
                        $record = $this->db->fetch_first('SELECT a.sn,a.deviceid,a.uid,b.notification_templateid FROM '.API_DBTABLEPRE.'device_business_record a LEFT JOIN '.API_DBTABLEPRE.'device_business_activity b ON a.aid=b.aid WHERE a.rid="'.$mid.'" AND a.status>=1 AND b.notification_type="10"');
                        if ($record) {
                            $msg = $this->db->fetch_first('SELECT title,intro FROM '.API_DBTABLEPRE.'notification_template WHERE tid="'.$record['notification_templateid'].'" AND status='.$status);
                            if ($msg) {
                                $temp = $this->db->fetch_first('SELECT pathname,filename FROM '.API_DBTABLEPRE.'notification_template_file WHERE tid="'.$record['notification_templateid'].'" AND type="template_thumb" ORDER BY lastupdate DESC LIMIT 1');
                                $msg['image'] = $temp ? 'http://7xoa8w.com2.z0.glb.qiniucdn.com/'.$temp['pathname'].$temp['filename'] : '';
                                $msg['url'] = 'https://www.iermu.com/business/activity/'.$mid.'/'.$record['sn'].'/'.md5($record['deviceid'].$record['uid']);
                            }
                        }
                        break;
                    
                    default: // 系统消息
                        $msg = $this->db->fetch_first('SELECT title,intro FROM '.API_DBTABLEPRE.'article_info WHERE aid="'.$mid.'" AND status='.$status);
                        if ($msg) {
                            $temp = $this->db->fetch_first('SELECT pathname,filename FROM '.API_DBTABLEPRE.'article_file WHERE aid="'.$mid.'" AND type="article_thumb" ORDER BY lastupdate DESC LIMIT 1');
                            $msg['image'] = $temp ? 'http://7xoa8w.com2.z0.glb.qiniucdn.com/'.$temp['pathname'].$temp['filename'] : '';
                            $msg['url'] = 'https://www.iermu.com/appmsg/'.$mid.'/r';
                        }
                        break;
                }
                break;
            
            default:
                break;
        }

        return $msg;
    }

    function listpushserver($uid, $push_type, $client_id, $appid) {
        if (!$uid || !$client_id || !$appid) return false;
        
        $list = array();
        
        // server_type=6 push server
        $this->base->load('server');
        $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."server WHERE appid='$appid' AND server_type=6 AND sub_type='$push_type' AND status>0");
        foreach($query as $v) {
            $value = $_ENV['server']->get_server_by_id($v['serverid']);
            if($value && $value['url']) {
                $list[] = $value['url'];
            }
        }

        if (!$list) return false;

        $result = array();
        $result['count'] = count($list);
        $result['server_list'] = $list;
        $result['token'] = $this->gen_server_push_token($uid, $push_type, $client_id, $appid);
        return $result;
    }

    function gen_server_push_token($uid, $push_type, $client_id, $appid) {
        $token = md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
        $dateline = $this->base->time;
        $expires = $dateline + 60;
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."server_push_token SET token='$token', uid='$uid', push_type='$push_type', client_id='$client_id', appid='$appid', dateline='$dateline', expires='$expires'");
        return $token;
    }

}