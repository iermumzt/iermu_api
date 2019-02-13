<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

class qiniustorage  extends storage {

    function __construct(&$base, $service) {
        $this->qiniustorage($base, $service);
    }

    function qiniustorage(&$base, $service) {
        parent::__construct($base, $service);
    }
    
    function temp_url($params) {
        $uid = $params['uid'];
        $type = $params['type'];
        $container = $params['container'];
        $object = $params['object'];
        $filename = $params['filename'];
        $expires_in = $params['expires_in'];
        $local = $params['local'];

        switch ($type) {
            case 'alarm': $url = $this->storage_config['alarm_url_prefix']; break;
            case 'preset':
            case 'snapshot': $url = $this->storage_config['preset_url_prefix']; break;
            default: $url = ''; break;
        }
        
        if(!$container || !$object || !$url)
            return '';
        
        $filepath = $container.$object;
        
        $deadline = $this->base->time + ($this->storage_config['download_token_validtime'] ? $this->storage_config['download_token_validtime'] : STORAGE_TEMP_URL_DEFAULT_VALIDTIME);
        
        $url .= ($filepath . (strpos($filepath, '?') === false ? '?' : '&') . 'e=' . $deadline);
        $token = $this->_download_token($url);
        $url .= '&token=' . $token;
        
        return $url;
    }
    
    function alarm_info($device, $time, $filename) {
        if(!$device || !$device['deviceid'] || !$time || !$filename)
            return array();
        
        $bucket = $this->storage_config['alarm_bucket'];
        
        $pathname = $device['uid'].'/alarmjpg/'.$device['deviceid'].'/';
        $filename = $filename.'.jpg';
        $filepath = $pathname.$filename;
        
        $cvr_day = $device['cvr_day'] ? $device['cvr_day'] : 7;
        $expiretime = $time + $cvr_day*24*3600;
        
        $cvr_day += 1;
        
        $upload_token = $this->_upload_token($bucket, $filepath, $cvr_day);
        
        $info = array(
            'pathname' => $pathname,
            'filename' => $filename,
            'filepath' => $filepath,
            'upload_token' => $upload_token,
            'expiretime' => $expiretime
        );
        
        $this->base->log('tttt qiniu', 'info='.json_encode($info));
        
        return $info;
    }

    function snapshot_info($device, $sid, $time, $client_id, $sign) {
        if(!$device || !$sid || !$time || !$client_id || !$sign)
            return array();

        $deviceid = $device['deviceid'];
        if($device['share_device']) {
            $uid = 0;
        } else {
            $uid = $device['grant_uid'] ? $device['grant_uid'] : $device['uid'];
        }
        $date = $this->base->time_format($time, 'YmdHis', $device['timezone']);
        
        $bucket = $this->storage_config['preset_bucket'];
        
        if($uid) {
            $pathname = $uid.'/snapshot/'.$deviceid.'/';
        } else {
            $pathname = 'share/snapshot/'.$deviceid.'/';
        }
        $filename = 'd'.$deviceid.'t'.$date.'.jpg';
        $filepath = $pathname . $filename;

        $param = array('sid' => $sid);
        $callbackBody = array(
            'deviceid' => $deviceid,
            'type' => 'device_snapshot',
            'param' => json_encode($param),
            'time' => $time,
            'client_id' => $client_id,
            'sign' => $this->base->_gen_device_sign($deviceid, $client_id, $time)
        );

        $upload_token = $this->_upload_token($bucket, $filepath, 0, http_build_query($callbackBody));
        
        $info = array(
            'pathname' => $pathname,
            'filename' => $filename,
            'filepath' => $filepath,
            'upload_token' => $upload_token
        );

        return $info;
    }

    function upload_face_token($uid, $black=''){
        $bucket = $this->storage_config['ai_bucket'];
        $pathname = 'ai/face/'.$uid.'/';
        $time = time()<<2;
        $filename = $uid.$time.$black.'.jpg';
        $filepath = $pathname . $filename;
        // $callbackBody = array(
        //     'uid' => $uid,
        //     'type' => 'face_register',
        //     'time' => $time,
        //     'client_id' => $client_id,
        // );
        $uploadToken = $this->_upload_token($bucket, $filepath, 0);
        if(!$uploadToken)
            return false;
        $result = array(
            'uploadToken' => $uploadToken,
            'filepath' => $filepath,
            'pathname' => $pathname,
            'filename' => $filename
        );
        return $result;

    }
    function upload_hack_token($params){
        $bucket = $this->storage_config['default_bucket'];
        $pathname = 'hackathon/';
        $time = time()<<2;
        $filename = $params['name'].'_'.$params['phone'].'_'.$time.'.doc';
        $filepath = $pathname . $filename;
        // $callbackBody = array(
        //     'uid' => $uid,
        //     'type' => 'face_register',
        //     'time' => $time,
        //     'client_id' => $client_id,
        // );
        $uploadToken = $this->_upload_token($bucket, $filepath, 0);

        if(!$uploadToken)
            return false;

        $result = array(
            'uploadToken' => $uploadToken,
            'filepath' => $filepath,
            'pathname' => $pathname,
            'filename' => $filename
        );
        return $result;

    }


    function upload_watermark_token($deviceid){
        $bucket = $this->storage_config['ai_bucket'];
        $pathname = 'watermark/'.$deviceid.'/';
        $time = time()<<2;
        $filename = $time.$deviceid.'.bmp';
        $filepath = $pathname . $filename;

        // $callbackBody = array(
        //     'uid' => $uid,
        //     'type' => 'face_register',
        //     'time' => $time,
        //     'client_id' => $client_id,
        // );
        $uploadToken = $this->_upload_token($bucket, $filepath, 0);

        if(!$uploadToken)
            return false;
        $result = array(
            'uploadToken' => $uploadToken,
            'filepath' => $filepath,
            'pathname' => $pathname,
            'filename' => $filename
        );
        return $result;

    }

    function preset_info($device, $preset, $time, $client_id, $sign) {
        if(!$device || !$device['deviceid'] || !$preset || !$time || !$client_id || !$sign)
            return array();
        
        $deviceid = $device['deviceid'];
        
        $bucket = $this->storage_config['preset_bucket'];

        $pathname = $device['uid'].'/presetjpg/'.$device['deviceid'].'/';
        $filename = 'd'.$device['deviceid'].'p'.$preset.'.jpg';
        $filepath = $pathname . $filename;

        $param = array('preset' => $preset);
        $callbackBody = array(
            'deviceid' => $device['deviceid'],
            'type' => 'device_preset',
            'param' => json_encode($param),
            'time' => $time,
            'client_id' => $client_id,
            'sign' => $this->base->_gen_device_sign($deviceid, $client_id, $time)
        );

        $upload_token = $this->_upload_token($bucket, $filepath, 0, http_build_query($callbackBody));
        
        $info = array(
            'pathname' => $pathname,
            'filename' => $filename,
            'filepath' => $filepath,
            'upload_token' => $upload_token
        );

        return $info;
    }

    function preset_image($filepath) {
        if (!$filepath)
            return '';

        $deadline = $this->base->time + ($this->storage_config['download_token_validtime'] ? $this->storage_config['download_token_validtime'] : STORAGE_TEMP_URL_DEFAULT_VALIDTIME);
        
        $baseUrl = $this->storage_config['preset_url_prefix'] . $filepath . (strpos($filepath, '?') === false ? '?' : '&') . 'e=' . $deadline;

        $token = $this->_download_token($baseUrl);

        $baseUrl .= '&token=' . $token;

        return $baseUrl;
    }
    function aiface_image($filepath){
        if (!$filepath)
            return '';

        $deadline = $this->base->time + ($this->storage_config['download_token_validtime'] ? $this->storage_config['download_token_validtime'] : STORAGE_TEMP_URL_DEFAULT_VALIDTIME);
        
        $baseUrl = $this->storage_config['ai_domain'] . $filepath . (strpos($filepath, '?') === false ? '?' : '&') . 'e=' . $deadline;

        $token = $this->_download_token($baseUrl);

        $baseUrl .= '&token=' . $token;

        return $baseUrl;
    }

    //获取黑客马拉松的文件地址

    function apply_hackathon_file($filepath){
        if (!$filepath)
            return '';

        $deadline = $this->base->time + ($this->storage_config['download_token_validtime'] ? $this->storage_config['download_token_validtime'] : STORAGE_TEMP_URL_DEFAULT_VALIDTIME);

        $baseUrl = $this->storage_config['default_url_prefix'] . $filepath . (strpos($filepath, '?') === false ? '?' : '&') . 'e=' . $deadline;


        $token = $this->_download_token($baseUrl);

        $baseUrl .= '&token=' . $token;

        return $baseUrl;
    }
    
    function _upload_token($bucket, $filepath, $savedays = 0, $callbackBody = '') {
        $ak = $this->storage_config['ak'];
        $sk = $this->storage_config['sk'];
        if (!$bucket || !$filepath || !$ak || !$sk)
            return '';

        $policy = array(
            'scope' => $bucket.':'.$filepath,
            'deadline' => $this->base->time + ($this->storage_config['upload_token_validtime'] ? $this->storage_config['upload_token_validtime'] : STORAGE_TEMP_URL_DEFAULT_VALIDTIME)
        );

        if ($callbackBody) {
            $policy['callbackUrl'] = QINIU_CALLBACK_URL;
            $policy['callbackBody'] = $callbackBody;
        }

        if ($savedays) {
            $policy['deleteAfterDays'] = intval($savedays);
        }
 
        $policy = $this->base->base64_url_encode(json_encode($policy));
        $sign = $this->base->base64_url_encode(hash_hmac('sha1', $policy, $sk, true));
        $token = $ak . ':' . $sign . ':' . $policy;

        return $token;
    }

    function _download_token($filepath) {
        $ak = $this->storage_config['ak'];
        $sk = $this->storage_config['sk'];

        if (!$filepath || !$ak || !$sk)
            return '';

        $sign = $this->base->base64_url_encode(hash_hmac('sha1', $filepath, $sk, true));
        $token = $ak . ':' . $sign;

        return $token;
    }

    function verify_notify() {
        $ak = $this->storage_config['ak'];
        $sk = $this->storage_config['sk'];
        $authstr = $_SERVER['HTTP_AUTHORIZATION'];

        if (!$authstr || !$ak || !$sk)
            return false;
        
        if (strpos($authstr, 'QBox ') != 0)
            return false;

        $auth = explode(':', substr($authstr, 5));
        if (count($auth) != 2 || $auth[0] != $ak)
            return false;

        $policy = $_SERVER['PATH_INFO']."\n".file_get_contents('php://input');
        $sign = $this->base->base64_url_encode(hash_hmac('sha1', $policy, $sk, true));
        return $sign == $auth[1];
    }
    function faceupload($uid, $file , $black=""){
        //获取uploadtoken
        $face = $this->upload_face_token($uid, $black);
        if(!$face)
            return false;
        
        include_once API_SOURCE_ROOT.'lib/qiniuUpload.class.php';
        $upload = new UploadManager();
        $ret = $upload->putFile($face['uploadToken'], $face['filepath'], $file);
        if(!$ret[0]['key'] || $ret[0]['key'] != $face['filepath']){
            return false;
        }

        return $face;
    }
}
