<?php

!defined('IN_API') && exit('Access Denied');

class notify {
	var $db;
	var $redis;
	var $base;
    
	var $notifyid = 0;
	var $appid = 0;
	var $notify_type = '';
    var $notify_config = array();
    var $sendmail = 0;
    var $sendsms = 0;
	var $status = 0;
	
	var $client = NULL;

	function __construct(&$base, $service) {
		$this->notify($base, $service);
	}

	function notify(&$base, $service) {
		$this->base = $base;
		$this->db = $base->db;
		$this->redis = $base->redis;
		
        $this->notifyid = $service['notifyid'];
		$this->appid = $service['appid'];
		$this->notify_type = $service['notify_type'];
		$this->notify_config = $service['notify_config'];
        $this->sendmail = $service['sendmail'];
        $this->sendsms = $service['sendsms'];
		$this->status = $service['status'];
	}
    
    function sendverify($verifycode, $params) {
        return false;
    }
    
    function sendreportnotify($admins, $params) {
        return false;
    }
    
    function sendreportoffline($admins, $params) {
        return false;
    }
    
    function _send_template_sms_by_service($countrycode, $mobile, $template, $vars) {
        return false;
    }
    
}
?>
