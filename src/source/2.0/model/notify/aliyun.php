<?php

!defined('IN_API') && exit('Access Denied');

include_once API_SOURCE_ROOT.'lib/connect/aliyun/aliyun-php-sdk-core/Config.php';
require_once API_SOURCE_ROOT.'lib/connect/aliyun/aliyun-dysms-php-sdk/api_sdk/vendor/autoload.php';

use Aliyun\Core\Config;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;
use Sms\Request\V20160927 as Sms;
use Dm\Request\V20151123 as Dm;

require_once API_SOURCE_ROOT.'model/notify/notify.php';

// 加载区域结点配置
Config::load();

class aliyunnotify  extends notify {

    static $acsClient = null;

	function __construct(&$base, $service) {
		$this->aliyunnotify($base, $service);
	}

	function aliyunnotify(&$base, $service) {
		parent::__construct($base, $service);
	}

    /**
     * 取得AcsClient
     *
     * @return DefaultAcsClient
     */
    function getAcsClient() {
        //产品名称:云通信流量服务API产品,开发者无需替换
        $product = "Dysmsapi";

        //产品域名,开发者无需替换
        $domain = "dysmsapi.aliyuncs.com";

        $accessKeyId = $this->notify_config['ak'];

        $accessKeySecret = $this->notify_config['sk']; // AccessKeySecret

        // 暂时不支持多Region
        $region = "cn-hangzhou";

        // 服务结点
        $endPointName = "cn-hangzhou";


        if(static::$acsClient == null) {

            //初始化acsClient,暂不支持region化
            $profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);

            // 增加服务结点
            DefaultProfile::addEndpoint($endPointName, $region, $product, $domain);

            // 初始化AcsClient用于发起请求
            static::$acsClient = new DefaultAcsClient($profile);
        }
        return static::$acsClient;
    }
    
    function sendverify($verifycode, $params) {
        if(!$verifycode)
            return false;
        
        if(!defined('API_APP')) define('API_APP', '');
        
        $this->base->log('aliyun sendverify', 'app='.API_APP.',code='.json_encode($verifycode).',params='.json_encode($params));
        
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
                $vars = array(
                    "to" => $email,
                    "sub" => array(
                        "%name%" => $params['name'],
                        "%operation%" => $params['operation'],
                        "%code%" => $params['code'],
                        "%expiretime%" => $params['expiretime']
                    )
                );
                if(API_APP == 'smartseye') {
                    $template = 'verifycode_smartseye';
                } else {
                    $template = 'verifycode';
                }
            } else if($type == 'resetpwd') {
                $vars = array(
                    "to" => $email,
                    "sub" => array(
                        "%name%" => $params['name'],
                        "%resetpwd_url%" => $params['resetpwd_url']
                    )
                );
                if(API_APP == 'smartseye') {
                    $template = 'resetpwd_smartseye';
                } else {
                    $template = 'resetpwd';
                }
            }  else if($type == 'checkauth') {
                $vars = array(
                    "to" => $email,
                    "sub" => array(
                        "%name%" => $params['name'],
                        "%operation%" => $params['operation'],
                        "%code%" => $params['code'],
                        "%expiretime%" => $params['expiretime']
                    )
                );
        
                if(API_APP == 'smartseye') {
                    $template = 'verifycode_smartseye';
                } else {
                    $template = 'verifycode';
                }
            }  else if($type == 'activeemail') {
                $vars = array(
                    "to" => $email,
                    "sub" => array(
                        "%name%" => $params['name'],
                        "%active_code%" => $params['active_code'],
                        "%active_url%" => $params['active_url']
                    )
                );
                
                if(API_APP == 'smartseye') {
                    $template = 'active_account_smartseye';
                } else {
                    $template = 'active_account';

                }
            }
            $ret = $this->_send_template_mail_by_service($email, $template, $vars);

            if($ret && $ret['data'] && $ret['data']['RequestId']) {
                if($ret['data']) {
                    $status = 1;
                    $senddate = $this->base->time;
                    $notifyno = $ret['data']['RequestId'];
                    $notifystatus = '200';
                    $notifymsg = 'success';
                } else if($ret['error']) {
                    $notifystatus = $ret['error']['ErrorCode'];
                    $notifymsg = $ret['error']['ErrorMessage'];
                }
            }
        } else if($send == 'sms') {
            $vars = array(
                "code" => strval($code),
                "expire_time" => "30分钟"
            );
            $template = 'SMS_41555232';
            $ret = $this->_send_template_sms_by_service_($countrycode, $mobile, $template, $vars);
            if($ret) {
                if($ret['data']) {
                    $status = 1;
                    $senddate = $this->base->time;
                    $notifyno = $ret['data']['RequestId'];
                    $notifystatus = '200';
                    $notifymsg = 'success';
                } else if($ret['error']) {
                    $notifystatus = $ret['error']['ErrorCode'];
                    $notifymsg = $ret['error']['ErrorMessage'];
                }
            }
        }
        
        $this->base->log('aliyun sendverify', 'update='."UPDATE ".API_DBTABLEPRE."member_verifycode SET `status`='$status', `senddate`='$senddate', `notifyno`='$notifyno', `notifystatus`='$notifystatus', `notifymsg`='$notifymsg', `notifydate`='$notifydate' WHERE `codeid`='$codeid'");
        
        // update db
        $this->db->query("UPDATE ".API_DBTABLEPRE."member_verifycode SET `status`='$status', `senddate`='$senddate', `notifyno`='$notifyno', `notifystatus`='$notifystatus', `notifymsg`='$notifymsg', `notifydate`='$notifydate' WHERE `codeid`='$codeid'");
        
        return $status>0?true:false;
    }

    function _send_template_mail_by_service($email, $template, $vars) {
        if(!$email || !$template || !$vars)
            return false;

        $this->base->log('aliyun _send_template_mail_by_service', 'email='.$email.',template='.$template.',vars='.json_encode($vars));

        //获取模板html内容
        $data_body = $this->gettemplatecontent($template);

        if(!$data_body) {
            return false;
        }
        $htmlBody = $data_body['content'];
        $titleBody = $data_body['title'];
        foreach ($vars['sub'] as $k=>$v){
            $htmlBody = str_replace($k,$v,$htmlBody);
            $titleBody = str_replace($k , $v , $titleBody);
        }
        $ak = $this->notify_config['ak'];
        $sk = $this->notify_config['sk'];
        $from = $this->notify_config['from'];
        $fromname = $this->notify_config['fromname'];
        if(!$ak || !$sk)
            return false;
        $iClientProfile = DefaultProfile::getProfile("cn-hangzhou", $ak, $sk);
        $client = new DefaultAcsClient($iClientProfile);
        $request = new Dm\SingleSendMailRequest();
        $request->setAccountName($from);
        $request->setFromAlias($fromname);
        $request->setAddressType(1);
        $request->setReplyToAddress("true");
        $request->setToAddress($vars['to']);
        $request->setSubject($titleBody);
        $request->setHtmlBody($htmlBody);
        $result = array();
        try {
            $response = $client->getAcsResponse($request);
            $data = array(
                'RequestId' => $response->RequestId
            );
            $result['data'] = $data;
        }
        catch (ClientException  $e) {
            $error = array(
                'ErrorCode' => $e->getErrorCode(),
                'ErrorMessage' => $e->getErrorMessage()
            );
            $result['error'] = $error;
        }
        catch (ServerException  $e) {
            $error = array(
                'ErrorCode' => $e->getErrorCode(),
                'ErrorMessage' => $e->getErrorMessage()
            );
            $result['error'] = $error;
        }
        return $result;
    }

    //老版本阿里云短信
    function _send_template_sms_by_service($countrycode, $mobile, $template, $vars) {
        if(!$countrycode || !$mobile || !$template || !$vars)
            return false;
        
        $this->base->log('aliyun _send_template_sms_by_service', 'countrycode='.$countrycode.',mobile='.$mobile.',template='.$template.',vars='.json_encode($vars));
        
        // 只支持国内手机号
        if($countrycode != '+86')
            return false;
        
        $template = strval($template);
        $mobile = strval($mobile);
        $param = json_encode($vars);
        
        $ak = $this->notify_config['ak'];
        $sk = $this->notify_config['sk'];

        if(!$ak || !$sk)
            return false;
        $iClientProfile = DefaultProfile::getProfile("cn-hangzhou", $ak, $sk);
        $client = new DefaultAcsClient($iClientProfile);    
        $request = new Sms\SingleSendSmsRequest();
        $request->setSignName("爱耳目摄像机");/*签名名称*/
        $request->setTemplateCode($template);/*模板code*/
        $request->setRecNum($mobile);/*目标手机号*/
        $request->setParamString($param);/*模板变量，数字一定要转换为字符串*/
        $result = array();
        try {
            $response = $client->getAcsResponse($request);
            $data = array(
                'RequestId' => $response->RequestId
            );
            $result['data'] = $data;
        }
        catch (ClientException  $e) {
            $error = array(
                'ErrorCode' => $e->getErrorCode(),
                'ErrorMessage' => $e->getErrorMessage()
            );
            $result['error'] = $error;
        }
        catch (ServerException  $e) {
            $error = array(
                'ErrorCode' => $e->getErrorCode(),
                'ErrorMessage' => $e->getErrorMessage()
            );
            $result['error'] = $error;
        }
        
        return $result;
    }

    /**
     * 发送短信 新版本阿里云短信
     * @return stdClass
     */
    function _send_template_sms_by_service_($countrycode, $mobile, $template, $vars) {

        if(!$countrycode || !$mobile || !$template || !$vars)
            return false;

        $this->base->log('aliyun _send_template_sms_by_service', 'countrycode='.$countrycode.',mobile='.$mobile.',template='.$template.',vars='.json_encode($vars));

        // 只支持国内手机号
        if($countrycode != '+86')
            return false;

        $template = strval($template);
        $mobile = strval($mobile);

        // 初始化SendSmsRequest实例用于设置发送短信的参数
        $request = new SendSmsRequest();

        // 必填，设置短信接收号码
        $request->setPhoneNumbers($mobile);

        // 必填，设置签名名称，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $request->setSignName("爱耳目摄像机");

        // 必填，设置模板CODE，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $request->setTemplateCode($template);

        $param = json_encode($vars);
        // 可选，设置模板参数, 假如模板中存在变量需要替换则为必填项
        $request->setTemplateParam($param);

        // 发起访问请求
        $result = array();
        try {
            $response = static::getAcsClient()->getAcsResponse($request);
            $data = array(
                'RequestId' => $response->RequestId
            );
            $result['data'] = $data;
        }
        catch (ClientException  $e) {
            $error = array(
                'ErrorCode' => $e->getErrorCode(),
                'ErrorMessage' => $e->getErrorMessage()
            );
            $result['error'] = $error;
        }
        catch (ServerException  $e) {
            $error = array(
                'ErrorCode' => $e->getErrorCode(),
                'ErrorMessage' => $e->getErrorMessage()
            );
            $result['error'] = $error;
        }

        return $result;
    }

    function gettemplatecontent($template){
        if(!$template){
            return false;
        }
        $lang = API_LANGUAGE;

        if(API_APP == 'smartseye'){
            $lang = 'en';
        }
        $result = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'notify_email_template_lang WHERE tid="'.$template.'" AND lang="'.$lang.'"');
        if (!$result){
            $result = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'notify_email_template WHERE tid="'.$template.'"');
            if(!$result){
                return false;
            }
        }
        return $result;
    }

    function sendreportnotify($admins, $params) {
        if(!$admins || !$params)
            return false;

        $template = 'cn_device_report_admin_notification';
        foreach($admins as $admin) {
            $vars = array(
                "to" => $admin['email'],
                "sub" => array(
                    "%deviceid%" => $params['deviceid'],
                    "%title%" => $params['title'],
                    "%thumbnail%" => $params['thumbnail'],
                    "%url%" => $params['url']
                )
            );
            $ret = $this->_send_template_mail_by_service($template, $vars);
            if(!$ret)
                return false;
        }
        return true;
    }

    function sendreportoffline($admins, $params) {
        if(!$admins || !$params)
            return false;

        $template = 'cn_device_report_offline_notification';
        foreach($admins as $admin) {
            $vars = array(
                "to" => $admin['email'],
                "sub" => array(
                    "%deviceid%" => $params['deviceid'],
                    "%title%" => $params['title'],
                    "%thumbnail%" => $params['thumbnail'],
                    "%admin%" => $params['admin']
                )
            );
            $ret = $this->_send_template_mail_by_service($template, $vars);
            if(!$ret)
                return false;
        }
        return true;
    }
    
}
?>