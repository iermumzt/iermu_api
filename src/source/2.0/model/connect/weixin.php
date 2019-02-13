<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class weixinconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->weixinconnect($base, $_config);
    }

    function weixinconnect(&$base, $_config) {
        parent::__construct($base, $_config);
        $this->base->load('device');
        $this->base->load('user');
    }
    
    function get_connect_info($uid, $sync=false) {
        if(!$uid)
            return false;
        
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND uid='$uid'");
        if($connect && $connect['connect_uid']) {
            return array(
                'connect_type' => $this->connect_type,
                'connect_uid' => $connect['connect_uid'],
                'status' => intval($connect['status'])
            );
        } else {
            return array(
                'connect_type' => $this->connect_type,
                'status' => 0
            );
        }
        
        return false;
    }

    function get_js_config($url) {
        if(!$url)
            return array();

        $jsapi_ticket = $this->get_ticket('jsapi');
        if(!$jsapi_ticket)
            return array();

        $noncestr = $this->_noncestr(16);
        $timestamp = $this->base->time;
        $datastr = 'jsapi_ticket='.$jsapi_ticket.'&noncestr='.$noncestr.'&timestamp='.$timestamp.'&url='.$url;
        $signature = sha1($datastr);

        $config = array(
            'debug' => true,
            'beta' => false,
            'appId' => $this->config['appid'],
            'nonceStr' => $noncestr,
            'timestamp' => $timestamp,
            'url' => $url,
            'signature' => $signature,
            'jsApiList' => array()
        );
        return $config;
    }

    function _noncestr($len=16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';  
        for ( $i = 0; $i < $len; $i++ ) {  
            $password .= $chars[ mt_rand(0, strlen($chars) - 1) ];  
        }  
        return $password;  
    }

    function get_ticket($type='jsapi') {
        if($this->config[$type.'_ticket'] && $this->config[$type.'_ticket_expires'] && $this->config[$type.'_ticket_expires'] - $this->base->time > 30) {
            return $this->config[$type.'_ticket'];
        }

        $weixin_token = $this->get_weixin_token();
        if(!$weixin_token || !$weixin_token['access_token'])
            return '';

        $access_token = $weixin_token['access_token'];
        $params = array(
            'access_token' => $access_token,
            'type' => $type
        );

        $ret = $this->_request('https://api.weixin.qq.com/cgi-bin/ticket/getticket', $params);
        if(!$ret || !$ret['ticket'])
            return '';

        $this->config[$type.'_ticket'] = $ret['ticket'];
        $this->config[$type.'_ticket_expires'] = $this->base->time + $ret['expires_in'];
        $this->update_config();

        return $ret['ticket'];
    }

    function update_config() {
        $config = serialize($this->config);
        $this->db->query("UPDATE ".API_DBTABLEPRE."connect SET connect_config='$config' WHERE connect_type='".$this->connect_type."'");
    }
    
    function get_weixin_token() {
        $token = $this->db->fetch_first('SELECT access_token,expires FROM '.API_DBTABLEPRE.'connect WHERE connect_type="'.$this->connect_type.'"');
        
        // 更新token
        if(!$token || !$token['access_token'] || $token['expires'] < $this->base->time - 120) {
            $ret = $this->_request('https://api.weixin.qq.com/cgi-bin/token', array('appid'=>$this->config['appid'], 'secret'=>$this->config['secret'], 'grant_type'=>'client_credential'));
            if($ret && $ret['access_token'] && $ret['expires_in']) {
                $expires = $this->base->time + $ret['expires_in'];
                $this->db->query('UPDATE '.API_DBTABLEPRE.'connect SET access_token="'.$ret['access_token'].'", expires="'.$expires.'" WHERE connect_type="'.$this->connect_type.'"');
                
                $token = array('access_token' => $ret['access_token'], 'expires' => $expires);
            }
        }
        
        return $token;
    }
    
    function get_connect_user_by_connect_uid($connect_uid) {
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='".$this->connect_type."' AND connect_uid='$connect_uid'");
        return $connect;
    }
    
    function get_user_by_openid($openid) {
        $user = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'member_weixin WHERE openid="'.$openid.'"');
        if($user && $user['unionid']) {
            $connect_user = $this->get_connect_user_by_connect_uid($user['unionid']);
            if($connect_user && $connect_user['uid']) {
                $user['uid'] = $connect_user['uid'];
            }
        }
        return $user;
    }
    
    function unbind_user_by_openid($openid) {
        $user = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'member_weixin WHERE openid="'.$openid.'"');
        if($user && $user['unionid']) {
            $connect_user = $this->get_connect_user_by_connect_uid($user['unionid']);
            if($connect_user && $connect_user['uid']) {
                $this->db->query("DELETE FROM ".API_DBTABLEPRE."member_connect WHERE uid='".$connect_user['uid']."' AND connect_type='".$this->connect_type."' AND connect_uid='".$connect_user['connect_uid']."'");
            }
        }
        return true;
    }
    
    function update_weixin_user($user) {
        $openid = $user['openid'] ? $user['openid'] : '';
        $unionid = $user['unionid'] ? $user['unionid'] : '';
        $nickname = $user['nickname'] ? $this->base->userTextEncode($user['nickname']) : '';
        $headimgurl = $user['headimgurl'] ? $user['headimgurl'] : '';
        $sex = $user['sex'] ? $user['sex'] : '';
        $language = $user['language'] ? $user['language'] : '';
        $country = $user['country'] ? $user['country'] : '';
        $province = $user['province'] ? $user['province'] : '';
        $city = $user['city'] ? $user['city'] : '';
        $subscribe = $user['subscribe'] ? $user['subscribe'] : 0;
        $subscribe_time = $user['subscribe_time'] ? $user['subscribe_time'] : 0;

        $arr = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'member_weixin WHERE openid="'.$openid.'"');
        if ($arr) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'member_weixin SET unionid="'.$unionid.'",nickname="'.$nickname.'",headimgurl="'.$headimgurl.'",sex="'.$sex.'",language="'.$language.'",country="'.$country.'",province="'.$province.'",city="'.$city.'",subscribe="'.$subscribe.'",subscribe_time="'.$subscribe_time.'",lastupdate="'.$this->base->time.'" WHERE openid="'.$openid.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'member_weixin(openid,unionid,nickname,headimgurl,sex,language,country,province,city,subscribe,subscribe_time,dateline,lastupdate) VALUES ("'.$openid.'","'.$unionid.'","'.$nickname.'","'.$headimgurl.'","'.$sex.'","'.$language.'","'.$country.'","'.$province.'","'.$city.'","'.$subscribe.'","'.$subscribe_time.'","'.$this->base->time.'","'.$this->base->time.'")');
        }
        
        $connect = $this->get_connect_user_by_connect_uid($unionid);
        if($connect && ($connect['username'] != $nickname || $connect['avatar'] != $headimgurl)) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'member_connect SET username="'.$nickname.'",avatar="'.$headimgurl.'" WHERE connect_type="'.$this->connect_type.'" AND connect_uid="'.$unionid.'"');
        }
        
        return true;
    }
    
    function _support_connect_login() {
        return true;
    }
    
    function get_connect_login_user($connect_uid) {
        if(!$connect_uid)
            return false;
        
        // 微信ua
        if(!strpos($_SERVER["HTTP_USER_AGENT"], "MicroMessenger"))
            return false;
        
        return $this->get_connect_user($connect_uid);
    }
    
    function get_connect_user($connect_uid) {
        if(!$connect_uid)
            return false;
        
        $connect_user = $this->db->fetch_first('SELECT unionid AS connect_uid,nickname AS username,headimgurl AS avatar FROM '.API_DBTABLEPRE.'member_weixin WHERE unionid="'.$connect_uid.'"');
        $connect_user['username'] = $this->base->userTextDecode($connect_user['username']);
        $connect_user['connect_type'] = $this->connect_type;
        
        $connect = $this->get_connect_user_by_connect_uid($connect_user['connect_uid']);
        if($connect && $connect['uid']) {
            $connect_user['uid'] = $connect['uid'];
        }
        return $connect_user;
    }
    
    function _request($url, $params = NULL) {
        if (is_array($params)) {
            $query = http_build_query($params, '', '&');
            $delimiter = strpos($url, '?') === false ? '?' : '&';
            $url .= $delimiter.$query;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200)
            return false;

        return json_decode($data, true);
    }
    
}
