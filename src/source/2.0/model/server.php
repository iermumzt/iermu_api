<?php

!defined('IN_API') && exit('Access Denied');

class servermodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->servermodel($base);
    }

    function servermodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function get_total_num($sqladd = '') {
        $data = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."server $sqladd");
        return $data;
    }

    function get_list($page, $ppp, $totalnum, $sqladd) {
        $start = $this->base->page_get_start($page, $ppp, $totalnum);
        $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."server $sqladd LIMIT $start, $ppp");
        return $data;
    }

    function get_servers($col = '*', $where = '') {
        $arr = $this->db->fetch_all("SELECT $col FROM ".API_DBTABLEPRE."server".($where ? ' WHERE '.$where : ''), 'mdid');
        foreach($arr as $k => $v) {
            $arr[$k] = $v;
        }
        return $arr;
    }

    function delete_servers($ids) {
        $ids = $this->base->implode($ids);
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."server WHERE serverid IN ($ids)");
        return $this->db->affected_rows();
    }

    function get_server_by_id($serverid) {
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE serverid='$serverid'");
        return $this->_net_format($arr);
    }
    
    function get_server_by_key($key) {
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server WHERE server_key='$key'");
        return $this->_net_format($arr);
    }
    
    function _net_format($server) {
        if(!$server)
            return array();
        
        if($server['net'] && $this->base->netid) {
            $snet = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."server_net WHERE serverid='".$server['serverid']."' AND netid='".$this->base->netid."'");
            if($snet && $snet['url']) {
                $server['url'] = $snet['url'];
            }
        }
        return $server;
    }
    
    function get_api($serverid) {
        $api = $this->db->result_first("SELECT api FROM ".API_DBTABLEPRE."server WHERE serverid='$serverid'");
        return $api?$api:'';
    }
    
    function user_cmd($deviceid, $serverid, $data, $response=0) {
        if(!$deviceid || !$serverid || !$data) return FALSE;
        
        $server = $this->get_server_by_id($serverid);
        if(!$server || $server['status'] < 1)
            return FALSE;
        
        $api = $server['api'];
        $url = $api.'v1/control/usercmd';
        $expire = $this->base->time + 20;
        $data = array(
            'deviceid' => $deviceid,
            'data' => base64_encode($data),
            'response' => $response,
            'sign' => $this->_gen_server_sign($server, $expire),
            'expire' => $expire
        );
        $result = $this->_curl_get($url, $data);
        
        /*
        if($result['http_code'] == 200 && $result['content'] == '0') {
            return TRUE;
        } else {
            return FALSE;
        }*/
        return $result;
    }
    
    function start_publish($device, $duration=0, $record=0) {
        if(!$device || !$device['deviceid']) return FALSE;
        
        $deviceid = $device['deviceid'];
        $serverid = $device['lastserverid'];
        
        $server = $this->get_server_by_id($serverid);
        if(!$server || $server['status'] < 1)
            return FALSE;
        
        $api = $server['api'];
        $url = $api.'v1/control/startpublish';
        $expire = $this->base->time + 20;
        $data = array(
            'deviceid' => $deviceid,
            'duration' => $duration,
            'record' => $record,
            'sign' => $this->_gen_server_sign($server, $expire),
            'expire' => $expire
        );
        $result = $this->_curl_get($url, $data);
        
        if($result['http_code'] == 200 && $result['content'] == '0') {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    function stop_publish($deviceid, $serverid) {
        if(!$deviceid || !$serverid) return FALSE;
        
        $server = $this->get_server_by_id($serverid);
        if(!$server || $server['status'] < 1)
            return FALSE;
        
        $api = $server['api'];
        $url = $api.'v1/control/stoppublish';
        $expire = $this->base->time + 20;
        $data = array(
            'deviceid' => $deviceid,
            'sign' => $this->_gen_server_sign($server, $expire),
            'expire' => $expire
        );
        $result = $this->_curl_get($url, $data);
        
        if($result['http_code'] == 200 && $result['content'] == '0') {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    function liveplay($device, $type='rtmp', $params) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $deviceid = $device['deviceid'];
        $serverid = $device['lastserverid'];
        $streamid = $device['stream_id'];
        $client_id = $device['client_id'];
        
        $live_url = '';
        if($type == 'rtmp') {
            $server = $this->get_server_by_id($serverid);
            if($server && $server['url'] && $server['status']>0) {
                $server_url = $server['url'];
                $expire = $this->base->time + 60;
                $sign = $this->base->_gen_client_sign($client_id, $expire);
                if($server_url && $streamid && $sign) {
                    $live_url = 'rtmp://'.$server_url.'/live/'.$streamid.'?deviceid='.$deviceid.'&sign='.$sign.'&time='.$this->base->time.'&expire='.$expire;
                } 
            }
            
            if($live_url) {
                $this->start_publish($device);
            } else {
                return false;
            }
        
            $result = array(
                'url' => $live_url,
                'status' => $device['status'],
                'description' => $device['desc']
            );
        
            return $result;
        }
        
        return false;
    }
    
    function _curl_get($url, $data){
        $ch = curl_init();
        $timeout = 30;
        foreach($data as $k=>$v) { $fields_string .= $k.'='.$v.'&'; }
        rtrim($fields_string ,'&') ; 
        if($fields_string) $url = $url.'?'.$fields_string;
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $handles = curl_exec($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);
        //echo $handles;
        $header['content'] = $handles;
        return $header;
    }
    
    function _gen_server_sign($server, $expire=0) {
        if(!$server || !$expire)
            return "";
        
        if($expire < $this->base->time)
            return "";
        
        $ak = $server['server_key'];
        $sk = $server['server_secret'];
        return md5($expire.$ak.$sk);
    }
    
    function device_usercmd($device, $command, $response=0) {
        if(!$device || !$device['deviceid'] || !$command) 
            return false;
        
        $cmd = $this->user_cmd($device['deviceid'], $device['lastserverid'], $command, $response);
        if($cmd['http_code'] == 200) {
            if($cmd['content'] == '0') {
                $result = array();
                $result['success'] = 1;
                return $result;
            } else if($cmd['content'] == '-1') {
                $this->base->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            } else if($cmd['content'] == '-2') {
                $this->base->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_USER_CMD_FAILED);
            } else if($cmd['content'] == '-3') {
                $this->base->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_USER_CMD_FAILED);
            } else {
                $result = $data = $userData = array();
                $userData['userData'] = $cmd['content'];
                $data[] = '_result';
                $data[] = $userData;
                $result['data'] = $data;
                return $result;
            }
        } else {
            $this->base->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_USER_CMD_FAILED);
        }
    }
    
    function device_batch_usercmd($device, $commands) {
        if(!$device || !$device['deviceid'] || !$commands) 
            return false;
        
        foreach($commands as $k => $v) {
            $cmd = $this->user_cmd($device['deviceid'], $device['lastserverid'], $v['command'], $v['response']);
            if($cmd['http_code'] != 200)
                return false;
            
            if($v['response']) {
                if(!$cmd['content'])
                    return false;
                $commands[$k]['data'] = $cmd['content'];
            } else {
                $commands[$k]['data'] = '';
            }
        }
        
        return $commands;
    }
    
    function _check_setting_sync($device) {
        if(!$device || !$device['deviceid']) 
            return 0;
        
        $deviceid = $device['deviceid'];
        
        $lastsettingsync = $this->db->result_first("SELECT lastsettingsync FROM ".API_DBTABLEPRE."devicefileds WHERE `deviceid`='$deviceid'");
        
        return $lastsettingsync?0:1;
    }
    
    function device_init_info($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $info = array();
        
        // 赠送3个月录像
        // TODO: 根据不同设备类型判断存储信息
        /*
        if($device['cvr_day'] == 0 || $device['cvr_end_time'] == 0) {
            $info['cvr'] = array(
                'cvr_type' => 1,
                'cvr_day' => 7,
                'cvr_end_time' => $this->base->time + 3 * 30 * 24 * 3600
            );
        } 
        */
        
        $info['status'] = -1;
        return $info;
    }
    
    function get_alarm_storageid($device) {
        return STORAGE_DEFAULT_ALARM_STORAGEID;
    }
    
    function need_cvr_after_save() {
        return false;
    }
    
    function need_cvr_record() {
        return true;
    }
    
    function on_device_online($device) {
        return true;
    }
    
    function _device_online($device) {
        if(!$device || !$device['deviceid'])
            return false;
        return $device['status']>0?true:false;
    }
    
    function device_config($device, $config, $request_no) {
        return $config;
    }
    
}
