<?php

!defined('IN_API') && exit('Access Denied');

class appcontrol extends base {

    function __construct() {
        $this->appcontrol();
    }

    function appcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');  
    }

    function onindex() {
    }

    function onservertime() {
        $servertime = gmdate('Y-m-d H:i:s \U\T\CP');
        //$servertime = "2023-02-27 04:07:37 GMT";
        $result['servertime'] = $servertime;
        return $result;
    }

    function oncheckupdate() {
        $this->init_input();
        $check_type = intval($this->input('check_type'));

        //应用认证
        $client = $_ENV['oauth2']->getClientCredentials();
        $client_id = $client[0];
        $client_secret = $client[1];
        $_ENV['oauth2']->client_id = $client_id;

        //应用有效性
        if(!$_ENV['oauth2']->checkClientCredentials($client_id, $client_secret))
            $this->error(API_HTTP_BAD_REQUEST, OAUTH2_ERROR_INVALID_CLIENT);
        
        $version = $_ENV['app']->get_update($client_id, $check_type);
        $result['version'] = $version;
        return $result;
    }

    function ongetnewposter() {
        $this->init_input();

        $client_id = $this->input('client_id');
        $width = intval($this->input('width'));
        $height = intval($this->input('height'));
        if (!$client_id || !$width || !$height)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $width = $width > 0 ? $width : 1;
        $height = $height > 0 ? $height : 1;

        $debug = intval($this->input('debug'));
        $status = $debug ? 0 : 1;

        $result = $_ENV['app']->get_new_poster($client_id, $width, $height, $status);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, CLIENT_ERROR_POSTER_NOT_EXIST);

        return $result;
    }

    function onfeedback() {
        $this->init_user();
        $uid = $this->uid;

        $this->init_input();
        $client_id = $this->input('client_id');
        $opinion = $this->input('opinion');
        $contact = $this->input('contact');
        if (!$client_id || !$opinion || !$contact)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $telmodel = $this->input('telmodel');
        $version = $this->input('version');

        if (!$_ENV['app']->feedback($uid, $client_id, $opinion, $contact, $telmodel, $version))
            $this->error(API_HTTP_BAD_REQUEST, CLIENT_ERROR_FEEDBACK_FAILED);

        $result = array();
        $result['opinion'] = $opinion;
        $result['contact'] = $contact;
        return $result;
    }
}
