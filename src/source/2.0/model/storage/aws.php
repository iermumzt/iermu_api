<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

class awsstorage  extends storage {

    function __construct(&$base, $service) {
        $this->awsstorage($base, $service);
    }

    function awsstorage(&$base, $service) {
        parent::__construct($base, $service);
    }
    
    function temp_url($params) {
        $container = $params['container'];
        $object = $params['object'];
        $filename = $params['filename'];
        $expires_in = $params['expires_in'];
        $local = $params['local'];
        
        if(!$container || !$object)
            return "";
        
        $url = "";
        
        if($local && $this->storage_config['storage_url_local']) {
            $storage_url =  $this->storage_config['storage_url_local'];
        } else {
            $storage_url =  $this->storage_config['storage_url'];
        }
        
        $ak =  $this->storage_config['ak'];
        $sk =  $this->storage_config['sk'];
        
        if(!$storage_url || !$ak || !$sk) {
            return "";
        }
        
        if(!$expires_in) {
            $expires_in = STORAGE_TEMP_URL_DEFAULT_VALIDTIME;
        }
        
        $method = "GET";
        $expires = $this->time + $expires_in;
        $path = "/".$container."/".$object;
    
        $hmac_body = $method."\n"."\n"."\n".$expires."\n".$path;

        $sig = urlencode(base64_encode(hash_hmac("sha1", $hmac_body, $sk, true)));

        $url = $storage_url."/".$container."/".$object."?AWSAccessKeyId=".$ak."&Expires=".$expires."&Signature=".$sig;
        
        return $url;
    }
    
}
