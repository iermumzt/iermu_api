<?php

!defined('IN_API') && exit('Access Denied');

class usercontrol extends base {

    function __construct() {
        $this->usercontrol();
    }

    function usercontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
    }

    function oninfo() {
        $this->init_user();
        
        $uid = $this->uid;
        $client_id = $this->client_id;

        if(!$uid) {
            if(!$this->connect_uid || !$this->connect_user)
                $this->user_error();
            
            $result = array('uid'=>0);
            $result = array_merge($result, $this->connect_user);
            return $result;
        }
        
        $this->init_input();
        $connect =  $this->input('connect');

        $result = is_numeric($uid) ? $_ENV['user']->_format_user($uid) : $this->user;
        if($connect) {
            $result['connect'] = $this->_connect($uid, $client_id);
        }
        
        return $result;
    }
    
    function _connect($uid, $client_id) {
        if(!$uid || !$client_id)
            return array();
        
        // connect
        $connects = array();
        $types = $_ENV['oauth2']->get_client_connect_support($client_id);
        foreach($types as $connect_type) {
            $connect = $this->load_connect($connect_type);
            if($connect && $connect->_token_extras()) {
                $extras = $connect->get_connect_info($uid);
                if($extras && is_array($extras)) {
                    $connects[] = $extras;
                } else {
                    //$this->error(OAUTH2_HTTP_BAD_REQUEST, API_ERROR_USER_GET_CONNECT_FAILED);
                    $connects[] = array('connect_type' => $connect_type, 'status' => 0);
                }
            }
        }
        
        return $connects;
    }
    
    function ongetloginuser() {
        if(!$this->init_user())
            $this->user_error();
        
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $user = $_ENV['user']->get_user_by_uid($uid);
        if(!$user)
            $this->user_error();
        
        $result['uid'] = $uid;
        $result['uname'] = $user['username'];
        $result['portrait'] = '';
        return $result;
    }

    function onregister() {
        $this->init_input('P');
        
        $this->log('user register', json_encode($this->input));
        
        $mobile = $this->input('mobile');
        $countrycode = $this->input('countrycode');
        $verifycode = $this->input('verifycode');
        $email = $this->input('email');
        $username = rawurldecode($this->input['username']);
        $password =  $this->input['password'];
        
        if(!$email && !$mobile)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if(!$username)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if($mobile) {
            if($verifycode === NULL)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            if(!$countrycode) $countrycode = '+86';
        }
        
        $sign = $this->input('sign');
        $expire = $this->input('expire');
        
        //应用认证
        $client = $_ENV['oauth2']->getClientCredentials();
        $client_id = $client[0];
        $_ENV['oauth2']->client_id = $client_id;

        $app = $_ENV['oauth2']->getAppCredentials();
        $appid = $app['appid'];
        $_ENV['oauth2']->appid = $app['appid'];
        $this->app = $app;
        
        $this->log('user register', 'app='.json_encode($app));
        
        // API_APP 处理
        if(!defined('API_APP') && $app && $app['code']) {
            define('API_APP', $app['code']);
        }
        
        $this->log('user register', 'API_APP='.API_APP);
        
        //应用有效性
        if($_ENV['oauth2']->checkAppCredentials($app) === FALSE)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_CLIENT);
        
        //$client_secret = $client[1];
        //if(!$_ENV['oauth2']->checkClientCredentials($client_id, $client_secret))
        //  $this->error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
        
        //用户名有效性
        $this->_check_username($username);
        
        // 手机号码
        if($mobile)
            $this->_check_mobile($countrycode, $mobile);
        
        // email有效性
        if($email)
            $this->_check_email($email);

        // 检查密码有效性
        $this->_check_password($password);
        
        //签名校验
        if(!$_ENV['user']->_check_sign($sign, $username, $client_id, $expire))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NO_AUTH);
        
        // mobile verifycode
        $mobilestatus = 0;
        if($mobile) {
            $vcode = $_ENV['user']->get_verifycode_by_type($verifycode, 'register');
            if(!$vcode || ($vcode['expiredate'] > 0 && $vcode['expiredate'] < $this->time))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
            
            if($vcode['countrycode'] != $countrycode || $vcode['mobile'] != $mobile)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
            
            $mobilestatus = 1;
            $_ENV['user']->use_verifycode($vcode['codeid']);
        }

        //添加用户
        $uid = $_ENV['user']->add_user($username, $password, $email, $countrycode, $mobile);
        if(!$uid || !$user = $_ENV['user']->get_user_by_uid($uid)){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_ADD_FAILED);
        }
        
        //生成Access Token
        //$_ENV['oauth2']->uid = $uid;
        //$token = $_ENV['oauth2']->createAccessToken($client_id, '');
        
        // send mail
        if($email) {
            $_ENV['user']->sendverify($user, 'activeemail', '', 'email', 'url', '', '', $email, $client_id, $appid);
        }
        
        $_ENV['user']->update_mobile($uid, $countrycode, $mobile, $mobilestatus);

        // log记录 ----------- init params
        log::$appid = $appid;
        log::$client_id = $client_id;
        log::$uid = $uid;
        
        $result = $_ENV['user']->_format_user($uid);



        return $result;
    }
    
    function onchangepwd() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        
        $this->init_input('P');
        $oldpassword =  $this->input['oldpassword'];
        $newpassword =  $this->input['newpassword'];
        $sign = $this->input('sign');
        $expire = $this->input('expire');
        
        if(!$uid || !$newpassword) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        // 签名校验
        $client_id = $this->client_id;
        $checkstr = $uid.$oldpassword.$newpassword;
        if(!$_ENV['user']->_check_sign($sign, $checkstr, $client_id, $expire))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NO_AUTH);
        
        //用户验证
        $auser = $_ENV['user']->get_user_by_uid($uid);
        if(!$auser)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
        
        if($auser['pwdstatus']) {
            if(!$oldpassword)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $md5password =  md5(md5($oldpassword).$auser['salt']);
            if($auser['password'] != $md5password) {
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_PW);
            }
        }

        // 检查密码有效性
        $this->_check_password($newpassword);

        // 修改密码
        if(!$user = $_ENV['user']->change_password($uid, $newpassword)) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_CHANGE_PASSWORD_FAILED);
        
        $result = array('uid'=>$uid);
        return $result;
    }
    
    function onlogin() {
        $this->init_input();
        $redirect = $this->input('redirect');
        if(!$redirect) {
            $redirect = $_SERVER['HTTP_REFERER']?$_SERVER['HTTP_REFERER']:API_DEFAULT_REDIRECT;
        }
        
        // 检查登录
        $this->check_login();
        
        if(API_AUTH_LOGIN && $this->submitcheck()) {
            // 接口登录方式
            if($this->user && $this->user['uid']) {
                return $_ENV['user']->_format_user($this->user['uid']);
            }

            if(!$this->clientcheck())
                $this->error(API_HTTP_UNAUTHORIZED, API_ERROR_INVALID_CLIENT);
            
            $authkey = md5(API_KEY.$_SERVER['HTTP_USER_AGENT'].$this->onlineip);
        
            $domain = getgpc('domain', 'P');
            $username = getgpc('username', 'P');
            $password = getgpc('password', 'P');
            
            $countrycode = getgpc('countrycode', 'P');
            $mobile = getgpc('mobile', 'P');
            $verifycode = getgpc('verifycode', 'P');
            
            $qrcode = getgpc('qrcode', 'P');
            
            if(!$username && !$mobile && !$qrcode)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $auth_type = 'password';
            if($mobile && $verifycode) {
                $auth_type = 'mobile';
            } else if($qrcode) {
                $auth_type = 'qrcode';
            }
            
            if($auth_type == 'password' && !$password)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            if($auth_type == 'mobile' && !$mobile)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            if($auth_type == 'qrcode' && !$qrcode)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            if($auth_type == 'qrcode') {
                $stored = $_ENV['qrcode']->get_qrcode_by_code($qrcode);
                if(!$stored || API_AUTH_CLIENTID != $stored["client_id"])
                    $this->error(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
                
                if($stored['expires'] < $this->time) {
                    if($stored['status'] >= 0) {
                        $_ENV['qrcode']->update_qrcode_status($stored['cid'], -2);
                    }
                    $this->error(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
                }
                
                if($stored['status'] != 2)
                    $this->error(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
                
                $auser = $_ENV['user']->get_user_by_uid($stored['uid']);
                if(!$auser)
                    $this->error(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
            } else {
                $failedlogin = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."failedlogins WHERE ip='$this->onlineip'");
                if($failedlogin['count'] > 10) {
                    if($this->time - $failedlogin['lastupdate'] < 3 * 60) {
                        $this->error(API_HTTP_BAD_REQUEST, API_ERROR_LOGIN_FAILED_TIMES);
                    } else {
                        $expiration = $this->time - 3 * 60;
                        $this->db->query("DELETE FROM ".API_DBTABLEPRE."failedlogins WHERE lastupdate<'$expiration'");
                    }
                }
            
                /*
                $seccodehidden = urldecode(getgpc('seccodehidden', 'P'));
                $seccode = strtoupper(getgpc('seccode', 'P'));
                $seccodehidden = $this->authcode($seccodehidden, 'DECODE', $authkey);
                require API_SOURCE_ROOT.'./lib/seccode.class.php';
                seccode::seccodeconvert($seccodehidden);
                if(empty($seccodehidden) || $seccodehidden != $seccode) {
                    $this->_update_failedlogin($failedlogin);
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_SECCODE);
                }
                */

                //用户验证
                if($domain) {
                    $auser = $_ENV['user']->get_user_by_domain($domain, $username);
                    // $auser = $_ENV['user']->get_user_by_domain($domain, $username);
                } else if($mobile) {
                    if(!$countrycode) $countrycode = '+86';
                    $auser = $_ENV['user']->get_user_by_mobile($mobile, $countrycode);
                } else {
                    $auser = $_ENV['user']->get_user_by_email($username);
                    if(empty($auser)) {
                        $auser = $_ENV['user']->get_user_by_mobile($username);
                    }
                }
            
                if(!empty($auser)) {
                    // 组织用户禁止普通登陆
                    if(!$domain) {
                        $check = $this->db->fetch_first("SELECT org_id FROM ".API_DBTABLEPRE."member_org WHERE uid='".$auser['uid']."'");
                        if($check)
                            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
                    }
                    
                    if($auth_type == 'password') {
                        $md5password =  md5(md5($password).$auser['salt']);
                        if($auser['password'] != $md5password) {
                            $this->_update_failedlogin($failedlogin);
                            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_PW);
                        }
                    } else if($auth_type == 'mobile') {
                        $vcode = $_ENV['user']->get_verifycode_by_type($verifycode, 'login');
                        if(!$vcode || ($vcode['expiredate'] > 0 && $vcode['expiredate'] < $this->time)) {
                            $this->_update_failedlogin($failedlogin);
                            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
                        }
                    
                        if($vcode['countrycode'] != $countrycode || $vcode['mobile'] != $mobile) {
                                $this->_update_failedlogin($failedlogin);
                                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
                        }   
        
                        $_ENV['user']->use_verifycode($vcode['codeid']);
                    }
                } else {
                    $this->_update_failedlogin($failedlogin);
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
                }
            }
            
            $this->view->sid = $this->set_login($auser['uid']);
            $this->set_relogin($auser['uid']);
            
            $result = $_ENV['user']->_format_user($auser['uid']);
            return $result;
        } else {
            // 页面登录方式
            if($this->user && $this->user['uid']) {
                $this->redirect($redirect);
            }

            $auth_url = BASE_API.'/oauth2/authorize?display='.API_DISPLAY.'&response_type=none&client_id='.API_AUTH_CLIENTID.'&redirect_uri='.$redirect;
            $this->redirect($auth_url);
        }
    }
    
    function onlogout() {
        $this->init_user();
		
		$this->logout();
		$_ENV['oauth2']->logout();
		
		$this->init_input();
		$redirect = $this->input('redirect');
		if(API_AUTH_TYPE == 'cookie' && !$redirect) {
			$redirect = $_SERVER['HTTP_REFERER']?$_SERVER['HTTP_REFERER']:API_DEFAULT_REDIRECT;
		}
		
		if($redirect)
			$this->redirect($redirect);
		
		return array();
    }
    
    function _update_failedlogin($failedlogin) {
        if(empty($failedlogin)) {
            $expiration = $this->time - 3 * 60;
            $this->db->query("DELETE FROM ".API_DBTABLEPRE."failedlogins WHERE lastupdate<'$expiration'");
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."failedlogins SET ip='$this->onlineip', count=1, lastupdate='$this->time'");
        } else {
            $this->db->query("UPDATE ".API_DBTABLEPRE."failedlogins SET count=count+1,lastupdate='$this->time' WHERE ip='$this->onlineip'");
        }
    }

	function _check_username($username) {
		$username = addslashes(trim(stripslashes($username)));
		if(!$_ENV['user']->check_username($username)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_CHECK_USERNAME_FAILED);
		} elseif(!$_ENV['user']->check_usernamecensor($username)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_USERNAME_BADWORD);
		} elseif($_ENV['user']->check_usernameexists($username)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_USERNAME_EXISTS);
		}

		return 1;
	}

	function _check_email($email, $username = '') {
		//$uc_setting = $this->get_api_setting('doublee');
		//$this->settings['doublee'] = $uc_setting['doublee'];

		if(!$_ENV['user']->check_emailformat($email)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_EMAIL_FORMAT_ILLEGAL);
		} elseif(!$_ENV['user']->check_emailaccess($email)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_EMAIL_ACCESS_ILLEGAL);
        //} elseif(!$this->settings['doublee'] && $_ENV['user']->check_emailexists($email, $username)) {
        } elseif($_ENV['user']->check_emailexists($email, $username)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_EMAIL_EXISTS);
		} else {
			return 1;
		}
	}

    function _check_password($password) {
        if (!$_ENV['user']->check_passwordformat($password)) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_PASSWORD_FORMAT_ILLEGAL);
        } elseif (!$_ENV['user']->check_passwordstrength($password)) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_PASSWORD_SECURITY_CHECK_FAILED);
        } else {
            return 1;
        } 
    }

    // 更新用户名
    function onupdate() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input('P');
        $username = $this->input('username');
        if (!$username)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $user = $_ENV['user']->get_user_by_uid($uid);
        if ($user['username'] !== $username) {
            $this->_check_username($username);

            if (!$_ENV['user']->update($uid, $username))
                $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_UPDATE_USERNAME_FAILED);
        }
        
        $result = $_ENV['user']->_format_user($uid);
        return $result;
    }

    // 补全用户资料
    function oncomplete() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input('P');
        $username = $this->input('username');
        $email = $this->input('email');
        $password = $this->input('password');
        $sign = $this->input('sign');
        $expire = $this->input('expire');
        if (!$username || !$email || !$email || !$password)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        // 签名校验
        $checkstr = $username.$email.$password;
        if(!$_ENV['user']->_check_sign($sign, $checkstr, $this->client_id, $expire))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NO_AUTH);

        $user = $_ENV['user']->get_user_by_uid($uid);
        if($user['email'])
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_COMPLETED);
        
        if ($user['username'] !== $username)
            $this->_check_username($username);

        if ($user['email'] !== $email)
            $this->_check_email($email);

        // 检查密码有效性
        $this->_check_password($password);

        $salt = $user['salt'] ? $user['salt'] : substr(uniqid(rand()), -6);

        if (!$_ENV['user']->complete($uid, $username, $email, $password, $salt))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_COMPLETE_FAILED);
        
        $result = $_ENV['user']->_format_user($uid);
        return $result;
    }
    
    // 发送验证
    function onsendverify() {
        $type_support = array('checkauth', 'updatemobile', 'updateemail', 'activeemail', 'register', 'login');
        $send_support = array('email', 'sms');
        
        $this->init_user();
        $uid = $this->uid;

        $this->init_input();
        $type = $this->input('type');
        if(!$type || !in_array($type, $type_support))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $operation = $this->input('operation');
        
        $send = $this->input('send');
        if(!$send || !in_array($send, $send_support)) {
            $send = 'sms';
        }
        
        $countrycode = $this->input('countrycode');
        $mobile = $this->input('mobile');
        $email = $this->input('email');
        
        $client_id = $this->input('client_id');
        if($client_id) {
            $this->client_id = $client_id;
            $this->appid = $this->db->result_first("SELECT appid FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        }
        $form = 'code';
        if($type == 'checkauth') {
            if(!$uid)
                $this->user_error();
            if(!$operation || $operation == 'checkauth' || !in_array($operation, $type_support))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);


        } else if($type == 'updatemobile') {
            if(!$uid)
                $this->user_error();
            
            if($send != 'sms' || !$mobile)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        } else if($type == 'updateemail') {
            if(!$uid)
                $this->user_error();
            
            if($send != 'email' || !$email)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        } else if($type == 'activeemail') {
            if(!$uid)
                $this->user_error();
            
            if($send != 'email')
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $form = 'url';
        } else if($type == 'register') {
            if($send != 'sms' || !$mobile)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $uid = 0;
        } else if($type == 'login') {
            if($send != 'sms' || !$mobile)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            $uid = 0;
        }

        // 签名校验
        $sign = $this->input('sign');
        $expire = $this->input('expire');
        $checkstr = $uid?$uid:'';
        $checkstr .= $type.$send.$countrycode.$mobile.$email;
        if(!$_ENV['user']->_check_sign($sign, $checkstr, $this->client_id, $expire))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NO_AUTH);
        
        if($send == 'sms' && !$countrycode) $countrycode = '+86';
        
        if($uid) {
            $user = $_ENV['user']->get_user_by_uid($uid);
            if($type == 'activeemail') {
                if( $user && $user['email'] && $user['emailstatus'] == 0) {
                    $email = $user['email'];
                } else {
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NO_AUTH);
                }
            }
        } else {
            $user = array();
        }
        
        if($send == 'sms') {
            if(!$mobile)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            if($type == 'login') {
                $check = $_ENV['user']->get_user_by_mobile($mobile, $countrycode);
                if(!$check)
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
            } else {
                $this->_check_mobile($countrycode, $mobile, $uid);
            }
        } else if($send == 'email') {
            if(!$email)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            
            if($type == 'login') {
                $check = $_ENV['user']->get_user_by_email($email);
                if(!$check)
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXISTS);
            } else {
                $this->_check_email($email, ($uid&&$user&&$user['username'])?$user['username']:'');
            }
        }
        
        if($type == 'checkauth') {
            if($send == 'sms') {
                if($countrycode != $user['countrycode'] || $mobile != $user['mobile'])
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_MOBILE_DISMATCH);
                if(!$user['mobilestatus'])
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_MOBILE_UNVERIFY);
            } else if($send == 'email') {
                if(strtolower($email) != strtolower($user['email']))
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_EMAIL_DISMATCH);
                if(!$user['emailstatus'])
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_EMAIL_UNVERIFY);
            } 
        }
        
        // 重试时间
        $retry_in = 60;
        $lastverifytime = $_ENV['user']->lastsendverifytime($uid, $type, $send, $form, $countrycode, $mobile, $email, $retry_in);
        $this->log('sendverify', 'uid='.$uid.', type='.$type.', send='.$send.', form='.$form.', countrycode='.$countrycode.', mobile='.$mobile.', email='.$email.', lastverifytime='.$lastverifytime);
        if($lastverifytime && $lastverifytime + $retry_in > $this->time) {
            $retry_in = $lastverifytime + $retry_in - $this->time;
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_SENDVERIFY_TOO_QUICK, NULL, NULL, NULL, array('retry_in'=>$retry_in));
        }

        // 验证码
        if($_ENV['user']->sendverifyneedseccode($uid, $type, $send, $form, $countrycode, $mobile, $email)) {
            $seccodehidden = urldecode(getgpc('seccodehidden'));
            $seccode = strtoupper(getgpc('seccode'));
        
            if(!$seccodehidden || !$seccode) 
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NEED_SECCODE);
        
            $seccodehidden = $this->base->decode_seccode($seccodehidden);
            if(empty($seccodehidden) || strtoupper($seccodehidden) !== $seccode) {
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_SECCODE);
            }
        }

        $ret = $_ENV['user']->sendverify($user, $type, $operation, $send, $form, $countrycode, $mobile, $email, $this->client_id, $this->appid);
        if(!$ret)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_SENDVERIFY_FAILED);
        
        $result = array('retry_in'=>$retry_in);
        return $result;
    }
    
    // 身份验证
    function oncheckauth() {
        $type_support = array('checkauth', 'updatemobile', 'updateemail');
        $send_support = array('email', 'sms');
        
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $send = $this->input('send');
        if(!$send || !in_array($send, $send_support)) {
            $send = 'sms';
        }
        
        $operation = $this->input('operation');
        if(!$operation || $operation == 'checkauth' || !in_array($operation, $type_support))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $countrycode = $this->input('countrycode');
        $mobile = $this->input('mobile');
        $email = $this->input('email');
        
        if($send == 'sms' && !$countrycode) $countrycode = '+86';
        
        if($send == 'sms') {
            if(!$mobile)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        } else if($send == 'email') {
            if(!$email)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        $verifycode = $this->input('verifycode');
        if($verifycode === NULL)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $vcode = $_ENV['user']->get_verifycode_by_type($verifycode, 'checkauth');
        if(!$vcode || ($vcode['expiredate'] > 0 && $vcode['expiredate'] < $this->time) || $send != $vcode['send'] || $operation != $vcode['operation'])
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
        
        if($send == 'sms' && ($vcode['countrycode'] != $countrycode || $vcode['mobile'] != $mobile))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
        
        if($send == 'email' && $vcode['email'] != $email)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);

        $ret = $_ENV['user']->authcode($uid, $operation, $this->client_id, $this->appid);
        if(!$ret || !$ret['code'])
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_CHECKAUTH_FAILED);
        
        $_ENV['user']->use_verifycode($vcode['codeid']);
        
        $result = array('authcode' => $ret['code']);
        return $result;
    } 
    
    // 手机号
    function onupdatemobile() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input('P');
        $countrycode = $this->input('countrycode');
        $mobile= $this->input('mobile');
        $verifycode = $this->input('verifycode');
        if(!$mobile || $verifycode === NULL)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if(!$countrycode) $countrycode = '+86';
        
        $this->_check_mobile($countrycode, $mobile, $uid);
        
        $vcode = $_ENV['user']->get_verifycode_by_type($verifycode, 'updatemobile');
        if(!$vcode || ($vcode['expiredate'] > 0 && $vcode['expiredate'] < $this->time))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);

        if($vcode['countrycode'] != $countrycode || $vcode['mobile'] != $mobile)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
        
        $user = $_ENV['user']->get_user_by_uid($uid);
        if($user['mobile']) {
            $authcode = $this->input('authcode');
            if(!$authcode)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_AUTHCODE_INVALID);
            
            $acode = $_ENV['user']->get_authcode_by_code($uid, $authcode);
            if(!$acode || $acode['usedate'] || $acode['operation'] != 'updatemobile' || ($acode['expiredate'] > 0 && $acode['expiredate'] < $this->time))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_AUTHCODE_INVALID);
        }

        if(!$_ENV['user']->update_mobile($uid, $countrycode, $mobile, 1))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_UPDATE_MOBILE_FAILED);
        
        $_ENV['user']->use_verifycode($vcode['codeid']);
        if($acode) {
            $_ENV['user']->use_authcode($acode['codeid']);
        }

        $result = $_ENV['user']->_format_user($uid);
        return $result;
    } 
    
    // 修改邮箱
    function onupdateemail() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input('P');
        $email = $this->input('email');
        $verifycode = $this->input('verifycode');
        if(!$email)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $this->_check_email($email, ($uid&&$this->user&&$this->user['username'])?$this->user['username']:'');
        
        $emailstatus = 0;
        if($verifycode !== NULL) {
            if(!$verifycode)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
            
            $vcode = $_ENV['user']->get_verifycode_by_type($verifycode, 'updateemail');
            if(!$vcode || ($vcode['expiredate'] > 0 && $vcode['expiredate'] < $this->time))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);

            if($vcode['email'] != $email)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
            
            $emailstatus = 1;
        }
        
        $user = $_ENV['user']->get_user_by_uid($uid);
        if($user['email']) {
            $authcode = $this->input('authcode');
            if(!$authcode)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_AUTHCODE_INVALID);
            
            $acode = $_ENV['user']->get_authcode_by_code($uid, $authcode);
            if(!$acode || $acode['usedate'] || $acode['operation'] != 'updateemail' || ($acode['expiredate'] > 0 && $acode['expiredate'] < $this->time))
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_AUTHCODE_INVALID);
        }

        if(!$_ENV['user']->update_email($uid, $email, $emailstatus))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_UPDATE_EMAIL_FAILED);
        
        $_ENV['user']->use_verifycode($vcode['codeid']);
        if($acode) {
            $_ENV['user']->use_authcode($acode['codeid']);
        }
        
        if(!$emailstatus) {
            // send mail
            $_ENV['user']->sendverify($user, 'activeemail', '', 'email', 'url', '', '', $email, $_ENV['oauth2']->client_id, $_ENV['oauth2']->appid);
        }
        
        $result = $_ENV['user']->_format_user($uid);
        return $result;
    } 

	function _check_mobile($countrycode, $mobile, $uid=0) {
		if(!$_ENV['user']->check_countrycode($countrycode)) 
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_COUNTRYCODE_NOT_SUPPORT);
        
		$mobile = addslashes(trim(stripslashes($mobile)));
		if(!$_ENV['user']->check_mobileformat($countrycode, $mobile)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_MOBILE_FORMAT_ILLEGAL);
		} elseif(!$_ENV['user']->check_mobileaccess($countrycode, $mobile)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_MOBILE_ACCESS_ILLEGAL);
        } elseif($_ENV['user']->check_mobileexists($countrycode, $mobile, $uid)) {
			$this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_MOBILE_EXISTS);
		}

		return true;
	}
    
    function oncheckemail() {
        $this->init_input();
        $email = $this->input('email');
        
        if(!$email)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $this->_check_email($email);
        
        return array('email' => $email);
    }
    
    function oncheckmobile() {
        $this->init_input();
        $countrycode = $this->input('countrycode');
        $mobile = $this->input('mobile');
        
        if(!$mobile)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        if(!$countrycode) $countrycode = '+86';
        $this->_check_mobile($countrycode, $mobile);
        
        return array('countrycode' => $countrycode, 'mobile' => $mobile);
    }

    function oncheckusername() {
        $this->init_input();
        $username = $this->input('username');
        
        if(!$username)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $this->init_user();
        if(!$this->uid || !$this->user || $this->user['username'] != $username)
            $this->_check_username($username);
        
        return array('username' => $username);
    }
    
    function oncheckpwd() {
        $this->init_input();
        $password = $this->input['password'];
        
        if(!$password)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $this->_check_password($password);
        
        return array();
    }
    
}
