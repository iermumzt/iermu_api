<?php

!defined('IN_API') && exit('Access Denied');

class searchcontrol extends base {

    function __construct() {
        $this->searchcontrol();
    }

    function searchcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('oauth2');
        $this->load('user');
        $this->load('device');
        $this->load('search');
    }

    // 获取搜索关键词列表
    function onlist() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['search']->list_keyword($uid, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_GET_KEYWORD_FAILED);
        
        return $result;
    }

    // 删除搜索关键词
    function ondrop() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $keyword = $this->input('keyword');

        $result = $_ENV['search']->drop_keyword($uid, $keyword);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_USER_DEL_KEYWORD_FAILED);
        
        return $result;
    }

    // 搜索用户列表
    function onuser() {
        $this->init_input();

        $keyword = $this->input('keyword');
        $sharenum = intval($this->input('sharenum'));
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->user_error();

            $_ENV['search']->save_keyword($uid, $keyword, $this->client_id, $this->appid);
        }

        $sharenum = $sharenum > 0 ? $sharenum : 0;
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['user']->search_user($keyword, $sharenum, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_SEARCH_USER_LIST_FAILED);
        
        return $result;
    }

    // 搜索分享设备列表
    function onshare() {
        $this->init_input();

        $uk = intval($this->input('uk'));
        $keyword = $this->input('keyword');
        $device_type = $this->input('device_type');
        $category = intval($this->input('category'));
        $orderby = $this->input('orderby');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $appid = intval($this->input('appid'));

        if($this->init_user()) {
            $appid = $this->appid;
            $uid = $this->uid;
            if (!$uid)
                $this->user_error();

            $_ENV['search']->save_keyword($uid, $keyword, $this->client_id, $appid);
        } else {
            $uid = 0;
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['device']->search_share($uid, $uk, $keyword, $device_type, $category, $orderby, $page, $count, $appid);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_SEARCH_SHARE_FAILED);
        
        return $result;
    }
}
