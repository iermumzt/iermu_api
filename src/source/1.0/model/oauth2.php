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

    function __construct(&$base) {
        parent::__construct();
        $this->oauth2model($base);
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

        $client = $this->db->fetch_first("SELECT client_secret,status FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        if($client_secret === NULL)
            return $client !== FALSE;
        
        //状态判断
        if($client['status']<0 && $client['status'] != -3)
            return FALSE;

        return $client["client_secret"] == $client_secret;
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
    protected function setAccessToken($oauth_token, $client_id, $expires, $scope = NULL) {
        if(!$oauth_token || !$this->appid || !$client_id || !$expires)
            return FALSE;

        $this->db->query("INSERT INTO ".API_DBTABLEPRE."oauth_access_token (oauth_token, appid, client_id, expires, scope, dateline, uid) VALUES ('$oauth_token', '".$this->appid."', '$client_id', '$expires', '$scope', '". $this->base->time ."', '$this->uid')");
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
            OAUTH2_GRANT_TYPE_USER_CREDENTIALS,
            OAUTH2_GRANT_TYPE_REFRESH_TOKEN
        );
    }

    /**
    * Overrides OAuth2::checkUserCredentials().
    */
    protected function checkUserCredentials($client_id, $username, $password) {
        if(!$client_id || !$username || !$password) 
            return FALSE;
        
        //用户
        $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE username='$username' OR email='$username'");
        if(!$user)
             $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);

        $this->uid = $user['uid'];

        //密码
        $passwordmd5 = preg_match('/^\w{32}$/', $password) ? $password : md5($password);
        if(!$user['password'] || $user['password'] != md5($passwordmd5.$user['salt']))
             $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_PASSWORD);
        
        return array(
            'scope' => 'basic',
        );
    }

    /**
    * Overrides OAuth2::createAccessToken().
    */
    public function createAccessToken($client_id, $scope = NULL) {
        
        //默认scope处理
        if($scope == NULL) $scope = 'basic';
        
        $access_token_lifetime = $this->getVariable('access_token_lifetime', OAUTH2_DEFAULT_ACCESS_TOKEN_LIFETIME);
        $refresh_token_lifetime = $this->getVariable('refresh_token_lifetime', OAUTH2_DEFAULT_REFRESH_TOKEN_LIFETIME);
        
        $token = array(
            "expires_in" => $access_token_lifetime,
            "access_token" => $this->genAccessToken(),
            "scope" => $scope,
            "uid" => strval($this->uid)
        );

        if($this->setAccessToken($token["access_token"], $client_id, $this->base->time + $access_token_lifetime, $scope)  === FALSE) 
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST);
        
        $token['access_token'] = $this->appid.'.'.$client_id.'.'.$access_token_lifetime.'.'.$this->base->time.'.'.$token['access_token'];

        //Refresh Token
        if (in_array(OAUTH2_GRANT_TYPE_REFRESH_TOKEN, $this->getSupportedGrantTypes())) {
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
        );

        $input = filter_input_array(INPUT_POST, $filters);
        if (!$input["grant_type"]) {
            $input = filter_input_array(INPUT_GET, $filters);
        }

        //授权类型
        if (!$input["grant_type"])
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'Invalid grant_type parameter or parameter missing');

        //支持的授权类型
        if (!in_array($input["grant_type"], $this->getSupportedGrantTypes()))
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_UNSUPPORTED_GRANT_TYPE);

        //应用认证
        $client = $this->getClientCredentials();
        $this->client_id = $client[0];

        $app = $this->getAppCredentials();
        $this->appid = $app['appid'];
        $this->base->app = $app;

        if ($this->checkAppCredentials($app) === FALSE)
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);

        if ($this->checkClientCredentials($client[0], $client[1]) === FALSE)
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);

        if (!$this->checkRestrictedGrantType($client[0], $input["grant_type"]))
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_UNAUTHORIZED_CLIENT);

        //授权
        switch ($input["grant_type"]) {
            case OAUTH2_GRANT_TYPE_AUTH_CODE:
                if (!$input["code"] || !$input["redirect_uri"])
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST);

                $stored = $this->getAuthCode($input["code"]);

                //检验URI
                if ($stored === NULL || (strcasecmp(substr($input["redirect_uri"], 0, strlen($stored["redirect_uri"])), $stored["redirect_uri"]) !== 0) || $client[0] != $stored["client_id"])
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

                if ($stored["expires"] < $this->base->time)
                    $this->errorJsonResponse(OAUTH2_HTTP_UNAUTHORIZED, OAUTH2_ERROR_INVALID_TOKEN);
                
                $this->uid = $stored['uid'];

                break;
            case OAUTH2_GRANT_TYPE_USER_CREDENTIALS:
                if (!$input["username"] || !$input["password"])
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_REQUEST, 'Missing parameters. "username" and "password" required');

                $stored = $this->checkUserCredentials($client[0], $input["username"], $input["password"]);

                if ($stored === FALSE)
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);

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
            $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_SCOPE);

        if (!$input["scope"])
            $input["scope"] = NULL;

        $token = $this->createAccessToken($client[0], $input["scope"]);
        
        // connect
        $connects = array();
        $types = $this->get_client_connect_support($client[0]);
        foreach($types as $connect_type) {
            $connect = $this->base->load_connect($connect_type);
            if($connect && $connect->_token_extras()) {
                $extras = $connect->get_token_extras($this->uid);
                if($extras && is_array($extras)) {
                    $connects[] = $extras;
                } else {
                    $this->errorJsonResponse(OAUTH2_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_GRANT);
                }
            }
        }
        $token['connect'] = $connects;
        
        $this->base->log('oauth2211', 'grant token success, token='.json_encode($token));

        $this->sendJsonHeaders();
        echo json_encode($token);
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
        
        //用户信息
        $this->uid = $token['uid'];
        $this->client_id = $token['client_id'];
        $this->appid = $token['appid'];
        $this->access_token = $token["oauth_token"];
        
        return TRUE;
    }
    
    /**
    * Overrides OAuth2::errorJsonResponse().
    * 错误输出
    */
    public function errorJsonResponse($http_status_code, $error, $error_description = NULL, $error_uri = NULL) {
        
        $this->base->log('oauth2211', 'error json response, http_status_code='.$http_status_code.', error='.$error);

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
        
        $this->base->log('oauth2211', 'error www response, http_status_code='.$http_status_code.', error='.$error);

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
        OAUTH2_AUTH_RESPONSE_TYPE_ACCESS_TOKEN,
        OAUTH2_AUTH_RESPONSE_TYPE_CODE_AND_TOKEN,
        OAUTH2_AUTH_RESPONSE_TYPE_NONE
      );
    }
    
    public function get_client_connect_type($client_id) {
        return $this->db->result_first("SELECT connect_type FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
    }
    
    public function get_client_connect_support($client_id) {
        $extras = $this->db->result_first("SELECT connect_support FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        if($extras) {
            $types = split(',', $extras);
            if($types && is_array($types) && $types[0])
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
