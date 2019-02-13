<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class lingyangconnect extends connect {

    function __construct(&$base, $_config) {
        $this->lingyangconnect($base, $_config);
    }

    function lingyangconnect(&$base, $_config) {
        parent::__construct($base, $_config);
        $this->base->load('device');
        $this->base->load('user');
    }
    
    function _connect_secret($uid, $length=8) {
        $md5 = md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
        return substr($md5, 0, $length);
    }
    
    function get_config($key) {
        if($this->domain && $this->config['domains'] && $this->config['domains'][$this->domain] 
            && $this->config['domains'][$this->domain][$key]) {
            return $this->config['domains'][$this->domain][$key];
        } else {
             return $this->config[$key];
        }
    }
    
    function set_config($key, $value) {
        if($this->domain && $this->config['domains'] && $this->config['domains'][$this->domain]) {
            $this->config['domains'][$this->domain][$key] = $value;
        } else {
            $this->config[$key] = $value;
        }
        $config = serialize($this->config);
        $this->db->query("UPDATE ".API_DBTABLEPRE."connect SET connect_config='$config' WHERE connect_type='".$this->connect_type."'");
    }
    
    function get_connect_info($uid, $sync=false) {
        if(!$uid)
            return false;
        
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
        $status = 1;
        
        if($connect) {
            if($this->domain) {
                $connect_domain = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect_domain WHERE connect_type='".$this->connect_type."' AND uid='$uid' AND connect_domain='".$this->domain."'");
                if(!$connect_domain) {
                    $connect_cid = $this->gen_user_connect_cid($uid);
                    if(!$connect_cid)
                        return false;
                    
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect_domain SET uid='$uid', connect_type='".$this->connect_type."', connect_cid='$connect_cid', connect_domain='".$this->domain."', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='$status'");
                } else {
                    if($connect_domain['connect_cid']) {
                        $connect_cid = $connect_domain['connect_cid'];
                        $status = $connect_domain['status'];
                    } else {
                        $connect_cid = $this->gen_user_connect_cid($uid);
                        if(!$connect_cid)
                            return false;
                        
                        $this->db->query("UPDATE ".API_DBTABLEPRE."member_connect_domain SET connect_cid='$connect_cid', lastupdate='".$this->base->time."' WHERE uid='$uid' AND connect_type='".$this->connect_type."' AND connect_domain='".$this->domain."'");
                    }
                }
            } else {
                if($connect['connect_cid']) {
                    $connect_cid = $connect['connect_cid'];
                    $status = $connect['status'];
                } else {
                    $connect_cid = $this->gen_user_connect_cid($uid);
                    if(!$connect_cid)
                        return false;
                    
                    $this->db->query("UPDATE ".API_DBTABLEPRE."member_connect SET connect_cid='$connect_cid', lastupdate='".$this->base->time."' WHERE uid='$uid' AND connect_type='".$this->connect_type."'");
                }
            }
        } else {
            $connect_cid = $this->gen_user_connect_cid($uid);
            if(!$connect_cid)
                return false;
            
            if($this->domain) {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect SET uid='$uid', connect_type='".$this->connect_type."', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='$status'");
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect_domain SET uid='$uid', connect_type='".$this->connect_type."', connect_cid='$connect_cid', connect_domain='".$this->domain."', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='$status'");
            } else {
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect SET uid='$uid', connect_type='".$this->connect_type."', connect_cid='$connect_cid', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='$status'");
            }
        }
        
        if($connect_cid) {
            if(API_AUTH_TYPE == 'token') {
                return array(
                    'connect_type' => $this->connect_type,
                    'connect_cid' => $connect_cid,
                    'user_token' => $this->user_token($connect_cid),
                    'init' => $this->_init_string(),
                    'status' => intval($status)
                );
            } else {
                return array(
                    'connect_type' => $this->connect_type,
                    'connect_cid' => $connect_cid,
                    'status' => intval($status)
                );
            }
            
        }
        
        return false;
    }
    
    function device_usercmd($device, $command, $response=0, $config=array(), $request_no='') {
        if(!$device || !$device['deviceid'] || !$command) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return false;
        
        $data = json_decode($command, true);
        if(!$data || !$data['main_cmd'])
            return false;
        $msg = '';
        
        if (!$request_no) {
            $request_no = $response ? $this->_request_no($deviceid, $response, $config) : $this->_d2h(0, 8);
        }
        if (!$request_no)
            return false;
        
        $msg .= $this->_h2b($request_no);
        
        $msg .= chr(intval($data['main_cmd']));
        $msg .= chr(intval($data['sub_cmd']));
        
        $this->base->log('lingyang _request', 'main_cmd='.$data['main_cmd'].', sub_cmd='.$data['sub_cmd']);
        
        $param_len = intval($data['param_len']);
        if($param_len + 12 > 256)
            return false;
        
        $msg .= $this->_d2b($param_len, 2);
        if($param_len > 0) {
            $msg .= $this->_h2b($data['params']);
        }
        
        $params = array(
            'app_id' => $this->get_config('app_id'),
            'app_key' => $this->get_config('app_key'),
            'proxy' => 0,
            'payload' => array(
                'msg' => base64_encode($msg),
                'flag' => 2,
                'clients' => array(intval($connect_cid))
            )
        );

        $ret = $this->_request('/v2/message/server/push', $params, 'POST');
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
        if(!$device || !$device['deviceid'] || !$commands) 
            return false;
        
        $this->base->log('lingyang', 'start batch usercmd, count(commands)='.count($commands));
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
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
            
            $this->base->log('lingyang _request', 'main_cmd='.$data['main_cmd'].', sub_cmd='.$data['sub_cmd']);
        
            $param_len = intval($data['param_len']);
            if($param_len + 12 > 256)
                return false;
        
            $msg .= $this->_d2b($param_len, 2);
            if($param_len > 0) {
                $msg .= $this->_h2b($data['params']);
            }
        
            $params = array(
                'app_id' => $this->get_config('app_id'),
                'app_key' => $this->get_config('app_key'),
                'proxy' => 0,
                'payload' => array(
                    'msg' => base64_encode($msg),
                    'flag' => 2,
                    'clients' => array(intval($connect_cid))
                )
            );
        
            $ret = $this->_request('/v2/message/server/push', $params, 'POST');
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
    
    function _request($url, $params = array(), $httpMethod = 'GET', $json = true) {
        if(!$url)
            return false;
        
        $this->base->log('lingyang _request', 'url='.$url);
        
        $url = $this->get_config('api_prefix').$url;
        
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
            $headers[] = 'X-Client-Token: ' . $params['user_token'];
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
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_USERAGENT       => 'iermu api server/1.0',
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
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
            $curl_opts[CURLOPT_CUSTOMREQUEST] = $httpMethod;
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

        return array('http_code' => $http_code, 'data' => $json ? json_decode($result, true) : $result);
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
    
    function _request_no($deviceid, $response=1, $config=array()) {
        if(!$deviceid)
            return false;
        
        // 锁处理
        $no_lock = API_RDKEYPRE.'lingyang_request_no_'.$deviceid.'_lock';
        
        $retry = 10;
        while($retry > 0) {
            $this->base->log('lingyang request_no', 'lock no_lock='.$no_lock.' retry='.$retry);
            $lock = $this->base->redlock->lock($no_lock, 1000);
            if($lock) break;
            $retry--;
            usleep(200000);
            continue;
        }
        
        if(!$lock) 
            return false;
        
        $this->base->log('lingyang request_no', 'get lock no_lock='.$no_lock.' success');
        
        $no_key = API_RDKEYPRE.'lingyang_request_no_'.$deviceid;
        $no = $this->redis->get($no_key);
        
        $this->base->log('lingyang request_no', 'old no='.$no);
        
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
        
        $this->base->log('lingyang request_no', 'new no='.$no);
        
        // 解除锁
        $this->base->redlock->unlock($lock);
        
        $this->base->log('lingyang request_no', 'unlock no_lock='.$no_lock.' success');
        
        $request_no = $this->_d2h($no, 2);
        
        // TODO: 兼容32位系统
        if(!(PHP_INT_SIZE === 8)) 
            return false;
        
        $deviceid = intval($deviceid) & 0x00ffffffffffffff;
        $request_no .= $this->_d2h($deviceid, 6);
        $notify_key = $this->_notify_key($request_no);
        
        $data = array('status'=>'0', 'data'=>'', 'response'=>$response, 'time'=>time());
        if($config) $data = array_merge($data, $config);
        $this->redis->hMset($notify_key, $data);
        
        return $request_no;
    }
    
    function _notify($request_no) {
        $notify_key = $this->_notify_key($request_no);
        
        do {
            $notify = $this->redis->hGetAll($notify_key);
            if(!$notify) 
                return false;
            
            if(!$notify['status']) {
                if(time() - $notify['time'] > 30) {
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
            
            $this->base->log('tttt msg_notify', 'message request_no='.$request_no);
        
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
        
            $this->redis->hMset($notify_key, array('data' => $datas, 'status' => 1));
        } else if($event == 'device-message') {
            $data = $param['data'];
            if(!$data)
                return false;
            
            $time = $data['created'];
            $msg = $data['msg'];
            
            $msg = base64_decode($msg);
        
            $request_no = substr($msg, 0, 8);
            $request_no = bin2hex($request_no);
            
            $this->base->log('tttt msg_notify', 'device-message request_no='.$request_no);
        
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

            $this->redis->hMset($notify_key, array('data' => $datas, 'status' => 1));
        }
        
        return true;
    }
    
    function device_notify($device, $request_no) {
        $this->base->log('tttt device_notify', 'request_no='.$request_no);
        if(!$request_no)
            return false;
        
        $request_no = strtolower($request_no);
        $notify_key = $this->_notify_key($request_no);
        $this->base->log('tttt device_notify', 'request_key='.$notify_key);
        $notify = $this->redis->hGetAll($notify_key);
        $this->base->log('tttt device_notify', 'notify='.json_encode($notify).', response='.$notify['response']);
        if(!$notify || $notify['response'] != 2) 
            return false;

        $this->redis->hMset($notify_key, array('data' => 1, 'status' => 1));
        
        return $notify;
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
        
        $deviceid = $device['deviceid'];
        
        if($this->domain) {
            $connect_domain = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_connect_domain WHERE connect_type='".$this->connect_type."' AND deviceid='$deviceid' AND connect_domain='".$this->domain."'");
            if(!$connect_domain || !$connect_domain['connect_cid'])
                return false;
            
            return $connect_domain['connect_cid'];
        } else {
            if($device['connect_cid']) 
                return $device['connect_cid'];
        
            if($device['connect_did']) {
                $connect_cid = $this->_decode_connect_did($device['connect_did']);
                if($connect_cid) {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_cid='$connect_cid' WHERE deviceid='".$device['deviceid']."'");
                    return $connect_cid;
                }
            }
        }
        
        return false;
    }
    
    function liveplay($device, $type, $ps) {
        if(!$device || !$device['deviceid']) 
            $this->base->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_SHARE_NOT_EXIST);
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return false;
        
        // 发送startplay命令
        if (!$ps['dvrplay']) {
            $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"01000000"}';
            $this->device_usercmd($device, $command, 0);
        }
        
        // 查询设备状态
        $params = array(
            'app_id' => $this->get_config('app_id'),
            'app_key' => $this->get_config('app_key'),
            'cids' => array(intval($connect_cid))
        );
        
        $ret = $this->_request('/v2/devices/state', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        if($ret['data']['init_string'] && $this->get_config('init') != $ret['data']['init_string']) {
            $this->set_config('init', $ret['data']['init_string']);
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
                $sqladd = "";
                if($data['cover_url']) {
                    $sqladd .= ", connect_thumbnail='".$data['cover_url']."'";
                }
                
                if($type == 'hls') {
                    $result['type'] = 'hls';
                    $result['url'] = $data['hls']?($data['hls'].'?client_token='.$access_token):'';
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
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_online='0'$sqladd WHERE connect_cid='".$data['cid']."' AND connect_type='".$this->connect_type."'");
                    
                    if($device['laststatusupdate'] + 60 < $this->base->time)
                        $device['status'] = 0;
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."device SET connect_online='1'$sqladd WHERE connect_cid='".$data['cid']."' AND connect_type='".$this->connect_type."'");
                    
                    if($device['status'] == 0 && $device['laststatusupdate'] + 60 < $this->base->time) {
                        $device['status'] = 5;
                    }
                }
                
                $result['status'] = intval($device['status']);
            }
        }
        
        return $result;
    }

    function multi_liveplay($uid, $devicelist) {
        if (!$uid || !$devicelist)
            return false;

        $liveplay_list = array();
        foreach ($devicelist as $device) {
            $liveplay = $this->liveplay($device, null, array('auth_type' => 'token'));
            if (!$liveplay)
                return false;
            $liveplay_list[] = $liveplay;
        }

        return $liveplay_list;
    }
    
    function createshare($device, $share_type, $expires=0) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        if($this->_device_public($deviceid))
            return true;
        
        /*
        $cvr = $this->_device_record($deviceid);
        $public = 1;

        $stream_param = '00';
        if ($cvr && $public)
            $stream_param = '04';
        else if ($cvr)
            $stream_param = '03';
        else if ($public)
            $stream_param = '02';

        $params = 'ffffffff'.$stream_param.'ffffff';

        $command = '{"main_cmd":75,"sub_cmd":78,"param_len":8,"params":"' . $params . '"}';
        
        $online = $this->_device_online($device);
        $response = $online?2:0;
        $config = array('cvr'=>$cvr?1:0, 'share'=>$public?1:0);
        
        $this->base->log('lingyang createshare', 'deviceid='.$deviceid.', share_type='.$share_type.', online='.$online.', response='.$response.', config='.json_encode($config));

        if (!$this->device_usercmd($device, $command, $response, $config))
            return false;
        */
        
        $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"03000000"}';
        if (!$this->device_usercmd($device, $command, 0))
            return false;
        
        $ret = array(
            'shareid' => '',
            'uk' => $uid,
            'status' => 1
        );
        
        return $ret;
    }
    
    function cancelshare($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        if(!$this->_device_public($deviceid))
            return true;
        
        /*
        $cvr = $this->_device_record($deviceid);
        $public = 0;

        $stream_param = '00';
        if ($cvr && $public)
            $stream_param = '04';
        else if ($cvr)
            $stream_param = '03';
        else if ($public)
            $stream_param = '02';

        $params = 'ffffffff'.$stream_param.'ffffff';

        $command = '{"main_cmd":75,"sub_cmd":78,"param_len":8,"params":"' . $params . '"}';
        
        $online = $this->_device_online($device);
        $response = $online?2:0;
        $config = array('cvr'=>$cvr?1:0, 'share'=>$public?1:0);

        if (!$this->device_usercmd($device, $command, $response, $config))
            return false;
        */
        
        $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"03000000"}';
        if (!$this->device_usercmd($device, $command, 0))
            return false;
        
        return true;
    }
    
    function _connect_list() {
        return true;
    }
    
    function listdevice($uid) {
        if(!$uid) 
            return false;

        //企业用户判断
        $this->base->load('org');
        $isorg = $_ENV['org']->getorgidbyuid($uid);
        if($isorg){
            if($isorg['admin'] == 1){
                //管理员
                $org_id = $isorg['org_id'];
                $query = $this->db->fetch_all("SELECT a.* FROM ".API_DBTABLEPRE."device a LEFT JOIN ".API_DBTABLEPRE."device_org b on b.deviceid=a.deviceid WHERE b.org_id='$org_id' AND a.connect_type='".$this->connect_type."'");
            }else{
                //子用户
                $query = $this->db->fetch_all("SELECT a.* FROM ".API_DBTABLEPRE."device a LEFT JOIN ".API_DBTABLEPRE."device_grant b on b.deviceid=a.deviceid WHERE b.uk='$uid' AND b.auth_type=1 AND a.connect_type='".$this->connect_type."'");
            }
        }else{
            $query = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."device WHERE uid='$uid' AND connect_type='".$this->connect_type."'");
        }

        // 每次最多同步100个设备
        $total = count($query);
        $start = 0;

        while($total>0) {
            $cids = array();

            $num = 100;
            if($total < $num) $num = $total;

            for($i=0; $i<$num; $i++) {
                $device = $query[$start + $i];
                $connect_cid = $this->_device_connect_cid($device);
                if($connect_cid) $cids[] = intval($connect_cid);
            }

            $start += $num;
            $total -= $num;
            
            if(!$cids)
                continue;
            
            $params = array(
                'app_id' => $this->get_config('app_id'),
                'app_key' => $this->get_config('app_key'),
                'cids' => $cids
            );
        
            $ret = $this->_request('/v2/devices/state', $params, 'POST');
            if(!$ret || $ret['http_code'] != 200 || !$ret['data']['init_string'])
                continue;
            
            if($ret['data']['init_string'] && $this->get_config('init') != $ret['data']['init_string']) {
                $this->set_config('init', $ret['data']['init_string']);
            }
            
            if($ret['data']['devices']) {
                foreach($ret['data']['devices'] as $data) {
                    if($data['state'] == 0) {
                        $connect_online = 0;
                    } else {
                        $connect_online = 1;
                    }
                    $connect_thumbnail = $data['cover_url'];
                    
                    $_ENV['device']->update_device_connect_by_cid($this->connect_type, $data['cid'], $connect_online, $connect_thumbnail);
                    
                    foreach($cids as $k=>$v) {
                        if($v == $data['cid']) {
                            unset($cids[$k]);
                        }
                    }
                }
            }
            
            if($cids) {
                foreach($cids as $cid) {
                    $_ENV['device']->update_device_connect_by_cid($this->connect_type, $cid, 0);
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
    
    function playlist($device, $ps, $starttime, $endtime, $type) {
        if(!$device || !$device['deviceid'] || !$starttime || !$endtime)
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        $access_token = $this->device_accesstoken($connect_cid);
        if(!$connect_cid || !$access_token)
            return false;
        
        $params = array(
            'user_token' => $access_token,
            'start' => $starttime,
            'end' => $endtime
        );

        $ret = $this->_request('/v2/record/'.$connect_cid.'/timeline', $params, 'GET');
        if(!$ret || $ret['http_code'] != 200 || !$ret['data'])
            return $this->_error($ret);
        
        $result = array();
        $result['stream_id'] = $device['stream_id'];
        $result['connect_type'] = $device['connect_type'];
        
        $eventinfo = array();
        if(!isset($ret['data']['eventinfo'])) {
            $eparams = array(
                'user_token' => $access_token,
                'begin' => $starttime,
                'end' => $endtime
            );

            $eret = $this->_request('/v2/record/'.$connect_cid.'/eventinfo', $params, 'GET');
            if(!$eret || $eret['http_code'] != 200 || !$eret['data'])
                return $this->_error($eret);
            
            if($eret['data']['eventinfo']) {
                $ret['data']['eventinfo'] = $eret['data']['eventinfo'];
            }
        }

        if ($type == 'hls') {
            $list = array();
            foreach ($ret['data']['videos'] as $item) {
                $list[] = array(
                    intval($item['from']),
                    intval($item['to']),
                    0
                );
            }
            $result['results'] = $list;
        } else {
            $expires_in = 3600*24;
            $access_token = $this->device_accesstoken($connect_cid, $expires_in);
            $result['results'] = array(
                'access_token' => $access_token,
                'expires_in' => $expires_in,
                'servers' => $ret['data']['servers'],
                'videos' => $ret['data']['videos']
            );
            
            if($ret['data']['events']) {
                $result['results']['events'] = $ret['data']['events'];
            }
            
            if($ret['data']['eventinfo']) {
                $result['results']['eventinfo'] = $ret['data']['eventinfo'];
            }
        }
        
        return $result;
    }

    function vod($device, $ps, $starttime, $endtime) {
        if (!$device['deviceid'] || !$device['uid'] || !$starttime || !$endtime)
            return false;

        $uid = $device['uid'];
        $deviceid = $device['deviceid'];

        $connect_cid = $this->_device_connect_cid($device);
        $access_token = $this->device_accesstoken($connect_cid);
        if(!$connect_cid || !$access_token)
            return false;

        $params = array(
            'user_token' => $access_token
        );
        
        if($ps['type'] == 'event') {
            $ret = $this->_request('/v2/record/'.$connect_cid.'/events/hls/'.$starttime.'_'.$endtime.'.m3u8', $params, 'GET', false);
        } else {
            $ret = $this->_request('/v2/record/'.$connect_cid.'/storage/hls/'.$starttime.'_'.$endtime.'.m3u8', $params, 'GET', false);
        }
        
        if(!$ret || $ret['http_code'] != 200)
            return $this->_error($ret);

        return $ret['data'];
    }

    function vodlist($device, $starttime, $endtime) {
        if (!$device['deviceid'] || !$device['uid'] || !$starttime || !$endtime)
            return false;

        $uid = $device['uid'];
        $deviceid = $device['deviceid'];

        $connect_cid = $this->_device_connect_cid($device);
        $access_token = $this->device_accesstoken($connect_cid);
        if(!$connect_cid || !$access_token)
            return false;

        $params = array(
            'user_token' => $access_token
        );

        $ret = $this->_request('/v2/record/'.$connect_cid.'/storage/hls/'.$starttime.'_'.$endtime.'.m3u8', $params, 'GET', false);
        if(!$ret || $ret['http_code'] != 200)
            return false;

        $content = $ret['data'];
        preg_match_all('/(http.+)[\.]ts(.+)/', $content, $temp);
        $num = count(array_shift($temp));

        $result = array();
        for($j = 0; $j < $num; ++$j) {
            if(isset($temp[0][$j])) {
                // $result[] = $temp[0][$j].'&rt=sh&owner='.$this->config['appid'].'&zhi='.$temp[1][$j].'&range='.$temp[2][$j].'-'.$temp[3][$j].'&response_status=206';
                $result[] = $temp[0][$j].'.ts'.$temp[1][$j];
            }
        }
        return $result;
    }

    function vodseek($device, $ps, $time) {
        if (!$device['uid'] || !$device['deviceid'] || !$time)
            return false;

        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $starttime = $time;
        $endtime = $time + 30;

        $connect_cid = $this->_device_connect_cid($device);
        $access_token = $this->device_accesstoken($connect_cid);
        if(!$connect_cid || !$access_token)
            return false;

        $params = array(
            'user_token' => $access_token,
        );
        
        if($ps['type'] == 'event') {
            $ret = $this->_request('/v2/record/'.$connect_cid.'/events/hls/'.$starttime.'_'.$endtime.'.m3u8', $params, 'GET', false);
        } else {
            $ret = $this->_request('/v2/record/'.$connect_cid.'/storage/hls/'.$starttime.'_'.$endtime.'.m3u8', $params, 'GET', false);
        }
        
        if(!$ret || $ret['http_code'] != 200)
            return false;

        preg_match('/http:.+\/v2\/record\/\d+\/storage\/hls\/(\d+)_(\d+)\.ts\?client_token=\d+_\d+_\d+_\w+/', $ret['data'], $firstrecord);
        $result = array(
            't' => intval($time),
            'start_time' => intval($firstrecord[1]),
            'end_time' => intval($firstrecord[2]),
            'deviceid' => $deviceid
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
        
        $connect_cid = $this->_device_connect_cid($device);
        $access_token = $this->device_accesstoken($connect_cid);
        if(!$connect_cid || !$access_token)
            return false;
        
        if($latest) {
            $starttime = $this->base->time - 3600;
            $endtime = $this->base->time;
        }
        
        // $params = array(
        //     'access_token' => $access_token,
        //     'from' => $starttime,
        //     'to' => $endtime
        // );
        // $ret = $this->_request('/API/cameras/'.$connect_cid.'/snapshots', $params, 'GET');
        $params = array(
            'client_token' => $access_token,
            'from' => $starttime,
            'to' => $endtime
        );
        
        $ret = $this->_request('/v2/snapshots/'.$connect_cid.'/timestamps', $params, 'GET');
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

        if($latest && !$list) {
            $list[] = array(
                'time' => $this->base->time, 
                'url' => $this->get_config('api_prefix').'/v2/snapshots/'.$connect_cid.'/cover?client_token='.$access_token
            );
        }
        
        if(!$list) 
            return false;
        
        $result = array();
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    
    function need_cvr_after_save() {
        return true;
    }
    
    function set_cvr($device, $cvr) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        /*
        $cvr = $cvr?1:0;
        $public = $this->_device_public($deviceid);
        
        
        $stream_param = '00';
        if ($cvr && $public)
            $stream_param = '04';
        else if ($cvr)
            $stream_param = '03';
        else if ($public)
            $stream_param = '02';

        $params = 'ffffffff'.$stream_param.'ffffff';

        $command = '{"main_cmd":75,"sub_cmd":78,"param_len":8,"params":"' . $params . '"}';
        
        $online = $this->_device_online($device);
        $response = $online?2:0;
        $config = array('cvr'=>$cvr?1:0, 'share'=>$public?1:0);

        if (!$this->device_usercmd($device, $command, $response, $config))
            return false;
        */
        
        $command = '{"main_cmd":75,"sub_cmd":80,"param_len":4,"params":"03000000"}';
        if (!$this->device_usercmd($device, $command, 0))
            return false;
        
        return true;
    }
    
    function _device_public($deviceid) {
        $public = $this->db->result_first("SELECT deviceid FROM ".API_DBTABLEPRE."device_share WHERE deviceid='$deviceid'");
        return $public?true:false;
    }
    
    function _device_record($deviceid) {
        $record = $this->db->result_first("SELECT cvr FROM ".API_DBTABLEPRE."devicefileds WHERE deviceid='$deviceid'");
        if ($record === false)
            return true;
        return $record?true:false;
    }
    
    function _set_config() {
        return true;
    }

    function connect_to_device($device) {
        return false;
    }
    
    function device_register($uid, $deviceid, $desc) {
        if(!$uid || !$deviceid || !$desc) 
            return false;
        
        $connect_cid = '';
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if($device) {
            $connect_cid = $this->_device_connect_cid($device);
            
            // 切换用户
            if($device['uid']) {
                $connect = $_ENV['user']->get_user_by_uid($device['uid']);
                if($connect && $connect['username']) {
                    $extras['username'] = $connect['username'];
                } 
                if($device['uid'] == $this->base->uid) {
                    $extras['isowner'] = 1;
                    $extras['uid'] = $device['uid'];
                    $extras['connect_type'] = $device['connect_type'];
                    $extras['connect_cid'] = $connect_cid;
                    $extras['stream_id'] = $device['stream_id'];
                } else {
                    $extras['isowner'] = 0;
                }
                $this->base->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_ALREADY_REG, NULL, NULL, NULL, $extras);
            }
        }
        
        if(!$connect_cid) $connect_cid = $this->gen_device_connect_cid($deviceid);
        if(!$connect_cid) return false;
        
        $result = array(
            'connect_cid' => $connect_cid
        );
        return $result;
    }
    
    function device_drop($device) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return false;
        
        $params = array(
            'app_id' => $this->get_config('app_id'),
            'app_key' => $this->get_config('app_key')
        );
    
        $ret = $this->_request('/v2/devices/'.$connect_cid.'/storage', $params, "DELETE");
        if ($ret) {
            if ($ret['http_code'] == 404)
                return true;

            if ($ret['http_code'] == 200) {
                $stream_param = '00';
                $params = '00ffffff'.$stream_param.'ffffff';

                $command = '{"main_cmd":75,"sub_cmd":78,"param_len":8,"params":"' . $params . '"}';

                if (!$this->device_usercmd($device, $command, 0))
                    return false;
                
                return true;
            }
        }

        return false;
    }

    function device_register_push($device, $data) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $partner_id = $data['partner_id'];
        $pushid = $data['pushid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        $access_token = $this->device_accesstoken($connect_cid);
        if(!$connect_cid || !$access_token)
            return false;
        
        $params = array(
            'action' => 'bind',
            'sn' => $deviceid
        );
    
        $ret = $this->_request('/cloudeye/v1/devices/'.$connect_cid.'/bindmapping?client_token='.$access_token, $params, "POST");
        if ($ret && $ret['http_code'] == 200) {
            return true;
        }

        return false;
    }
    
    function device_drop_push($device, $data) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $deviceid = $device['deviceid'];
        $partner_id = $data['partner_id'];
        $pushid = $data['pushid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        $access_token = $this->device_accesstoken($connect_cid);
        if(!$connect_cid || !$access_token)
            return false;
        
        $params = array(
            'action' => 'unbind',
            'sn' => $deviceid
        );

        $ret = $this->_request('/cloudeye/v1/devices/'.$connect_cid.'/bindmapping?client_token='.$access_token, $params, "POST");
        if ($ret && $ret['http_code'] == 200) {
            return true;
        }

        return false;
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
        
        $deviceid = $device['deviceid'];
        $connect_type = $device['connect_type'];
        
        $info = array();
        
        // 初始化设备
        if(!$device['isinit']) {
            // 20170901之后不再赠送
            // 河北沧州渠道除外
            if($this->base->time <= 1504195200 || $_ENV['device']->check_partner_device('zfT67AHcDdvXZ3By8MGt', $deviceid)) {
                // 20170405 赠送90天事件云存储
                // 永久免费列表不赠送
                $scheck = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_status WHERE `deviceid`='$deviceid' AND connect_type='$connect_type'");
                if(!$scheck) {
                    // 20170405 之前上线设备加入免费列表
                    if($device['dateline'] < 1491321600) {
                        $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_cvr_status SET deviceid='$deviceid', connect_type='$connect_type', cvr_type='1', cvr_day='7', dateline='".$this->base->time."'");
                    } else {
                        $check = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."device_cvr_record WHERE `deviceid`='$deviceid' AND connect_type='$connect_type' AND  type='2'");
                        if(!$check) {
                            $cvr_start_time = $this->base->time > 1491321600 ? $this->base->time : 1491321600;
                            $cvr_end_time = $cvr_start_time + 90 * 24 * 3600;
                            $this->db->query("INSERT INTO ".API_DBTABLEPRE."device_cvr_record SET deviceid='$deviceid', type='2', connect_type='$connect_type', cvr_type='1', cvr_day='7', cvr_start_time='$cvr_start_time', cvr_end_time='$cvr_end_time', dateline='".$this->base->time."'");
                        }
                    }
                }
            }
            
            $this->db->query("UPDATE ".API_DBTABLEPRE."device SET isinit='1' WHERE deviceid='$deviceid'");
        }
        
        $_ENV['device']->update_cvr_info($device, true);
        
        return true;
    }
    
    function _connect_meta() {
        return true;
    }
    
    function device_meta($device, $ps) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return false;
        
        $params = array(
            'app_id' => $this->get_config('app_id'),
            'app_key' => $this->get_config('app_key'),
            'cids' => array(intval($connect_cid))
        );
    
        $ret = $this->_request('/v2/devices/state', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        if($ret['data']['init_string'] && $this->get_config('init') != $ret['data']['init_string']) {
            $this->set_config('init', $ret['data']['init_string']);
        }
        
        if($ret['data']['devices'] && $ret['data']['devices'][0]) {
            $data = $ret['data']['devices'][0];
            if($data['cid'] == $connect_cid) {
                $connect_online = ($data['state'] == 0)?0:1;
                $connect_thumbnail = $data['cover_url'];
                $_ENV['device']->update_device_connect_by_cid($this->connect_type, $connect_cid, $connect_online, $connect_thumbnail);
            }
        }
        
        return true;
    }
    
    function device_batch_meta($devices, $ps) {
        if(!$devices) 
            return false;
        
        $cids = array();
        foreach($devices as $device) {
            $connect_cid = $this->_device_connect_cid($device);
            if($connect_cid) {
                $cids[] = intval($connect_cid);
            }
        }
        
        if(!$cids)
            return false;
        
        $params = array(
            'app_id' => $this->get_config('app_id'),
            'app_key' => $this->get_config('app_key'),
            'cids' => $cids
        );
    
        $ret = $this->_request('/v2/devices/state', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        if($ret['data']['init_string'] && $this->get_config('init') != $ret['data']['init_string']) {
            $this->set_config('init', $ret['data']['init_string']);
        }
        
        if($ret['data']['devices']) {
            foreach($ret['data']['devices'] as $data) {
                $connect_cid = $data['cid'];
                $connect_online = ($data['state'] == 0)?0:1;
                $connect_thumbnail = $data['cover_url'];
                $_ENV['device']->update_device_connect_by_cid($this->connect_type, $connect_cid, $connect_online, $connect_thumbnail);
            }
        }
        
        return true;
    }
    
    function device_uploadtoken($device, $request_no) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $deviceid = $device['deviceid'];
        
        // cid
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return array('error' => -1);
        
        $data = array();
        if($request_no) {
            $data = $this->device_notify($device, $request_no);
            $this->base->log('tttt device_notify', 'data='.json_encode($data));
        }
        
        if($data && isset($data['cvr']) && isset($data['share'])) {
            $cvr = $data['cvr'];
            $share = $data['share'];
        } else {
            $cvr = $this->_device_record($deviceid);
            $share = $_ENV['device']->get_share_by_deviceid($deviceid);
        }

        // 录像保存日期
        if($device['cvr_type'] > 0 && ($device['cvr_free'] || $device['cvr_end_time'] > $this->base->time) && $device['cvr_day'] > 7) {
            $cvr_day = $device['cvr_day'];
        } else {
            $cvr_day = 7;
        }
        
        // uploadtoken
        $upload_token = $this->_uploadtoken($connect_cid, array('cvr_type'=>$device['cvr_type'], 'cvr_day'=>$cvr_day));
        if(!$upload_token)
            return false;
        
        $fileds = $_ENV['device']->_check_fileds($deviceid);
        
        if($fileds['bitrate'] < 500) {
            $bitlevel = 3;
        } else if($fileds['bitrate'] < 1024) {
            $bitlevel = 2;
        } else {
            $bitlevel = 1;
        }
        
        $audio = intval($fileds['audio']);
        
        $cvr_alarm = $_ENV['device']->gen_cvr_alarm($device, false);
        $logo = $_ENV['device']->get_logo($device);
        
        $init = $this->_init_string($device);
        
        $config = array(
            //'init' => $this->_init_string(),
            'init' => $init,
            'bitlevel' => $bitlevel,
            'audio' => $audio,
            'share' => ($share && ($share['share_type'] == 1 || $share['share_type'] == 3))?1:0,
            'cvr' => $cvr?1:0,
            'cvr_type' => intval($device['cvr_type']),
            'cvr_alarm' => $cvr_alarm?1:0,
            'logo' => $logo
        );
        
        if($this->get_config('api_prefix') != 'http://api.topvdn.com') {
            $config['api_url'] = $this->get_config('api_prefix');
        }
        
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

        // partner
        $partner_config = array();
        $partner = $this->db->fetch_first("SELECT p.* FROM ".API_DBTABLEPRE."device_partner d LEFT JOIN ".API_DBTABLEPRE."partner p ON d.partner_id=p.partner_id
        WHERE d.deviceid='$deviceid'");
        if($partner && $partner['config']) {
            $partner_config = json_decode($partner['config'], true);
        }

        // alarm
        if($fileds['alarm_count'] && $fileds['alarm_interval']) {
            $config['alarm_interval'] = intval($fileds['alarm_interval'])?intval($fileds['alarm_interval']):30;
            $config['alarm_count'] = intval($fileds['alarm_count'])?intval($fileds['alarm_count']):1;
        } else {
            // partner
            if($partner_config) {
                if($partner_config['alarm_interval']) {
                    $config['alarm_interval'] = intval($partner_config['alarm_interval']);
                }
                if($partner_config['alarm_count']) {
                    $config['alarm_count'] = intval($partner_config['alarm_count']);
                }
            }
        }

        // ai
        if($partner_config && $partner_config['ai']) {
            if(false && $fileds['ai'] > 0 && $fileds['ai_api_key'] != '-2') {
                $ai = array();
                if($fileds['ai_lan'] > 0) $ai['ai_lan'] = 1;
                if($fileds['ai_face_type'] > 0) $ai['ai_face_type'] = intval($fileds['ai_face_type']);
                if($fileds['ai_upload_host'] != '') $ai['ai_upload_host'] = strval($fileds['ai_upload_host']);
                if($fileds['ai_upload_port'] > 0) $ai['ai_upload_port'] = intval($fileds['ai_upload_port']);
                if($fileds['ai_face_host'] != '') $ai['ai_face_host'] = strval($fileds['ai_face_host']);
                if($fileds['ai_face_port'] > 0) $ai['ai_face_port'] = intval($fileds['ai_face_port']);
                if($fileds['ai_api_key'] != '') $ai['ai_api_key'] = strval($fileds['ai_api_key']);
                if($fileds['ai_secret_key'] != '') $ai['ai_secret_key'] = strval($fileds['ai_secret_key']);
                if($fileds['ai_face_ori'] > 0) $ai['ai_face_ori'] = intval($fileds['ai_face_ori']);
                if($fileds['ai_face_pps'] > 0) $ai['ai_face_pps'] = intval($fileds['ai_face_pps']);
                if($fileds['ai_face_position'] > 0) $ai['ai_face_position'] = intval($fileds['ai_face_position']);
                if($fileds['ai_face_frame'] > 0) $ai['ai_face_frame'] = intval($fileds['ai_face_frame']);
                if($fileds['ai_face_min_width'] > 0) $ai['ai_face_min_width'] = intval($fileds['ai_face_min_width']);
                if($fileds['ai_face_min_height'] > 0) $ai['ai_face_min_height'] = intval($fileds['ai_face_min_height']);
                if($fileds['ai_face_reliability'] > 0) $ai['ai_face_reliability'] = intval($fileds['ai_face_reliability']);
                if($fileds['ai_face_retention'] > 0) $ai['ai_face_retention'] = intval($fileds['ai_face_retention']);
                if($fileds['ai_face_group_id'] != '') $ai['ai_face_group_id'] = strval($fileds['ai_face_group_id']);
                if($fileds['ai_face_group_type'] > 0) $ai['ai_face_group_type'] = intval($fileds['ai_face_group_type']);
                if($ai) $config['ai'] = $ai;
            } else {
                if($partner_config && $partner_config['ai']) {
                    $config['ai'] = $partner_config['ai'];
                }
            }
        }

        if($partner_config) {
            if($partner_config['alarm_image_max_size']) {
                $config['alarm_image_max_size'] = intval($partner_config['alarm_image_max_size']);
            }
            if($partner_config['osd']) {
                $config['osd'] = 1;
            }
        }
        
        $result = array(
            'deviceid' => $deviceid,
            'connect_type' => $device['connect_type'],
            //'connect_cid' => $connect_cid,
            'connect_cid' => $device['uid'],
            'upload_token' => $upload_token,
            'config' => $config,
            'servertime' => round(microtime(true)*1000)
        );
        return $result;
    }
    
    function device_config($device, $config, $request_no) {
        if(!$device || !$device['deviceid']) 
            return false;
        
        $deviceid = $device['deviceid'];
        
        // cid
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return false;
        
        $data = array();
        if($request_no) {
            $data = $this->device_notify($device, $request_no);
            $this->base->log('tttt device_notify', 'data='.json_encode($data));
            
            if($data && isset($data['cvr']) && isset($data['share'])) {
                $config['cvr'] = intval($data['cvr']);
                $config['share'] = intval($data['share']);
            }
        }

        // 录像保存日期
        if($device['cvr_type'] > 0 && ($device['cvr_free'] || $device['cvr_end_time'] > $this->base->time) && $device['cvr_day'] > 7) {
            $cvr_day = $device['cvr_day'];
        } else {
            $cvr_day = 7;
        }
        
        // uploadtoken
        $upload_token = $this->_uploadtoken($connect_cid, array('cvr_type'=>$device['cvr_type'], 'cvr_day'=>$cvr_day));
        if(!$upload_token)
            return false;
        
        $config['upload_token'] = $upload_token;
        
        $config['init'] = $this->_init_string($device);
        
        if($this->get_config('api_prefix') != 'http://api.topvdn.com') {
            $config['api_url'] = $this->get_config('api_prefix');
        }
        
        return $config;
    }
    
    function _uploadtoken($connect_cid, $set, $expires_in=0) {
        if(!$connect_cid)
            return '';
        
        // cvr
        $cvr_type = isset($set['cvr_type'])?$set['cvr_type']:0;
        $cvr_day = isset($set['cvr_day'])?$set['cvr_day']:0;
        
        // control
        $isrtmp = true;
        $ishls = true;
        $ischeckip = false;
        $ischeckrefer = false;
        $isudpstandby = true;
        $flv_save = false;
        $hls_save = true;
        
        // 循环存储标记
        if($cvr_type == 2) {
            $flv_save = true;
            $hls_save = false;
        }
        
        $control = '';
        $control .= $isrtmp?'1':'0';
        $control .= $ishls?'1':'0';
        $control .= $ischeckip?'1':'0';
        $control .= $ischeckrefer?'1':'0';
        $control .= $isudpstandby?'1':'0';
        $control = str_pad($control, 8, "0", STR_PAD_RIGHT);
        if($cvr_day == 7) {
            $control .= '0001';
        } else if($cvr_day == 30) {
            $control .= '0010';
        } else if($cvr_day == 90) {
            $control .= '0011';
        } else {
            $control .= '0000';
        }
        $control .= $flv_save?'1':'0';
        $control .= $hls_save?'1':'0';
        $control = str_pad($control, 32, "0", STR_PAD_RIGHT);
        $control = bindec($control);
        
        // expire
        if(!$expires_in) $expires_in = 3600*24*30;
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

        $token = hash_hmac("md5", $token, $this->get_config('app_key'));
        
        $upload_token = $connect_cid.'_'.$control.'_'.$expire.'_'.$token;
        return $upload_token;
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

        $token = hash_hmac("md5", $token, $this->get_config('app_key'));
        
        return $connect_cid.'_'.$control.'_'.$expire.'_'.$token;
    }
    
    function device_clienttoken($connect_cid, $expires_in=0) {
        if(!$connect_cid) 
            return '';
        
        $control = 0;
        
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

        $token = hash_hmac("md5", $token, $this->get_config('app_key'));
        
        return $connect_cid.'_'.$control.'_'.$expire.'_'.$token;
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

        $token = hash_hmac("md5", $token, $this->get_config('app_key'));
        
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
        
        // 锁处理
        $no_lock = API_RDKEYPRE.'lingyang_device_cid_lock';
        
        $retry = 10;
        while($retry > 0) {
            $this->base->log('lingyang device_cid', 'lock no_lock='.$no_lock.' retry='.$retry);
            $lock = $this->base->redlock->lock($no_lock, 1000);
            if($lock) break;
            $retry--;
            usleep(200000);
            continue;
        }
        
        if(!$lock) 
            return false;

        $cid = '';

        $appid = $this->base->appid;
        $group = 0;

        // partner
        $partner = $this->db->fetch_first("SELECT p.* FROM ".API_DBTABLEPRE."device_partner d LEFT JOIN ".API_DBTABLEPRE."partner p ON d.partner_id=p.partner_id WHERE d.deviceid='$deviceid'");
        if($partner && $partner['config']) {
            $config = json_decode($partner['config'], true);
            if($config && $config['device_connect_cid_group']) {
                $group = $config['device_connect_cid_group'];
            }
        }

        //根据可用数量最小的排序 查找cid段
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."connect_cid_segment WHERE `available`>0 AND `type`=0 AND connect_type='".$this->connect_type."' AND `index`>0 AND `group`='$group' AND appid='$appid' ORDER BY `available` ASC");
        if(!$arr){
            $this->base->redlock->unlock($lock);
            return false;
        }

        $sid = $arr['sid'];

        //首先在回收表中查找数据
        $ret = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."connect_cid_record WHERE `status` = '0' AND `type` = 0 AND sid='$sid' ORDER BY `dateline` ASC");
        if($ret){
            $action = 1;
            $cid = $ret['connect_cid'];
            $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_record SET `status`='1', `usedate`='".$this->base->time."' WHERE rid='".$ret['rid']."'");
            $available = $arr['available'] - 1;
            $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_segment SET available='$available', lastupdate='".$this->base->time."' WHERE sid='$sid'");
        }else{
            if($arr['index'] && $arr['index']>=$arr['start'] && $arr['index']<=$arr['end']){
                if($arr['end'] == $arr['index']){
                    $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_segment SET `index`='0', available='0', lastupdate='".$this->base->time."' WHERE sid='$sid'");
                }else{
                    $index = $arr['index'] + 1;
                    $available = $arr['available'] - 1;
                    $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_segment SET `index`='$index',  available='$available', lastupdate='".$this->base->time."' WHERE sid='$sid'");
                }
                $action = 0;
                $cid = $arr['index'];
            }
        }

        $this->base->log('lingyang_device_cid', 'distribute cid, deviceid='.$deviceid.' cid='.$cid);
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."connect_cid_log SET id='$deviceid', connect_cid='".$cid."', dateline='".$this->base->time."', sid='".$sid."', connect_type ='".$this->connect_type."', action = '$action', `type` = 0, appid='$appid'");
        // 解除锁
        $this->base->redlock->unlock($lock);
        return $cid;
    }
    
    function gen_user_connect_cid($uid) {
        if(!$uid) 
            return false;

        // 锁处理
        $no_lock = API_RDKEYPRE.'lingyang_user_cid_lock';
        
        $retry = 10;
        while($retry > 0) {
            $this->base->log('lingyang user_cid', 'lock no_lock='.$no_lock.' retry='.$retry);
            $lock = $this->base->redlock->lock($no_lock, 1000);
            if($lock) break;
            $retry--;
            usleep(200000);
            continue;
        }
        
        if(!$lock) 
            return false;

        $cid = '';

        $appid = $this->base->appid;

        //根据可用数量最小的排序 查找cid段
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."connect_cid_segment WHERE `available`>0 AND `type`=1 AND connect_type='".$this->connect_type."' AND `index`>0 AND appid='$appid' ORDER BY `available` ASC");
        if(!$arr){
            $this->base->redlock->unlock($lock);
            return false;
        }
        $sid = $arr['sid'];

        //首先在回收表中查找数据
        $ret = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."connect_cid_record WHERE `status` = '0' AND `type` = 1 AND sid='$sid' ORDER BY `dateline` ASC");
        if($ret){
            $action = 1;
            $cid = $ret['connect_cid'];
            $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_record SET `status`='1', usedate='".$this->base->time."' WHERE rid='".$ret['rid']."'");
            $available = $arr['available'] - 1;
            $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_segment SET available='$available', lastupdate='".$this->base->time."' WHERE sid='$sid'");
        }else{
            if($arr['index'] && $arr['index']>=$arr['start'] && $arr['index']<=$arr['end']){
                if($arr['end'] == $arr['index']){
                    $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_segment SET `index`='0', available='0', lastupdate='".$this->base->time."' WHERE sid='$sid'");
                }else{
                    $index = $arr['index'] + 1;
                    $available = $arr['available'] - 1;
                    $this->db->query("UPDATE ".API_DBTABLEPRE."connect_cid_segment SET `index`='$index',  available='$available', lastupdate='".$this->base->time."' WHERE sid='$sid'");
                }
                $action = 0;
                $cid = $arr['index'];
            }
        }

        $this->base->log('lingyang_user_cid', 'distribute cid, uid='.$uid.' cid='.$cid);
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."connect_cid_log SET id='$uid', connect_cid='".$cid."', dateline='".$this->base->time."', sid='".$sid."', connect_type ='".$this->connect_type."', action = '$action', `type` = 1, appid='$appid'");
        // 解除锁
        $this->base->redlock->unlock($lock);
        return $cid;
    }
    
    function _init_string($device=array()) {
        // device
        if($device && $device['deviceid']) {
            $deviceid = $device['deviceid'];

            // dubug
            /*
            if(in_array($deviceid, array('137894312011', '137894797371', '137894012699', '137894459499', '137894804667', '137894126747', '137894607323', '137894135483'))) {
                return "[Config]
IsDebug=0
LocalBasePort=8200
IsCaptureDev=1
IsPlayDev=1
UdpSendInterval=2
[Tracker]
Count=3
IP1=42.51.7.44
Port1=80
IP2=122.226.181.30
Port2=80
IP3=61.55.189.131
Port3=80
[LogServer]
Count=1
IP1=42.51.12.137
Port1=80
";
            }
            */
        }

        if(!$this->get_config('init')) {
            $params = array(
                'app_id' => $this->get_config('app_id'),
                'app_key' => $this->get_config('app_key'),
                'cids' => array(1)
            );
        
            $ret = $this->_request('/v2/devices/state', $params, 'POST');
            if(!$ret || $ret['http_code'] != 200 || !$ret['data']['init_string'])
                return '';
            
            $this->set_config('init', $ret['data']['init_string']);
        }
        
        return $this->get_config('init');
    }
    
    function _device_online($device) {
        if(!$device || !$device['deviceid']) 
            return false; 
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        
        $connect_cid = $this->_device_connect_cid($device);
        if(!$connect_cid)
            return false;
        
        $params = array(
            'app_id' => $this->get_config('app_id'),
            'app_key' => $this->get_config('app_key'),
            'cids' => array(intval($connect_cid))
        );
    
        $ret = $this->_request('/v2/devices/state', $params, 'POST');
        if(!$ret || $ret['http_code'] != 200)
            return false;
        
        if($ret['data']['init_string'] && $this->get_config('init') != $ret['data']['init_string']) {
            $this->set_config('init', $ret['data']['init_string']);
        }
        
        if($ret['data']['devices'] && $ret['data']['devices'][0]) {
            $data = $ret['data']['devices'][0];
            if($data['cid'] == $connect_cid) {
                $connect_thumbnail = $data['cover_url'];
                if($data['state'] == 0 || $data['state'] == 5) {
                    $_ENV['device']->update_device_connect_by_cid($this->connect_type, $connect_cid, 0, $connect_thumbnail);
                    if($device['status'] > 0 && $device['laststatusupdate'] + 60 > $this->base->time) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    $_ENV['device']->update_device_connect_by_cid($this->connect_type, $connect_cid, 1, $connect_thumbnail);
                    if($device['status'] == 0 && $device['laststatusupdate'] + 60 > $this->base->time) {
                        return false;
                    } else {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    function clip($device, $starttime, $endtime, $name, $client_id, $uk=0) {
        if (!$device || !$device['deviceid'] || !$device['uid']) 
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_cid = $this->_device_connect_cid($device);
        //兼容企业用户
        if(!$uk)
            return false;

        if (!$connect_cid)
            return false;
        
        $token_extras = $this->get_connect_info($uid);
        $access_token = $this->device_accesstoken($connect_cid);

        if (!$access_token || !$token_extras)
            return false;

        $params = array(          
            'user_token' => $this->user_token($token_extras['connect_cid']),
            'access_token' => $access_token,
            'cid' => $connect_cid,
            'start' => $starttime,
            'end' => $endtime
        );

        $ret = $this->_request('/v2/record/'.$token_extras['connect_cid'].'/clips', $params, 'POST');

        if (!$ret || ($ret['http_code'] != 200 && $ret['http_code'] != 409))
            //$this->_error($ret);
            return false;

        if ($ret['http_code'] == 409 && !isset($ret['data']['clip_id']))
            //$this->_error($ret);
            return false;
        
        $clipid = $ret['data']['clip_id'];
        $dateline = $this->base->time;
        $storageid = $this->get_config('storageid');
        $filename = $name;
        $pathname = $name;

        $setarray = array();
        $setarray[] = 'deviceid="'.$deviceid.'"';
        $setarray[] = 'uid='.$uk;
        $setarray[] = 'storageid='.$storageid;
        $setarray[] = 'name="'.$name.'"';
        $setarray[] = 'starttime='.$starttime;
        $setarray[] = 'endtime='.$endtime;
        $setarray[] = 'pathname="'.$pathname.'"';
        $setarray[] = 'filename="'.$filename.'"';
        $setarray[] = 'client_id="'.$client_id.'"';
        $setarray[] = 'dateline='.$dateline;
        $setarray[] = 'lastupdate='.$dateline;
        $setarray[] = 'status=1';
        $setarray[] = 'progress=0';
        $setarray[] = 'storage_cid='.$clipid;
        $sets = implode(',', $setarray);
        $this->db->query('INSERT INTO '.API_DBTABLEPRE."device_clip SET $sets");
        $id = $this->db->insert_id();

        $result = array();
        $result['name'] = $name;
        $result['clipid'] = intval($id);
        $result['deviceid'] = $deviceid;

        return $result;
    }

    function infoclip($uid, $type, $clipid, $uk=0) {
        if(!$uid || !$clipid)
            return false;
        if(!$uk)
            $uk= $uid;
        
        $token_extras = $this->get_connect_info($uk);
        
        if(!$token_extras)
            return false;

        $storageid = $this->get_config('storageid');
        $clipinfo = $this->db->fetch_first('SELECT storageid, storage_cid, deviceid, name FROM '.API_DBTABLEPRE."device_clip WHERE uid=$uid AND storageid=$storageid AND clipid=$clipid");
        if (!$clipinfo || !$clipinfo['storage_cid'])
            $this->base->error(API_HTTP_NOT_FOUND, CONNECT_ERROR_CLIPID_NOT_FOUND);
        
        $params = array(
            'user_token' => $this->user_token($token_extras['connect_cid']),
        );
        
        $ret = $this->_request('/v2/record/clips/'.$clipinfo['storage_cid'].'/status', $params, 'GET');
        if (!$ret || $ret['http_code'] != 200)
            $this->_error($ret);

        $status = 1;
        $progress = 0;
        $segments = null;
        if ($ret['data']['status'] == 'WAIT') {
            $status = 1;
            $progress = 0;
        }
        else if ($ret['data']['status'] == 'START') {
            $status = 1;
            $progress = 50;
        }
        else if ($ret['data']['status'] == 'FINISH') {
            $status = 0;
            $progress = 100;
            $params = array(
                'user_token' => $this->user_token($token_extras['connect_cid']),
            );
            
            $ret = $this->_request('/v2/record/clips/'.$clipinfo['storage_cid'], $params, 'GET');
            if (!$ret || $ret['http_code'] != 200)
                $this->_error($ret);

            $segments = $ret['data']['segments'];
        }
        else if ($ret['data']['status'] == 'FAIL') {
            $status = -1;
            $progress = 0;
        }

        $updatetime = $this->base->time;
        $this->db->query('UPDATE '.API_DBTABLEPRE."device_clip SET status=$status, progress=$progress, lastupdate=$updatetime WHERE clipid=$clipid");

        $result = array();
        $result['clipid'] = strval($clipid);
        $result['status'] = intval($status);
        $result['progress'] = intval($progress);
        $result['deviceid'] = $clipinfo['deviceid'];
        $result['name'] = $clipinfo['name'];
        $result['storageid'] = intval($clipinfo['storageid']);
        if ($status == 0)
            $result['segments'] = $segments;

        return $result;
    }

    function listdeviceclip($device, $page, $count) {
        if(!$device || !$device['deviceid'])
            return false;
        
        $uid = $device['uid'];
        $deviceid = $device['deviceid'];
        $connect_cid = $this->_device_connect_cid($device);
        
        $user_token = $this->user_token($connect_cid);
        
        if(!$user_token)
            return false;
        
        $params = array(
            'user_token' => $user_token,
            'cid' => $connect_cid,
            'page' => $page,
            'size' => $count
        );
        
        $list = array();
        $ret = $this->_request('/API/cameras/'.$connect_cid.'/clips', $params, 'GET');
        if (!$ret || $ret['http_code'] != 200)
            $this->_error($ret);
        
        $list['count'] = count($ret['data']['clips']); //羚羊返回的total不对
        foreach ($ret['data']['clips'] as $item) {
            foreach ($item['segments'] as $subitem) {
                $list['list'][] = array('thumbnail' => $subitem['cover'], 'url' => $subitem['download'], 'clipid' => $item['id']);
            }
        }
        return $list;
    }

    //添加uk 设备uid
    function listuserclip($uid, $page, $count, $client_id, $uk=0) {
        if(!$uid || !$uk)
            return false;
               
        $token_extras = $this->get_connect_info($uk);
        if(!$token_extras)
            return false;
        
        $params = array(
            'user_token' => $this->user_token($token_extras['connect_cid']),
            'page' => $page,
            'size' => $count
        );
        
        $ret = $this->_request('/v2/record/'.$token_extras['connect_cid'].'/clips', $params, 'GET');
        if (!$ret || ($ret['http_code'] != 200 && $ret['http_code'] != 401))
            $this->_error($ret);
        
        $storageid = $this->get_config('storageid');
        foreach ($ret['data']['clips'] as $item) {
            $storage_cid = $item['id'];
            $updatetime = $this->base->time;
            $this->db->query('UPDATE '.API_DBTABLEPRE."device_clip SET status=0, progress=100, lastupdate=$updatetime WHERE uid=$uid AND storageid=$storageid AND storage_cid=$storage_cid");
        }

        return true;
    }
    
    function need_cvr_record() {
        return true;
    }
    
    function on_device_online($device) {
        if(!$device || !$device['deviceid'])
            return false;

        $device = $_ENV['device']->update_cvr_info($device);
        $deviceid = $device['deviceid'];
        
        /*
        $online = $this->_device_online($device);
        $this->base->log('tttt on_device_online', 'online='.$online);
        if(!$online) {
            // 等待200ms
            usleep(2000);
        }
        */
        usleep(1000000);
        
        $current_firmware = $_ENV['device']->_get_current_firmware($device);
        if(!$current_firmware)
            return false;

        $this->base->log('tttt on_device_online', 'need bitlevel');
        
        // 羚羊码率处理逻辑
        if($current_firmware <= 7123023) {
            $this->base->log('tttt on_device_online', 'need bitlevel');
            $fileds = $_ENV['device']->_check_fileds($deviceid);
            $bitlevel = $fileds['bitlevel'];
            $this->base->log('tttt on_device_online', $deviceid.' bitlevel='.$bitlevel);
            $_ENV['device']->updatesetting($device, array('bitlevel'=>$bitlevel), 0);
            $this->base->log('tttt on_device_online', $deviceid.' bitlevel done');
        }
        
        // 羚羊升级cid处理
        if(!$this->domain && $current_firmware > 7123023) {
            $connect_cid = $this->_device_connect_cid($device);
            if(!$connect_cid || $connect_cid < 537000000) {
                $this->base->log('tttt on_device_online', 'need update cid');
                $this->base->log('tttt on_device_online', 'old cid='.$connect_cid);
                $connect_cid = $this->gen_device_connect_cid($deviceid);
                if(!$connect_cid)
                    return false;
                
                $this->base->log('tttt on_device_online', 'new cid='.$connect_cid);
                
                if(strlen($connect_cid) > 31)
                    return false;
                
                // set new cid
                $params = $_ENV['device']->_bin2hex(chr(0), 160);
                $params .= $_ENV['device']->_bin2hex(chr(0), 160);
                $params .= $_ENV['device']->_bin2hex($connect_cid.''.chr(0), 64);
                $command = '{"main_cmd":75,"sub_cmd":39,"param_len":192,"params":"' . $params . '"}';
                
                $this->base->log('tttt on_device_online', 'command='.$command);

                if (!$this->device_usercmd($device, $command, 1))
                    return false;
                
                // save setting
                $command = '{"main_cmd":65,"sub_cmd":3,"param_len":0,"params":0}';
                if (!$this->device_usercmd($device, $command, 1))
                    return false;
                
                $_ENV['device']->update_device_connect_cid($deviceid, $this->connect_type, $connect_cid);
                
                // reboot
                $command = '{"main_cmd":65,"sub_cmd":1,"param_len":0,"params":0}';
                if (!$this->device_usercmd($device, $command, 1))
                    return false;
                
                $this->base->log('tttt on_device_online', 'update cid sueess');
            }
        }

        if ($device['init_cvr_cron']) {
            $cron = $this->db->fetch_first('SELECT cvr_cron,cvr_start,cvr_end,cvr_repeat FROM '.API_DBTABLEPRE.'devicefileds WHERE deviceid="'.$device['deviceid'].'"');

            if ($cron['cvr_cron'] && !$_ENV['device']->set_cvr_cron($this, $device, $cron['cvr_cron'], $cron['cvr_start'], $cron['cvr_end'], $cron['cvr_repeat']))
                return false;

            if ($device['dev_cvr']) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_dev SET init_cvr_cron=0 WHERE deviceid="'.$device['deviceid'].'"');
            } else if ($device['partner_cvr']) {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device_partner SET init_cvr_cron=0 WHERE deviceid="'.$device['deviceid'].'"');
            } else {
                $this->db->query('UPDATE '.API_DBTABLEPRE.'device SET init_cvr_cron=0 WHERE deviceid="'.$device['deviceid'].'"');
            }
        }
        
        return true;
    }
}
