<?php

!defined('IN_API') && exit('Access Denied');

class statisticscontrol extends base
{

    function __construct()
    {
        $this->statisticscontrol();
    }

    function statisticscontrol()
    {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');
        $this->load('server');
        $this->load('statistics');
    }

    /**
     * 获取当前用户AI摄像机
     * 参数：
     *      access_token
     */
    function onlistalldevice()
    {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        $this->init_input();
        //设备 IDs
        $result = $_ENV['statistics']->list_ai_devices($uid);
        $data = [];
        $data['list'] = [];
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NOT_EXIST);
        //return $result;
        foreach ($result['list'] as $k => $val){
            $device = $_ENV['device']->get_device_by_did($val['deviceid']);
            if (!$device)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            $params['auth_type'] = 'token';
            $params['uid'] = $uid;
            //是否为统计中的设备
            $params['statistics'] = $val['statistics'];
            $data['list'][] =  $_ENV['statistics']->meta($device, $params);
            //var_dump($data);die;
        }
        $data['count'] = count($data['list']);
        return $data;
    }

    /**
     * 获取当前用户正在统计中的AI摄像机信息
     *   参数：
     *      access_token
     */
    function onlistdevice()
    {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        $this->init_input();
        //是否在统计中
        $statistics = $this->input('is_statistics');
        // 默认输出全部AI设备
        $is_statistics = false;
        if(isset($statistics) && $statistics == 1){
            $is_statistics = true;
        }
        $data = [];
        //设备类型
        if($is_statistics){
            $result = $_ENV['statistics']->list_devices_in_statistics($uid);
        }else{
            $result = $_ENV['statistics']->list_ai_devices($uid);
        }
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, DEVICE_ERROR_NOT_EXIST);
        foreach ($result['list'] as $k => $val){
            $device = $_ENV['device']->get_device_by_did($val['deviceid']);
            if (!$device)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
            $params['auth_type'] = 'token';
            $params['uid'] = $uid;
            $params['statistics'] = $val['statistics'];
            $data['list'][] =  $_ENV['statistics']->meta($device, $params);
        }
        $data['count'] = count($data['list']);
        return $data;
    }

    /**
     * 增加AI摄像机
     *   参数：
     *      access_token
     */
    function onadddevice()
    {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $dev_list = array();
        $list = explode(',', $deviceid);
        foreach ($list as $value) {
            if($value) {
                $device = $_ENV['statistics']->add_ai_device_statistics($uid , $value);
                if(!$device || $device['error'] == -1)
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
                $dev_list[] = $value;
            }
        }

        $result['deviceid'] = $dev_list;
        $result['uid'] = $uid;
        //$result = $_ENV['statistics']->add_ai_device_statistics($uid , 102010002);
        //if (!$result)
        //    $this->error(NO_ADDED_AI_DEVICE, NO_ADDED_AI_DEVICE);
        //if($result['code'] == 10000){
        //    $device = $_ENV['device']->get_device_by_did($result['deviceid']);
        //    $params['auth_type'] = 'token';
        //    $params['uid'] = $uid;
        //    $data =  $_ENV['statistics']->meta($device, $params);
        //    $result['info'] = $data;
        //}
        return $result;
    }

    /**
     * 减少AI摄像机
     * 参数：
     *      access_token
     */
    function ondropdevice()
    {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $this->init_input();
        //设备ID
        $deviceid = $this->input('deviceid');
        $dev_list = array();
        $list = explode(',', $deviceid);

        foreach ($list as $value) {
            if($value) {
                $device = $_ENV['statistics']->del_ai_device_statistics($uid , $value);
                if(!$device || $device['error'] == -1)
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
                $dev_list[] = $value;
            }
        }
        //$deviceid = $this->input('deviceid');
        $result['deviceid'] = $dev_list;
        $result['uid'] = $uid;
        return $result;
    }

    /**
     * 用户下AI摄像机的添加和减少
     * 参数：
     *      access_token
     *      deviceid
     */
    function oneditdevice()
    {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $this->init_input();
        //设备ID
        $deviceid = $this->input('deviceid');
        //需要添加的设备数组
        $list = explode(',', $deviceid);
        $result = $_ENV['statistics']->edit_device_statistics($uid , $list);
        if($result['error'] == -1)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);
        $res['deviceid'] = $list;
        $res['uid'] = $uid;
        return $res;
    }


    /**
     * 店内实时人数
     */
    function oncurrentnumber()
    {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $this->init_input();

    }

    /**
     * 获取某一天的所有统计记录（提供统计图数据）
     * 参数：
     * access_token
     * statistics_type（默认1小时）
     * date_type:1:day 2:week 3:month
     * date ： 获取点击的日期，接收格式 2017-8-21
     */
    function oncartogram()
    {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $this->init_input();
        $statistics_type = $this->input('statistics_type');
        $date_type = $this->input('date_type');

        //若设置deviceid 则为取单个设备
        $deviceid = $this->input('deviceid');
        //获取点击的日期，接收格式 2017-8-21
        $date = $this->input('date');

        switch ($date_type){
            case 'day':
                //默认按照小时返回数据
                $statistics_type = 3;
                //查询某一天24小时的数据
                $result = $_ENV['statistics']->get_cartogram_data_day($uid  , $statistics_type , $date , $date_type , $deviceid);
                break;
            case 'week':
                //默认按照天返回数据
                $statistics_type = 1;
                //查询当天到前7天的数据
                $result = $_ENV['statistics']->get_cartogram_data($uid  , $statistics_type , 7);
                break;
            case 'month':
                //默认按照天返回数据

                $statistics_type = 1;
                //查询当天到前30天的数据
                $result = $_ENV['statistics']->get_cartogram_data($uid  , $statistics_type , 30);
                break;
        }

        return $result;
    }


    /**
     * 客流量
     * 参数：
     * access_token
     * day_type:(
     *              现在:this_time
     *              今天:this_day
     *              昨天:last_day
     *              本周:this_week
     *              上周:last_week
     *              本月:this_month
     *              上月:last_month
     *      )
     * statistics_type:1：按照一天统计 2：按照5min统计 3：按照1h统计
     */
    function ondatetimestatistics()
    {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $this->init_input();
        $day_type = $this->input('day_type');
        $statistics_type = $this->input('statistics_type');
        //若设置deviceid 则为取单个设备
        $deviceid = $this->input('deviceid');
        if($deviceid){
            if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) {
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        switch ($day_type){
            case 'this_week':
                $result = $_ENV['statistics']->get_thisweek_device_avg($uid , $statistics_type, $day_type);
                break;
            case 'last_week':
                $result = $_ENV['statistics']->get_lastweek_device_avg($uid , $statistics_type, $day_type);
                break;
            case 'last_month':
                $result = $_ENV['statistics']->get_lastmont_device_avg($uid , $statistics_type, $day_type);
                break;
            case 'this_time':
                $result = $_ENV['statistics']->get_now_data($uid ,$statistics_type, $day_type);
                break;
            default:
                //默认是今天或者昨天
                $result = $_ENV['statistics']->get_all_device_total($uid , $statistics_type, $day_type,$deviceid);
                break;
        }
        return $result;
    }


    /**
     * 客流量汇总
     * 参数：
     * access_token
     */
    function oncollect()
    {
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $this->init_input();
        $result = [];
        //$this_week = $_ENV['statistics']->get_thisweek_device_avg($uid , 1, 'this_week');
        //$last_week = $_ENV['statistics']->get_lastweek_device_avg($uid , 1, 'last_week');
        $last_month = $_ENV['statistics']->get_lastmont_device_avg($uid , 1, 'last_month');
        $this_day = $_ENV['statistics']->get_all_device_total($uid , 2, 'this_day');
        //$last_day = $_ENV['statistics']->get_all_device_total($uid , 2, 'last_day');

        //上月平均客流量
        $last_month_days = date('t', strtotime('-1 month'));
        $result['last_month_avg'] = intval($last_month['in_sum']/$last_month_days);
        //较前日
        //if($last_day['in_sum'] != 0){
        //    $compare_last_day = ($this_day['in_sum']-$last_day['in_sum'])/$last_day['in_sum'];
        //    $result['compare_last_day'] = sprintf("%.2f",$compare_last_day);
        //}else{
        //    $result['compare_last_day'] = $this_day['in_sum'];
        //}
        //较上周
        //if($last_week['in_sum'] != 0){
        //    $compare_last_week = ($this_week['in_sum']-$last_week['in_sum'])/$last_week['in_sum'];
        //    $result['compare_last_week'] = sprintf("%.2f",$compare_last_week);
        //}else{
        //    $result['compare_last_week'] = $this_week['in_sum'];
        //}
        $result['this_day_in'] = $this_day['in_sum'];
        $result['this_day_out'] = $this_day['out_sum'];
        //$result['last_day_in'] = $last_day['in_sum'];
        //$result['last_day_out'] = $last_day['out_sum'];
        $result['last_month_in'] = $last_month['in_sum'];
        $result['last_month_out'] = $last_month['out_sum'];
        //$result['this_week_in'] = $this_week['in_sum'];
        //$result['this_week_out'] = $this_week['out_sum'];
        //$result['last_week_in'] = $last_week['in_sum'];
        //$result['last_week_out'] = $last_week['out_sum'];

        return $result;
    }

    /**
     * 获取当前时间段内的进出统计123
     *
     */
    function onquantumtime(){
        $this->init_user();

        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $this->init_input();
        $statistics_type = $this->input('statistics_type');
        $deviceid = $this->input('deviceid');
        if($deviceid){
            if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) {
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
            }
        }
        $integral  = $this->input('integral');
        $result = $_ENV['statistics']->get_time_quantum($uid , $statistics_type ,time() , $integral , $deviceid);
        return $result;
    }


    /**
     * 民政通统计
     *
     */
    function onmzt(){
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        $this->init_input();
        $deviceids = $this->input('deviceid');
        $start_time = $this->input('st');
        $end_time = $this->input('et');
        if( $end_time < $start_time || !$deviceids)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['statistics']->get_mzt($uid , $deviceids , $start_time , $end_time);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, GET_MZT_DATA_ERROR);
        return $result;
    }
}