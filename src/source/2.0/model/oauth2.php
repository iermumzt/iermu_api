<?php

!defined('IN_API') && exit('Access Denied');

require API_SOURCE_ROOT.'lib/oauth2.class.php';

class oauth2model extends OAuth2 {

    var $db;
    var $base;

    var $grant_type;
    var $client_id;
    var $appid;
    var $access_token;
    var $uid;
    var $user = array();
    
    var $app;
    
    var $auth_status = 0;
    var $partner_push = 0;
    
    var $partner_id;
    var $partner;
    var $connect_domain;
    var $client_partner_type;

    function __construct(&$base) {
        parent::__construct();
        $this->oauth2model($base);
        $this->base->load('user');
        $this->base->load('qrcode');
    }

    function oauth2model(&$base) {
        $this->base = $base;
        $this->db = $base->db;
        $this->setVariable('display_error', API_ERROR_DISPLAY_ERROR);
        $this->setVariable('force_api_response', 1);    
    }

    //初始化app
    public function getAppCredentials() {
        if(empty($this->client_id)) 
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);

        $this->base->load('app');
        $app = $_ENV['app']->get_app_by_client_id($this->client_id);
        return $app;
    }
    
    //验证应用有效性
    public function checkAppCredentials($app) {
        //状态判断
        return ($app && $app['status'] && ($app['status']>0 || $app['status'] == -3))?TRUE:FALSE;
    }

    /**
    * Implements OAuth2::checkClientCredentials().
    *
    * TO-DO:secret加密
    */
    public function checkClientCredentials($client_id, $client_secret = NULL) {
        if(!$client_id)
            return FALSE;

        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");

        //状态判断
        if($client && $client['status']<0 && $client['status'] != -3)
            return FALSE;
        
        if($client_secret === NULL)
            return ($client !== FALSE)?$client:FALSE;
        
        return ($client["client_secret"] == $client_secret)?$client:FALSE;
    }

    /**
    * Implements OAuth2::getAccessToken().
    */
    public function getAccessToken($oauth_token) {
        if(!$oauth_token)
            return NULL;
        

        $token = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_access_token WHERE oauth_token='$oauth_token'");

        return empty($token)?NULL:$token;
    }

    /**
    * Implements OAuth2::setAccessToken().
    */
    protected function setAccessToken($oauth_token, $client_id, $expires, $scope = NULL, $partner_push = NULL) {
        if(!$oauth_token || !$this->appid || !$client_id || !$expires)
            return FALSE;

        $this->db->query("INSERT INTO ".API_DBTABLEPRE."oauth_access_token (oauth_token, appid, client_id, expires, scope, dateline, uid, partner_push) VALUES ('$oauth_token', '".$this->appid."', '$client_id', '$expires', '$scope', '". $this->base->time ."', '$this->uid', '$partner_push')");
        return $oauth_token;
    }
    
    /**
    * Implements OAuth2::getRefreshToken().
    */
    protected function getRefreshToken($refresh_token) {
        if(!$refresh_token)
            return NULL;
    
        $token = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_refresh_token WHERE refresh_token='$refresh_token'");
        return empty($token)?NULL:$token;
    }
    
    /**
    * Implements OAuth2::setRefreshToken().
    */
    protected function setRefreshToken($refresh_token, $client_id, $expires, $scope = NULL) {
        if(!$refresh_token || !$this->appid || !$client_id || !$expires)
            return FALSE;
    
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."oauth_refresh_token (refresh_token, appid, client_id, expires, scope, dateline, uid) VALUES ('$refresh_token', '".$this->appid."', '$client_id', '$expires', '$scope', '". $this->base->time ."', '$this->uid')");
        return $refresh_token;
    }
    
    /**
    * Implements OAuth2::unsetRefreshToken().
    */
    protected function unsetRefreshToken($refresh_token) {
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_refresh_token SET expires=0 WHERE refresh_token='$refresh_token'");
        return;
    }

    /**
    * Overrides OAuth2::getSupportedGrantTypes().
    */
    public function getSupportedGrantTypes() {
        return array(
            OAUTH2_GRANT_TYPE_AUTH_CODE,
            OAUTH2_GRANT_TYPE_QRCODE,
            OAUTH2_GRANT_TYPE_USER_CREDENTIALS,
            OAUTH2_GRANT_TYPE_REFRESH_TOKEN,
            OAUTH2_GRANT_TYPE_USER_MOBILE,
            OAUTH2_GRANT_TYPE_CLIENT_CREDENTIALS,
            OAUTH2_GRANT_TYPE_DOMAIN
        );
    }

    public function getSecCheckGrantTypes() {
        return array(
            OAUTH2_GRANT_TYPE_USER_CREDENTIALS,
            OAUTH2_GRANT_TYPE_USER_MOBILE
        );
    }

    /**
    * Overrides OAuth2::checkUserCredentials().
    */
    protected function checkUserCredentials($client_id, $username, $password, $countrycode, $mobile) {
        if(!$client_id || !$password) 
            return FALSE;
        //用户
        if($username) {
            $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE username='$username' OR email='$username'");
        } else if($mobile) {
            if(!$countrycode) $countrycode = '+86';
    		$user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE mobile='$mobile' AND countrycode='$countrycode'");
        }
        
        if(!$user)
             $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);

        $this->uid = $user['uid'];

        // 组织用户禁止登陆
        $check = $this->db->fetch_first("SELECT org_id FROM ".API_DBTABLEPRE."member_org WHERE uid='".$this->uid."'");
        if($check)
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);

        //密码
        $passwordmd5 = preg_match('/^\w{32}$/', $password) ? $password : md5($password);
        if(!$user['password'] || $user['password'] != md5($passwordmd5.$user['salt']))
             $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_PASSWORD);
        
        return array(
            'scope' => 'basic',
        );
    }

    protected function checkOrgCredentials($client_id, $domain, $username, $password){
        if(!$client_id || !$password || !$domain || !$username) 
            return FALSE;
        //用户
        if($domain) {
            $user_id=$this->db->fetch_first("SELECT a.uid FROM ".API_DBTABLEPRE."member_connect a LEFT JOIN ".API_DBTABLEPRE."org b ON a.connect_type=b.connect_type WHERE b.domain='$domain' AND a.connect_uid='$username'");
            if(!$user_id)
                $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);
            $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid=".$user_id['uid']);
        }
        
        if(!$user)
             $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);

        $this->uid = $user['uid'];
        
        //密码
        $passwordmd5 = preg_match('/^\w{32}$/', $password) ? $password : md5($password);
        if(!$user['password'] || $user['password'] != md5($passwordmd5.$user['salt']))
             $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_PASSWORD);
        
        return array(
            'scope' => 'basic',
        );

    }
    
    protected function checkMobileCredentials($client_id, $countrycode, $mobile, $verifycode) {
        if(!$client_id || !$mobile || !$verifycode) 
            return FALSE;
        
        //用户
        if(!$countrycode) $countrycode = '+86';
        $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE mobile='$mobile' AND countrycode='$countrycode'");
        
        if(!$user)
             $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);

        $this->uid = $user['uid'];

        // 组织用户禁止登陆
        $check = $this->db->fetch_first("SELECT org_id FROM ".API_DBTABLEPRE."member_org WHERE uid='".$this->uid."'");
        if($check)
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);
        
        $vcode = $_ENV['user']->get_verifycode_by_type($verifycode, 'login');
        if(!$vcode || ($vcode['expiredate'] > 0 && $vcode['expiredate'] < $this->base->time))
            $this->errorFailedLogin(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);

        if($vcode['countrycode'] != $countrycode || $vcode['mobile'] != $mobile)
            $this->errorFailedLogin(API_HTTP_BAD_REQUEST, API_ERROR_USER_VERIFYCODE_INVALID);
        
        $_ENV['user']->use_verifycode($vcode['codeid']);
        
        return array(
            'scope' => 'basic',
        );
    }

    /**
    * Overrides OAuth2::createAccessToken().
    */
    public function createAccessToken($client_id, $scope = NULL, $partner_push = NULL) {
        
        //默认scope处理
        if($scope == NULL) $scope = 'basic';
        
        $partner_push = $partner_push?1:0;

        $access_token_lifetime = $this->getVariable('access_token_lifetime', OAUTH2_DEFAULT_ACCESS_TOKEN_LIFETIME);
        $token = array(
            "expires_in" => $access_token_lifetime,
            "access_token" => $this->genAccessToken(),
            "scope" => $scope,
            "uid" => strval($this->uid)
        );

        if($this->setAccessToken($token["access_token"], $client_id, $this->base->time + $access_token_lifetime, $scope, $partner_push)  === FALSE) 
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST);
        
        $token['access_token'] = $this->appid.'.'.$client_id.'.'.$access_token_lifetime.'.'.$this->base->time.'.'.$token['access_token'];

        //Refresh Token
        if (in_array(OAUTH2_GRANT_TYPE_REFRESH_TOKEN, $this->getSupportedGrantTypes()) && !$this->getVariable('_remove_refresh_token')) {
            $refresh_token_lifetime = $this->getVariable('refresh_token_lifetime', OAUTH2_DEFAULT_REFRESH_TOKEN_LIFETIME);
            $token["refresh_token"] = $this->genAccessToken();
            
            $this->setRefreshToken($token["refresh_token"], $client_id, $this->base->time + $refresh_token_lifetime, $scope);
            $token['refresh_token'] = $this->appid.'.'.$client_id.'.'.$refresh_token_lifetime.'.'.$this->base->time.'.'.$token['refresh_token'];

            // 过期处理
            if($this->getVariable('_old_refresh_token') && ($this->getVariable('_old_refresh_token') != $token["refresh_token"]))
                $this->unsetRefreshToken($this->getVariable('_old_refresh_token'));
        }

        $this->access_token = $token["access_token"];

        return $token;
    }
    
    public function createQRCode($client_id, $scope = NULL, $hardware = NULL, $hardware_id = NULL) {
        // 默认处理
        if($scope == NULL) $scope = 'basic';
        
        $client = $this->get_client_by_client_id($client_id);
        if(!$client)
            $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);

        // 硬件
        if($hardware && $client['hardware'] && $hardware != $client['hardware'])
            $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
        
        $hardware = $client['hardware'];
        if($hardware_id == NULL) $hardware_id = '';
        
        $code = $this->genAccessToken();
        $qrcode_lifetime = $this->getVariable('qrcode_lifetime', OAUTH2_DEFAULT_QRCODE_LIFETIME);
        
        $qrcode = $this->setQRCode($code, $client_id, $client['client_secret'], $client['appid'], $client['title'], $client['platform'], $hardware, $hardware_id, $this->base->time + $qrcode_lifetime, $scope);
        if(!$qrcode || !$qrcode['cid'] || !$qrcode['key'])
            $this->errorJsonResponse(API_HTTP_INTERNAL_SERVER_ERROR, OAUTH2_ERROR_QRCODE_CREATE_FAILED);

        $qrcode_url = $this->getQRCodeURL($qrcode);
        if(!$qrcode_url)
            $this->errorJsonResponse(API_HTTP_INTERNAL_SERVER_ERROR, OAUTH2_ERROR_QRCODE_CREATE_FAILED);
        
        $result = array(
            "code" => $code,
            "qrcode_url" => $qrcode_url,
            "expires_in" => intval($qrcode_lifetime),
            "scope" => $scope
        );
        
        return $result;
    }
    
    protected function setQRCode($code, $client_id, $client_secret, $appid, $title, $platform, $hardware, $hardware_id, $expires, $scope = NULL) {
        if(!$code || !$appid || !$client_id || !$expires)
            return FALSE;

        $this->db->query("INSERT INTO ".API_DBTABLEPRE."oauth_qrcode (code, appid, client_id, expires, scope, dateline, title, platform, hardware, hardware_id, status) VALUES ('$code', '$appid', '$client_id', '$expires', '$scope', '". $this->base->time ."', '$title', '$platform', '$hardware', '$hardware_id', '0')");
        $cid = $this->db->insert_id();
        
        $key = $_ENV['qrcode']->qrcode_key($cid, $client_secret);
        if(!$key)
            return false;
        
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_qrcode SET `key`='$key' WHERE cid='$cid'");
        
        return $_ENV['qrcode']->get_qrcode_by_cid($cid);
    }
    
    protected function getQRCodeURL($qrcode) {
        if(!$qrcode || !$qrcode['cid'] || !$qrcode['key'])
            return false;
        
        $qrcode_baseurl = $this->getVariable('qrcode_baseurl', OAUTH2_QRCODE_BASEURL);
        if(!$qrcode_baseurl)
            return false;
        
        return $qrcode_baseurl.$qrcode['key'];
    }
    
    protected function checkFailedLogin() {
        // 屏蔽非主版本应用
        if($this->appid > 1)
            return true;
        
        $failedlogin = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."failedlogins WHERE ip='".$this->base->onlineip."'");
        // 超过3次需要验证码
        if($failedlogin['count'] > 3) {
            if($this->base->time - $failedlogin['lastupdate'] < 3 * 60) {
                // 超过10次需要等待
                if($failedlogin['count'] > 10) {
                    $retry_in = $failedlogin['lastupdate'] + 3 * 60 - $this->base->time;
                    $this->errorJsonResponse(API_HTTP_BAD_REQUEST, API_ERROR_LOGIN_FAILED_TIMES, NULL, NULL, array('retry_in'=>$retry_in));
                }
                return false;
            } else {
                $expiration = $this->base->time - 3 * 60;
                $this->db->query("DELETE FROM ".API_DBTABLEPRE."failedlogins WHERE lastupdate<'$expiration'");
            }
        }
        return true;
    }
    
    protected function errorFailedLogin($http_status_code, $error, $error_description = NULL, $error_uri = NULL, $extras=array()) {
        // update failed login
        $failedlogin = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."failedlogins WHERE ip='".$this->base->onlineip."'");
        if(empty($failedlogin)) {
            $expiration = $this->base->time - 3 * 60;
            $this->db->query("DELETE FROM ".API_DBTABLEPRE."failedlogins WHERE lastupdate<'$expiration'");
            $this->db->query("INSERT INTO ".API_DBTABLEPRE."failedlogins SET ip='".$this->base->onlineip."', count=1, lastupdate='".$this->base->time."'");
        } else {
            $this->db->query("UPDATE ".API_DBTABLEPRE."failedlogins SET count=count+1,lastupdate='".$this->base->time."' WHERE ip='".$this->base->onlineip."'");
        }
        $this->errorJsonResponse($http_status_code, $error, $error_description, $error_uri, $extras);
    }
    
    /**
    * Overrides OAuth2::grantAccessToken().
    */
    public function grantAccessToken() {
        $filters = array(
            "grant_type" => array("filter" => FILTER_VALIDATE_REGEXP, "options" => array("regexp" => OAUTH2_GRANT_TYPE_REGEXP), "flags" => FILTER_REQUIRE_SCALAR),
            "scope" => array("flags" => FILTER_REQUIRE_SCALAR),
            "code" => array("flags" => FILTER_REQUIRE_SCALAR),
            "redirect_uri" => array("filter" => FILTER_SANITIZE_URL),
            "username" => array("flags" => FILTER_REQUIRE_SCALAR),
            "password" => array("flags" => FILTER_REQUIRE_SCALAR),
            "assertion_type" => array("flags" => FILTER_REQUIRE_SCALAR),
            "assertion" => array("flags" => FILTER_REQUIRE_SCALAR),
            "refresh_token" => array("flags" => FILTER_REQUIRE_SCALAR),
            "countrycode" => array("flags" => FILTER_REQUIRE_SCALAR),
            "mobile" => array("flags" => FILTER_REQUIRE_SCALAR),
            "verifycode" => array("flags" => FILTER_REQUIRE_SCALAR),
            "client_uid" => array("flags" => FILTER_REQUIRE_SCALAR),
            "domain" => array("flags" => FILTER_REQUIRE_SCALAR)
        );

        $input = filter_input_array(INPUT_POST, $filters);
        if (!$input["grant_type"]) {
            $input = filter_input_array(INPUT_GET, $filters);
        }
       
        //授权类型
        if (!$input["grant_type"])
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'Invalid grant_type parameter or parameter missing');

        //支持的授权类型
        if (!in_array($input["grant_type"], $this->getSupportedGrantTypes()))
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_UNSUPPORTED_GRANT_TYPE);

        //应用认证
        $client = $this->getClientCredentials();
        $this->client_id = $client[0];

        $app = $this->getAppCredentials();
        $this->appid = $app['appid'];
        $this->base->app = $app;

        if ($this->checkAppCredentials($app) === FALSE)
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);

        $client_check = $this->checkClientCredentials($client[0], $client[1]);
        if ($client_check === FALSE)
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);

        $official = $client_check['official'];

        if (!$this->checkRestrictedGrantType($client[0], $input["grant_type"]))
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_UNAUTHORIZED_CLIENT);
        
        if(in_array($input["grant_type"], $this->getSecCheckGrantTypes()) && !$this->checkFailedLogin()) {
            $seccodehidden = urldecode(getgpc('seccodehidden'));
            $seccode = strtoupper(getgpc('seccode'));
        
            if(!$seccodehidden || !$seccode) 
                $this->errorJsonResponse(API_HTTP_BAD_REQUEST, API_ERROR_NEED_SECCODE);
        
            $seccodehidden = $this->base->decode_seccode($seccodehidden);
            if(empty($seccodehidden) || strtoupper($seccodehidden) !== $seccode) {
                $this->errorJsonResponse(API_HTTP_BAD_REQUEST, API_ERROR_SECCODE);
            } 
        }

        //授权
        switch ($input["grant_type"]) {
            case OAUTH2_GRANT_TYPE_AUTH_CODE:
                if (!$input["code"] || !$input["redirect_uri"])
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST);

                $stored = $this->getAuthCode($input["code"]);

                //检验URI
                if ($stored === NULL || (strcasecmp(substr($input["redirect_uri"], 0, strlen($stored["redirect_uri"])), $stored["redirect_uri"]) !== 0) || $client[0] != $stored["client_id"])
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

                if ($stored["expires"] < $this->base->time)
                    $this->errorFailedLogin(OAUTH2_HTTP_UNAUTHORIZED, OAUTH2_ERROR_INVALID_TOKEN);
                
                $this->uid = $stored['uid'];

                break;
            case OAUTH2_GRANT_TYPE_QRCODE:
                if (!$input["code"])
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST);

                $stored = $_ENV['qrcode']->get_qrcode_by_code($input["code"]);
                if(!$stored || $client[0] != $stored["client_id"])
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
                
                if($stored['expires'] < $this->base->time) {
                    if($stored['status'] >= 0) {
                        $_ENV['qrcode']->update_qrcode_status($stored['cid'], -2);
                    }
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
                }
                
                if($stored['status'] != 2)
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
                
                $_ENV['qrcode']->use_qrcode($stored['cid']);
            
                $this->uid = $stored['uid'];

                break;
            case OAUTH2_GRANT_TYPE_USER_CREDENTIALS:
                // 限制非官方客户端不能使用密码方式登录
                if(!$official) $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_UNSUPPORTED_GRANT_TYPE);

                if ((!$input["username"] && !$input["mobile"]) || !$input["password"])
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'Missing parameters.');
                //企业登陆
                if($input["domain"] && $input["domain"]!=''){
                    $stored = $this->checkOrgCredentials($client[0], $input["domain"], $input["username"], $input["password"]);
                }else{
                    $stored = $this->checkUserCredentials($client[0], $input["username"], $input["password"], $input["countrycode"], $input["mobile"]);
                }

                if ($stored === FALSE)
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

                break;
            case OAUTH2_GRANT_TYPE_USER_MOBILE:
                if (!$input["mobile"] || !$input["verifycode"])
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'Missing parameters. "mobile" and "verifycode" required');
                
                if (!$input["countrycode"]) $input["countrycode"] = '+86';
                    
                $stored = $this->checkMobileCredentials($client[0], $input["countrycode"], $input["mobile"], $input["verifycode"]);

                if ($stored === FALSE)
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

                break;
            case OAUTH2_GRANT_TYPE_CLIENT_CREDENTIALS:
                if(!$client[1])
                    $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
            
                $client_data = $this->get_client_by_client_id($client[0]);
                if(!$client_data)
                    $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
                
                // 不支持授权类型
                if(!$client_data['partner_type'] || !$client_data['partner_id'])
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_UNSUPPORTED_GRANT_TYPE);
                
                if($client_data['partner_type'] == 2 && !$input["client_uid"])
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'Missing parameters. "client_uid" required');
                
                $partner_id = $client_data['partner_id'];
        
                $partner = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."partner WHERE partner_id='$partner_id'");
        
                // 状态判断
                if(!$partner || ($partner['status']<0 && $partner['status'] != -3))
                    $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);

                $stored = array(
                    'scope' => 'basic',
                );

                if ($stored === FALSE)
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);
                
                //用户
                if($client_data['partner_type'] == 1) {
                    // 大账号模式
                    $uid = $this->db->result_first('SELECT uid FROM '.API_DBTABLEPRE.'members WHERE username="'.$partner_id.'"');
                    if(!$uid)
                        $uid = $_ENV['user']->add_user($partner_id, '', '');
                } else if($client_data['partner_type'] == 2) {
                    // 小账号模式
                    $connect_type = $partner['connect_type'];
                    $connect_uid = $input["client_uid"];
        
                    if(!$partner_id || !$connect_type || !$connect_uid)
                        $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);
        
                    $user = $_ENV['user']->get_user_by_connect_uid($connect_type, $connect_uid);
                    if(!$user) {
                        $username = $this->gen_connect_username($connect_type, $connect_uid);
                        if(!$username)
                            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);
        
                        $uid = $_ENV['user']->add_user($username, '', '');
                        if($uid) {
                            // add connect
                            $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect SET uid='$uid', connect_type='$connect_type', connect_uid='".$connect_uid."', username='".$connect_uid."', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='1'");
                
                            // add partner
                            $p = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_partner WHERE partner_id='$partner_id' AND uid='$uid'");
                            if(!$p) {
                                $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_partner SET partner_id='$partner_id', uid='$uid'");
                            }
                        }
                    } else {
                        $uid = $user['uid'];
                    }
                }
                
                if(!$uid)
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);
                
                $this->uid = strval($uid);

                // access_token有效期为3个小时
                $this->setVariable('access_token_lifetime', 10800);

                // no need to create refresh token
                $this->setVariable('_remove_refresh_token', TRUE);
                
                break;
            case OAUTH2_GRANT_TYPE_DOMAIN:
                if(!$client[1])
                    $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
                
                $client_data = $this->get_client_by_client_id($client[0]);
                if(!$client_data)
                    $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
                
                // 不支持授权类型
                if(!$client_data['partner_type'] || $client_data['partner_type'] != 1 || !$client_data['partner_id'])
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_UNSUPPORTED_GRANT_TYPE);
                
                // TODO: 校验partner和domain关系
                /*
                $partner_id = $client_data['partner_id'];
                $partner = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."partner WHERE partner_id='$partner_id'");
        
                // 状态判断
                if(!$partner || ($partner['status']<0 && $partner['status'] != -3))
                    $this->errorJsonResponse(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
                */

                if (!$input["domain"] || !$input["username"] || !$input["password"])
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'Missing parameters.');
                
                //企业登陆
                $stored = $this->checkOrgCredentials($client[0], $input["domain"], $input["username"], $input["password"]);

                if ($stored === FALSE)
                    $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

                break;
            case OAUTH2_GRANT_TYPE_ASSERTION:
            if (!$input["assertion_type"] || !$input["assertion"])
                $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST);

                $stored = $this->checkAssertion($client[0], $input["assertion_type"], $input["assertion"]);

                if ($stored === FALSE)
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

                break;
            case OAUTH2_GRANT_TYPE_REFRESH_TOKEN:
                if (!$input["refresh_token"])
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'No "refresh_token" parameter found');
                
                $params = explode('.', $input["refresh_token"]);
                if (is_array($params) && count($params) == 5) {
                    $input["refresh_token"] = $params[4];
                }

                $stored = $this->getRefreshToken($input["refresh_token"]);

                //if ($stored === NULL || $client[0] != $stored["client_id"])
                if ($stored === NULL)
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

                if ($stored["expires"] < $this->base->time)
                    $this->errorJsonResponse(OAUTH2_HTTP_UNAUTHORIZED, OAUTH2_ERROR_INVALID_REFRESH_TOKEN);

                // store the refresh token locally so we can delete it when a new refresh token is generated
                $this->setVariable('_old_refresh_token', $stored["token"]);
                
                $this->uid = $stored['uid'];

                break;
            case OAUTH2_GRANT_TYPE_NONE:
                $stored = $this->checkNoneAccess($client[0]);

                if ($stored === FALSE)
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST);
        }

        //scope检验
        if ($input["scope"] && (!is_array($stored) || !isset($stored["scope"]) || !$this->checkScope($input["scope"], $stored["scope"])))
            $this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_SCOPE);

        if (!$input["scope"])
            $input["scope"] = NULL;

        $token = $this->createAccessToken($client[0], $input["scope"]);
        
        // connect
        $connects = array();
        $types = $this->get_client_connect_support($client[0]);
        foreach($types as $connect_type) {
            $connect = $this->base->load_connect($connect_type);
            if($connect && $connect->_token_extras()) {
                $extras = $connect->get_connect_info($this->uid, true);
                if($extras && is_array($extras)) {
                    $connects[] = $extras;
                } else {
                    //$this->errorFailedLogin(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);
                }
            }
        }
        //$token['connect'] = $connects;
        
        $this->base->log('oauth2222', 'grant token success, token='.json_encode($token));

        $this->sendJsonHeaders();
        echo json_encode($token);
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
    
    /**
    * Overrides OAuth2::verifyAccessToken().
    * 验证Access Token
    */
    public function verifyAccessToken($scope = NULL, $exit_not_present = TRUE, $exit_invalid = TRUE, $exit_expired = TRUE, $exit_scope = TRUE, $realm = NULL) {
        $token_param = $this->getAccessTokenParams();
        if ($token_param === FALSE)
            return $exit_not_present ? $this->errorWWWAuthenticateResponseHeader(OAUTH2_HTTP_BAD_REQUEST, $realm, OAUTH2_ERROR_INVALID_REQUEST, 'The request is missing a required parameter, includes an unsupported parameter or parameter value, repeats the same parameter, uses more than one method for including an access token, or is otherwise malformed.', NULL, $scope) : FALSE;
    
        $params = explode('.', $token_param);
        if (is_array($params) && count($params) == 5) {
            $token_param = $params[4];
        }
    
        //获取Token信息
        $token = $this->getAccessToken($token_param);
        if ($token === NULL)
        return $exit_invalid ? $this->errorWWWAuthenticateResponseHeader(OAUTH2_HTTP_UNAUTHORIZED, $realm, OAUTH2_ERROR_INVALID_TOKEN, 'The access token provided is invalid.', NULL, $scope) : FALSE;

        //检验Token是否过期
        if (isset($token["expires"]) && $this->base->time > $token["expires"])
        return $exit_expired ? $this->errorWWWAuthenticateResponseHeader(OAUTH2_HTTP_UNAUTHORIZED, $realm, OAUTH2_ERROR_INVALID_TOKEN, 'The access token provided has expired.', NULL, $scope) : FALSE;

        //如果提供scope,则检验scope
        if ($scope && (!isset($token["scope"]) || !$token["scope"] || !$this->checkScope($scope, $token["scope"])))
        return $exit_scope ? $this->errorWWWAuthenticateResponseHeader(OAUTH2_HTTP_FORBIDDEN, $realm, OAUTH2_ERROR_INSUFFICIENT_SCOPE, 'The request requires higher privileges than provided by the access token.', NULL, $scope) : FALSE;
        
        $this->auth_status = $token['status'];
        $this->partner_push = $token['partner_push'];
        
        //检验Token是否撤销
        if($token['status'] <= 0)
            return $exit_invalid ? $this->errorWWWAuthenticateResponseHeader(OAUTH2_HTTP_UNAUTHORIZED, $realm, OAUTH2_ERROR_INVALID_TOKEN, 'The access token provided has expired.', NULL, $scope) : FALSE;
        
        //用户信息
        $this->uid = $token['uid'];
        $this->user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid='".$token['uid']."'");
        $this->client_id = $token['client_id'];
        $this->appid = $token['appid'];
        $this->access_token = $token["oauth_token"];
        
        $partner = $this->get_partner_by_client_id($token['client_id']);
        if($partner && $partner['partner_id']) {
            $this->partner_id = $partner['partner_id'];
            $this->partner = $partner;
            $this->connect_domain = $partner['connect_domain'];
            $this->client_partner_type = $partner['partner_type'];
        }
        
        $this->app = $this->get_app_by_appid($token['appid']);
        
        return TRUE;
    }
    
    public function get_partner_by_client_id($client_id) {
        return $this->db->fetch_first("SELECT p.*, c.partner_type FROM ".API_DBTABLEPRE."oauth_client c LEFT JOIN ".API_DBTABLEPRE."partner p ON c.partner_id=p.partner_id WHERE c.client_id='$client_id'");
    }
    
    /**
    * Overrides OAuth2::errorJsonResponse().
    * 错误输出
    */
    public function errorJsonResponse($http_status_code, $error, $error_description = NULL, $error_uri = NULL, $extras=array()) {
        
        $this->base->log('oauth2222', 'error json response, http_status_code='.$http_status_code.', error='.$error);

        //是否强制使用 API 错误输出
        if ($this->getVariable('force_api_response')) {
            $this->base->error($http_status_code, $error, $error_description, $error_uri);
        }

        //error code处理
        if($error && $p = strpos($error, ':')) {
            $error_code = intval(substr($error, 0, $p));
            $error = substr($error, $p+1);
        } else {
            $error_code = intval(substr($http_status_code, 0, strpos($http_status_code, ' ')));
        }

        $result['error_code'] = $error_code;
        $result['error'] = $error;

        if ($this->getVariable('display_error') && $error_description)
            $result["error_description"] = $error_description;

        if ($this->getVariable('display_error') && $error_uri)
            $result["error_uri"] = $error_uri;
        
        if ($extras && is_array($extras))
                $result = array_merge($result, $extras);

        // log记录 ----------- access_log
        log::access_log($http_status_code, $result);

        header("HTTP/1.1 " . $http_status_code);
        $this->sendJsonHeaders();
        echo json_encode($result);

        exit;
    }

    /**
    * Overrides OAuth2::errorWWWAuthenticateResponseHeader().
    * 错误输出
    */
    public function errorWWWAuthenticateResponseHeader($http_status_code, $realm, $error, $error_description = NULL, $error_uri = NULL, $scope = NULL) {

        //是否强制使用 API 错误输出
        if ($this->getVariable('force_api_response')) {
            $this->errorJsonResponse($http_status_code, $error, $error_description, $error_uri);
        }

        $realm = $realm === NULL ? $this->getDefaultAuthenticationRealm() : $realm;

        $result = "WWW-Authenticate: OAuth realm='" . $realm . "'";

        if ($error)
          $result .= ", error='" . $error . "'";

        if ($this->getVariable('display_error') && $error_description)
          $result .= ", error_description='" . $error_description . "'";

        if ($this->getVariable('display_error') && $error_uri)
          $result .= ", error_uri='" . $error_uri . "'";

        if ($scope)
          $result .= ", scope='" . $scope . "'";

        header("HTTP/1.1 ". $http_status_code);
        header($result);

        exit;
    }

    
    /**
     * 获取注册的返回 URI
     *
     * @param $client_id
     *   应用ID
     *
     * @return
     *   注册的返回 URI ，不存在或无效则需返回 FALSE
     *
     * @ingroup oauth2_section_3
     */
    public function getRedirectUri($client_id) {
        if(!$client_id)
            return FALSE;

        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        if(!$client)
            return FALSE;
        
        //状态判断
        if($client['status']<0 && $client['status'] != -3)
            return FALSE;

        return $client["redirect_uri"]?$client["redirect_uri"]:FALSE;
    }
    
    /**
     * Fetch authorization code data (probably the most common grant type).
     *
     * Retrieve the stored data for the given authorization code.
     *
     * Required for OAUTH2_GRANT_TYPE_AUTH_CODE.
     *
     * @param $code
     *   Authorization code to be check with.
     *
     * @return
     *   An associative array as below, and NULL if the code is invalid:
     *   - client_id: Stored client identifier.
     *   - redirect_uri: Stored redirect URI.
     *   - expires: Stored expiration in unix timestamp.
     *   - scope: (optional) Stored scope values in space-separated string.
     *
     * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-10#section-4.1.1
     *
     * @ingroup oauth2_section_4
     */
    protected function getAuthCode($code) {
        if(!$code)
            return NULL;

        $code = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_code WHERE code='$code'");
        if(!$code)
            return NULL;
        
        if($code['usedate']) {
            return NULL;
        }
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_code SET usedate='".$this->base->time."' WHERE code='".$code['code']."'");
        
        return $code;
    }

    /**
     * Take the provided authorization code values and store them somewhere.
     *
     * This function should be the storage counterpart to getAuthCode().
     *
     * If storage fails for some reason, we're not currently checking for
     * any sort of success/failure, so you should bail out of the script
     * and provide a descriptive fail message.
     *
     * Required for OAUTH2_GRANT_TYPE_AUTH_CODE.
     *
     * @param $code
     *   Authorization code to be stored.
     * @param $client_id
     *   Client identifier to be stored.
     * @param $redirect_uri
     *   Redirect URI to be stored.
     * @param $expires
     *   Expiration to be stored.
     * @param $scope
     *   (optional) Scopes to be stored in space-separated string.
     *
     * @ingroup oauth2_section_4
     */
    protected function setAuthCode($code, $client_id, $redirect_uri, $expires, $scope = NULL) {
        if(!$code || !$this->appid || !$client_id || !$expires)
            return FALSE;

        $this->db->query("INSERT INTO ".API_DBTABLEPRE."oauth_code (code, appid, client_id, redirect_uri, expires, scope, dateline, uid) VALUES ('$code', '".$this->appid."', '$client_id', '$redirect_uri', '$expires', '$scope', '". $this->base->time ."', '$this->uid')");
        return $code;
    }
    
    public function getSupportedAuthResponseTypes() {
      return array(
        OAUTH2_AUTH_RESPONSE_TYPE_AUTH_CODE,
        OAUTH2_AUTH_RESPONSE_TYPE_QRCODE,
        OAUTH2_AUTH_RESPONSE_TYPE_ACCESS_TOKEN,
        OAUTH2_AUTH_RESPONSE_TYPE_CODE_AND_TOKEN,
        OAUTH2_AUTH_RESPONSE_TYPE_NONE
      );
    }
    
    public function get_app_by_appid($appid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_app WHERE appid='$appid'");
    }
    
    public function get_client_by_client_id($client_id) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
    }
    
    public function _format_client_by_client_id($client_id) {
        return $this->db->fetch_first('SELECT a.client_id,IFNULL(b.title,a.title) AS `title`,IFNULL(b.desc,a.desc) AS `desc` FROM '.API_DBTABLEPRE.'oauth_client a LEFT JOIN '.API_DBTABLEPRE.'oauth_client_lang b ON a.client_id=b.client_id AND b.lang="'.API_LANGUAGE.'" WHERE a.client_id="'.$client_id.'"');
    }
    
    public function get_client_connect_type($client_id) {
        return $this->db->result_first("SELECT connect_type FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
    }
    
    public function get_client_connect_support($client_id) {
        $extras = $this->db->result_first("SELECT connect_support FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        if($extras) {
            $types = split(',', $extras);
            if($types && is_array($types))
                return $types;
        }
        return false;
    }
    
    public function get_client_platform($client_id) {
        return $this->db->result_first("SELECT platform FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
    }
    
    function logout() {
        if($this->access_token && $this->uid) {
            $udid = $this->db->result_first("SELECT udid FROM ".API_DBTABLEPRE."oauth_access_token WHERE oauth_token='".$this->access_token."'");
            if($udid) {
                $this->db->query("UPDATE ".API_DBTABLEPRE."push_client SET status='-1' WHERE `udid`='".$udid."' AND `uid`='".$this->uid."' AND `status`>0");
            }
        }
    }
    
}
