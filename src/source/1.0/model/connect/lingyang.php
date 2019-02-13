<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class lingyangconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->lingyangconnect($base, $_config);
    }

    function lingyangconnect(&$base, $_config) {
        parent::__construct($base, $_config);
        $this->base->load('device');
    }
    
    function _connect_uid($uid) {
        return $uid;
    }
    
    function _connect_secret($uid, $length=8) {
        $md5 = md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
        return substr($md5, 0, $length);
    }
    
    function get_token_extras($uid) {
        if(!$uid)
            return false;
        
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
        $status = 1;
        if(!$connect || !$connect['connect_uid'] || !$connect['connect_secret'] || !$connect['connect_cid']) {
            if(!$connect || !$connect['connect_uid'] || !$connect['connect_secret']) {
                $connect_uid = $this->_connect_uid($uid);
                $connect_secret = $this->_connect_secret($uid);
            
                // 羚羊用户注册接口
                if(!$this->user_register($connect_uid, $connect_secret)) 
                    return false;
            } else {
                $connect_uid = $connect['connect_uid'];
                $connect_secret = $connect['connect_secret'];
            }
            
            if(!$connect || !$connect['connect_cid']) {
                /*
                $connect_cid = $this->gen_user_connect_cid($uid);
                if(!$connect_cid)
                    return false;
                */
                $connect_cid = '';
            }
            
            if(!$connect) {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect SET uid='$uid', connect_type='".$this->connect_type."', connect_uid='$connect_uid', connect_secret='$connect_secret', connect_cid='$connect_cid', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='$status'");
            } else {
                $this->db->query("UPDATE ".API_DBTABLEPRE."member_connect SET connect_uid='$connect_uid', connect_secret='$connect_secret', connect_cid='$connect_cid', lastupdate='".$this->base->time."' WHERE uid='$uid' AND connect_type='".$this->connect_type."'");
            }
        } else {
            $connect_uid = $connect['connect_uid'];
            $connect_secret = $connect['connect_secret'];
            $connect_cid = $connect['connect_cid'];
            $status = $connect['status'];
        }
        
        if($connect_uid && $connect_secret) {
            return array(
                'connect_type' => $this->connect_type,
                'connect_uid' => $connect_uid,
                'connect_secret' => $connect_secret,
                //'connect_cid' => $connect_cid,
                'connect_appid' => $this->config['app_id'],
                //'init' => $this->_init_string(),
                //'user_token' => $this->user_token($connect_cid),
                'status' => $status
            );
        }
        
        return false;
    }
    
    function get_user_token($uid) {
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
        if($connect && $connect['access_token'] && $connect['expires'] > $this->base->time)
            return $connect['access_token'];
        
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'uname' => $connect['connect_uid'],
            'passwd' => $connect['connect_secret']
        );
        
        $ret = $this->_request('/API/users/login', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data']['token'])
            return false;
        
        $user_token = $ret['data']['token'];
        $expires = $ret['data']['timestamp'] + 24 * 3600;
        $this->set_user_token($uid, $user_token, '', $expires);
        
        return $user_token;
    }
    
    function get_device_token($device, $user_token) {
        if(!$device || !$user_token)
            return false;
        
        if($device['connect_token'] && $device['connect_token_expires'] > $this->base->time + 3600)
            return $device['connect_token'];
        
        $params = array(
            'user_token' => $user_token
        );
        
        $ret = $this->_request('/API/cameras/'.$device['connect_did'].'/accesstoken', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data']['access_token'])
            return false;
        
        $device_token = $ret['data']['access_token'];
        $expires = $this->base->time + 3600 * 24;
        $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_token='$device_token', connect_token_expires='$expires' WHERE deviceid='".$device['deviceid']."'");
        
        return $device_token;
    }
    
    function set_user_token($uid, $access_token, $refresh_token='', $expires=0) {
        $this->db->query("UPDATE ".API_DBTABLEPRE."member_connect SET access_token='$access_token', refresh_token='$refresh_token', expires='$expires' WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
    }
    
    function user_register($connect_uid, $connect_secret) {
        if(!$connect_uid || !$connect_secret) 
            return false;
        
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'uname' => $connect_uid,
            'passwd' => $connect_secret
        );
        
        $ret = $this->_request('/API/users/old_register', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);
        
        return true;
    }
    
    function device_usercmd($device, $command, $response=0) {
        if(!$device || !$device['deviceid'] || !$device['connect_did'] || !$command) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $data = json_decode($command, true);
        if(!$data || !$data['main_cmd'])
            return false;
        
        $msg = '';
        
        if($response) {
            $request_no = $this->_request_no($deviceid);
        } else {
            $request_no = $this->_d2h(0, 8);
        }
        
        if(!$request_no)
            return false;
        
        $msg .= $this->_h2b($request_no);
        
        $msg .= chr(intval($data['main_cmd']));
        $msg .= chr(intval($data['sub_cmd']));
        
        $param_len = intval($data['param_len']);
        if($param_len + 12 > 256)
            return false;
        
        $msg .= $this->_d2b($param_len, 2);
        if($param_len > 0) {
            $msg .= $this->_h2b($data['params']);
        }
        
        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'msg' => base64_encode($msg)
        );

        $ret = $this->_request('/API/message/tocamera', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);
        
        if($response) {
            // TODO: async
            $ret = $this->_notify($request_no);
            if(!$ret)
                return false;
        
            $result = $data = $userData = array();
            $userData['userData'] = $ret;
            $data[] = '_result';
            $data[] = $userData;
            $result['data'] = $data;
            return $result;
        }
        
        return true;
    }
    
    function device_batch_usercmd($device, $commands) {
        if(!$device || !$device['deviceid'] || !$device['connect_did'] || !$commands) 
            return false;
        
        $this->base->log('lingyang', 'start batch usercmd, count(commands)='.count($commands));
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $ids = array();
        foreach($commands as $k => $v) {
            $command = $v['command'];
            $response = $v['response'];
            if(!$command)
                return false;
            
            $data = json_decode($command, true);
            if(!$data || !$data['main_cmd'])
                return false;
        
            $msg = '';
        
            if($response) {
                $request_no = $this->_request_no($deviceid);
            } else {
                $request_no = $this->_d2h(0, 8);
            }
        
            if(!$request_no)
                return false;
        
            $msg .= $this->_h2b($request_no);
        
            $msg .= chr(intval($data['main_cmd']));
            $msg .= chr(intval($data['sub_cmd']));
        
            $param_len = intval($data['param_len']);
            if($param_len + 12 > 256)
                return false;
        
            $msg .= $this->_d2b($param_len, 2);
            if($param_len > 0) {
                $msg .= $this->_h2b($data['params']);
            }
        
            $params = array(
                'user_token' => $user_token,
                'access_token' => $connect_token,
                'msg' => base64_encode($msg)
            );
        
            $ret = $this->_request('/API/message/tocamera', $params, 'POST');
            if(!$ret || $ret['http_code'] != 200)
                return $this->_error($ret);
            
            
            if($response) {
                $ids[] = $request_no;
                $commands[$k]['request_no'] = $request_no;
            } else {
                $commands[$k]['request_no'] = '';
            }
            $commands[$k]['data'] = '';
        }
        
        $this->base->log('lingyang', 'post request finished. wait for batch notify. count(ids)='.count($ids));
        
        if($ids) {
            // TODO: async
            $ret = $this->_batch_notify($ids);
            if(!$ret)
                return false;
        
            foreach($commands as $k => $v) {
                if($v['request_no'] && $ret[$v['request_no']]) {
                    $commands[$k]['data'] = $ret[$v['request_no']];
                }
            }
        }
        
        return $commands;
    }
    
    function _request($url, $params = array(), $httpMethod = 'GET') {
        if(!$url || !$this->config['api_prefix'])
            return false;
        
        $this->base->log('lingyang _request', 'url='.$url);
        
        $url = $this->config['api_prefix'].$url;
        
            $ch = curl_init();
        
        $headers = array();
        if (isset($params['host'])) {
            $headers[] = 'X-FORWARDED-FOR: ' . $params['host'] . '; CLIENT-IP: ' . $params['host'];
            unset($params['host']);
        }

        if (isset($params['app_id'])) {
            $headers[] = 'X-APP-ID: ' . $params['app_id'];
            unset($params['app_id']);
        }

        if (isset($params['app_key'])) {
            $headers[] = 'X-APP-Key: ' . $params['app_key'];
            unset($params['app_key']);
        }

        if (isset($params['user_token'])) {
            $headers[] = 'X-User-Token: ' . $params['user_token'];
            unset($params['user_token']);
        }

        if (isset($params['camera_token'])) {
            $headers[] = 'X-Camera-Token: ' . $params['camera_token'];
            unset($params['camera_token']);
        }

        if (isset($params['access_token'])) {
            $headers[] = 'X-Access-Token: ' . $params['access_token'];
            unset($params['access_token']);
        }

            $curl_opts = array(
            CURLOPT_CONNECTTIMEOUT  => 3,
            CURLOPT_TIMEOUT         => 20,
            CURLOPT_USERAGENT       => 'iermu api server/1.0',
                CURLOPT_HTTP_VERSION        => CURL_HTTP_VERSION_1_1,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_HEADER          => false,
                CURLOPT_FOLLOWLOCATION  => false,
        );

        if (stripos($url, 'https://') === 0) {
            $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
        }

        $delimiter = strpos($url, '?') === false ? '?' : '&';
        if (strtoupper($httpMethod) === 'GET') {
            $query = http_build_query($params, '', '&');
            $curl_opts[CURLOPT_URL] = $url.$delimiter.$query;
            $curl_opts[CURLOPT_POST] = false;
        } else {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($params);
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_POSTFIELDS] = $body;
        }

        if (!empty($headers)) {
            $curl_opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $curl_opts);
        $result = curl_exec($ch);

        if (empty($result)) {
            // log记录 ----------- error_log
            log::error_log('curl error', $url.$delimiter.http_build_query($params, '', '&'), $result, curl_errno($ch), curl_error($ch));
            // log记录 ----------- debug_log
            log::debug_log('LINGYANG', $url, $params, curl_errno($ch), $result, curl_error($ch));
            curl_close($ch);

            return false;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // log记录 ----------- debug_log
        log::debug_log('LINGYANG', $url, $params, $http_code, $result, json_encode($headers));
        
        $this->base->log('lingyang _request finished', 'http_code='.$http_code.',result='.$result);
        
        return array('http_code' => $http_code, 'data' => json_decode($result, true)); 
    } 
    
    function _error($ret) {
        $this->base->log('lingyang _error', 'err='.json_encode($ret));
        if($ret['http_code'] == 400) {
            $this->base->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        } elseif($ret['http_code'] == 401) {
            $this->base->error(API_HTTP_FORBIDDEN, CONNECT_ERROR_USER_TOKEN_INVALID);
        } elseif($ret['http_code'] == 403) {
            $this->base->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        } elseif($ret['http_code'] == 404) {
            $this->base->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        } else {
            $this->base->error(API_HTTP_SERVICE_UNAVAILABLE, CONNECT_ERROR_API_FAILED);
        }
    }
    
    function _request_no($deviceid) {
        if(!$deviceid)
            return false;
        
        $no_key = API_RDKEYPRE.'lingyang_request_no_'.$deviceid;
        $no = $this->redis->get($no_key);
        if($no === false) {
            $no = 1;
        } else {
            $no = intval($no);
            if($no > 0 && $no < 65536) {
                $no++;
            } else {
                $no = 1;
            }
        }
        $this->redis->set($no_key, $no);
        
        $request_no = $this->_d2h($no, 2);
        
        // TODO: 兼容32位系统
        if(!(PHP_INT_SIZE === 8)) 
            return false;
        
        $deviceid = intval($deviceid) & 0x00ffffffffffffff;
        $request_no .= $this->_d2h($deviceid, 6);
        
        $notify_key = $this->_notify_key($request_no);
        $this->redis->hMset($notify_key, array('status'=>'0', 'data'=>'', 'time'=>time()));
        
        return $request_no;
    }
    
    function _notify($request_no) {
        $notify_key = $this->_notify_key($request_no);
        
        do {
            $notify = $this->redis->hGetAll($notify_key);
            if(!$notify) 
                return false;
            
            if(!$notify['status']) {
                if(time() - $notify['time'] > 10) {
                    $this->base->log('lingyang notify', 'notify 10s failed');
                    $this->redis->delete($notify_key);
                    return false;
                }
                
                usleep(200000);
                continue;
            }

            // log记录 ----------- debug_log
            log::debug_log('LINGYANG', '_notify', NULL, 200, $notify['data'], $request_no);
            
            $this->redis->delete($notify_key);

            return $notify['data'];
        } while(true);
        
        return false;
    }
    
    function _batch_notify($ids) {
        if(!$ids)
            return false;
        
        $this->base->log('batch notify', 'batch notify start.');
        
        $result = array();
        $loop = true;
        do {
            foreach($ids as $k => $request_no) {
                $notify_key = $this->_notify_key($request_no);
                $notify = $this->redis->hGetAll($notify_key);
                if(!$notify) {
                    $this->base->log('batch notify', 'redis notify key not exist');
                    $loop = false;
                    break;
                }
                
                if(!$notify['status']) {
                    if(time() - $notify['time'] > 30) {
                        $this->base->log('batch notify', 'batch notify 30s failed');
                        $loop = false;
                        break;
                    }
                } else {
                    $result[$request_no] = $notify['data'];
                    $this->redis->delete($notify_key);
                    unset($ids[$k]);
                    
                    $this->base->log('batch notify', 'get notify data request_no='.$request_no.', data='.$notify['data']);
                }
            }
            
            if(count($ids) == 0) break;
            
            // sleep 200ms
            usleep(200000);
        } while($loop);

        // log记录 ----------- debug_log
        log::debug_log('LINGYANG', '_batch_notify', NULL, 200, $result, json_encode($ids));
        
        $this->base->log('batch notify', 'loop stop. count(ids)='.count($ids));
        
        foreach($ids as $request_no) {
            $notify_key = $this->_notify_key($request_no);
            $this->redis->delete($notify_key);
        }
        
        if(!$result || count($ids) > 0) {
            $this->base->log('lingyang', 'batch notify failed.');
            return false;
        }
        
        $this->base->log('lingyang', 'batch notify finished.');
        
        return $result;
    }
    
    function _notify_key($request_no) {
        return API_RDKEYPRE.'lingyang_notify_'.$request_no;
    }
    
    function msg_notify($param) {
        if(!$param || !$param['event'])
            return false;
        
        $event = $param['event'];
        
        if($event == 'message') {
            $data = $param['data'];
            if(!$data)
                return false;
            
            $connect_did = $data['cid'];
            $connect_uid = $data['uname'];
            $time = $data['created'];
            $msg = $data['msg'];
            
            $msg = base64_decode($msg);
        
            $request_no = substr($msg, 0, 8);
            $request_no = bin2hex($request_no);
        
            $main_cmd = substr($msg, 8, 1);
            $main_cmd = hexdec(bin2hex($main_cmd));
        
            $sub_cmd = substr($msg, 9, 1);
            $sub_cmd = hexdec(bin2hex($sub_cmd));
        
            $param_len = substr($msg, 10, 2);
            $param_len = hexdec(bin2hex($param_len));
        
            $params = substr($msg, 12);
            $params = bin2hex($params);
        
            $datas = array(
                'main_cmd' => $main_cmd,
                'sub_cmd' => $sub_cmd,
                'param_len' => $param_len,
                'params' => $params
            );
            $datas = json_encode($datas);
        
            $notify_key = $this->_notify_key($request_no);
            $notify = $this->redis->hGetAll($notify_key);
            if(!$notify || $notify['response'] != 1) 
                return false;
        
            $this->redis->hSet($notify_key, 'data', $datas);
            $this->redis->hSet($notify_key, 'status', 1);
        } else if($event == 'device-message') {
            $data = $param['data'];
            if(!$data)
                return false;
            
            $time = $data['created'];
            $msg = $data['msg'];
            
            $msg = base64_decode($msg);
        
            $request_no = substr($msg, 0, 8);
            $request_no = bin2hex($request_no);
        
            $main_cmd = substr($msg, 8, 1);
            $main_cmd = hexdec(bin2hex($main_cmd));
        
            $sub_cmd = substr($msg, 9, 1);
            $sub_cmd = hexdec(bin2hex($sub_cmd));
        
            $param_len = substr($msg, 10, 2);
            $param_len = hexdec(bin2hex($param_len));
        
            $params = substr($msg, 12);
            $params = bin2hex($params);
        
            $datas = array(
                'main_cmd' => $main_cmd,
                'sub_cmd' => $sub_cmd,
                'param_len' => $param_len,
                'params' => $params
            );
            $datas = json_encode($datas);
        
            $notify_key = $this->_notify_key($request_no);
            $notify = $this->redis->hGetAll($notify_key);
            if(!$notify || $notify['response'] != 1) 
                return false;
        
            $this->redis->hSet($notify_key, 'data', $datas);
            $this->redis->hSet($notify_key, 'status', 1);
        }
        
        return true;
    }
    
    function _h2b($hex) {
        $str = '';
        $len = strlen($hex);
        if($len > 0) {
            for ($i = 0; $i < $len; $i += 2) {
                $temp = substr($hex, $i, 2);
                $str .= chr(intval($temp, 16));
            }
        }
        return $str;
    }
    
    function _d2h($num, $len) {
        $str = '';
        $hex = dechex($num);
        $hex = str_pad($hex, $len*2, '0', STR_PAD_LEFT);
        return $hex;
    }

    function _d2b($num, $len) {
        $hex = $this->_d2h($num, $len);
        return $this->_h2b($hex);
    }
    
    function _device_connect_cid($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        if($device['connect_cid']) 
            return $device['connect_cid'];
        
        if($device['connect_did']) {
            $connect_cid = $this->_decode_connect_did($device['connect_did']);
            if($connect_cid) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_cid='$connect_cid' WHERE deviceid='".$device['deviceid']."'");
                return $connect_cid;
            }
        }
        return false;
    }
    
    function liveplay($device, $type, $ps) {
        if(!$device || !$device['deviceid']) 
            $this->base->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_SHARE_NOT_EXIST);
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        /*
        $connect_did = $device['connect_did'];
        
        $url = '';
        $share = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE connect_type='".$this->connect_type."' AND deviceid='$deviceid'");
        if($share) {
            if($type == 'rtmp') {
                $url = $share['connect_rtmp'];
            } elseif($type == 'hls') {
                $url = $share['connect_hls'];
            }
        }
        
        $user_token = $this->get_user_token($uid);
        if(!$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did, $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data'])
            return false;
        
        $meta = array();
        
        if(isset($ret['data']['extra']) && !$_ENV['device']->checkyoueryundevice($deviceid)) {
            $device['status'] = $this->_extra_status($ret['data']['extra']);
        }
        
        $result = array(
            'url' => $url,
            'status' => $device['status'],
            'description' => $share['title']?$share['title']:$device['desc']
        );
        */
        
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return false;
        
        $device['connect_cid'] = $connect_cid;
        
        // 发送startplay命令
        $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"01000000"}';
        $this->device_usercmd($device, $command, 0);
        
        // 查询设备状态
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'cids' => array(intval($connect_cid))
        );
        
        $ret = $this->_request('/v2/devices/state', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        if($ret['data']['init_string'] && $this->config['init'] != $ret['data']['init_string']) {
            $this->config['init'] = $ret['data']['init_string'];
            $config = serialize($this->config);
            $this->db->query("UPDATE ".API_DBTABLEPRE."connect SET connect_config='$config' WHERE connect_type='".$this->connect_type."'");
        }
        
        $result = array();
        
        $share = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_share WHERE connect_type='".$this->connect_type."' AND deviceid='$deviceid'");
        $result['description'] = $share['title']?$share['title']:$device['desc'];
        $result['connect_type'] = intval($this->connect_type);
        $result['deviceid'] = $deviceid;
        $result['status'] = 0;
        
        $expires_in = 3600*24;
        $access_token = $this->device_accesstoken($connect_cid, $expires_in);
        $result['access_token'] = $access_token;
        $result['expires_in'] = $expires_in;
        
        if($ret['data']['devices'] && $ret['data']['devices'][0]) {
            $data = $ret['data']['devices'][0];
            if($data['cid'] == $connect_cid) {
                if($type == 'hls') {
                    $result['type'] = 'hls';
                    $result['url'] = $data['hls']?$data['hls']:'';
                } else {
                    if($data['config_type'] == 0 || $data['config_type'] == 1) {
                        $result['type'] = 'p2p';
                        $result['state'] = $data['state'];
                        $result['tracker_ip'] = $data['tracker_ip'];
                        $result['tracker_port'] = $data['tracker_port'];
                    } else {
                        $result['type'] = 'rtmp';
                    
                        if($data['config_type'] == 3) {
                            $url = 'rtmp://'.$data['relay_ip'].':'.$data['relay_port'].'/live/'.$access_token;
                        } else {
                            if($ps['auth_type'] == 'token') {
                                $url = 'rtmp://'.$data['relay_ip'].':'.$data['relay_port'].'/live/'.$access_token;
                            } else {
                                $url = '';
                                if($data['rtmp_url']) {
                                    $url = $data['rtmp_url'].'live/'.$access_token;
                                } else if($data['relay_ip']) {
                                    $url = 'rtmp://'.$data['relay_ip'].':'.$data['relay_port'].'/live/'.$access_token;
                                }
                            }
                        }
                        $result['url'] = $url;
                    }
                }
                
                if($data['state'] == 0) {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_online='0' WHERE connect_cid='".$data['cid']."' AND connect_type='".$this->connect_type."'");
                    
                    if($device['laststatusupdate'] + 60 < $this->base->time)
                        $device['status'] = 0;
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_online='1' WHERE connect_cid='".$data['cid']."' AND connect_type='".$this->connect_type."'");
                    
                    if($device['status'] == 0 && $device['laststatusupdate'] + 60 < $this->base->time) {
                        $device['status'] = 5;
                    }
                }
                
                $result['status'] = intval($device['status']);
            }
        }
        
        return $result;
    }
    
    function device_accesstoken($connect_cid, $expires_in=0) {
        if(!$connect_cid) 
            return '';
        
        /*
        第一字节为int类型的低位，第四字节为int类型的高位
        第一字节（推送播放验证）	第二字节（录制控制）	第三字节（播放控制）	第四字节（多码流保留）
 
        第一字节（0-7位）：验证及推送控制字段
        0位：是否开启rtmp直播
        1位：是否开启hls直播
        2位：是否验证推送IP
        3位：是否验证refer
        4位：UDP standby，是否可以接受UDP连接
        5-7位：保留
        第二字节（0-7位）：录制控制
        0-3位  : 存储时间权限, 0000=>没有存储权限, 0001=>存储7天, 0010=>存储30天,0011=>90天其他保留
        4位   : FLV 持久化开关，默认为 0 不打开
        5位   : HLS 持久化开关，默认为 0 不打开
        6-7位 : 保留
        第三字节（0-7位）： 播放控制
        0位  : 能否观看公众
        1位   : 能否观看私有
        2位   : 能否观看时移
        3位   : 能否观看录像
        4位   : 能否语音回传
        5位   : 能否视频回传
        6位   : 能否查看截图 
        7位   : 能否收听声音
        */
        
        // control
        $isrtmp = false;
        $ishls = false;
        $ischeckip = false;
        $ischeckrefer = false;
        $udpstandby = true;
        
        $control = '';
        $control .= $isrtmp?'1':'0';
        $control .= $ishls?'1':'0';
        $control .= $ischeckip?'1':'0';
        $control .= $ischeckrefer?'1':'0';
        $control .= $udpstandby?'1':'0';
        $control = str_pad($control, 8, "0", STR_PAD_RIGHT);
        
        $control .= '00000000';
        $control .= '11111111';
        
        $control = str_pad($control, 32, "0", STR_PAD_RIGHT);
        $control = bindec($control);
        
        // expire
        if(!$expires_in) $expires_in = 3600*24;
        $expire = $this->base->time + $expires_in;
        
        $token = '';
        
        $hash_cid = dechex($connect_cid);
        $hash_cid = str_pad($hash_cid, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_cid, -2*$i, 2)));
        }

        $hash_control = dechex($control);
        $hash_control = str_pad($hash_control, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_control, -2*$i, 2)));
        }

        $hash_expire = dechex($expire);
        $hash_expire = str_pad($hash_expire, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_expire, -2*$i, 2)));
        }

        $token = hash_hmac("md5", $token, $this->config['app_key']);
        
        return $connect_cid.'_'.$control.'_'.$expire.'_'.$token;
    }
    
    function createshare($device, $share_type) {
        if(!$device || !$device['deviceid'] || !$device['connect_did']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($connect_did, $connect_token, $user_token);
        if(!$config)
            return false;
        
        $record = $this->_device_record($deviceid);
        $config = $this->_config_public($config, true, $record);
        if(!$config)
            return false;
        
        $ret = $this->_set_device_config($connect_did, $connect_token, $user_token, $config);
        if(!$ret)
            return false;
        
        $ret = array(
            'shareid' => '',
            'uk' => $uid,
            'status' => 0
        );
        
        return $ret;
    }
    
    function cancelshare($device) {
        if(!$device || !$device['deviceid'] || !$device['connect_did']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($connect_did, $connect_token, $user_token);
        if(!$config)
            return false;
        
        $record = $this->_device_record($deviceid);
        $config = $this->_config_public($config, false, $record);
        if(!$config)
            return false;
        
        $ret = $this->_set_device_config($connect_did, $connect_token, $user_token, $config);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function _get_device_config($connect_did, $connect_token, $user_token) {
        if(!$connect_did || !$connect_token || !$user_token) 
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did.'/config', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data'] || !$ret['data']['config'])
            return $this->_error($ret);
        
        $this->base->log('lingyang', 'get device config='.$ret['data']['config']);
        
        return $ret['data']['config'];
    }
    
    function _set_device_config($connect_did, $connect_token, $user_token, $config) {
        if(!$connect_did || !$connect_token || !$user_token || !$config) 
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'cid' => $connect_did,
            'config' => $config
        );
        
        $ret = $this->_request('/API/cameras/configs', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);
        
        $this->base->log('lingyang', 'set device config='.$config);
        
        return true;
    }
    
    // config配置串：
    // 第1个字节：配置摄像机码率    1：1M码率；     2:500K； 3:300K
    // 第2个字节：配置摄像机音频开关  0：关闭声音；     1：打开声音
    // 第3个字节：配置录像存储的大区ID 1：深圳区；     2：杭州区；      3：北京区
    // 第5个字节：配置摄像机的模式   0：待命状态；     2：设置公众摄像头  3：设置录像    4：公众和录像
    function _config_public($config, $public=false, $record=false) {
        if(!$config)
            return false;
        
        $code = base64_decode($config);
        if($public && $record) {
            $code[4] = chr(4);
        } elseif($record) {
            $code[4] = chr(3);
        } elseif($public) {
            $code[4] = chr(2);
        } else {
            $code[4] = chr(0);
        }
        return base64_encode($code);
    }
    
    function _config_audio($config, $audio) {
        if(!$config)
            return false;
        
        $code = base64_decode($config);
        $code[1] = $audio?chr(1):chr(0);
        return base64_encode($code);
    }
    
    function _config_bitrate($config, $bitrate) {
        if(!$config)
            return false;
        
        $code = base64_decode($config);
        //$code[0] = chr(1);
        if($bitrate <= 300) {
            $code[0] = chr(3);
        } else if($bitrate <= 500) {
            $code[0] = chr(2);
        } else {
            $code[0] = chr(1);
        }
        return base64_encode($code);
    }
    
    function grant($uid, $uk, $name, $auth_code, $device) {
        if(!$uid || !$uk || !$auth_code || !$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_did || !$connect_token || !$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'cid' => $connect_did,
            'permission' => 1,
            'uname' => $uk
        );
        
        $ret = $this->_request('/API/cameras/auth/add', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);
        
        return true;
    }
    
    function dropgrantuser($device, $uk) {
        if(!$device || !$device['deviceid'] || !$uk) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_did || !$connect_token || !$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'cid' => $connect_did,
            'permission' => 1,
            'uname' => $uk
        );
        
        $ret = $this->_request('/API/cameras/auth/remove', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);
        
        return true;
    }
    
    function _connect_list() {
        return true;
    }
    
    function listdevice($uid) {
        if(!$uid) 
            return false;
        
        $user_token = $this->get_user_token($uid);
        if(!$user_token)
            return false;
        
        $connect_uid = $this->db->result_first("SELECT connect_uid FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
        if(!$connect_uid)
            return false;
        
        $params = array(
            'user_token' => $user_token
        );
        
        $ret = $this->_request('/API/users/'.$connect_uid.'/cameras', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);
        
        if($ret['data'] && $ret['data']['cameras']) {
            foreach($ret['data']['cameras'] as $v) {
                if($v['cid'] && $v['access_token']) {
                    $status = $this->_extra_status($v['extra']);
                    $coverurl = $v['cover_url'];
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_thumbnail='$coverurl', connect_token='".$v['access_token']."',connect_token_expires='".($this->base->time + 3600)."',status='$status' WHERE connect_type='".$this->connect_type."' AND uid='$uid' AND connect_did='".$v['cid']."'");
                }
            }
        }
        
        return true;
    }
    
    function _extra_status($extra) {
        if(!$extra)
            return 0;
        
        $extra = json_decode($extra, true);
        if(!$extra)
            return 0;
        
        if(!isset($extra['property']))
            return 0;
        
        $property = intval($extra['property']);
        if($property < 0) 
            return 0;
        
        if($property == 0)
            return 1;
        
        $isonline = $this->_get_b($property, 1)?1:0;
        $isalert = $this->_get_b($property, 2)?1:0;
        $isrecord = $this->_get_b($property, 3)?1:0;
        $isupload = ($isalert || $isrecord)?1:0;
    
        $status = $property << 2;
        $status = $this->_set_b(1, $isonline, $status);
        $status = $this->_set_b(2, $isupload, $status);
        return $status;
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
    
    function _get_device_coverurl($user_token, $connect_did) {
        if(!$user_token || !$connect_did)
            return false;
        
        $params = array(
            'user_token' => $user_token
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did.'/coverurl', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data']['cover_url'])
            return false;
        
        return $ret['data']['cover_url'];
    }
    
    function playlist($device, $ps, $starttime, $endtime) {
        if(!$device || !$device['deviceid'] || !$starttime || !$endtime)
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_did || !$connect_token || !$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'start' => $starttime,
            'end' => $endtime
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did.'/videos', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data'])
            return $this->_error($ret);
        
        $result = array();
        $result['stream_id'] = $device['stream_id'];
        $result['connect_type'] = $device['connect_type'];
        $result['results'] = array(
            'servers' => $ret['data']['servers'],
            'videos' => $ret['data']['videos']
        );
        return $result;
    }
    
    function thumbnail($device, $ps, $starttime, $endtime, $latest) {
        if(!$device || !$device['deviceid'])
            return false;
        
        if(!$latest && (!$starttime || !$endtime))
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_did || !$connect_token || !$user_token)
            return false;
        
        if($latest) {
            $starttime = $this->base->time - 3600;
            $endtime = $this->base->time;
        }
        
        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'from' => $starttime,
            'to' => $endtime
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did.'/snapshots', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);
        
        $list = array();
        if($ret['data'] && $ret['data']['base_urls'] && $ret['data']['timestamps']) {
            $base_urls = $ret['data']['base_urls'];
            if($latest) {
                $end = end($ret['data']['timestamps']);
                if($end) {
                    $base_url = $base_urls[$end['base_url_index']];
                    $t = end($end['list']);
                    $list[] = array(
                        'time' => $t, 
                        'url' => $base_url.$t
                    );
                }
            } else {
                foreach($ret['data']['timestamps'] as $v) {
                    $base_url = $base_urls[$v['base_url_index']];
                    foreach($v['list'] as $t) {
                        $list[] = array(
                            'time' => $t, 
                            'url' => $base_url.$t
                        );
                    }
                }
            }
        }
        
        if(!$list) 
            return false;
        
        $result = array();
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    
    function _get_thumbnail_url($connect_did, $connect_token, $user_token, $time) {
        if(!$connect_did || !$connect_token || !$user_token || !$time) 
            return '';
        return $this->config['api_prefix'].'/API/cameras/'.$connect_did.'/images?timestamp='
            .$time.'&user_token='.$user_token.'&access_token='.$connect_token;
    }
    
    function set_bitrate($device, $bitrate) {
        if(!$device || !$device['deviceid'] || !$device['connect_did']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($connect_did, $connect_token, $user_token);
        if(!$config)
            return false;
        
        $config = $this->_config_bitrate($config, $bitrate);
        if(!$config)
            return false;
        
        $ret = $this->_set_device_config($connect_did, $connect_token, $user_token, $config);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function set_cvr($device, $cvr) {
        if(!$device || !$device['deviceid'] || !$device['connect_did']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($connect_did, $connect_token, $user_token);
        if(!$config)
            return false;
        
        $public = $this->_device_public($deviceid);
        $config = $this->_config_public($config, $public, $cvr);
        if(!$config)
            return false;
        
        $ret = $this->_set_device_config($connect_did, $connect_token, $user_token, $config);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function _device_public($deviceid) {
        $public = $this->db->result_first("SELECT deviceid FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");
        return $public?true:false;
    }
    
    function _device_record($deviceid) {
        $record = $this->db->result_first("SELECT cvr FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        return $record?true:false;
    }
    
    function _set_config() {
        return true;
    }

    function connect_to_device($device) {
        return false;
    }
    
    function set_audio($device, $audio) {
        if(!$device || !$device['deviceid'] || !$device['connect_did']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($connect_did, $connect_token, $user_token);
        if(!$config)
            return false;
        
        $config = $this->_config_audio($config, $audio);
        if(!$config)
            return false;
        
        $ret = $this->_set_device_config($connect_did, $connect_token, $user_token, $config);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function device_register($uid, $deviceid, $desc) {
        if(!$uid || !$deviceid || !$desc) 
            return false;
        
        $username = $this->db->result_first("SELECT m.username FROM ".API_DBTABLEPRE."device d LEFT JOIN ".API_DBTABLEPRE."members m ON d.uid=m.uid WHERE d.deviceid='$deviceid'");
        if($username) {
            $extras = array();
            $extras['username'] = $username;
            $this->base->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ALREADY_REG, NULL, NULL, NULL, $extras); 
        }
        
        $user_token = $this->get_user_token($uid);
        if(!$user_token)
            return false;
        
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'user_token' => $user_token,
            'sn' => $deviceid
        );
        
        $ret = $this->_request('/API/cameras/bind', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data']['cid'] || !$ret['data']['access_token']) {
            if($ret['http_code'] == 404) {
                $this->base->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            }
            return $this->_error($ret);
        }
        
        $connect_did = $ret['data']['cid'];
        $connect_token = $ret['data']['access_token'];
        
        $result = array(
            'connect_did' => $connect_did,
            'connect_token' => $connect_token
        );
        return $result;
    }
    
    function camera_register($deviceid) {
        if(!$deviceid) 
            return false;
        
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'sn' => $deviceid,
            'config' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=='
        );
        
        $ret = $this->_request('/API/cameras/old_register', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data']['cid'])
            return $this->_error($ret);
        
        return $ret['data']['cid'];
    }
    
    function device_drop($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'cid' => $connect_did
        );
        
        $ret = $this->_request('/API/cameras/unbind', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        return true;
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

        $user_token = $this->get_user_token($device['uid']);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($device['connect_did'], $connect_token, $user_token);
        if(!$config)
            return false;
        
        $code = base64_decode($config);
        $code[1] = chr(1);
        $code[4] = chr(3);
        $config = base64_encode($code);
        
        $ret = $this->_set_device_config($device['connect_did'], $connect_token, $user_token, $config);
        if(!$ret)
            return false;
        
        $info = array();
        
        // 赠送3个月录像
        // TODO: 根据不同设备类型判断存储信息
        if($device['cvr_day'] == 0 || $device['cvr_end_time'] == 0) {
            $info['cvr'] = array(
                'cvr_day' => 7,
                'cvr_end_time' => $this->base->time + 3 * 30 * 24 * 3600
            );
        } 
        
        return $info;
    }
    
    function device_sync_config($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($connect_did, $connect_token, $user_token);
        if(!$config)
            return false;
        
        $code = base64_decode($config);
        
        $result = array(
            'audio' => ($code[1] == chr(1))?1:0,
            'cvr' => ($code[4] == chr(3) || $code[4] == chr(4))?1:0
        );
        return $result;
    }
    
    function _connect_meta() {
        return true;
    }
    
    function device_meta($device, $ps) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        if(!$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did, $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data'])
            return false;
        
        $meta = array();
        if(isset($ret['data']['extra']) && !$_ENV['device']->checkyoueryundevice($deviceid)) {
            $meta['status'] = $this->_extra_status($ret['data']['extra']);
        }
        return $meta;
    }
    
    function device_repair($device) {
        if(!$device || !$device['deviceid'] || !$device['connect_did']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;
        
        $config = $this->_get_device_config($connect_did, $connect_token, $user_token);
        if(!$config)
            return false;
        
        $code = base64_decode($config);
        if($code[4] == chr(0)) {
            $code[4] = chr(4);
        } else {
            $code[4] = chr(0);
        }
        $config_repair = base64_encode($code);
        
        $ret = $this->_set_device_config($connect_did, $connect_token, $user_token, $config_repair);
        if(!$ret)
            return false;
        
        $ret = $this->_set_device_config($connect_did, $connect_token, $user_token, $config);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function _check_need_savemenu($params) {
        if (count($params) == 1 && (isset($params['cvr']) || isset($params['audio']) || isset($params['bitrate']) || isset($params['bitlevel']))) {
            return false;
        }
        return true;
    }

    function _check_need_sendcvrcmd($deviceid, $open) {
        /*
        $cvr = $this->db->result_first('SELECT cvr FROM '.API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        if (intval($open) == 1 && intval($cvr) == 0)
            return true;
        */
        return false;
    }

    function clip($device, $starttime, $endtime, $name) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        $connect_token = $this->get_device_token($device, $user_token);
        
        if(!$connect_token || !$user_token)
            return false;

        $params = array(
            'user_token' => $user_token,
            'access_token' => $connect_token,
            'cid' => $connect_did,
            'start' => $starttime,
            'end' => $endtime
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did.'/clips', $params, 'POST');
        if (!$ret)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, LOG_ERROR_CURL_REQUEST_FAILED);
        if ($ret['http_code'] != 200) {
            $this->_external_error($ret['http_code']);
        }
        
        return $ret['data'];
    }

    function infoclip($device, $type) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        
        if(!$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'cid' => $connect_did,
            'clip_id' => $type
        );
        
        $ret = $this->_request('/API/cameras/'.$connect_did.'/clips/'.$type.'/status', $params, 'GET');
        if (!$ret)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, LOG_ERROR_CURL_REQUEST_FAILED);
        if ($ret['http_code'] != 200) {
            $this->_external_error($ret['http_code']);
        }
        
        return $ret['data'];
    }

    function listdeviceclip($device, $page, $count) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_did = $device['connect_did'];
        
        $user_token = $this->get_user_token($uid);
        
        if(!$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'cid' => $connect_did,
            'page' => $page,
            'size' => $count
        );
        
        $list = array();
        $ret = $this->_request('/API/cameras/'.$connect_did.'/clips', $params, 'GET');
        if (!$ret)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, LOG_ERROR_CURL_REQUEST_FAILED);
        if ($ret['http_code'] != 200) {
            $this->_external_error($ret['http_code']);
        }
        
        $list['count'] = count($ret['data']['clips']); //羚羊返回的total不对
        foreach ($ret['data']['clips'] as $item) {
            foreach ($item['segments'] as $subitem) {
                $list['list'][] = array('thumbnail' => $subitem['cover'], 'url' => $subitem['download'], 'clip_id' => $item['id']);
            }
        }
        return $list;
    }

    function listuserclip($uid, $page, $count) {
        if(!$uid)
            return false;

        $connect_uid = _connect_uid($uid);                
        $user_token = $this->get_user_token($uid);
        
        if(!$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'uname' => $uid,
            'page' => $page,
            'size' => $count
        );
        
        $list = array();
        $ret = $this->_request('/API/users/'.$connect_uid.'/clips', $params, 'GET');
        if (!$ret)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, LOG_ERROR_CURL_REQUEST_FAILED);
        if ($ret['http_code'] != 200) {
            $this->_external_error($ret['http_code']);
        }
        
        $list['count'] = $ret['data']['total'];
        foreach ($ret['data']['clips'] as $item) {
            foreach ($item['segments'] as $subitem) {
                $list['list'][] = array('thumbnail' => $subitem['cover'], 'url' => $subitem['download'], 'clip_id' => $item['id']);
            }
            
        }
        return $list;
    }
 
    function _external_error($http_code) {
        if ($http_code == 400)
            $this->base->error(API_HTTP_BAD_REQUEST, LOG_ERROR_EXTERNAL_PARAM_ERROR);
        else if ($http_code == 401)
            $this->base->error(API_HTTP_FORBIDDEN, LOG_ERROR_EXTERNAL_TOKEN_ERROR);
        else if ($http_code == 403)
            $this->base->error(API_HTTP_FORBIDDEN, LOG_ERROR_EXTERNAL_TOKEN_ERROR);
        else if ($http_code == 404)
            $this->base->error(API_HTTP_NOT_FOUND, LOG_ERROR_EXTERNAL_CONTENT_NOT_FOUND);
        else if ($http_code == 406)
            $this->base->error(API_HTTP_BAD_REQUEST, LOG_ERROR_EXTERNAL_LIMIT);
        else if ($http_code == 409)
            $this->base->error(API_HTTP_BAD_REQUEST, LOG_ERROR_EXTERNAL_HAS_CLIPED);
        else if ($http_code == 500)
            $this->base->error(API_HTTP_SERVICE_UNAVAILABLE, LOG_ERROR_EXTERNAL_SERVICE_UNAVAILABLE);
        else
            $this->base->error(API_HTTP_SERVICE_UNAVAILABLE, LOG_ERROR_EXTERNAL_UNKNOWN);
    }
    
    function device_uploadtoken($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $deviceid = $device['deviceid'];
        
        // cid
        $connect_cid = $device['connect_cid'];
        if(!$connect_cid) {
            if($device['connect_did']) {
                $connect_cid = $this->_decode_connect_did($device['connect_did']);
            } else {
                //$connect_cid = $this->gen_device_connect_cid($deviceid);
            }
            
            if($connect_cid) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_cid='$connect_cid' WHERE deviceid='$deviceid'");
            } else {
                return array('error' => -1);
            }
        }
        
        // control
        $isrtmp = true;
        $ishls = true;
        $ischeckip = false;
        //$cvr = ($cvr_day && $device['cvr_end_time']>$this->base->time)?1:0;
        //$cvr_day = $device['cvr_day'];
        $cvr = 1;
        $cvr_day = 7;
        $flv_save = false;
        $hls_save = true;
        
        $control = '';
        $control .= $isrtmp?'1':'0';
        $control .= $ishls?'1':'0';
        $control .= $ischeckip?'1':'0';
        $control = str_pad($control, 8, "0", STR_PAD_RIGHT);
        if($cvr && $cvr_day == 7) {
            $control .= '0001';
        } else if($cvr && $cvr_day == 30) {
            $control .= '0010';
        } else if($cvr && $cvr_day == 90) {
            $control .= '0011';
        } else {
            $control .= '0000';
        }
        $control .= $flv_save?'1':'0';
        $control .= $hls_save?'1':'0';
        $control = str_pad($control, 32, "0", STR_PAD_RIGHT);
        $control = bindec($control);
        
        // expire
        $expire = $this->base->time + 3600*24*30;
        
        $token = '';
        
        $hash_cid = dechex($connect_cid);
        $hash_cid = str_pad($hash_cid, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_cid, -2*$i, 2)));
        }

        $hash_control = dechex($control);
        $hash_control = str_pad($hash_control, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_control, -2*$i, 2)));
        }

        $hash_expire = dechex($expire);
        $hash_expire = str_pad($hash_expire, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_expire, -2*$i, 2)));
        }

        $token = hash_hmac("md5", $token, $this->config['app_key']);
        
        $upload_token = $connect_cid.'_'.$control.'_'.$expire.'_'.$token;
        
        $fileds = $_ENV['device']->_check_fileds($deviceid);
        $bitlevel = $fileds['bitlevel'] + 1;
        $audio = intval($fileds['audio']);
        $share = $_ENV['device']->get_share_by_deviceid($deviceid);
        
        $config = array(
            'init' => $this->_init_string(),
            'bitlevel' => $bitlevel,
            'audio' => $audio,
            'share' => $share?1:0,
            'cvr' => $cvr
        );
        
        $result = array(
            'deviceid' => $deviceid,
            'connect_type' => $device['connect_type'],
            'connect_cid' => $connect_cid,
            'upload_token' => $upload_token,
            'config' => $config
        );
        return $result;
    }
    
    function user_token($connect_cid) {
        if(!$connect_cid) 
            return '';
        
        // control
        
        /*
        $isrtmp = false;
        $ishls = false;
        $ischeckip = false;
        $cvr = 0;
        $cvr_day = 0;
        $flv_save = false;
        $hls_save = false;
        
        $control = '';
        $control .= $isrtmp?'1':'0';
        $control .= $ishls?'1':'0';
        $control .= $ischeckip?'1':'0';
        $control = str_pad($control, 8, "0", STR_PAD_RIGHT);
        if($cvr && $cvr_day == 7) {
            $control .= '0001';
        } else if($cvr && $cvr_day == 30) {
            $control .= '0010';
        } else if($cvr && $cvr_day == 90) {
            $control .= '0011';
        } else {
            $control .= '0000';
        }
        $control .= $flv_save?'1':'0';
        $control .= $hls_save?'1':'0';
        $control = str_pad($control, 32, "0", STR_PAD_RIGHT);
        $control = bindec($control);
        */
        $control = 0;
        
        // expire
        $expire = $this->base->time + OAUTH2_DEFAULT_ACCESS_TOKEN_LIFETIME;
        
        $token = '';
        
        $hash_cid = dechex($connect_cid);
        $hash_cid = str_pad($hash_cid, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_cid, -2*$i, 2)));
        }

        $hash_control = dechex($control);
        $hash_control = str_pad($hash_control, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_control, -2*$i, 2)));
        }

        $hash_expire = dechex($expire);
        $hash_expire = str_pad($hash_expire, 8, "0", STR_PAD_LEFT);
        for($i=1; $i<5; $i++) {
            $token .= chr(hexdec(substr($hash_expire, -2*$i, 2)));
        }

        $token = hash_hmac("md5", $token, $this->config['app_key']);
        
        return $connect_cid.'_'.$control.'_'.$expire.'_'.$token;
    }
    
    function _a_convert($str) {
        $sum = $t = 0;
        for($i=0; $i<strlen($str); $i++) {
            $c = $str[$i];
            if($c >= 'A' && $c <= 'F') {
                $t = ord($c) - 55;
            } else {
                $t = ord($c) - 48;
            }
            $sum<<=4;
            $sum|=$t;
        }
        return $sum;
    }

    function _decode_connect_did($connect_did) {
        // TODO: 兼容32位系统
        if(!(PHP_INT_SIZE === 8)) 
            return false;
    
        $lcid = '';
        $lhashID = $this->_a_convert($connect_did);
        if($lhashID) {
            $lhashID = dechex($lhashID);
            $lhashID = str_pad($lhashID, 16, "0", STR_PAD_LEFT);
            $lcid .= substr($lhashID, -10, 2);
            $lcid .= substr($lhashID, -6, 2);
            $lcid .= substr($lhashID, -2, 2);
            $lcid .= substr($lhashID, 2, 2);
        }
    
        return hexdec($lcid);
    }
    
    function gen_device_connect_cid($deviceid) {
        if(!$deviceid) 
            return false;
        
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
        );
        
        $ret = $this->_request('/aiermu/getnextcid', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data']['cid'])
            return false;
        
        return $ret['data']['cid'];
    }
    
    function gen_user_connect_cid($uid) {
        if(!$deviceid) 
            return false;
        
        $params = array(
            'app_id' => $this->config['app_id'],
            'app_key' => $this->config['app_key'],
        );
        
        $ret = $this->_request('/aiermu/getnextuid', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data']['uid'])
            return false;
        
        return $ret['data']['uid'];
    }
    
    function _init_string() {
        if(!$this->config['init']) {
            $params = array(
                'app_id' => $this->config['app_id'],
                'app_key' => $this->config['app_key'],
                'cids' => array(1)
            );
        
            $ret = $this->_request('/API/admin/devices/state', $params, 'POST');
            if(!$ret || $ret['http_code'] != 200 || !$ret['data']['init_string'])
                return '';
            
            $this->config['init'] = $ret['data']['init_string'];
            $config = serialize($this->config);
            $this->db->query("UPDATE ".API_DBTABLEPRE."connect SET connect_config='$config' WHERE connect_type='".$this->connect_type."'");
        }
        
        return $this->config['init'];
    } 
    
}
