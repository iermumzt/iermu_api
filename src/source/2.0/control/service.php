<?php

!defined('IN_API') && exit('Access Denied');

class servicecontrol extends base {

    function __construct() {
        $this->servicecontrol();
    }

    function servicecontrol() {
        parent::__construct();
        $this->load('oauth2');
        $this->load('service');
    }
    
    function onindex() {
        $this->init_input();
        $model = $this->input('model');
        $method = $this->input('method');
        $action = 'on'.$model.'_'.$method;
        if ($model && $method && method_exists($this, $action)) {
            unset($this->input['model']);
            unset($this->input['method']);
            return $this->$action();
        }
        $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    }

    function onmessage_listcontact() {
        $this->init_user();
        $uid = $this->uid;
        $client_id = $this->client_id;
        $platform = $_ENV['oauth2']->get_client_platform($client_id);
        if (!$uid || !$client_id || !$platform)
            $this->user_error();

        $result = $_ENV['service']->service_message_contact($uid, $client_id, $platform);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, SERVICE_ERROR_LIST_MESSAGE_CONTACT_FAILED);

        return $result;
    }

    function onmessage_history() {
        $this->init_user();
        $uid = $this->uid;
        $client_id = $this->client_id;
        $platform = $_ENV['oauth2']->get_client_platform($client_id);
        if (!$uid || !$client_id || !$platform)
            $this->user_error();

        $this->init_input();
        $id = intval($this->input('id'));
        $type = intval($this->input('type'));
        if (!$id || !$type) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);


        $list_type = $this->input('list_type');
        if (!in_array($list_type, array('page', 'timeline')))
            $list_type = 'page';

        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $st = intval($this->input('st'));
        $et = intval($this->input('et'));
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        $st = $st > 0 ? $st : 0;
        $et = $et > 0 ? $et : 0;

        switch ($type) {
            case 3:
                $result = $_ENV['service']->list_service_message($id, $uid, $client_id, $platform, $list_type, $page, $count, $st, $et);
                break;
            
            default:
                $result = array();
                break;
        }

        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, SERVICE_ERROR_LIST_MESSAGE_HISTORY_FAILED);

        return $result;
    }

    function onpush_listpushserver() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        // push_type
        $push_type = $this->input('push_type');
        if (!$push_type) {
            $push_type = "";
        } else {
            if (!in_array($push_type, array('ws'))) {
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
            }
        }
        
        $result = $_ENV['service']->listpushserver($uid, $push_type, $this->client_id, $this->appid);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, SERVICE_ERROR_LIST_PUSH_SERVER_FAILED);

        return $result;
    }
}