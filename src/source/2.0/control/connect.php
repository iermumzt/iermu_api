<?php

!defined('IN_API') && exit('Access Denied');

class connectcontrol extends base {

    function __construct() {
        $this->connectcontrol();
    }

    function connectcontrol() {
        parent::__construct();
        $this->load('oauth2');
        $this->load('device');
        $this->load('user');
        $this->load('store');
    }

    function onindex() {
        $this->init_input();
        $model = $this->input('model');
        $method = $this->input('method');
        $action = 'on'.$model.'_'.$method;
        if($model && $method && method_exists($this, $action)) {
            unset($this->input['model']);
            unset($this->input['method']);
            return $this->$action();
        }
        $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    }
    
    function onchinacache_notify() {
        $this->init_input();
        
        if(empty($this->input))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $data = json_encode($this->input);
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."connect_notify SET connect_type='".API_CHINACACHE_CONNECT_TYPE."', data='$data', dateline='".$this->time."'");
        return array();
    }
    
    function onlingyang_notify() {
        $this->log('lingyang_notify', 'start notify.');
        $raw = file_get_contents("php://input");
        if(!$raw)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
        
        $this->log('lingyang_notify', 'post raw='.$raw);
        
        $input = json_decode($raw, true);
        if(!$input || !$input['event'])
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $client = $this->load_connect(API_LINGYANG_CONNECT_TYPE);
        if(!$client) {
            $this->log('lingyang_notify', 'load connect client failed.');
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }
        
        if(!$client->msg_notify($input)) {
            $this->log('lingyang_notify', 'connect client notify failed.');
            $this->error(API_HTTP_BAD_REQUEST, CONNECT_ERROR_MSG_NOTIFY_FAILED);
        }
        
        $this->log('lingyang_notify', 'notify success.');
        
        return array();
    }

    function onqiniu_notify() {
        $this->init_input();
        $deviceid = $this->input('deviceid');
        $type = $this->input('type');
        $param = stripcslashes(rawurldecode($this->input('param')));
        if($param) {
            $param = json_decode($param, true);
        }
        $time = intval($this->input('time'));
        $client_id = $this->input('client_id');
        $sign = $this->input('sign');

        if(!$deviceid || !$type || !$param || !$time || !$client_id || !$sign)
            $this->error(API_HTTP_OK, API_ERROR_PARAM);
        
        $check = $this->_check_device_sign($sign, $deviceid, $client_id, $time);
        if(!$check)
            $this->error(API_HTTP_OK, DEVICE_ERROR_NO_AUTH);
        
        $device = $_ENV['device']->get_device_by_did($deviceid);
        if(!$device) 
            $this->error(API_HTTP_OK, DEVICE_ERROR_NOT_EXIST);

        switch ($type) {
            case 'device_preset':
                $ret = $_ENV['device']->preset_qiniu_notify($device, $param['preset']);
                if(!$ret) {
                    $this->error(API_HTTP_OK, DEVICE_ERROR_UPLOAD_PRESET_THUMBNAIL_FAILED);
                }
                break;

            case 'device_snapshot':
                $snapshot = $_ENV['device']->get_snapshot_by_sid($param['sid']);
                if (!$snapshot)
                    $this->error(API_HTTP_OK, DEVICE_ERROR_SNAPSHOT_NOT_EXIST);
                
                if($snapshot['deviceid'] != $deviceid) 
                    $this->error(API_HTTP_OK, DEVICE_ERROR_UPLOAD_SNAPSHOT_REFUSED);

                $ret = $_ENV['device']->snapshot_qiniu_notify($snapshot);
                if(!$ret) {
                    $this->error(API_HTTP_OK, DEVICE_ERROR_UPLOAD_SNAPSHOT_FAILED);
                }
                break;
            
            default:
                $this->error(API_HTTP_OK, API_ERROR_PARAM);
                break;
        }

        return $ret;
    }

    function onzhifubao_notify() {
        $this->init_input();
        $notify_type = $this->input('notify_type');
        $ordersn = $this->input('out_trade_no');
        $total_fee = $this->input('total_fee');
        $payno = $this->input('trade_no');
        $logid = intval($this->input('logid'));

        $pay_log = $_ENV['store']->get_pay_log_by_logid($logid);
        $redirect = $pay_log['redirect'] ? $pay_log['redirect'] : API_DEFAULT_REDIRECT;

        // 判断是否是同步回调通知
        $isSync = $this->input('is_success') ? true : false;

        if(!$notify_type || !$ordersn || !$total_fee || !$payno || !$redirect || !$logid)
            return $isSync ? $this->redirect($redirect) : 'true';

        if ($notify_type != 'trade_status_sync')
            return $isSync ? $this->redirect($redirect) : 'true';
        
        $order = $_ENV['store']->get_order_by_ordersn($ordersn);
        if (!$order)
            return $isSync ? $this->redirect($redirect) : 'true';

        if ($order['orderstatus'] != 1)
            return $isSync ? $this->redirect($redirect) : 'true';

        if ($order['paystatus'] != 0)
            return $isSync ? $this->redirect($redirect) : 'true';

        $trade_status = $this->input('trade_status');
        switch ($trade_status) {
            case 'TRADE_SUCCESS':
            case 'TRADE_FINISHED': $ispaid = 1; break;

            default: $ispaid = 0; break;
        }

        if (!$_ENV['store']->payment_notify($order, $total_fee, 1, $payno, $logid, $ispaid))
            return $isSync ? $this->redirect($redirect) : 'false';

        $_ENV['store']->order_action('PAYMENT_SUCCESS', $order);

        return $isSync ? $this->redirect($redirect) : 'true';
    }

    function onweixin_sync() {
        $this->init_input();
        
        $redirect = $this->input('redirect');
        if (!$redirect)
            $redirect = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : API_DEFAULT_REDIRECT;
        
        $this->log('weixin_sync', 'start weixin sync. redirect='.$redirect);
        
        $user = array();
        
        $connect = $this->load_connect(API_WEIXIN_CONNECT_TYPE);
        if($connect && $connect->config['appid'] && $connect->config['secret']) {
            $request_url = urlencode(request_url().'?redirect='.urlencode($redirect));

            $state = $this->input('state');
            $code = $this->input('code');
            
            $this->log('weixin_sync', 'state='.$state.', code='.$code);
            
            if (!$state || !$code)
                $this->redirect('https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$connect->config['appid'].'&redirect_uri='.$request_url.'&response_type=code&scope=snsapi_base&state=silence#wechat_redirect');
            
            $ret = $connect->_request('https://api.weixin.qq.com/sns/oauth2/access_token', array('appid'=>$connect->config['appid'], 'secret'=>$connect->config['secret'], 'grant_type'=>'authorization_code', 'code'=>$code));
            
            $this->log('weixin_sync', 'token ret='.json_encode($ret));
            
            if($ret && $ret['openid']) {
                $user = $connect->get_user_by_openid($ret['openid']);
                
                $this->log('weixin_sync', 'user='.json_encode($user));
                
                if(!$user || $user['lastupdate'] + 604800 < $this->time) {
                    $this->log('weixin_sync', 'need sync');
                    if ($state === 'silence') {
                        $this->log('weixin_sync', 'silence');
                        $token = $connect->get_weixin_token();
                        if($token) {
                            $user = $connect->_request('https://api.weixin.qq.com/cgi-bin/user/info', array('openid'=>$ret['openid'], 'access_token'=>$token['access_token']));
                            if (!$user || !$user['nickname'])
        $this->redirect('https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$connect->config['appid'].'&redirect_uri='.$request_url.'&response_type=code&scope=snsapi_userinfo&state=nosilence#wechat_redirect');
                        }
                    } else {
                        $this->log('weixin_sync', 'userinfo');
                        $user = $connect->_request('https://api.weixin.qq.com/sns/userinfo', array('openid'=>$ret['openid'], 'access_token'=>$ret['access_token'], 'lang'=>'zh_CN'));
                    }
                
                    if($user && $user['nickname']) {
                        $connect->update_weixin_user($user);
                    }
                }
            }
        }
        
        $this->log('weixin_sync', 'sync user='.json_encode($user));
        
        // 登录状态
        if($user && $user['unionid']) {
            $this->set_connect_login(API_WEIXIN_CONNECT_TYPE, $user['unionid']);
        } else {
            $this->logout();
        }

        // 设置nickname
        $nickname = 'unknown';
        if($user && $user['nickname']) $nickname = $user['nickname'];
        $this->setcookie(API_COOKIE_WX_NICKNAME, $nickname, 86400);
        
        $this->log('weixin_sync', 'set nickname='.$nickname);
        
        $this->redirect($redirect);
    }

    function onweixin_js_config() {
        $this->init_input();
        $url = $this->input('u');
        if (!$url)
            $url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : API_DEFAULT_REDIRECT;
        
        $this->log('weixin_js_conifg', 'start weixin js config. url='.$url);
        
        $config = array();
        
        $connect = $this->load_connect(API_WEIXIN_CONNECT_TYPE);
        if($connect && $connect->config['appid']) {
            $config = $connect->get_js_config($url);
        }
        
        $this->log('weixin_js_conifg', 'weixin js config='.json_encode($config));
        
        return $config;
    }
    
    function show_error($error, $description="") {
    	//error code处理
    	if($error && $p = strpos($error, ':')) {
    		$error_code = intval(substr($error, 0, $p));
    		$error = substr($error, $p+1);
    	}
        $this->showmessage('error', 'oauth2_error', array('error'=>$error));
    }
    
    function onqdlt_auth() {
        $this->init_input();
        
        $bind_connect_uid = $this->input('connect_uid');
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        
        if(!$bind_connect_uid || !$sign || !$expire)
            $this->show_error(API_ERROR_PARAM);
        
        $check = $this->_check_client_sign($sign, $expire, $bind_connect_uid);
        if(!$check)
            $this->show_error(API_ERROR_NO_AUTH);
        
        $auth_url = BASE_API.'/oauth2/authorize?';
        $response_type = $this->input('response_type');
        if(!$response_type) {
            $response_type = 'none';
        }
        $auth_url .= 'response_type='.$response_type;
        
        $client_id = $this->input('client_id');
        if(!$client_id || $client_id != $check['client_id'])
            $this->show_error(API_ERROR_NO_AUTH);
        
        $auth_url .= '&client_id='.$client_id;
        
        $redirect_uri = $this->input('redirect_uri');
        if(!$redirect_uri) $redirect_uri = $this->input('redirect');
        if(!$redirect_uri) $redirect_uri = $_SERVER['HTTP_REFERER']?$_SERVER['HTTP_REFERER']:API_DEFAULT_REDIRECT;
        $auth_url .= '&redirect_uri='.$redirect_uri;
        
        $scope = $this->input('scope');
        if(!$scope) {
            $scope = 'netdisk';
        }
        $auth_url .= '&scope='.$scope;
        
        $display = $this->input('display');
        if($display) {
            $auth_url .= '&display='.$display;
        }
        
        //$bind_token = $_ENV['oauth2']->gen_bind_token();
        
        $auth_url .= '&connect_type='.API_BAIDU_CONNECT_TYPE.'&bind_connect_uid='.$bind_connect_uid.'&bind_connect_type='.API_QDLT_CONNECT_TYPE;
        $this->redirect($auth_url);
    }
    
    function onpartner_auth() {
        $this->init_input();
        
        $this->log('partner_auth', 'input='.json_encode($this->input));
        
        $partner_token = $this->input['token'];
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);
        if(!$partner_token || !$sign || !$expire)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $check = $this->_check_client_sign($sign, $expire, $partner_token);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_NO_AUTH);
        
        $appid = $check['appid'];
        $client_id = $check['client_id'];
        
        $this->log('partner_auth', 'check='.json_encode($check));
        
        $partner = $this->_check_partner_token($partner_token);
        if(!$partner)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_NO_AUTH);
        
        $this->log('partner_auth', 'partner='.json_encode($partner));
        
        $partner_id = $partner['partner_id'];
        $connect_type = $partner['connect_type'];
        $connect_uid = $partner['connect_uid'];
        $connect_user = $partner['connect_user'];
        
        if(!$partner_id || !$connect_type || !$connect_uid)
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_NO_AUTH);
        
        $data = '';
        if($connect_user) {
            $data = serialize($connect_user);
        }
        
        $user = $_ENV['user']->get_user_by_connect_uid($connect_type, $connect_uid);
        if(!$user) {
            $username = $this->gen_connect_username($connect_type, $connect_uid);
            if(!$username)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_USERNAME_EXISTS);
        
            $uid = $_ENV['user']->add_user($username, '', '');
            if($uid) {
                // add connect
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect SET uid='$uid', connect_type='$connect_type', connect_uid='".$connect_uid."', username='".$connect_uid."', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='1'");
                
                // add partner
                $p = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_partner WHERE partner_id='$partner_id' AND uid='$uid'");
                if(!$p) {
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_partner SET partner_id='$partner_id', uid='$uid', data='$data'");
                } else {
                    $this->db->query("UPDATE ".API_DBTABLEPRE."member_partner SET data='$data' WHERE partner_id='$partner_id' AND uid='$uid'");
                }
            }
        } else {
            $uid = $user['uid'];
            $this->db->query("UPDATE ".API_DBTABLEPRE."member_partner SET data='$data' WHERE partner_id='$partner_id' AND uid='$uid'");
        }
        
        if(!$uid)
            $this->error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_USER_NOT_EXIST);
        
        // partner connect
        if($partner['connect_type']) {
            $partner_client = $this->load_connect($partner['connect_type']);
            if($partner_client) {
                $partner_client->update_partner_data($uid, $partner, $connect_user);
            }
        }
        
        $_ENV['oauth2']->uid = $uid;
        $_ENV['oauth2']->appid = $appid;
        $_ENV['oauth2']->client_id = $client_id;
        
        // 设置推送状态
        $partner_push = 0;
        if($connect_user && $connect_user['push']) {
            $partner_push = 1;
        }
        
        $token = $_ENV['oauth2']->createAccessToken($client_id, '', $partner_push);
        
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
    
    function oncctv_client_token() {
        $client = $this->load_connect(API_LINGYANG_CONNECT_TYPE);
        if(!$client)
            $this->error(API_HTTP_BAD_REQUEST, CONNECT_ERROR_API_FAILED);
        
        $client_token = $client->device_clienttoken(537002172);
        if(!$client_token)
            $this->error(API_HTTP_BAD_REQUEST, CONNECT_ERROR_API_FAILED);
        
        return array('client_token' => $client_token);
    }
    
    function oncctv_identity_card() {
        $this->init_input();
        
        $uid = $this->input('uid');
        if(!$uid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['user']->get_cctv_identity_card($uid);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_NOT_EXISTS);

        return $result;
    }
    
    function onsendcloud_email_notify() {
        $this->init_input();
        
        $this->log('sendcloud email_notify', json_encode($this->input));
        
        return array();
    }
    
    function onsendcloud_sms_notify() {
        $this->init_input();
        
        $this->log('sendcloud sms_notify', json_encode($this->input));
        
        return array();
    }
    
    function onweixin_notify() {
        $this->log('weixin_notify', 'start notify.');
        
        $echo = $_GET["echostr"];
        if($echo)
            return $echo;
        
        $raw = file_get_contents("php://input");
        if(!$raw)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    
        include_once API_SOURCE_ROOT.'./lib/xml.class.php';
        $data = xml_unserialize($raw, true);
    
        $this->log('weixin_notify', 'post data='.json_encode($data));
        
        $from_user = $data['FromUserName'];
        $to_user = $data['ToUserName'];
    
        $msg_type = $data['MsgType'];
        
        $connect = $this->load_connect(API_WEIXIN_CONNECT_TYPE);
        if($connect && $connect->config['appid'] && $connect->config['secret']) {
            if($msg_type == "text") {
                $content = strtoupper(trim($data['Content']));
                $tpl = "<xml>
    						<ToUserName><![CDATA[%s]]></ToUserName>
    						<FromUserName><![CDATA[%s]]></FromUserName>
    						<CreateTime>%s</CreateTime>
    						<MsgType><![CDATA[text]]></MsgType>
    						<Content><![CDATA[%s]]></Content>
    						<FuncFlag>0</FuncFlag>
    						</xml>";  
                $response = '';
            
                if($content == 'QXBD' || $content == 'JCBD') {
                    $this->log('weixin_notify', 'start unbind');
                    $wx_user = $connect->get_user_by_openid($from_user);
                    if(!$wx_user || !$wx_user['uid']) {
                        $this->log('weixin_notify', 'wx user not exsit');
                        $response = '您尚未绑定爱耳目帐号，请先<a href="https://passport.iermu.com/account/weixin/connect">绑定</a>';
                    } else {
                        $this->log('weixin_notify', 'wx user='.json_encode($wx_user));
                        // 解除绑定
                        $ret = $connect->unbind_user_by_openid($from_user);
                        if($ret) {
                            $this->log('weixin_notify', 'unbind success');
                            $response = '爱耳目帐号解除绑定成功！';
                        } else {
                            $this->log('weixin_notify', 'unbind failed');
                            $response = '爱耳目帐号解除绑定失败！请稍后再试！';
                        }
                    }
                }
                
                $this->log('weixin_notify', 'response='.$response);
            
                if($response) {
                    $result = sprintf($tpl, $from_user, $to_user, $this->time, $response);
                    return $result;
                }
            } else if($msg_type == "event") {
                $event_type = $data['Event'];
                $tpl = "<xml>
    						<ToUserName><![CDATA[%s]]></ToUserName>
    						<FromUserName><![CDATA[%s]]></FromUserName>
    						<CreateTime>%s</CreateTime>
    						<MsgType><![CDATA[text]]></MsgType>
    						<Content><![CDATA[%s]]></Content>
    						<FuncFlag>0</FuncFlag>
    						</xml>";  
                $response = '';
                
                if($event_type == 'CLICK') {
                    $event_key = $data['EventKey'];
                    $this->log('weixin_notify', 'event_key='.$event_key);
                    if($event_key == 'IERMU_ACCOUNT_BIND') {
                        $this->log('weixin_notify', 'start bind');
                        $wx_user = $connect->get_user_by_openid($from_user);
                        if(!$wx_user || !$wx_user['uid']) {
                            $this->log('weixin_notify', 'wx user not exsit');
                            $response = '欢迎绑定爱耳目账号，通过微信即可接收摄像机报警提醒，<a href="https://passport.iermu.com/account/weixin/connect">立即绑定</a>！';
                        } else {
                            $this->log('weixin_notify', 'wx user='.json_encode($wx_user));
                            $user = $_ENV['user']->_format_user($wx_user['uid']);
                            $username = $user['mobile']?$user['mobile']:$user['username'];
                            $response = "您好，您已绑定 ".$username." 的爱耳目帐号：
1、 下载手机APP，请<a href=\"http://www.iermu.com/appdownload\">点击这里</a>
2、 购买爱耳目智能摄像机，请<a href=\"http://mall.jd.com/index-1000008608.html\">点击这里</a>
3、 解绑爱耳目账号，请回复【QXBD】";
                        }
                    } else if($event_key == 'IERMU_CUSTOMER_SERVICE') {
                        $response = '亲爱的用户您好！欢迎您关注爱耳目官方微信。客服工作时间是每天早9点至晚6点。请详细描述下您的问题，我们看到后会尽快为您解答。如在非工作时间留言，请您耐心等待，上班后我们会第一时间逐一回复。感谢您对爱耳目的关注与支持，祝您生活愉快！';
                    }
                }
                
                $this->log('weixin_notify', 'response='.$response);
            
                if($response) {
                    $result = sprintf($tpl, $from_user, $to_user, $this->time, $response);
                    return $result;
                }
            }
        }
        
        if(defined('WX_MP_URL') && WX_MP_URL) {
            $this->log('weixin_notify mp request', 'raw='.$raw);
            $ret = $this->weixin_request(WX_MP_URL, $raw);
            $this->log('weixin_notify mp request', 'response='.json_encode($ret));
            if($ret && $ret['http_code'] && $ret['http_code'] == 200 && $ret['data']) {
                return $ret['data'];
            }
        }
        
        if(false && $msg_type != "event") {
            $tpl = "<xml>
    					<ToUserName><![CDATA[%s]]></ToUserName>
    					<FromUserName><![CDATA[%s]]></FromUserName>
    					<CreateTime>%s</CreateTime>
    					<MsgType><![CDATA[transfer_customer_service]]></MsgType>
    					</xml>";
            $result = sprintf($tpl, $from_user, $to_user, $this->time);
            return $result;
        }
        
        return 'success';
    }
    
    function weixin_request($url, $raw='', $httpMethod = 'POST') {
        if(!$url || !$raw)
            return false;
    
        $ch = curl_init();
    
        $curl_opts = array(
            CURLOPT_CONNECTTIMEOUT  => 3,
            CURLOPT_TIMEOUT         => 20,
            CURLOPT_USERAGENT       => 'iermu api server/1.0',
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_FOLLOWLOCATION  => false,
        );

        if (stripos($url, 'https://') === 0) {
            $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
        }

        if (strtoupper($httpMethod) === 'GET') {
            //$query = http_build_query($params, '', '&');
            //$delimiter = strpos($url, '?') === false ? '?' : '&';
            //$curl_opts[CURLOPT_URL] = $url . $delimiter . $query;
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_POST] = false;
        } else {
            $body = $raw;
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_CUSTOMREQUEST] = $httpMethod;
            $curl_opts[CURLOPT_POSTFIELDS] = $body;
        }

        if (!empty($headers)) {
            $curl_opts[CURLOPT_HTTPHEADER] = $headers;
        }
    
        curl_setopt_array($ch, $curl_opts);
        $result = curl_exec($ch);

        if ($result === false) {
            curl_close($ch);
            return false;
        }
    
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        return array('http_code' => $http_code, 'data' => $result);
    } 
    
}
