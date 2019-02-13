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
        
        if(!defined('API_APP')) define('API_APP', '');
        
        $this->base->log('sendcloud sendverify', 'app='.API_APP.',code='.json_encode($verifycode).',params='.json_encode($params));
        
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
            if($type == 'updateemail') {
                $vars = json_encode(array(
                    "to" => array($email),
                    "sub" => array(
                        "%name%" => Array($params['name']),
                        "%operation%" => Array($params['operation']),
                        "%code%" => Array($params['code']),
                        "%expiretime%" => Array($params['expiretime'])
                    )
                ));
                
                if(API_APP == 'smartseye') {
                    $template = 'en_verifycode_smartseye';
                } else {
                    if(API_LANGUAGE == 'zh-Hans') {
                        $template = 'cn_verifycode';
                    } else if(API_LANGUAGE == 'ja') {
                        $template = 'ja_verifycode';
                    } else {
                        $template = 'en_verifycode';
                    }
                }
            } else if($type == 'resetpwd') {
                $vars = json_encode(array(
                    "to" => array($email),
                    "sub" => array(
                        "%name%" => Array($params['name']),
                        "%resetpwd_url%" => Array($params['resetpwd_url'])
                    )
                ));
                
                if(API_APP == 'smartseye') {
                    $template = 'en_resetpwd_smartseye';
                } else {
                    if(API_LANGUAGE == 'zh-Hans') {
                        $template = 'cn_resetpwd';
                    } else if(API_LANGUAGE == 'ja') {
                        $template = 'ja_resetpwd';
                    } else {
                        $template = 'en_resetpwd';
                    }
                }
            }  else if($type == 'checkauth') {
                $vars = json_encode(array(
                    "to" => array($email),
                    "sub" => array(
                        "%name%" => Array($params['name']),
                        "%operation%" => Array($params['operation']),
                        "%code%" => Array($params['code']),
                        "%expiretime%" => Array($params['expiretime'])
                    )
                ));
        
                if(API_APP == 'smartseye') {
                    $template = 'en_verifycode_smartseye';
                } else {
                    if(API_LANGUAGE == 'zh-Hans') {
                        $template = 'cn_verifycode';
                    } else if(API_LANGUAGE == 'ja') {
                        $template = 'ja_verifycode';
                    } else {
                        $template = 'en_verifycode';
                    }
                }
            }  else if($type == 'activeemail') {
                $vars = json_encode(array(
                    "to" => array($email),
                    "sub" => array(
                        "%name%" => Array($params['name']),
                        "%active_code%" => Array($params['active_code']),
                        "%active_url%" => Array($params['active_url'])
                    )
                ));
                
                if(API_APP == 'smartseye') {
                    $template = 'en_active_account_smartseye';
                } else {
                    if(API_LANGUAGE == 'zh-Hans') {
                        $template = 'cn_active_account';
                    } else if(API_LANGUAGE == 'ja') {
                        $template = 'ja_active_account';
                    } else {
                        $template = 'en_active_account';
                    }
                }
            }
            
            $ret = $this->_send_template_mail_by_service($template, $vars);
            if($ret && $ret['data'] && $ret['data']['message']) {
                if($ret['data']['message'] == 'success') {
                    $status = 1;
                    $senddate = $this->base->time;
                    $notifyno = $ret['data']['email_id_list'][0];
                } else if($ret['data']['message'] == 'error') {
                    $notifymsg = $ret['data']['errors'][0];
                }
            }
        } else if($send == 'sms') {
            $vars = json_encode(array(
                "%code%" => strval($code)
            ));
            $template = '835';
            
            $ret = $this->_send_template_sms_by_service($countrycode, $mobile, $template, $vars);
            if($ret && $ret['data'] && $ret['data']['message']) {
                if($ret['data']['result']) {
                    $status = 1;
                    $senddate = $this->base->time;
                    $notifyno = $ret['data']['info']['smsIds'][0];
                    $notifystatus = $ret['data']['statusCode'];
                    $notifymsg = $ret['data']['message'];
                } else {
                    $notifystatus = $ret['data']['statusCode'];
                    $notifymsg = $ret['data']['message'];
                }
            }
        }
        
        $this->base->log('sendcloud sendverify', 'update='."UPDATE ".API_DBTABLEPRE."member_verifycode SET `status`='$status', `senddate`='$senddate', `notifyno`='$notifyno', `notifystatus`='$notifystatus', `notifymsg`='$notifymsg', `notifydate`='$notifydate' WHERE `codeid`='$codeid'");
        
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."member_verifycode SET `status`='$status', `senddate`='$senddate', `notifyno`='$notifyno', `notifystatus`='$notifystatus', `notifymsg`='$notifymsg', `notifydate`='$notifydate' WHERE `codeid`='$codeid'");
        
        return $status>0?true:false;
    }
    
    function sendreportnotify($admins, $params) {
        if(!$admins || !$params)
            return false;
        
        $to_arr = $deviceid_arr = $title_arr = $thumbnail_arr = $url_arr = array();
        foreach($admins as $admin) {
            $to_arr[] = $admin['email'];
            $deviceid_arr[] = $params['deviceid'];
            $title_arr[] = $params['title'];
            $thumbnail_arr[] = $params['thumbnail'];
            $url_arr[] = $params['url'];
        }
        
        $vars = json_encode(array(
            "to" => $to_arr,
            "sub" => array(
                "%deviceid%" => $deviceid_arr,
                "%title%" => $title_arr,
                "%thumbnail%" => $thumbnail_arr,
                "%url%" => $url_arr
            )
        ));
        
        $template = 'cn_device_report_admin_notification';
        
        $ret = $this->_send_template_mail_by_service($template, $vars);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function sendreportoffline($admins, $params) {
        if(!$admins || !$params)
            return false;
        
        $to_arr = $deviceid_arr = $title_arr = $thumbnail_arr = $admin_arr = array();
        foreach($admins as $admin) {
            $to_arr[] = $admin['email'];
            $deviceid_arr[] = $params['deviceid'];
            $title_arr[] = $params['title'];
            $thumbnail_arr[] = $params['thumbnail'];
            $admin_arr[] = $params['admin'];
        }
        
        $vars = json_encode(array(
            "to" => $to_arr,
            "sub" => array(
                "%deviceid%" => $deviceid_arr,
                "%title%" => $title_arr,
                "%thumbnail%" => $thumbnail_arr,
                "%admin%" => $admin_arr
            )
        ));
        
        $template = 'cn_device_report_offline_notification';
        
        $ret = $this->_send_template_mail_by_service($template, $vars);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function _send_template_mail_by_service($template, $vars) {
        if(!$template || !$vars)
            return false;
        
        $this->base->log('sendcloud _send_template_mail_by_service', ',template='.$template.',vars='.json_encode($vars));
        
        $api_user = $this->notify_config['service_mail']['api_user'];
        $api_key = $this->notify_config['service_mail']['api_key'];
        $from = $this->notify_config['service_mail']['from'];
        $fromname = $this->notify_config['service_mail']['fromname'];
        
        if(API_APP == 'smartseye') {
            $from = 'smartseye@service.iermu.com';
            $fromname = 'SMARTSEYE';
        } else {
            if(API_LANGUAGE != 'zh-Hans') {
                $fromname = 'IERMU';
            }
        }
        
        $params = array(
            'api_user' => $api_user,
            'api_key' => $api_key,
            'from' => $from,
            'fromname' => $fromname,
            'substitution_vars' => $vars,
            'template_invoke_name' => $template
        );
        
        return $this->_request(SENDCLOUD_WEB_API.'mail.send_template.json', $params);
    }
    
    function _send_template_sms_by_service($countrycode, $mobile, $template, $vars) {
        if(!$countrycode || !$mobile || !$template || !$vars)
            return false;
        
        $this->base->log('sendcloud _send_template_sms_by_service', 'countrycode='.$countrycode.',mobile='.$mobile.',template='.$template.',vars='.json_encode($vars));
        
        // 只支持国内手机号
        if($countrycode != '+86')
            return false;
        
        $template = strval($template);
        
        $sms_user = $this->notify_config['service_sms']['sms_user'];
        $sms_key = $this->notify_config['service_sms']['sms_key'];
        
        if(!$sms_user || !$sms_key)
            return false;
        
        $phone = $mobile;
        
        $param = array(
            'smsUser' => $sms_user, 
            'templateId' => $template,
            'phone' => $phone,
            'vars' => $vars
        );

        $str = "";
        ksort($param);
        foreach ($param as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str = trim($str, '&');
        
        $signature = md5($sms_key."&".$str."&".$sms_key);
        
        $params = array(
            'smsUser' => $sms_user,
            'templateId' => $template,
            'phone' =>  $phone,
            'vars' => $vars,
            'signature' => $signature
        );
        
        return $this->_request(SENDCLOUD_SMS_API.'send', $params);
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