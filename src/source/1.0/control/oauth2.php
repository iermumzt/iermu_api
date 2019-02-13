<?php

!defined('IN_API') && exit('Access Denied');

define('API_USER_REG_SUCCEED', 0);
define('API_USER_CHECK_USERNAME_FAILED', -1);
define('API_USER_USERNAME_BADWORD', -2);
define('API_USER_USERNAME_EXISTS', -3);
define('API_USER_EMAIL_FORMAT_ILLEGAL', -4);
define('API_USER_EMAIL_ACCESS_ILLEGAL', -5);
define('API_USER_EMAIL_EXISTS', -6);
define('API_USER_REG_FAILED', -7);

define('API_LOGIN_SUCCEED', 0);
define('API_LOGIN_ERROR_FOUNDER_PW', -1);
define('API_LOGIN_ERROR_PW', -2);
define('API_LOGIN_ERROR_NOT_EXISTS', -3);
define('API_LOGIN_ERROR_SECCODE', -4);
define('API_LOGIN_ERROR_FAILEDLOGIN', -5);
define('API_LOGIN_ERROR_SUBMIT', -6);

class oauth2control extends base {
    
    var $cookie_status = 1;

    function __construct() {
        $this->oauth2control();
    }

    function oauth2control() {
        parent::__construct();
        $this->load('oauth2');
        $this->load('user');
        $_ENV['oauth2']->setVariable('force_api_response', 0);  
    }

    function ontoken() {
        $this->init_input();
        $this->log('oauth2211', 'start token, input='.json_encode($this->input));
        $_ENV['oauth2']->grantAccessToken();
    }
    
    function onauthorize() {
        $this->init_input();
        $this->log('oauth2211', 'start authorize, input='.json_encode($this->input));
        $filters = array(
            "client_id" => array("filter" => FILTER_VALIDATE_REGEXP, "options" => array("regexp" => OAUTH2_CLIENT_ID_REGEXP), "flags" => FILTER_REQUIRE_SCALAR),
            "response_type" => array("filter" => FILTER_VALIDATE_REGEXP, "options" => array("regexp" => OAUTH2_AUTH_RESPONSE_TYPE_REGEXP), "flags" => FILTER_REQUIRE_SCALAR),
            "redirect_uri" => array("filter" => FILTER_SANITIZE_URL),
            "state" => array("flags" => FILTER_REQUIRE_SCALAR),
            "scope" => array("flags" => FILTER_REQUIRE_SCALAR),
            "display" => array("flags" => FILTER_REQUIRE_SCALAR),
            "force_login" => array("flags" => FILTER_REQUIRE_SCALAR),
            "confirm_login" => array("flags" => FILTER_REQUIRE_SCALAR),
            "login_type" => array("flags" => FILTER_REQUIRE_SCALAR),
            "bind_uid" => array("flags" => FILTER_REQUIRE_SCALAR),
            "connect_type" => array("flags" => FILTER_REQUIRE_SCALAR),
        );
        
        $input = filter_input_array(INPUT_GET, $filters);
        
        //展示类型
        $this->view->assign('display', $input['display']);

        if (!$input["client_id"]) 
          $this->show_error(OAUTH2_ERROR_INVALID_CLIENT);
        
        //应用认证
        $_ENV['oauth2']->client_id = $input["client_id"];
        $app = $_ENV['oauth2']->getAppCredentials();
        $_ENV['oauth2']->appid = $app['appid'];
        $_ENV['oauth2']->base->app = $app;

        if ($_ENV['oauth2']->checkAppCredentials($app) === FALSE)
            $this->show_error(OAUTH2_ERROR_INVALID_CLIENT);
        
        // redirect_uri is not required if already established via other channels
        // check an existing redirect URI against the one supplied
        $redirect_uri = $_ENV['oauth2']->getRedirectUri($input["client_id"]);
        
        // At least one of: existing redirect URI or input redirect URI must be specified
        if (!$redirect_uri && !$input["redirect_uri"])
          $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);
        
        // getRedirectUri() should return FALSE if the given client ID is invalid
        // this probably saves us from making a separate db call, and simplifies the method set
        if ($redirect_uri === FALSE)
          $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);

        // If there's an existing uri and one from input, verify that they match
        if ($redirect_uri && $input["redirect_uri"]) {
          // Ensure that the input uri starts with the stored uri
          if (strcasecmp(substr($input["redirect_uri"], 0, strlen($redirect_uri)), $redirect_uri) !== 0)
            $this->show_error(OAUTH2_ERROR_REDIRECT_URI_MISMATCH);
        }
        elseif ($redirect_uri) { // They did not provide a uri from input, so use the stored one
          $input["redirect_uri"] = $redirect_uri;
        }
        
        // type and client_id are required
        if (!$input["response_type"])
          $this->show_error(OAUTH2_ERROR_INVALID_REQUEST, 'Invalid response type.');

        // Check requested auth response type against the list of supported types
        if (array_search($input["response_type"], $_ENV['oauth2']->getSupportedAuthResponseTypes()) === FALSE)
          $this->show_error(OAUTH2_ERROR_UNSUPPORTED_RESPONSE_TYPE);
        
        /*
        // Restrict clients to certain authorization response types
        if ($this->checkRestrictedAuthResponseType($input["client_id"], $input["response_type"]) === FALSE)
          $this->errorDoRedirectUriCallback($input["redirect_uri"], OAUTH2_ERROR_UNAUTHORIZED_CLIENT, NULL, NULL, $input["state"]);

        // Validate that the requested scope is supported
        if ($input["scope"] && !$this->checkScope($input["scope"], $this->getSupportedScopes()))
          $this->errorDoRedirectUriCallback($input["redirect_uri"], OAUTH2_ERROR_INVALID_SCOPE, NULL, NULL, $input["state"]);
        */
        
        // 登录状态
        // TODO: confirm_login,login_type
        if($input["force_login"]) {
            $this->check_relogin();
        } else {
            $this->check_login();
        }
        $input["force_login"] = 1;
        
        if(!$this->user || !$this->user['uid']) {
            $input['connect_type'] = $input['connect_type']?$input['connect_type']:$_ENV['oauth2']->get_client_connect_type($input["client_id"]);
            $connect_type = $input['connect_type'];
            if($connect_type > 0 && $this->connect_auth($input)) {
                return;
            } else {
                $this->view->assign('connect_type', $connect_type);
                $this->view->assign('params', $input);
                $this->view->assign('page_url', rawurlencode($this->page_url()));
                $this->view->display('oauth2_login');
            }
        } else {
            $_ENV['oauth2']->uid = $this->user['uid'];
            $_ENV['oauth2']->finishClientAuthorization(true, $input);
        }
    }
    
    function onreg() {
        if($this->submitcheck()) {
            $email = getgpc('email', 'P');
            $username = getgpc('username', 'P');
            $password = getgpc('password', 'P');
            
            if(($status = $this->_check_email($email)) < 0) {
                return $status;
            }
            
            if(($status = $this->_check_username($username)) < 0) {
                return $status;
            }
            
            $uid = $_ENV['user']->add_user($username, $password, $email);
            return $uid?API_USER_REG_SUCCEED:API_USER_REG_FAILED;
        }
        
        $this->init_input();
        $url = $this->input['u'];
        $this->view->assign('url', rawurldecode($url));
        $this->view->display('oauth2_reg');
    }
    
    function _check_username($username) {
        $username = addslashes(trim(stripslashes($username)));
        if(!$_ENV['user']->check_username($username)) {
            return API_USER_CHECK_USERNAME_FAILED;
/*      } elseif($username != $_ENV['user']->replace_badwords($username)) {
            return API_USER_USERNAME_BADWORD;*/
        } elseif($_ENV['user']->check_usernameexists($username)) {
            return API_USER_USERNAME_EXISTS;
        }
        return 1;
    }

    function _check_email($email) {
        if(!$_ENV['user']->check_emailformat($email)) {
            return API_USER_EMAIL_FORMAT_ILLEGAL;
        } elseif(!$_ENV['user']->check_emailaccess($email)) {
            return API_USER_EMAIL_ACCESS_ILLEGAL;
        } elseif(!$this->settings['doublee'] && $_ENV['user']->check_emailexists($email)) {
            return API_USER_EMAIL_EXISTS;
        } else {
            return 1;
        }
    }
    
    function onlogin() {
        $authkey = md5(API_KEY.$_SERVER['HTTP_USER_AGENT'].$this->onlineip);

        $username = getgpc('username', 'P');
        $password = getgpc('password', 'P');

        $errorcode = API_LOGIN_ERROR_SUBMIT;
        if($this->submitcheck()) {
            $failedlogin = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."failedlogins WHERE ip='$this->onlineip'");
            if($failedlogin['count'] > 10) {
                if($this->time - $failedlogin['lastupdate'] < 15 * 60) {
                    $errorcode = API_LOGIN_ERROR_FAILEDLOGIN;
                } else {
                    $expiration = $this->time - 15 * 60;
                    $this->db->query("DELETE FROM ".API_DBTABLEPRE."failedlogins WHERE lastupdate<'$expiration'");
                }
            } else {
                /*
                $seccodehidden = urldecode(getgpc('seccodehidden', 'P'));
                $seccode = strtoupper(getgpc('seccode', 'P'));
                $seccodehidden = $this->authcode($seccodehidden, 'DECODE', $authkey);
                require API_SOURCE_ROOT.'./lib/seccode.class.php';
                seccode::seccodeconvert($seccodehidden);
                if(empty($seccodehidden) || $seccodehidden != $seccode) {
                    $errorcode = API_LOGIN_ERROR_SECCODE;
                } else {
                */
                    $errorcode = API_LOGIN_SUCCEED;
                    $this->user['username'] = $username;
                    //用户验证
                    $auser = $_ENV['user']->get_user_by_username($username);
                    if(empty($auser)) {
                        $auser = $_ENV['user']->get_user_by_email($username);
                    }
                    if(!empty($auser)) {
                        $md5password =  md5(md5($password).$auser['salt']);
                        if($auser['password'] != $md5password) {
                            $errorcode = API_LOGIN_ERROR_PW;
                        }
                    } else {
                        $errorcode = API_LOGIN_ERROR_NOT_EXISTS;
                    }

                    if($errorcode == API_LOGIN_SUCCEED) {
                        $this->view->sid = $this->set_login($auser['uid']);
                        $this->set_relogin($auser['uid']);
                    } else {
                        if(empty($failedlogin)) {
                            $expiration = $this->time - 15 * 60;
                            $this->db->query("DELETE FROM ".API_DBTABLEPRE."failedlogins WHERE lastupdate<'$expiration'");
                            $this->db->query("INSERT INTO ".API_DBTABLEPRE."failedlogins SET ip='$this->onlineip', count=1, lastupdate='$this->time'");
                        } else {
                            $this->db->query("UPDATE ".API_DBTABLEPRE."failedlogins SET count=count+1,lastupdate='$this->time' WHERE ip='$this->onlineip'");
                        }
                    }
                    //}
            }
        }
        return $errorcode;
    }
    
    function onlogout() {
        $this->logout();
        $_ENV['oauth2']->logout();
        return array();
    }
    
    function show_error($error, $description="") {
        $this->log('oauth2211', 'show error, error='.$error);
    	//error code处理
    	if($error && $p = strpos($error, ':')) {
    		$error_code = intval(substr($error, 0, $p));
    		$error = substr($error, $p+1);
    	}
        $this->showmessage('error', 'oauth2_error', array('error'=>$error));
    }
    
    function page_url() {
        $url = 'http';
        if($_SERVER["HTTPS"]) {
            $url .= "s";
        }
        $url .= "://";

        if($_SERVER["SERVER_PORT"] != "80") {
            $url .= $_SERVER["HTTP_HOST"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $url .= $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        }
        return $url;
    }
    
    function connect_auth($input) {
        $connect_type = $input['connect_type'];
        $connect = $this->load_connect($connect_type);
        if(!$connect) 
            $this->show_error(OAUTH2_ERROR_INVALID_CLIENT);
        
        // no need to connect auth
        if(!$connect->_connect_auth()) 
            return false;
        
        $state = $this->_encode_state($input);
        if($state) $connect->set_state($state);
        
        $authorize_url = $connect->get_authorize_url($input['display'], $input['force_login']);
        if(!$authorize_url)
            $this->show_error(OAUTH2_ERROR_INVALID_CLIENT);

        log::access_log(API_HTTP_FOUND, $authorize_url);

        header("Location: ".$authorize_url);
        exit();
    }
    
    function onconnect_success() {
        $this->init_input('G');
        
        $code = $this->input('code');
        $state = $this->input('state');
        
        if(!$code || !$state)
            $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);
        
        $input = $this->_decode_state($state);
        if(!$input || !is_array($input) || !$input['redirect_uri'])
            $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);

        if (!$input["client_id"]) 
          $this->show_error(OAUTH2_ERROR_INVALID_CLIENT);
        
        //应用认证
        $_ENV['oauth2']->client_id = $input["client_id"];
        $app = $_ENV['oauth2']->getAppCredentials();
        $_ENV['oauth2']->appid = $app['appid'];
        $_ENV['oauth2']->base->app = $app;

        if ($_ENV['oauth2']->checkAppCredentials($app) === FALSE)
            $this->show_error(OAUTH2_ERROR_INVALID_CLIENT);
        
        // redirect_uri is not required if already established via other channels
        // check an existing redirect URI against the one supplied
        $redirect_uri = $_ENV['oauth2']->getRedirectUri($input["client_id"]);
        
        // At least one of: existing redirect URI or input redirect URI must be specified
        if (!$redirect_uri && !$input["redirect_uri"])
          $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);
        
        // getRedirectUri() should return FALSE if the given client ID is invalid
        // this probably saves us from making a separate db call, and simplifies the method set
        if ($redirect_uri === FALSE)
          $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);

        // If there's an existing uri and one from input, verify that they match
        if ($redirect_uri && $input["redirect_uri"]) {
          // Ensure that the input uri starts with the stored uri
          if (strcasecmp(substr($input["redirect_uri"], 0, strlen($redirect_uri)), $redirect_uri) !== 0)
            $this->show_error(OAUTH2_ERROR_REDIRECT_URI_MISMATCH);
        }
        elseif ($redirect_uri) { // They did not provide a uri from input, so use the stored one
          $input["redirect_uri"] = $redirect_uri;
        }
        
        // type and client_id are required
        if (!$input["response_type"])
          $this->show_error(OAUTH2_ERROR_INVALID_REQUEST, 'Invalid response type.');

        // Check requested auth response type against the list of supported types
        if (array_search($input["response_type"], $_ENV['oauth2']->getSupportedAuthResponseTypes()) === FALSE)
          $this->show_error(OAUTH2_ERROR_UNSUPPORTED_RESPONSE_TYPE);
        
        /*
        // Restrict clients to certain authorization response types
        if ($this->checkRestrictedAuthResponseType($input["client_id"], $input["response_type"]) === FALSE)
          $this->errorDoRedirectUriCallback($input["redirect_uri"], OAUTH2_ERROR_UNAUTHORIZED_CLIENT, NULL, NULL, $input["state"]);

        // Validate that the requested scope is supported
        if ($input["scope"] && !$this->checkScope($input["scope"], $this->getSupportedScopes()))
          $this->errorDoRedirectUriCallback($input["redirect_uri"], OAUTH2_ERROR_INVALID_SCOPE, NULL, NULL, $input["state"]);
        */
        
        $connect_type = $input['connect_type'];
        $connect = $this->load_connect($connect_type);
        if(!$connect) 
             $this->show_error(OAUTH2_ERROR_INVALID_CLIENT);
        
        $connect->set_state($state);
        $connect_token = $connect->get_connect_token();
        $this->log('oauth connect token', json_encode($connect_token));
        if(!$connect_token || !$connect_token['access_token'] || !$connect_token['refresh_token']) 
             $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);
        
        $bind_uid = $input['bind_uid'];
        $connect_uid = $connect_token['uid'];
        
        if(!$connect_uid)
            $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);
        
        if($bind_uid) {
            $bind_connect = $_ENV['user']->get_connect_by_uid($connect_type, $bind_uid);
            if($bind_connect && $bind_connect['connect_uid'] != $connect_uid)
                $this->showmessage('error', 'connect_alreay_bind');
        }
        
        $user = $_ENV['user']->get_user_by_connect_uid($connect_type, $connect_uid);
        if(!$user) {
            if($bind_uid) {
                $user = $_ENV['user']->get_user_by_uid($bind_uid);
                if(!$user)
                    $this->showmessage('error', 'connect_bind_user_not_exist');
                
                $uid = $user['uid'];
                if($_ENV['user']->get_connect_by_uid($connect_type, $uid)) {
                    $_ENV['user']->update_connect_user($uid, $connect_type, $connect_token); 
                } else {
                    $_ENV['user']->add_connect_user($uid, $connect_type, $connect_token);
                }
            } else {
                $username = $this->gen_connect_username($connect_type, $connect_token['uname']);
                if(!$username)
                    $this->show_error(API_ERROR_USER_USERNAME_EXISTS);
            
                $uid = $_ENV['user']->add_user($username, '', '');
                $_ENV['user']->add_connect_user($uid, $connect_type, $connect_token);
            }
        } else {
            $uid = $user['uid'];
            if($bind_uid && $bind_uid != $uid)
                $this->showmessage('error', 'connect_alreay_bind_other');
            
            $_ENV['user']->update_connect_user($uid, $connect_type, $connect_token); 
        }
        
        if(!$uid) {
            if($bind_uid) {
                $this->showmessage('error', 'connect_bind_failed');
            } else {
                $this->show_error(OAUTH2_ERROR_INVALID_REQUEST);
            }
        }
        
        // 设置登录状态
        $this->set_login($uid);
        $this->set_relogin($uid);

        $result = $_ENV['user']->_format_user($uid);

        $this->set_userinfo($result['username'], $uid);

        $_ENV['oauth2']->uid = $uid;
        $_ENV['oauth2']->finishClientAuthorization(true, $input);
        
        return $token;
    }
    
    function gen_connect_username($connect_type, $username) {
        if(!$connect_type || !$username)
            return false;
        if(!$_ENV['user']->check_usernameexists($username)) 
            return $username;
        $username = $username.'_'.rand(100,999);
        if(!$_ENV['user']->check_usernameexists($username)) 
            return $username;
        return false;
    }
    
    function _encode_state($data) {
        if(!$data) return '';
        return base64_encode(json_encode($data));
    }
    
    function _decode_state($data) {
        if(!$data) return array();
        return json_decode(base64_decode($data), true);
    }

}
