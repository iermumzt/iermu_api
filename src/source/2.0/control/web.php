<?php

!defined('IN_API') && exit('Access Denied');

class webcontrol extends base {

    function __construct() {
        $this->webcontrol();
    }

    function webcontrol() {
        parent::__construct();
        $this->load('web');
    }

    // 获取首页图
    function ongetnewposter() {
        $this->init_input();
        $debug = intval($this->input('debug'));

        $status = $debug ? 0 : 1;

        $result = $_ENV['web']->get_new_poster($status);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, CLIENT_ERROR_POSTER_NOT_EXIST);
        
        return $result;
    }

    // 导航图
    function onbanner() {
        $this->init_input();
        $debug = intval($this->input('debug'));

        $status = $debug ? 0 : 1;

        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if(!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['web']->get_banner_list($status, $list_type, $page, $count);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, CLIENT_ERROR_GET_BANNER_LIST_FAILED);
        
        return $result;
    }

    // 文章详情
    function onarticle() {
        $this->init_input();
        $type = intval($this->input('type'));

        switch ($type) {
            case 2: // 商业消息
                $rid = intval($this->input('rid'));
                $sn = $this->input('sn');
                $sign = $this->input('sign');
                if(!$rid || !$sn || !$sign)
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

                $result = $_ENV['web']->get_business_article_info($rid, $sn, $sign);
                break;
            
            default: // 系统消息
                $aid = intval($this->input('aid'));
                if(!$aid)
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

                $result = $_ENV['web']->get_article_info($aid);
                break;
        }
        
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, CLIENT_ERROR_GET_ARTICLE_INFO_FAILED);

        return $result;
    }
}