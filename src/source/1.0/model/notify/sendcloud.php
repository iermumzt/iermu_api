<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/notify/notify.php';

define('SENDCLOUD_WEB_API', 'http://sendcloud.sohu.com/webapi/');
define('SENDCLOUD_SMS_API', 'http://sendcloud.sohu.com/smsapi/');

class sendcloudnotify  extends notify {

	function __construct(&$base, $service) {
		$this->sendcloudnotify($base, $service);
	}

	function sendcloudnotify(&$base, $service) {
		parent::__construct($base, $service);
	}
    
    function sendverify($verifycode, $params) {
        if(!$verifycode)
            return false;
        
        $this->base->log('sendcloud sendverify', 'code='.json_encode($verifycode).',params='.json_encode($params));
        
        $codeid = $verifycode['codeid'];
        $code = $verifycode['code'];
        $type = $verifycode['type'];
        $send = $verifycode['send'];
        $form = $verifycode['form'];
        $uid = $verifycode['uid'];
        $email = $verifycode['email'];
        $countrycode = $verifycode['countrycode'];
        $mobile = $verifycode['mobile'];
        
        // 通知状态
        $status = -2;
        $senddate = 0;
        $notifyno = '';
        $notifystatus = '';
        $notifymsg = '';
        $notifydate = $this->base->time;
        
        if($send == 'email') {
            if($type == 'activeemail') {
                $vars = json_encode(array(
                    "to" => array($email),
                    "sub" => array(
                        "%name%" => Array($params['name']),
                        "%active_code%" => Array($params['active_code']),
                        "%active_url%" => Array($params['active_url'])
                    )
                ));
                
                if(API_LANGUAGE == 'zh-Hans') {
                    $template = 'cn_active_account';
                } else {
                    $template = 'en_active_account';
                } 
            } else if($type == 'resetpwd') {
                $vars = json_encode(array(
                    "to" => array($email),
                    "sub" => array(
                        "%name%" => Array($params['name']),
                        "%resetpwd_url%" => Array($params['resetpwd_url'])
                    )
                ));
        
                if(API_LANGUAGE == 'zh-Hans') {
                    $template = 'cn_resetpwd';
                } else {
                    $template = 'en_resetpwd';
                }
            }
            
            $ret = $this->_send_template_mail_by_service($email, $template, $vars);
            if($ret && $ret['data'] && $ret['data']['message']) {
                if($ret['data']['message'] == 'success') {
                    $status = 1;
                    $senddate = $this->base->time;
                    $notifyno = $ret['data']['email_id_list'][0];
                } else if($ret['data']['message'] == 'error') {
                    $notifymsg = $ret['data']['errors'][0];
                }
            }
        }
        
        $this->base->log('sendcloud sendverify', 'update='."UPDATE ".API_DBTABLEPRE."member_verifycode SET `status`='$status', `senddate`='$senddate', `notifyno`='$notifyno', `notifystatus`='$notifystatus', `notifymsg`='$notifymsg', `notifydate`='$notifydate' WHERE `codeid`='$codeid'");
        
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."member_verifycode SET `status`='$status', `senddate`='$senddate', `notifyno`='$notifyno', `notifystatus`='$notifystatus', `notifymsg`='$notifymsg', `notifydate`='$notifydate' WHERE `codeid`='$codeid'");
        
        return $status>0?true:false;
    }
    
    function _send_template_mail_by_service($email, $template, $vars) {
        if(!$email || !$template || !$vars)
            return false;
        
        $params = array(
            'api_user' => $this->notify_config['service_mail']['api_user'],
            'api_key' =>  $this->notify_config['service_mail']['api_key'],
            'from' =>  $this->notify_config['service_mail']['from'],
            'fromname' =>  $this->notify_config['service_mail']['fromname'],
            'substitution_vars' => $vars,
            'template_invoke_name' => $template
        );
        
        return $this->_request(SENDCLOUD_WEB_API.'mail.send_template.json', $params);
    }
    
	function _request($url, $params = array(), $httpMethod = 'POST') {
    	$ch = curl_init();
		
  		$headers = array();
		if (isset($params['host'])) {
		    $headers[] = 'X-FORWARDED-FOR: ' . $params['host'] . '; CLIENT-IP: ' . $params['host'];
		    unset($params['host']);
		}

    	$curl_opts = array(
			CURLOPT_CONNECTTIMEOUT	=> 3,
			CURLOPT_TIMEOUT			=> 20,
			CURLOPT_USERAGENT		=> 'iermu api server/1.0',
	    	CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_1_1,
	    	CURLOPT_RETURNTRANSFER	=> true,
	    	CURLOPT_HEADER			=> false,
	    	CURLOPT_FOLLOWLOCATION	=> false,
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
		$this->base->log('sendcloud _request', 'info errorno='.curl_errno($ch).', errormsg='.curl_error($ch).', url='.$info['url'].',total_time='.$info['total_time'].', namelookup_time='.$info['namelookup_time'].', connect_time='.$info['connect_time'].', pretransfer_time='.$info['pretransfer_time'].',starttransfer_time='.$info['starttransfer_time']);
		
    	if ($result === false) {
			$this->base->log('sendcloud _request', 'erro result false');
			curl_close($ch);
			return false;
    	}
		
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    	curl_close($ch);
		
		$this->base->log('sendcloud _request', 'finished http_code='.$http_code.',result='.$result);
		
		return array('http_code' => $http_code, 'data' => json_decode($result, true)); 
    }
    
}
?>