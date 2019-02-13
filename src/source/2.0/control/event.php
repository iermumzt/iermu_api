<?php

!defined('IN_API') && exit('Access Denied');

class eventcontrol extends base {

    function __construct() {
        $this->eventcontrol();
    }

    function eventcontrol() {
        parent::__construct();
        $this->load('user');
        $this->load('aiface');
        $this->load('device');
        $this->load('oauth2');
    }

    //陌生人添加到已认识的人
    function onupdate(){
        $this->init_input();
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $event_id = strval($this->input('event_id'));
        $face_id = strval($this->input('face_id'));
        $deviceid = strval($this->input('deviceid'));

        $param = stripcslashes(rawurldecode($this->input('param')));
        $param = json_decode($param, true);
        if(!$param || !is_array($param)) {
            $param = array();
        }
        
        if(count($param)==0 && (!$event_id || $face_id==NULL || !$deviceid))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        if(count($param)>0){
            $result = $_ENV['aiface']->eventmerge($param, $uid);
        }else{
            $result = $_ENV['aiface']->event_update($face_id, $event_id, $deviceid);
        }
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_EVENT_UPDATE_FAILED);
        }
        return $result;
    }
    //获取事件列表
    function onlist(){
        $this->init_input("G");
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $face_id = strval($this->input('face_id'));     //0.陌生人 不传.全部 -1 所有认识的人
        $deviceid = strval($this->input('deviceid'));
        $st = strval($this->input('st'));
        $et = strval($this->input('et'));
        $event_type = intval($this->input('event_type'));
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        if(($st && strlen($st)!=10) || ($et && strlen($et)!=10)){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        }

        if(strpos($deviceid,',') !==false){
            $dids = explode(',', $deviceid);
            foreach ($dids as $id) {
                if ($_ENV['device']->check_user_grant($id, $uid)!=2)
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
                # code...
            }
            $deviceid = $dids;

            // $dlist = split(",", $deviceid);
            // $deviceid = "";
            // if($dlist && is_array($dlist)) {
            //     $deviceid = "'";
            //     $deviceid .= join("', '", $dlist);
            //     $deviceid .= "'";
            // }
        }else{
            if ($deviceid && $_ENV['device']->check_user_grant($deviceid, $uid)!=2)
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        }

        $result = $_ENV['aiface']->event_list($uid, $face_id, $page, $count, $event_type, $deviceid, $st, $et);
        if($result == NULL)
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_GET_EVENT_LIST_FAILED);
        }
        return $result;
    }
    //事件删除操作
    function ondrop(){
        $this->init_input("P");
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $event_id = $this->input('event_id');
        $deviceid = $this->input('deviceid');
        if($_ENV['device']->check_user_grant($deviceid, $uid) != 2) 
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        $result = $_ENV['aiface']->event_del($uid, $event_id, $deviceid);
        if($result == NULL)
            $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_OBJECT_NOT_EXIST);
        if(!$result){
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_NETWORK);
        }
        return $result;
    }

    //统计操作
    function oncount(){
        $this->init_input('G');
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();
        $cluster = intval($this->input('cluster'));
        $is_strange = $this->input('is_strange');
        if($is_strange){
            $cluster = $is_strange;
        }
        $day = intval($this->input('day'));
        $st = strval($this->input('st'));
        $et = strval($this->input('et'));
        $deviceid = strval($this->input('deviceid'));
        if((!$day && !$st && !$et) || $st>$et)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        $result = $_ENV['aiface']->eventcount($uid, $cluster, $day, $st, $et, $deviceid);
        if(!$result)
             $this->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_COUNT_FAILED);
         return $result;
    }
}
