<?php

!defined('IN_API') && exit('Access Denied');

class qrcodecontrol extends base {
    
    var $cookie_status = 1;

    function __construct() {
        $this->qrcodecontrol();
    }

    function qrcodecontrol() {
        parent::__construct();
        $this->load('oauth2');
        $this->load('user');
        $_ENV['oauth2']->setVariable('force_api_response', 0);  
    }
    
    function onstatus() {
        $this->init_input();
        
        $code = $this->input('code');
        if(!$code)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $qrcode = $_ENV['qrcode']->get_qrcode_by_code($code);
        if(!$qrcode)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        
        $status = intval($qrcode['status']);
        if($qrcode['expires'] < $this->time || ($status == 1 && $this->time - $qrcode['scandate'] > 30)) {
            if($status >= 0) {
                $status = -2;
                $_ENV['qrcode']->update_qrcode_status($qrcode['cid'], $status);
            }
        }
        
        $result = array(
            'status' => $status
        );
        
        if($status == 1) {
            $result['avatar'] = $_ENV['user']->get_avatar($qrcode['uid']);
        }
        
        return $result;
    }

    function onscan() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        
        $key = $this->input('key');
        if(!$key)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $qrcode = $_ENV['qrcode']->get_qrcode_by_key($key);
        if(!$qrcode)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        
        if($qrcode['expires'] < $this->time || ($qrcode['status'] == 1 && $this->time - $qrcode['scandate'] > 30)) {
            if($qrcode['status'] >= 0) {
                $_ENV['qrcode']->update_qrcode_status($qrcode['cid'], -2);
            }
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        }
        
        if($qrcode['status'] < 0 || $qrcode['status'] > 1)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        
        if($qrcode['status'] == 1 && $qrcode['uid'] != $uid)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        
        $ret = $_ENV['qrcode']->scan_qrcode($qrcode['cid'], $uid);
        if(!$ret)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_SCAN_FAILED);
        
        $title = $qrcode['title'];
        $client = $_ENV['oauth2']->_format_client_by_client_id($qrcode['client_id']);
        if($client && $client['title']) {
            $title = $client['title'];
        }
        
        $result = array(
            'client_id' => $qrcode['client_id'],
            'title' => $title,
            'platform' => intval($qrcode['platform']),
            'hardware' => intval($qrcode['hardware']),
            'hardware_id' => strval($qrcode['hardware_id']),
            'scope' => $qrcode['scope']
        );
        return $result;
    }
    
    function onauth() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->_error(API_HTTP_FORBIDDEN, API_ERROR_TOKEN);
        
        $this->init_input();
        
        $key = $this->input('key');
        if(!$key)
            $this->_error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $auth = $this->input('auth')?1:0;
        
        $qrcode = $_ENV['qrcode']->get_qrcode_by_key($key);
        if(!$qrcode)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        
        if($qrcode['expires'] < $this->time || ($qrcode['status'] == 1 && $this->time - $qrcode['scandate'] > 30)) {
            if($qrcode['status'] >= 0) {
                $_ENV['qrcode']->update_qrcode_status($qrcode['cid'], -2);
            }
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        }
        
        if($qrcode['status'] != 1 || $qrcode['uid'] != $uid)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_INVALID);
        
        if($auth) {
            $ret = $_ENV['qrcode']->confirm_qrcode($qrcode['cid']);
        } else {
            $ret = $_ENV['qrcode']->cancel_qrcode($qrcode['cid']);
        }
        
        if(!$ret)
            $this->_error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_QRCODE_AUTH_FAILED);
        
        $result = array();
        return $result;
    }
    
    function _error($http_status_code, $error, $error_description = NULL, $error_uri = NULL) {
        return $_ENV['oauth2']->errorJsonResponse($http_status_code, $error, $error_description, $error_uri);
    }

}
