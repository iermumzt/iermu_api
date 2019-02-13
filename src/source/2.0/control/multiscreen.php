<?php

!defined('IN_API') && exit('Access Denied');

class multiscreencontrol extends base {

    function __construct() {
        $this->multiscreencontrol();
    }

    function multiscreencontrol() {
        parent::__construct();
        $this->load('multiscreen');
        $this->load('user');
    }

    function onindex() {
        $this->init_input();
        $model = $this->input('model');
        $method = $this->input('method');
        $action = 'on'.$model.'_'.$method;

        if (!$model || !$method || !method_exists($this, $action))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);

        unset($this->input['model']);
        unset($this->input['method']);

        return $this->$action();
    }

    // 获取多屏布局
    function onlayout_list() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        
        if (!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['multiscreen']->list_layout($uid, $list_type, $page, $count);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, MULTISCREEN_ERROR_LIST_LAYOUT_FAILED);

        return $result;
    }

    // 添加用户自定义布局
    function onlayout_add() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $rows = intval($this->input('rows'));
        $cols = intval($this->input('cols'));
        $coords = $this->input('coords');
        if ($rows <= 0 || $cols <= 0)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $data = stripcslashes(rawurldecode($coords));
        if ($data) {
            if (!is_array($data = json_decode($coords, true)))
                $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_LAYOUT_FORMAT_ILLEGAL);

            foreach ($data as $value) {
                if (!is_array($value['coord']) || count($value['coord']) !== 2)
                    $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_LAYOUT_FORMAT_ILLEGAL);

                $row = intval($value['coord'][0]);
                $col = intval($value['coord'][1]);
                $rowspan = intval($value['rowspan']);
                $colspan = intval($value['colspan']);

                if ($row <= 0 || $col <= 0 || $rowspan <= 0 || $colspan <= 0 || $row + $rowspan > $rows + 1 || $col + $colspan > $cols + 1)
                    $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_LAYOUT_FORMAT_ILLEGAL);
            }
        }

        $result = $_ENV['multiscreen']->add_layout($uid, $rows, $cols, $coords);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, MULTISCREEN_ERROR_ADD_LAYOUT_FAILED);

        return $result;
    }

    // 删除用户自定义布局
    function onlayout_drop() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $lid = $this->input('lid');

        $layout = $_ENV['multiscreen']->get_layout_by_lid($lid);
        if (!$layout)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_LAYOUT_NOT_EXIST);

        if ($layout['type'] != 1 || $layout['uid'] != $uid)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_LAYOUT_NO_AUTH);

        if (!$_ENV['multiscreen']->drop_layout($lid))
            $this->error(API_HTTP_FORBIDDEN, MULTISCREEN_ERROR_DROP_LAYOUT_FAILED);
        
        return array('lid' => $lid);
    }

    // 获取轮播列表
    function ondisplay_list() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if (!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['multiscreen']->list_display($uid, $list_type, $page, $count);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, MULTISCREEN_ERROR_LIST_DISPLAY_FAILED);

        return $result;
    }

    // 添加用户自定义轮播
    function ondisplay_add() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $lid = intval($this->input('lid'));
        $cycle = intval($this->input('cycle'));
        if (!$lid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        switch ($this->input('data_type')) {
            case 'all': $type = 0; break;
            case 'none': $type = 1; break;
            case 'category': $type = 2; break;
            case 'category_and_none': $type = 3; break;

            default: $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM); break;
        }

        if ($type >= 2) {
            $cid = $this->input('cid');
            if (!$cid)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $cids = explode(',', $cid);
            foreach ($cids as $value) {
                if (!$_ENV['user']->check_category($uid, $value))
                    $this->error(API_HTTP_NOT_FOUND, API_ERROR_USER_CATEGORY_NOT_EXIST);
            }
        } else {
            $cids = array();
        }

        $layout = $_ENV['multiscreen']->get_layout_by_lid($lid);
        if (!$layout)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_LAYOUT_NOT_EXIST);

        if ($layout['type'] == 1 && $layout['uid'] != $uid)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_LAYOUT_NO_AUTH);

        $display = $_ENV['multiscreen']->get_display_by_pk($uid, $lid);
        if ($display) {
            $_ENV['multiscreen']->drop_display($display['did']);
        }

        $result = $_ENV['multiscreen']->add_display($uid, $lid, $cycle, $type, $cids);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, MULTISCREEN_ERROR_ADD_DISPLAY_FAILED);

        return $result;
    }

    // 用户自定义轮播生效
    function ondisplay_active() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $did = intval($this->input('did'));

        $display = $_ENV['multiscreen']->get_display_by_did($did);
        if (!$display)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_DISPLAY_NOT_EXIST);

        if ($display['uid'] != $uid)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_DISPLAY_NO_AUTH);

        if (!$_ENV['multiscreen']->active_display($uid, $did))
            $this->error(API_HTTP_FORBIDDEN, MULTISCREEN_ERROR_ACTIVE_DISPLAY_FAILED);

        return array('did' => $did);
    }

    // 删除用户自定义轮播
    function ondisplay_drop() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $did = intval($this->input('did'));

        $display = $_ENV['multiscreen']->get_display_by_did($did);
        if (!$display)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_DISPLAY_NOT_EXIST);

        if ($display['uid'] != $uid)
            $this->error(API_HTTP_BAD_REQUEST, MULTISCREEN_ERROR_DISPLAY_NO_AUTH);

        if (!$_ENV['multiscreen']->drop_display($did))
            $this->error(API_HTTP_FORBIDDEN, MULTISCREEN_ERROR_DROP_DISPLAY_FAILED);

        return array('did' => $did);
    }
}