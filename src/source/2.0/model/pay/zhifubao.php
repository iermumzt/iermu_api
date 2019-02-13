<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/pay/pay.php';

class zhifubaopay extends pay {

    function __construct(&$base, $service) {
        $this->zhifubaopay($base, $service);
    }

    function zhifubaopay(&$base, $service) {
        parent::__construct($base, $service);
    }

    function payment($out_trade_no, $subject, $total_fee, $logid) {
        $pid = $this->pay_config['pid'];
        $key = $this->pay_config['key'];
        $notify_url = $this->pay_config['notify_url'];

        if (!$pid || !$key || !$notify_url || !$out_trade_no || !$subject || !$total_fee || !$logid)
            return false;

        // 兼容手机端不能传递参数的问题
        $notify_url .= '/'.$logid;

        $arr = array(
            '_input_charset' => 'utf-8',
            'notify_url' => $notify_url,
            'out_trade_no' => $out_trade_no,
            'partner' => $pid,
            'payment_type' => 1,
            'return_url' => $notify_url,
            'seller_id' => $pid,
            'service' => API_DISPLAY === 'mobile' ? 'alipay.wap.create.direct.pay.by.user' : 'create_direct_pay_by_user',
            'subject' => $subject,
            'total_fee' => $total_fee
        );

        $param = $this->_build_request_param($arr, $key);

        return $this->_build_request_form($param);
    }

    function _build_request_param($param, $key) {
        $param['sign'] = $this->_sign($param, $key);
        $param['sign_type'] = 'MD5';

        return $param;
    }

    function _build_request_form($param, $method = 'POST') {
        $html = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>支付宝即时到账交易接口</title></head><body><form id="alipaysubmit" name="alipaysubmit" action="https://mapi.alipay.com/gateway.do?_input_charset=utf-8" method="'.$method.'">';
        foreach ($param as $key => $value) {
            $html .= '<input type="hidden" name="'.$key.'" value="'.$value.'"/>';
        }
        $html .= '<input type="submit" value="确定" style="display:none;"></form><script>document.forms["alipaysubmit"].submit();</script></body></html>';

        return $html;
    }

    function payment_notify() {
        $pid = $this->pay_config['pid'];
        $key = $this->pay_config['key'];
        $param = $_POST ? $_POST : $_GET;

        if (!$param || !$pid || !$key)
            return false;

        if ($param['seller_id'] != $pid)
            return false;

        $arr = $this->_parse_param($param);
        $sign = $this->_sign($arr, $key);
        if ($param['sign'] != $sign)
            return false;

        $veryfy_url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&partner='.$pid.'&notify_id='.$param['notify_id'];
        $response = $this->_request($veryfy_url);
        if ($response != 'true')
            return false;

        switch ($param['trade_status']) {
            case 'TRADE_SUCCESS': $status = 1; break;
            case 'TRADE_FINISHED': $status = 2; break;    
            default: $status = 0; break;
        }

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'pay_notify SET payid="1",payno="'.$param['trade_no'].'",status="'.$status.'",data=\''.serialize($param).'\',dateline="'.$this->base->time.'"');

        return true;
    }

    function _parse_param($param) {
        $arr = array();
        foreach ($param as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type' || $value === '')
                continue;

            $arr[$key] = $value;
        }
        ksort($arr);
        return $arr;
    }

    function _sign($param, $key) {
        $str = $this->_http_build_query($param, false);
        return md5($str.$key);
    }

    function _http_build_query($arr, $encode = TRUE) {
        $str = '';
        foreach ($arr as $key => $value) {
            if ($encode) {
                $key = urlencode($key);
                $value = urlencode($value);
            }

            $str .= $key.'='.$value.'&';
        }
        return rtrim($str, '&');
    }

    function _request($url, $param = array(), $method = 'GET') {
        $ch = curl_init();
        $curl_opts = array(
            CURLOPT_CONNECTTIMEOUT  => 10,
            CURLOPT_TIMEOUT         => 20,
            CURLOPT_USERAGENT       => 'iermu-api-php-v2',
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_FOLLOWLOCATION  => false
        );

        if (stripos($url, 'https://') === 0) {
            $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
        }

        $query = $this->_http_build_query($param);
        if (strtoupper($method) === 'GET') {
            $delimiter = strpos($url, '?') === false ? '?' : '&';
            $curl_opts[CURLOPT_URL] = $url.$delimiter.$query;
            $curl_opts[CURLOPT_POST] = false;
        } else {
            $curl_opts[CURLOPT_URL] = $url;
            $curl_opts[CURLOPT_POST] = true;
            $curl_opts[CURLOPT_POSTFIELDS] = $query;
        }

        curl_setopt_array($ch, $curl_opts);
        $response = curl_exec($ch);

        return $response;
    }
}