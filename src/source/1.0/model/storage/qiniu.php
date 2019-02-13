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
    
    function temp_url($container, $object, $filename, $expires_in, $local) {
        return '';
    }
    
    function alarm_info($device, $time, $filename) {
        if(!$device || !$device['deviceid'] || !$time || !$filename)
            return array();
        
        $bucket = $this->storage_config['alarm_bucket'];
        $filepath = $device['uid'].'/alarmjpg/'.$device['deviceid'].'/'.$filename.'.jpg';
        $cvr_day = $device['cvr_day'] ? $device['cvr_day'] : 7;

        $upload_token = $this->_upload_token($bucket, $filepath, $cvr_day);
        
        $info = array(
            'filepath' => $filepath,
            'upload_token' => $upload_token
        );
        
        $this->base->log('tttt qiniu', 'info='.json_encode($info));
        
        return $info;
    }

    function preset_info($device, $preset, $time, $client_id, $sign) {
        if(!$device || !$device['deviceid'] || !$preset || !$time || !$client_id || !$sign)
            return array();
        
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
            'sign' => $sign
        );

        $upload_token = $this->_upload_token($bucket, $filepath, 0, http_build_query($callbackBody));
        
        $info = array(
            'pathname' => $pathname,
            'filename' => $filename,
            'upload_token' => $upload_token
        );

        return $info;
    }

    function preset_image($filepath) {
        if (!$filepath)
            return '';

        $deadline = $this->base->time + ($this->storage_config['download_token_validtime'] ? $this->storage_config['download_token_validtime'] : STORAGE_TEMP_URL_DEFAULT_VALIDTIME);
        
        $baseUrl = 'http://7xr64e.com2.z0.glb.qiniucdn.com/' . $filepath . (strpos($filepath, '?') === false ? '?' : '&') . 'e=' . $deadline;

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
            $policy['callbackUrl'] = ($_SERVER['SERVER_PORT'] === '443' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/rest/2.0/connect/qiniu/notify';
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
}
