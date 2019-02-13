<?php

!defined('IN_API') && exit('Access Denied');

class homecontrol extends base {

    function __construct() {
        $this->homecontrol();
    }

    function homecontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
    }

    function oninfo() {
        $this->init_input();
        $uk = intval($this->input('uk'));
        if(!$uk) 
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);

        $user = $_ENV['user']->get_user_by_uid($uk);
        if (!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXIST);

        $result = array();
        $result['uid'] = $uk;
        $result['username'] = $user['username'];
        $result['avatar'] = $_ENV['user']->get_avatar($uk);
        return $result;
    }

    function onlistshare() {
        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, TOKEN_ERROR);
        } else {
            $uid = 0;
        }
        
        $this->init_input();
        $uk = intval($this->input('uk'));
        $sign = $this->input['sign'];
        $expire = intval($this->input['expire']);

        if(!$sign || !$expire || !$uk)
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);

        $check = $_ENV['device']->_check_sign($sign, $expire);
        if(!$check)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_PUBLIC_SHARE_AUTH);

        $client_id = $check['client_id'];
        $appid = $check['appid'];

        $user = $_ENV['user']->get_user_by_uid($uk);
        if (!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXIST);
        
        $orderby = $this->input['orderby'];
        $category = intval($this->input['category']);
        $commentnum = intval($this->input['commentnum']);
        $page = intval($this->input['page']);
        $count = intval($this->input['count']);
        
        // 兼容老参数: start,num
        $start = intval($this->input['start']);
        $num = intval($this->input['num']);

        if($start<0 || $num<0)
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);
        
        if($start || $num) {
            if(!$num) $num = 10;
            $page = $start/$num + 1;
            $count = $num;
        }

        $category = $category > 0 ? $category : 0;
        $commentnum = $commentnum > 0 ? $commentnum : 0;
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $support_type = $_ENV['oauth2']->get_client_connect_support($client_id);

        return $_ENV['device']->list_share($uid, $uk, $support_type, $category, $orderby, $commentnum, $page, $count, $appid, 0);
    }

    // 获取设备评论列表
    function onlistcomment() {
        $this->init_input();

        $uk = intval($this->input('uk'));
        if(!$uk) 
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);

        $user = $_ENV['user']->get_user_by_uid($uk);
        if (!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXIST);

        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $st = intval($this->input('st'));
        $et = intval($this->input('et'));

        if(!$list_type || !in_array($list_type, array('page', 'timeline'))) {
            $list_type = 'page';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        $st = $st > 0 ? $st : 0;
        $et = $et > 0 ? $et : 0;

        $result = $_ENV['user']->list_comment($uk, $list_type, $page, $count, $st, $et);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_GET_USER_COMMENT_FAILED);
        
        return $result;
    }

    // 添加设备评论
    function oncomment() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, TOKEN_ERROR);

        $this->init_input();
        $uk = intval($this->input('uk'));
        if(!$uk) 
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);

        $user = $_ENV['user']->get_user_by_uid($uk);
        if (!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXIST);

        $comment = $this->input('comment');
        $parent_cid = $this->input('parent_cid');

        $result = $_ENV['user']->save_comment($uid, $uk, $comment, $parent_cid, $this->client_id, $this->appid);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_ADD_USER_COMMENT_FAILED);

        $user = $_ENV['user']->_format_user($uid);
        $result['comment']['uid'] = $user['uid'];
        $result['comment']['username'] = $user['username'];
        $result['comment']['avatar'] = $user['avatar'];

        return $result;
    }

    // 获取访客记录
    function onlistview() {
        $this->init_input();
        $uk = intval($this->input('uk'));
        if(!$uk) 
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);

        $user = $_ENV['user']->get_user_by_uid($uk);
        if (!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXIST);

        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['user']->list_view($uk, $this->appid, $page, $count);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_LIST_USER_VIEW_FAILED);
        
        return $result;
    }

    // 添加访客记录
    function onview() {
        $this->init_input();
        $uk = intval($this->input('uk'));
        if(!$uk) 
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);

        $user = $_ENV['user']->get_user_by_uid($uk);
        if (!$user)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXIST);

        if($this->init_user()) {
            $uid = $this->uid;
            if(!$uid)
                $this->error(API_HTTP_FORBIDDEN, TOKEN_ERROR);

            $_ENV['user']->save_view($uid, $uk, $this->client_id, $this->appid);
        }

        $result = $_ENV['user']->add_view($uk);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_ADD_USER_VIEW_FAILED);
        
        return $result;
    }

    // 删除访客记录
    function ondropview() {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->error(API_HTTP_FORBIDDEN, TOKEN_ERROR);

        $this->init_input();
        $uk = $this->input('uk');
        $uk_list = explode(',', $uk);
        $list = array();
        foreach ($uk_list as $value) {
            if($value) {
                $user = $_ENV['user']->get_user_by_uid($value);
                if(!$user) 
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_USER_NOT_EXIST);

                $list[] = $value;
            }
        }

        if(empty($list))
            $this->error(API_HTTP_BAD_REQUEST, PARAM_ERROR);

        $result = $_ENV['user']->drop_view($uid, $list);
        if(!$result) 
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, API_ERROR_DROP_USER_VIEW_FAILED);
        
        return $result;
    }

    // 用户昵称
    function onupdate() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $uk = $this->input['uk'];
        if(!$uk)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $remarkname = $this->input['remarkname'];
        $remarkname = ($remarkname === NULL || $remarkname === '') ? '' : strval($remarkname);

        if (!$_ENV['user']->save_remarkname($uid, $uk, $remarkname))
            $this->error(API_HTTP_FORBIDDEN, API_ERROR_USER_SAVE_REMARKNAME_FAILED);

        return array('uid' => $uk, 'remarkname' => $remarkname);
    }
}
