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
        if(!$this->init_user())
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TOKEN);
        
        $uid = $this->uid;

        if(!$uid)
            $this->error(API_HTTP_UNAUTHORIZED, API_ERROR_TOKEN);

        $result = $_ENV['user']->_format_user($uid);
        return $result;
    }
    
    function onconnect() {
        if(!$this->init_user())
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TOKEN);
        
        $uid = $this->uid;

        if(!$uid)
            $this->error(API_HTTP_UNAUTHORIZED, API_ERROR_TOKEN);

        // connect
        $connects = array();
        $types = $_ENV['oauth2']->get_client_connect_support($this->client_id);
        foreach($types as $connect_type) {
            $connect = $this->load_connect($connect_type);
            if($connect && $connect->_token_extras()) {
                $extras = $connect->get_token_extras($uid);
                if($extras && is_array($extras)) {
                    $connects[] = $extras;
                } else {
                    $this->error(OAUTH2_HTTP_BAD_REQUEST, API_ERROR_USER_GET_CONNECT_FAILED);
                }
            }
        }
        
        return array('uid' => $uid, 'connect' => $connects);
    }
    
    function ongetloginuser() {
        if(!$this->init_user())
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TOKEN);
        
        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TOKEN);
        
        $user = $_ENV['user']->get_user_by_uid($uid);
        if(!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_TOKEN);
        
        $result['uid'] = $uid;
        $result['uname'] = $user['username'];
        $result['portrait'] = '';
        return $result;
    }

    function onregister() {
        $this->init_input('P');

        $username = rawurldecode($this->input['username']);
        $password =  $this->input['password'];
        $email = $this->input('email');
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
        
        //应用有效性
        if($_ENV['oauth2']->checkAppCredentials($app) === FALSE)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_CLIENT);
        
        //$client_secret = $client[1];
        //if(!$_ENV['oauth2']->checkClientCredentials($client_id, $client_secret))
        //  $this->error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
        
        //签名校验
        if(!$_ENV['user']->_check_sign($sign, $username, $client_id, $expire))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NO_AUTH);
        
        //用户名有效性
        $this->_check_username($username);

        //email有效性
        $this->_check_email($email);

        //添加用户
        $uid = $_ENV['user']->add_user($username, $password, $email, 0, '', '', '');
        if(!$uid || !$user = $_ENV['user']->get_user_by_uid($uid)){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_ADD_FAILED);
        }
        
        //生成Access Token
        //$_ENV['oauth2']->uid = $uid;
        //$token = $_ENV['oauth2']->createAccessToken($client_id, '');
        
        // send mail
        $_ENV['user']->sendverify($user, 'activeemail', '', 'email', 'url', '', '', $email, $client_id, $appid);
        
        $result = $_ENV['user']->_format_user($uid);
        return $result;
    }
    
    function onchangepwd() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input('P');
        $oldpassword =  $this->input['oldpassword'];
        $newpassword =  $this->input['newpassword'];
        $sign = $this->input('sign');
        $expire = $this->input('expire');
        
        if(!$uid || !$oldpassword || !$newpassword) 
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
        
        $md5password =  md5(md5($oldpassword).$auser['salt']);
        if($auser['password'] != $md5password) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_PW);
        }

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
        
        // 已登录
        $this->check_login();
        if($this->user && $this->user['uid']) 
            $this->redirect($redirect);
        
        $auth_url = BASE_API.'/oauth/2.0/authorize?response_type=none&client_id='.API_AUTH_CLIENTID.'&redirect_uri='.$redirect;
        $this->redirect($auth_url);
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

	function oncheckemail() {
		$this->init_input();
		$email = $this->input('email');
		return $this->_check_email($email);
	}

	function oncheckusername() {
		$this->init_input();
		$username = $this->input('username');
		if(($status = $this->_check_username($username)) < 0) {
			return $status;
		} else {
			return 1;
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

    // 更新用户名
    function onupdate() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);

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

        $salt = $user['salt'] ? $user['salt'] : substr(uniqid(rand()), -6);

        if (!$_ENV['user']->complete($uid, $username, $email, $password, $salt))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_COMPLETE_FAILED);
        
        $result = $_ENV['user']->_format_user($uid);
        return $result;
    }
}
