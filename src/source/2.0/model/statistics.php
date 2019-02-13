<?php

!defined('IN_API') && exit('Access Denied');

class statisticsmodel
{

    var $db;
    var $base;

    function __construct(&$base)
    {
        $this->statisticsmodel($base);
    }

    function statisticsmodel(&$base)
    {
        $this->base = $base;
        $this->db = $base->db;
    }

    /**
     * 获取用户设备（AI设备）
     *
     */
    function list_ai_devices($uid){
        //base_devicefileds 此表的ai字段判定device是否为AI设备
        $table = API_DBTABLEPRE.'device a LEFT JOIN '.API_DBTABLEPRE.'devicefileds d ON a.deviceid=d.deviceid';
        $where = 'WHERE a.uid='.$uid.' AND d.ai=1';
        //switch ($online) {
        //    case 0: $where .= ' AND (a.connect_online=0 OR a.status&4=0)'; break;
        //    case 1: $where .= ' AND a.status&4!=0 AND a.connect_online=1'; break;
        //    default: break;
        //}
        // AI   102010002 100647558946
        $data = $this->db->fetch_all("SELECT a.deviceid,a.connect_thumbnail,a.uid,a.desc FROM $table $where");
        $deviceid_list = [];
        $ai_devices_statistics  = [];
        $list_ai_devices_statistics = $this->list_devices_in_statistics($uid);
        foreach($list_ai_devices_statistics['list'] as $val){
            $ai_devices_statistics[] = $val['deviceid'];
        }
        $params['uid'] = $uid;
        if($data){
            foreach ($data as $k => $val){
                $deviceid_list[$k]['deviceid'] = $val['deviceid'];
                $deviceid_list[$k]['uid'] = $val['uid'];
                //是否是统计设备
                if(in_array($val['deviceid'] , $ai_devices_statistics)){
                    $deviceid_list[$k]['statistics'] = 1;
                }else{
                    $deviceid_list[$k]['statistics'] = 0;
                }
            }
        }
        //拥有的AI摄像机数
        $count = count($data);
        $result = array(
            'count' => $count,
            'list' => $deviceid_list
        );

        return $result;
    }

    /**
     * 获取用户设备（AI设备）正在统计中的设备
     */
    function list_devices_in_statistics($uid)
    {
        $list = array();
        //获取当前用户正在统计中的设备
        $result = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'ai_statistics_device WHERE uid='.$uid);
        $deviceid_list = [];
        foreach ($result as $k => $val){
            $deviceid_list[$k]['deviceid'] = $val['deviceid'];
            $deviceid_list[$k]['uid'] = $val['uid'];
            $deviceid_list[$k]['statistics'] = 1;
        }
        $count = count($result);
        $res = array(
            'count' => $count,
            'list' => $deviceid_list
        );
        return $res;
    }

    /**
     * 增加AI摄像机统计
     */
    function add_ai_device_statistics($uid , $deviceid)
    {
        //y用户所有的AI设备id的集合
        $ai_devices = array();
        //用户所有的在统计中的AI设备id的集合
        $ai_devices_statistics = array();
        $list_ai_devices = $this->list_ai_devices($uid);
        foreach($list_ai_devices['list'] as $data){
            $ai_devices[] = $data['deviceid'];
        }
        //是否在所有AI设备ID里面
        if (!in_array($deviceid, $ai_devices)) {
            return array('error' => -1  , 'msg' => 'DEVICE NOT EXIST');
        }
        $list_ai_devices_statistics = $this->list_devices_in_statistics($uid);
        foreach($list_ai_devices_statistics['list'] as $val){
            $ai_devices_statistics[] = $val['deviceid'];
        }
        //var_dump(!in_array($deviceid, $ai_devices_statistics));die;
        //是否存在增加的设备ID
        if (!in_array($deviceid, $ai_devices_statistics)) {
            $device = $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_statistics_device (uid,deviceid) VALUES ('.$uid.','.$deviceid.')');
            if(!$device){
                return false;
            }
        }
        return true;
    }

    /**
     * 用户下AI摄像机的添加和减少
     */
    function edit_device_statistics($uid , $device)
    {
        //y用户所有的AI设备id的集合
        $ai_devices = array();
        $list_ai_devices = $this->list_ai_devices($uid);
        foreach($list_ai_devices['list'] as $data){
            $ai_devices[] = $data['deviceid'];
        }
        //需要撤下的设备ID
        $drop_deviceid = (array_diff($ai_devices,$device));
        if(!empty($drop_deviceid)){
            foreach ($drop_deviceid as $val){
                $result_drop = $this->del_ai_device_statistics($uid , $val);
                if(!$result_drop || $result_drop['error'] == -1){
                    return array('error' => -1  , 'msg' => 'DELETE ERROR');
                }

            }
        }
        //需要添加的设备ID
        if(!empty($device[0])){
            foreach ($device as $val){
                $result_add = $this->add_ai_device_statistics($uid , $val);
                if(!$result_add || $result_add['error'] == -1){
                    continue;
                }
            }
        }
        return array('error' => 0  , 'msg' => 'ADD SUCCESS');
    }


    /**
     * 减少AI摄像机出统计
     */
    function del_ai_device_statistics($uid , $deviceid)
    {
        //y用户所有的AI设备id的集合
        $ai_devices = array();
        //用户所有的在统计中的AI设备id的集合
        $ai_devices_statistics = array();
        $list_ai_devices = $this->list_ai_devices($uid);
        foreach($list_ai_devices['list'] as $data){
            $ai_devices[] = $data['deviceid'];
        }
        //是否在所有AI设备ID里面
        if (!in_array($deviceid, $ai_devices)) {
            return array('error' => -1  , 'msg' => 'DEVICE NOT EXIST');
        }
        $result = $this->db->query('DELETE FROM '.API_DBTABLEPRE.'ai_statistics_device WHERE uid='.$uid.' AND deviceid='.$deviceid);
        if(!$result){
            return array('error' => -1 , 'code' => 'DELETE ERROR');
        }
        return $result;
    }


    /**
     * 获取一台摄像机累计人流---------------------
     */
    function get_one_device_total($uid , $deviceid , $time , $statistics_type)
    {
        error_reporting(0);
        //获取时间零点
        //$zero_time = strtotime(date("Y-m-d" , $time));
        $zero_time = $this->base->day_start_time($time);
        $endtime = 0;
        //用于判断是否为当天
        $zero_today_time = $this->base->day_start_time(time());
        $is_today = false;
        if($zero_today_time == $zero_time){
            $is_today = true;
            $endtime = $time;
        }else{
            $endtime = $this->base->day_end_time($time);
        }
        $in_total_num = 0;
        $out_total_num = 0;
        $res = [];
        $params = [];
        $result = $this->db->fetch_all('SELECT `in_num`,`out_num` FROM '.API_DBTABLEPRE.'ai_statistics WHERE uid='.$uid.' AND device_id='.$deviceid . ' AND starttime >= '.$zero_time . ' AND endtime <= '.$endtime.' AND statistics_type='.$statistics_type);
        if(!$result && empty($result)){
            $result_event = $this->ai_event($deviceid , $zero_time , $endtime , $uid);
            if($result_event){
                $in_total_num = $result_event['total']['in_num'];
                $out_total_num = $result_event['total']['out_num'];
                //判断昨天或者今天
                if(!$is_today){
                    $params['starttime'] = $zero_time;
                    $params['endtime'] =$endtime;
                    $params['statistics_type'] = 1;
                    $params['in_num'] = $in_total_num;
                    $params['out_num'] = $out_total_num;
                    $this->insert_statistics($deviceid , $uid , $params);
                }
            }
        }else{
            foreach ($result as $val){
                $in_total_num += $val['in_num'];
                $out_total_num += $val['out_num'];
            }
        }
        $res['in_total_num'] = $in_total_num;
        $res['out_total_num'] = $out_total_num;
        return $res;
    }

    /**
     * 获取所有统计中的摄像机累计人流(进， 出)---------------------
     */
    function get_all_device_total($uid, $statistics_type,$day_type,$deviceid )
    {
        $in_sum = 0;
        $out_sum = 0;
        if(isset($deviceid) || !empty($deviceid)){
            $list_devices['list'][0]['deviceid'] = $deviceid;
        }else{
            $list_devices = $this->list_devices_in_statistics($uid);
        }
        if(!$statistics_type){
            //默认按照5分钟获取
            $statistics_type = 2;
        }
        //昨天为2
        if($day_type == 'last_day'){
            //如果有前一天的记录
            foreach ($list_devices['list'] as $data) {
                $result = $this->get_one_device_total($uid , $data['deviceid'] , strtotime("-1 day") , $statistics_type);
                $in_sum += $result['in_total_num'];
                $out_sum += $result['out_total_num'];
            }
        }else{
            foreach ($list_devices['list'] as $data) {
                $result = $this->get_one_device_total($uid , $data['deviceid'] , time() ,$statistics_type);
                $in_sum += $result['in_total_num'];
                $out_sum += $result['out_total_num'];
            }
        }
        $devices = ['in_sum'=> $in_sum , 'out_sum' => $out_sum , 'day_type' =>$day_type];
        return $devices;
    }

    /**
     * 获取上个月的平均客流量-----------------------------------------
     */

    function get_lastmont_device_avg($uid ,$statistics_type , $day_type)
    {
        error_reporting(0);
        $thismonth = date('m');
        $thisyear = date('Y');
        if ($thismonth == 1) {
            $lastmonth = 12;
            $lastyear = $thisyear - 1;
        } else {
            $lastmonth = $thismonth - 1;
            $lastyear = $thisyear;
        }
        $lastStartDay = $lastyear . '-' . $lastmonth . '-1';
        $lastEndDay = $lastyear . '-' . $lastmonth . '-' . date('t', strtotime($lastStartDay));
        $last_start_time = $this->base->day_start_time(strtotime($lastStartDay));//上个月的月初时间戳
        $last_end_time = $this->base->day_start_time(strtotime($lastEndDay));//上个月的月末时间戳
        $time_num = $last_start_time;
        $time_arr = array();
        //整合时间戳
        while ($time_num <= $last_end_time){
            $time_arr[$time_num]['starttime'] = $time_num;
            $time_arr[$time_num]['endtime'] = $time_num + 3600 * 24 - 1;
            $time_num += 60 * 60 * 24;
        }
        $in_sum = 0;
        $out_sum = 0;
        $in_total_num = 0;
        $out_total_num = 0;
        $result_arr = [];
        if(!$statistics_type)$statistics_type = 1;
        $list_devices = $this->list_devices_in_statistics($uid);
        foreach ($list_devices['list'] as $data) {
            $result = $this->db->fetch_first('SELECT sum(`in_num`) as in_total_num,sum(`out_num`) as out_total_num FROM '.API_DBTABLEPRE.'ai_statistics WHERE uid='.$uid.' AND device_id='.$data['deviceid'] . ' AND starttime >= '.$last_start_time . ' AND endtime <= '.$last_end_time.' AND statistics_type='.$statistics_type);
            //若不存在，则请求event表
            if(!$result['in_total_num'] && empty($result['in_total_num']) ){
                $result = $this->ai_event($data['deviceid'] , $last_start_time , $last_end_time , $uid);
                if($result){
                    $in_total_num += $result['total']['in_num'];
                    $out_total_num += $result['total']['out_num'];
                }
            }else{
                $in_total_num += $result['in_total_num'];
                $out_total_num += $result['out_total_num'];
            }
        }
        $result_arr['in_sum'] = $in_total_num;
        $result_arr['out_sum'] = $out_total_num;
        $result_arr['day_type'] = $day_type;
        return $result_arr;
    }

    /**
     * 获取上周的平均客流量
     */

    function get_lastweek_device_avg($uid ,$statistics_type,$day_type)
    {
        $beginLastweek=mktime(0,0,0,date('m'),date('d')-date('w')+1-7,date('Y'));
        $endLastweek=mktime(23,59,59,date('m'),date('d')-date('w')+7-7,date('Y'));
        $in_sum = 0;
        $out_sum = 0;
        $result_arr = [];
        if(!$statistics_type)$statistics_type = 1;
        $list_devices = $this->list_devices_in_statistics($uid);
        foreach ($list_devices['list'] as $data) {
            $result = $this->db->fetch_first('SELECT sum(`in_num`) as in_total_num ,sum(`out_num`) as out_total_num FROM '.API_DBTABLEPRE.'ai_statistics WHERE uid='.$uid.' AND device_id='.$data['deviceid'] . ' AND starttime >= '.$beginLastweek . ' AND endtime <= '.$endLastweek.' AND statistics_type='.$statistics_type);
            $in_sum += $result['in_total_num'];
            $out_sum += $result['out_total_num'];
        }
        $result_arr['in_sum'] = $in_sum;
        $result_arr['out_sum'] = $out_sum;
        $result_arr['day_type'] = $day_type;
        return $result_arr;
    }

    /**
     * 获取本周的平均客流量
     */

    function get_thisweek_device_avg($uid ,$statistics_type,$day_type)
    {
        $beginThisweek=strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("w")+1,date("Y"))));
        $time=time();
        $in_sum = 0;
        $out_sum = 0;
        //默认按照1天取数据  1
        if(!$statistics_type)$statistics_type = 1;
        $list_devices = $this->list_devices_in_statistics($uid);
        foreach ($list_devices['list'] as $data) {
            $result = $this->db->fetch_first('SELECT sum(`in_num`) as in_total_num,sum(`out_num`) as out_total_num FROM '.API_DBTABLEPRE.'ai_statistics WHERE uid='.$uid.' AND device_id='.$data['deviceid'] . ' AND starttime >= '.$beginThisweek . ' AND endtime <= '.$time.' AND statistics_type='.$statistics_type);
            $in_sum += $result['in_total_num'];
            $out_sum += $result['out_total_num'];
        }
        $result_arr['in_sum'] = $in_sum;
        $result_arr['out_sum'] = $out_sum;
        $result_arr['day_type'] = $day_type;
        return $result_arr;
    }

    /**
     * 获取统计图信息（某一天--------------------------------------------）
     */
    function get_cartogram_data_day($uid ,$statistics_type , $date , $date_type ='' , $deviceid ='')
    {
        if(date_default_timezone_get() != "1Asia/Shanghai") date_default_timezone_set("Asia/Shanghai");
        $date = strtotime($date);
        if(!$date)$date = time();
        $start = mktime(0,0,0,date("m",$date),date("d",$date),date("Y",$date));
        $end = mktime(23,59,59,date("m",$date),date("d",$date),date("Y",$date));
        $data_arr = array();
        $result = array();
        //默认按照小时取数据  3
        if(!$statistics_type)$statistics_type = 3;
        if(isset($deviceid) || !empty($deviceid)){
            $list_devices['list'][0]['deviceid'] = $deviceid;
        }else{
            $list_devices = $this->list_devices_in_statistics($uid);
        }
        //是否为今天
        $is_today = false;
        //若当前时间小于结束时间，判定为当前时间
        if(time() <= $end){
            $end = time();
            $is_today = true;
        }
        if(!$is_today){
            foreach ($list_devices['list'] as $data) {
                //若不是今天
                    $result[] = $this->db->fetch_all('SELECT device_id,starttime,endtime,in_num,out_num FROM ' . API_DBTABLEPRE . 'ai_statistics WHERE uid=' . $uid . ' AND device_id=' . $data['deviceid'] . ' AND starttime >= ' . $start . ' AND endtime <= ' . $end . ' AND statistics_type=' . $statistics_type);
                    $has_data = false;
                    foreach ($result as $v){
                        if(!empty($v)){
                            $has_data = true;
                        }
                    }
                    //若为空，则请求event表数据
                    if(!$has_data){
                        $time_arr = [];
                        $time_num = $start;
                        //组合时间段
                        while ($time_num <= $end){
                            $time_arr[$time_num]['starttime'] = $time_num;
                            $time_arr[$time_num]['endtime'] = $time_num + 3600 - 1;
                            $time_num += 60 * 60;
                        }
                        //插入表
                        foreach ($time_arr as $k => $v){
                            $ai_event = $this->ai_event($data['deviceid'] , $v['starttime'] , $v['endtime'] , $uid , $deviceid);
                            if($ai_event){
                                //var_dump($params);
                                $params['starttime'] = $v['starttime']+3600;
                                $params['endtime'] =$v['endtime']+3600;
                                $params['statistics_type'] = 3;
                                $params['in_num'] = $ai_event['total']['in_num'];
                                $params['out_num'] = $ai_event['total']['out_num'];
                                $this->insert_statistics($data['deviceid'],$uid , $params);
                            }

                        }
                        $result[] = $this->db->fetch_all('SELECT device_id,starttime,endtime,in_num,out_num FROM ' . API_DBTABLEPRE . 'ai_statistics WHERE uid=' . $uid . ' AND device_id=' . $data['deviceid'] . ' AND starttime >= ' . $start . ' AND endtime <= ' . $end . ' AND statistics_type=' . $statistics_type);
                    }
            }
        }else{
            //今天返回的数值
            $time_arr = [];
            //包含昨天的23-0点的人流统计
            $time_num = $start - 3600;
            $resu = array();
            //组合时间段
            while ($time_num <= $end){
                $time_arr[$time_num]['starttime'] = $time_num;
                $time_arr[$time_num]['endtime'] = $time_num + 3600 - 1;
                $time_num += 60 * 60;
            }
            foreach ($time_arr as $k => $v){
                $in_num = 0;
                $out_num = 0;
                foreach ($list_devices['list'] as $data){
                    $res_ai_event = $this->ai_event($data['deviceid'] , $v['starttime'] , $v['endtime'] , $uid,$deviceid);
                    $in_num += $res_ai_event['total']['in_num'];
                    $out_num += $res_ai_event['total']['out_num'];
                }
                $resu[$k]['in'] = $in_num;
                $resu[$k]['out'] = $out_num;
                $resu[$k]['time'] = $v['starttime'] + 3600;
            }
        }
        if(isset($resu)){
            $arr_resu = array();
            $resu = array_values($resu);
            $_pop_resu = array_pop($resu);
            $arr_resu['list'] = $resu;
            return $arr_resu;
        }
        foreach ($result as $k=>$v){
            foreach ($v as $val){
                $m[] = $val;
            }
        }
        if($m){
            foreach ($m as $k => $val){
                $start_time = $val['starttime'];
                $hour = date('H',$start_time);
                //整点i
                $i = 0;
                while ($i < 24){
                    if($hour == $i){
                        $data_arr[$i]['in'][$k] = $val['in_num'];
                        $data_arr[$i]['out'][$k] = $val['out_num'];
                    }else{
                        $data_arr[$i]['in'][$k] = 0;
                        $data_arr[$i]['out'][$k] = 0;
                    }
                    $i++;
                }
            }
        }
        //24小时各个整点对应的进出人数
        $res = [];
        //当前小时数
        $h = date("H" , time());
        if($data_arr){
            foreach ($data_arr as $k =>$value){
                $res[$k]['in'] = array_sum($value['in']);
                $res[$k]['out'] = array_sum($value['out']);
                $res[$k]['time'] = $date + 3600 * $k;
                if($is_today){
                    if($h == $k){
                        break;
                    }
                }
            }
        }else{
            if(!$is_today){
                $max = 24;
            }else{
                $max = $h;
            }
            //整点i
            $i = 0;
            while ($i < $max){
                $res[$i]['in'] = 0;
                $res[$i]['out'] = 0;
                $res[$i]['time'] = $date + 3600 * $i;
                $i++;
            }
        }

        $arr = array();
        $arr['list'] = $res;
        return $arr;
    }

    /**
     * 获取统计图信息（当前开始前7 、 30 天每天的数据-------------------------------------------）
     */
    function get_cartogram_data($uid ,$statistics_type , $days)
    {
        error_reporting(0);
        if(date_default_timezone_get() != "1Asia/Shanghai") date_default_timezone_set("Asia/Shanghai");
        //默认30天，取到30天包含7天

        //7 / 30天前的零点
        $time = strtotime(date("Y-m-d" , time())) - 3600 * 24 * 30;
        //7 / 30天的时间段(00:00:00 - 23:59:59)
        $time_arr = [];
        $res = [];
        for($i = 0;$i < 30;$i ++ ){
            $time_arr[$i]['start_time'] = $time + 3600 * 24 * $i;
            $time_arr[$i]['end_time'] = $time + 3600 * 24 * ($i+1) -1;
        }
        //默认按照1天取数据  1
        if(!$statistics_type)$statistics_type = 1;
        $list_devices = $this->list_devices_in_statistics($uid);
        foreach ($time_arr as $k => $value){
            $in_sum = 0;
            $out_sum = 0;
            foreach ($list_devices['list'] as $data) {
                $result = $this->db->fetch_first('SELECT sum(`in_num`) as in_total_num,sum(`out_num`) as out_total_num FROM '.API_DBTABLEPRE.'ai_statistics WHERE uid='.$uid.' AND device_id='.$data['deviceid'] . ' AND starttime >= '.$value['start_time'] . ' AND endtime <= '.$value['end_time'].' AND statistics_type='.$statistics_type);
                //如果返回空值，则请求event表
                if($result['out_total_num'] === null){
                    $ai_event = $this->ai_event($data['deviceid'] , $value['start_time'] , $value['end_time'] ,$uid);
                        $in_sum += $ai_event['total']['in_num'];
                        $out_sum += $ai_event['total']['out_num'];
                        //插入ai_statistics表数据
                        $params['starttime'] = $value['start_time'];
                        $params['endtime'] =$value['end_time'];
                        $params['statistics_type'] = 1;
                    if($ai_event){
                        $params['in_num'] = $ai_event['total']['in_num'];
                        $params['out_num'] = $ai_event['total']['out_num'];
                    }else{
                        $params['in_num'] = 0;
                        $params['out_num'] = 0;
                    }
                    $this->insert_statistics($data['deviceid'] , $uid , $params);
                }else{
                    $in_sum += $result['in_total_num'];
                    $out_sum += $result['out_total_num'];
                }
            }
            $res[$k]['in'] = $in_sum;
            $res[$k]['out'] = $out_sum;
            $res[$k]['time'] = $value['start_time'];
        }
        $arr = array();
        if($days == 7){
            $arr['list'] = array_slice($res , -7 ,7);
        }else{
            $arr['list'] = $res;
        }
        return $arr;
    }
    /**
     * 获取刷新数据----
     */

    function get_now_data($uid,$statistics_type,$day_type)
    {
        error_reporting(0);
        $in_sum = 0;
        $out_sum = 0;
        $list_devices = $this->list_devices_in_statistics($uid);
        //默认按照5分钟获取
        if(!$statistics_type)$statistics_type = 2;
        foreach ($list_devices['list'] as $data) {
            $result = $this->get_one_device_total($uid , $data['deviceid'] , time() ,$statistics_type);
            $in_sum += $result['in_total_num'];
            $out_sum += $result['out_total_num'];
        }
        $result_arr['in_sum'] = $in_sum;
        $result_arr['out_sum'] = $out_sum;
        $result_arr['day_type'] = $day_type;
        return $result_arr;
    }

    /**
     * 获取某两个整点之间的进出人数
     * 时间段人数
     ----------------------------------*/

    function get_time_quantum ($uid,$statistics_type,$time,$integral,$deviceid)
    {
        error_reporting(0);
        $in_sum = 0;
        $out_sum = 0;
        if(!$time)$time = time();
        //获取整点时间戳
        $start_time = strtotime(date("Y-m-d H",$time).":00:00");
        $end_time = $start_time + 3600;
        if(isset($deviceid) || !empty($deviceid)){
            $list_devices['list'][0]['deviceid'] = $deviceid;
        }else{
            $list_devices = $this->list_devices_in_statistics($uid);
        }
        //默认按照5分钟获取
        if(!$statistics_type)$statistics_type = 2;
        foreach ($list_devices['list'] as $data) {
            //$result = $this->db->fetch_first('SELECT sum(`in_num`) as in_total_num,sum(`out_num`) as out_total_num FROM '.API_DBTABLEPRE.'ai_statistics WHERE uid='.$uid.' AND device_id='.$data['deviceid'] . ' AND starttime >= '.$start_time . ' AND endtime <= '.$end_time.' AND statistics_type='.$statistics_type);
            //$in_sum += $result['in_total_num'];
            //$out_sum += $result['out_total_num'];
            //若不存在，则请求event表
            $result = $this->ai_event($data['deviceid'] , $start_time , $end_time , $uid , $deviceid);
            if($result){
                $in_sum += $result['total']['in_num'];
                $out_sum += $result['total']['out_num'];
            }
        }
        $result_arr['in_sum'] = $in_sum;
        $result_arr['out_sum'] = $out_sum;
        if($integral == 1){
            //整点
            $result_arr['time'] = $end_time;
        }else{
            $result_arr['time'] = $time;
        }
        return $result_arr;
    }


    function meta($device, $params) {
        error_reporting(0);
        $meta = array();

        if($device && $device['deviceid']) {
            $deviceid = $device['deviceid'];
            $connect_type = $device['connect_type'];
            $appid = $device['appid'];
            $uid = $params['uid'];
        } else {
            $deviceid = $appid = $uid = 0;
            $connect_type = API_BAIDU_CONNECT_TYPE;
        }

        //if ($connect_type > 0) {
        //    $client = $this->_get_api_client($connect_type);
        //    if (!$client)
        //        return false;
        //
        //    //if ($client->_connect_meta()) {
        //    //    $meta = $client->device_meta($device, $params);
        //    //    if (!$meta)
        //    //        return false;
        //    //
        //    //    if (!$device)
        //    //        return $meta;
        //    //}
        //}

        $grant_device = $device['grant_device'] ? 1 : 0;

        $device = $_ENV['device']->get_device_by_did($deviceid);
        $sub_list = $params['uid'] ?  $_ENV['device']->get_subscribe_by_uid($params['uid'], $device['appid']) : array();
        $check_sub = !empty($sub_list);

        // 更新设备云录制信息
        $device =  $_ENV['device']->update_cvr_info($device);

        if($device['laststatusupdate'] + 60 <  $_ENV['device']->base->time) {
            if($device['connect_online'] == 0 && $device['status'] > 0) {
                $device['status'] = '0';
            }
        }

        $share =  $_ENV['device']->get_share_by_deviceid($device['deviceid']);
        if (!$share) {
            $share = array();
        }

        $user = $_ENV['user']->_format_user($device['uid']);
        $meta = array(
            'deviceid' => $device['deviceid'],
            'data_type' => ($params['auth_type'] == 'share') ? 2 : $grant_device,
            'device_type' => intval($device['device_type']),
            'connect_type' => $device['connect_type'],
            'connect_cid' => $device['connect_cid'],
            'stream_id' => $device['stream_id'],
            'status' => $device['status'],
            'description' => ($params['auth_type'] == 'share' && $share['title']) ? $share['title'] : $device['desc'],
            'cvr_type' => intval($device['cvr_type']),
            'cvr_day' => $device['cvr_day'],
            'cvr_end_time' => $device['cvr_end_time'],
            'share' => strval(intval($share['share_type'])),
            'shareid' => strval($share['shareid']),
            'uk' => strval(intval($share['uid'] ? $share['uid'] : $share['connect_uid'])),
            'uid' => $user['uid'],
            'username' => $user['username'],
            'avatar' => $user['avatar'],
            'intro' => strval($share['intro']),
            'thumbnail' =>  $_ENV['device']->get_device_thumbnail($device),
            'timezone' => strval($device['timezone']),
            'subscribe' => ($check_sub && in_array($device['deviceid'], $sub_list)) ? 1 : 0,
            'viewnum' => $device['viewnum'],
            'approvenum' => $device['approvenum'],
            'commentnum' => $device['commentnum'],
            'statistics' => $params['statistics']
        );

        if (isset($share['password']) && $share['password'] !== '') {
            $meta['needpassword'] = 1;
        }

        if ($share['expires']) {
            $meta['share_end_time'] = $share['dateline']+$share['expires'];
            $meta['share_expires_in'] = $meta['share_end_time']- $_ENV['device']->base->time;
        }

        if ($share['share_type']) {
            $meta['showlocation'] = !$share['showlocation'] || !$device['location'] ? 0 : 1;
        }

        if ($params['auth_type'] == 'token' && $device['location']) {
            $meta['location'] = array(
                'type' => $device['location_type'],
                'name' => $device['location_name'],
                'address' => $device['location_address'],
                'latitude' => floatval($device['location_latitude']),
                'longitude' => floatval($device['location_longitude'])
            );
        } elseif ($meta['showlocation']) {
            $meta['location'] = array(
                'type' => $device['location_type'],
                'name' => $device['location_name'],
                'address' => $device['location_address']
            );
        }

        if ($device['cvr_free']) {
            $meta['cvr_free'] = 1;
        }

        if($device['reportstatus'])
            $meta['reportstatus'] = intval($device['reportstatus']);

        return $meta;
    }

    /*
     * ai_event，记录了每台AI设备单位秒内的进出人数
     */
    function ai_event($deviceid , $starttime , $endtime , $uid , $device = ''){
        if(!$deviceid){
            return false;
        }
        //若未传时间段，则默认当前时间
        if(!$starttime) $starttime = time();
        if(!$endtime) $endtime = $starttime;
        //记录进出人数
        $in_sum = 0;
        $out_sum = 0;
        $res = [];
        //在 ai_event 搜索出在这个时间段内的进出人数统计
        if(isset($deviceid) && !empty($deviceid)){
            $result = $this->db->fetch_all('SELECT `in_num`,`out_num`,time FROM '.API_DBTABLEPRE.'ai_event WHERE deviceid='.$deviceid . ' AND time BETWEEN '.$starttime . ' AND '.$endtime.' AND event_type=1');
        }else{
            $result = $this->db->fetch_all('SELECT `in_num`,`out_num`,time FROM '.API_DBTABLEPRE.'ai_event WHERE deviceid='.$deviceid . ' AND time BETWEEN '.$starttime . ' AND '.$endtime.' AND event_type=1 AND deviceid IN 
(SELECT deviceid FROM '.API_DBTABLEPRE.'ai_statistics_device WHERE uid='.$uid.')');
        }

        //var_dump($result);
        //var_dump('SELECT `in_num`,`out_num`,time FROM '.API_DBTABLEPRE.'ai_event WHERE deviceid='.$deviceid . ' AND time BETWEEN '.$starttime . ' AND '.$endtime.' AND event_type=1');
        foreach ($result as $val){
            $in_sum += $val['in_num'];
            $out_sum += $val['out_num'];
        }
        $res['list'] = $result;
        $res['total']['in_num'] = $in_sum;
        $res['total']['out_num'] = $out_sum;
        return $res;
    }

    /*
     * 若statistics表内无数据，则请求ai_event表，若此表有数据则插入statistics表
     */

    function insert_statistics($deviceid,$uid,$params){
        if(!$deviceid || !$uid){
            return false;
        }
        $starttime = time();
        $endtime = $starttime;
        $statistics_type = 3;
        if(isset($params['starttime']) && !empty($params['starttime'])){
            $starttime = $params['starttime'];
        }
        if(isset($params['endtime']) && !empty($params['endtime'])){
            $endtime = $params['endtime'];
        }
        if(isset($params['statistics_type']) && !empty($params['statistics_type'])){
            $statistics_type = $params['statistics_type'];
        }
        if(isset($params['in_num'])){
            $in_num = $params['in_num'];
        }
        if(isset($params['out_num'])){
            $out_num = $params['out_num'];
        }
        $result = $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_statistics (uid,device_id,starttime,endtime,in_num,out_num,statistics_type) VALUES ('.$uid.','.$deviceid.','.$starttime.','.$endtime.','.$in_num.','.$out_num.','.$statistics_type.')');
        return $result;
    }

    /*
     * redis获取进出人数
     */
    function get_data_by_redis($deviceid){
        if (!$deviceid)
            return false;
        $io_key = "ai:io:".$deviceid;
        $data = $this->base->redis->hGetAll($io_key);
        if(!$data || $data['response'] != 2)
            return false;
        $params = json_decode($data['data'], true);
        if (!$params)
            return false;
        $params['param_len'] = strlen($data) / 2;
        $params['params'] = $data;
        $params['data'] = ['sex'=>'man','name' => 'jack'];
        $this->base->redis->hMset($io_key, array('data' => json_encode($params), 'status' => 1));
        return true;
    }


    //民政通
    function mzt_event_data($uid, $deviceid='',$st='', $et='' , $group = ''){

        if($deviceid){
            $deviceid = $deviceid;
        }else{
            $deviceid = "SELECT deviceid from ".API_DBTABLEPRE."device WHERE uid = '$uid'";
        }

        $where = " WHERE event_type = 0 AND deviceid in ($deviceid)";

        if($st)
            $where .=" AND time >= $st";
        if($et)
            $where .=" AND time <= $et";

        //识别人数

        $_face_id_sql = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_event $where group by _face_id";

        $result = $this->db->fetch_all("SELECT b.face_id,b.name,LEFT(c.class , 2) as class,c.age,LEFT(c.area , 4 ) as area FROM ".API_DBTABLEPRE."ai_member_face_bind a LEFT JOIN ".API_DBTABLEPRE."ai_member_face b on b.face_id = a.face_id LEFT JOIN ".API_DBTABLEPRE."ai_face_fileds c ON c.face_id=b.face_id where a._face_id in ($_face_id_sql) AND b.uid ='$uid' group by b.face_id");

        return $result;
    }

    function get_mzt($uid, $deviceid='',$st='', $et=''){
        if(!$uid)
            return false;
        $deviceid_arr = explode("," , $deviceid);
        $deviceids = "";
        foreach ($deviceid_arr as $v){
            $deviceids .= "'".$v."',";
        }
        $deviceids = rtrim($deviceids , ",");

        $data = $this->mzt_event_data($uid , $deviceids , $st , $et);

        $result = array();

        //获取列表（年龄 ，类别 ， 地区分布 ）
        $array = $this->get_array();

        //总识别人数
        $total = count($data);

        foreach ($data as $k => $v ) {
            $result['area'][$v['area']][] = $v;
            $result['class'][$v['class']][] = $v;
            //年龄段
            if ($v['age']){
                $age = $this->get_age($v['age']);
                if($age <= 35){
                    $array["age"][0]['total'] += 1;
                }elseif ($age > 35 && $age<= 60){
                    $array["age"][1]['total'] += 1;
                }elseif ($age > 60 && $age<= 80){
                    $array["age"][2]['total'] += 1;
                }elseif ($age > 80){
                    $array["age"][3]['total'] += 1;
                }
            }
        }

        foreach ($array['area'] as $k => $v){
            if($result['area'][$v['area_code']]){
                $array['area'][$k]['total'] = count($result['area'][$v['area_code']]);
            }
        }

        foreach ($array['class'] as $k => $v){
            if($result['class'][$v['class_code']]){
                $array['class'][$k]['total'] = count($result['class'][$v['class_code']]);
            }
        }

        return array(
            "total" => $total,
            "data" => $array
        );
    }

    //构建民政通分布数组

    function get_array(){
        $age = [
            [
                "name" => "35岁以下",
                "total" => 0
            ],
            [
                "name" => "35-60岁",
                "total" => 0
            ],
            [
                "name" => "60-80岁",
                "total" => 0
            ],
            [
                "name" => "80岁以上",
                "total" => 0
            ],
        ];

        $class = [
            [
                "class_code" => "01",
                "name" => "伤残人员",
                "total" => 0
            ],
            [
                "class_code" => "02",
                "name" => "三属",
                "total" => 0
            ],
            [
                "class_code" => "03",
                "name" => "三红",
                "total" => 0
            ],
            [
                "class_code" => "04",
                "name" => "在乡复员军人",
                "total" => 0
            ],
            [
                "class_code" => "05",
                "name" => "带病回乡退伍军人",
                "total" => 0
            ],
            [
                "class_code" => "06",
                "name" => "两参人员",
                "total" => 0
            ],
            [
                "class_code" => "07",
                "name" => "60周岁以上农村籍退役人员",
                "total" => 0
            ],
            [
                "class_code" => "08",
                "name" => "烈士子女",
                "total" => 0
            ],
            [
                "class_code" => "09",
                "name" => "铀矿开采军队退役人员",
                "total" => 0
            ],
        ];

        $area = [
            [
                "area_code" => "4401",
                "name" => "广州市",
                "total" => 0
            ],
            [
                "area_code" => "4402",
                "name" => "韶关市",
                "total" => 0
            ],
            [
                "area_code" => "4403",
                "name" => "深圳市",
                "total" => 0
            ],
            [
                "area_code" => "4404",
                "name" => "珠海市",
                "total" => 0
            ],
            [
                "area_code" => "4405",
                "name" => "汕头市",
                "total" => 0
            ],
            [
                "area_code" => "4406",
                "name" => "佛山市",
                "total" => 0
            ],
            [
                "area_code" => "4407",
                "name" => "江门市",
                "total" => 0
            ],
            [
                "area_code" => "4408",
                "name" => "湛江市",
                "total" => 0
            ],
            [
                "area_code" => "4409",
                "name" => "茂名市",
                "total" => 0
            ],
            [
                "area_code" => "4412",
                "name" => "肇庆市",
                "total" => 0
            ],
            [
                "area_code" => "4413",
                "name" => "惠州市",
                "total" => 0
            ],
            [
                "area_code" => "4414",
                "name" => "梅州市",
                "total" => 0
            ],
            [
                "area_code" => "4415",
                "name" => "汕尾市",
                "total" => 0
            ],
            [
                "area_code" => "4416",
                "name" => "河源市",
                "total" => 0
            ],
            [
                "area_code" => "4417",
                "name" => "阳江市",
                "total" => 0
            ],
            [
                "area_code" => "4418",
                "name" => "清远市",
                "total" => 0
            ],
            [
                "area_code" => "4419",
                "name" => "东莞市",
                "total" => 0
            ],
            [
                "area_code" => "4420",
                "name" => "中山市",
                "total" => 0
            ],
            [
                "area_code" => "4451",
                "name" => "潮州市",
                "total" => 0
            ],
            [
                "area_code" => "4452",
                "name" => "揭阳市",
                "total" => 0
            ],
            [
                "area_code" => "4453",
                "name" => "云浮市",
                "total" => 0
            ]
        ];

        return [
            "age" => $age,
            "class" => $class,
            "area" => $area
        ];
    }

    //格式化出生时间年月日

    function get_age($birthday){
        $birthday = substr($birthday , 0,4).'-'.substr($birthday , 4,2).'-'.substr($birthday , 6,2);
        list($year,$month,$day) = explode("-",$birthday);
        $year_diff = date("Y") - $year;
        $month_diff = date("m") - $month;
        $day_diff  = date("d") - $day;
        if ($day_diff < 0 || $month_diff < 0)
            $year_diff--;
        return $year_diff+1;
    }

    //通过code获取信息
    //type = 1 (class)
    //type = 2 (area)

    function get_info_by_code($code , $type){
        $data = $this->get_array();
        $result = "";
        switch ($type){
            case 1:
                $code = substr($code , 0 , 2);
                foreach ($data['class'] as $k=>$v){
                    if($v['class_code'] == $code){
                        $result = $v['name'];
                        break;
                    }
                }
                break;
            case 2:
                $code = substr($code , 0 , 4);
                foreach ($data['area'] as $k=>$v){
                    if($v['area_code'] == $code){
                        $result = $v['name'];
                        break;
                    }
                }
                break;
        }
        return $result;
    }

}