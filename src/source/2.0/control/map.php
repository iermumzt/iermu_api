<?php

!defined('IN_API') && exit('Access Denied');

class mapcontrol extends base {

    function __construct() {
        $this->mapcontrol();
    }

    function mapcontrol() {
        parent::__construct();
        $this->load('map');
        $this->load('device');
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

    function onbuilding_preview() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        // 判断单个文件上传
        if (!isset($_FILES['upfile']['error']) || is_array($_FILES['upfile']['error']))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        switch ($_FILES['upfile']['error']) {
            case UPLOAD_ERR_OK:
                break;

            case UPLOAD_ERR_NO_FILE:
                $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_NO_UPLOAD_FILE);

            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_EXCEED_FILESIZE_LIMIT);

            default:
                $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_UPLOAD_FILE_FAILED);
        }

        $size = $_FILES['upfile']['size'];
        if ($size > 1000000)
            $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_EXCEED_FILESIZE_LIMIT);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $tempname = $_FILES['upfile']['tmp_name'];
        $type = finfo_file($finfo, $tempname);
        $ext = array_search($type, array('jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'), true);
        if ($ext === false)
            $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_INVALID_FILE_FORMAT);

        $storageid = 11;
        $pathname = 'map/';
        $filename = 'building_preview'.$this->time.rand(100, 999).'.'.$ext;

        if (!move_uploaded_file($tempname, API_UPLOAD_DIR.$pathname.$filename))
            $this->error(API_HTTP_FORBIDDEN, MAP_ERROR_MOVE_FILE_FAILED);

        $uploadname = $_FILES['upfile']['name'];
        $result = $_ENV['map']->add_building_preview($type, $storageid, $pathname, $filename, $uploadname, $size);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, MAP_ERROR_ADD_BUILDING_PREVIEW_FAILED);

        return $result;
    }

    // 添加电子地图标记
    function onmarker_add() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $location = stripcslashes(rawurldecode($this->input('location')));
        if (!$location || !is_array($location = json_decode($location, true)))
            $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_LOCATION_FORMAT_ILLEGAL);

        $location_type = strval($location['type']); // 使用地图 amap
        $location_name = strval($location['name']); // POI位置
        $location_address = strval($location['address']); // POI位置
        $location_latitude = floatval($location['latitude']); // 纬度
        $location_longitude = floatval($location['longitude']); // 经度
        if (!$location_type || !$location_name || !$location_latitude || !$location_longitude)
            $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_LOCATION_FORMAT_ILLEGAL);

        $deviceid = $this->input['deviceid'];
        if ($deviceid) {
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($device['uid'] != $uid && !$_ENV['device']->check_user_grant($deviceid, $uid))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            // 设备地理位置变更
            if (!$_ENV['device']->update_location($deviceid, 1, $location_type, $location_name, $location_address, $location_latitude, $location_longitude))
                $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_LOCATION_FAILED);

            $result = array(
                'type' => 0,
                'tid' => $deviceid,
                'location_type' => $location_type,
                'location_name' => $location_name,
                'location_address' => $location_address,
                'location_latitude' => $location_latitude,
                'location_longitude' => $location_longitude,
                'marker' => array('deviceid' => $deviceid),
                'time' => $this->time
            );
        } else {
            $bid = $this->input('bid');
            $name = $this->input('name');
            $intro = strval($this->input('intro'));
            $type = intval($this->input('type'));
            $minfloor = intval($this->input('minfloor'));
            $maxfloor = intval($this->input('maxfloor'));

            if (!$name || $minfloor > $maxfloor)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            // 对已有楼宇进行初始化
            if ($bid) {
                $marker = $_ENV['map']->get_marker_by_pk($uid, 1, $bid);
                if (!$marker)
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

                $_ENV['map']->drop_building($bid);
            }

            $building = $_ENV['map']->add_building($bid, $name, $intro, $type, $minfloor, $maxfloor);
            if (!$building)
                $this->error(API_HTTP_FORBIDDEN, MAP_ERROR_ADD_BUILDING_FAILED);

            $bid = $building['bid'];

            $preview = stripcslashes(rawurldecode($this->input('preview')));
            if ($preview) {
                if (!is_array($preview = json_decode($preview, true)))
                    $this->error(API_HTTP_BAD_REQUEST, MAP_ERROR_BUILDING_PREVIEW_FORMAT_ILLEGAL);

                for ($i = 0, $n = count($preview); $i < $n; $i++) {
                    $pid = intval($preview[$i]['pid']);
                    $floor = intval($preview[$i]['floor']);

                    foreach ($preview[$i]['device'] as $value) {
                        $deviceid = $value['deviceid'];
                        $left = floatval($value['left']);
                        $top = floatval($value['top']);

                        $device = $_ENV['device']->get_device_by_did($deviceid);
                        if (!$device)
                            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

                        if ($device['uid'] != $uid && !$_ENV['device']->check_user_grant($deviceid, $uid))
                            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

                        // 设备地理位置变更
                        if (!$_ENV['device']->update_location($deviceid, 1, $location_type, $location_name, $location_address, $location_latitude, $location_longitude))
                            $this->error(API_HTTP_SERVICE_UNAVAILABLE, DEVICE_ERROR_UPDATE_LOCATION_FAILED);

                        // 预览图位置
                        if (!$_ENV['map']->add_building_device($deviceid, $pid, $left, $top))
                            $this->error(API_HTTP_FORBIDDEN, MAP_ERROR_ADD_BUILDING_DEVICE_FAILED);
                    }

                    if (!$_ENV['map']->update_building_preview($pid, $bid, $floor, $i+1))
                        $this->error(API_HTTP_FORBIDDEN, MAP_ERROR_UPDATE_BUILDING_PREVIEW_FAILED);
                }
            }

            // 添加楼宇标记
            $result = $_ENV['map']->add_marker($uid, 1, $bid, $location_type, $location_name, $location_address, $location_latitude, $location_longitude);
            if (!$result)
                $this->error(API_HTTP_FORBIDDEN, MAP_ERROR_ADD_MARKER_FAILED);
        }

        return $result;
    }

    // 删除电子地图标记
    function onmarker_drop() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $bid = $this->input('bid');

        $type = 0; // 操作类型,1删除楼宇,2删除摄像机地理位置,3删除楼层摄像机

        if ($bid) {
            $marker = $_ENV['map']->get_marker_by_pk($uid, 1, $bid);
            if (!$marker)
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

            $type += 1;
        }

        if ($deviceid) {
            $device = $_ENV['device']->get_device_by_did($deviceid);
            if (!$device)
                $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

            if ($device['uid'] != $uid && !$_ENV['device']->check_user_grant($deviceid, $uid))
                $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

            $type += 2;
        }

        switch ($type) {
            case 1: // 删除楼宇
                $result = $_ENV['map']->drop_marker($uid, 1, $bid);
                break;

            case 2: // 删除摄像机地理位置
                $result = $_ENV['device']->drop_location($deviceid);
                break;

            case 3: // 删除楼层摄像机
                $result = $_ENV['map']->drop_building_device($uid, $bid, $deviceid);
                break;
            
            default:
                $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
                break;
        }

        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, MAP_ERROR_DROP_MARKER_FAILED);
        
        return array('uid' => $uid);
    }

    // 列举电子地图标记
    function onmarker_list() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $list = $_ENV['map']->list_marker($uid);

        $result = array('count' => count($list), 'list' => $list);

        return $result;
    }
}