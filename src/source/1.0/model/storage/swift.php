<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

class swiftstorage  extends storage {

    function __construct(&$base, $service) {
        $this->swiftstorage($base, $service);
    }

    function swiftstorage(&$base, $service) {
        parent::__construct($base, $service);
    }
    
    function temp_url($container, $object, $filename, $expires_in, $local) {
        if(!$container || !$object)
            return "";
        
        $url = "";
        
        if($local && $this->storage_config['storage_url_local']) {
            $storage_url =  $this->storage_config['storage_url_local'];
        } else {
            $storage_url =  $this->storage_config['storage_url'];
        }
        
        $temp_url_key =   $this->storage_config['temp_url_key'];
        $temp_url_prefix =   $this->storage_config['temp_url_prefix'];
        $temp_url_validtime =   $this->storage_config['temp_url_validtime'];

        if(!$storage_url || !$temp_url_key) {
           return "";
        }

        if(!$expires_in)
           $expires_in = $temp_url_validtime?$temp_url_validtime:STORAGE_TEMP_URL_DEFAULT_VALIDTIME;

        $method = "GET";
        $expires = $this->base->time + $expires_in;
        $path = $temp_url_prefix.$container."/".$object;

        $hmac_body = $method."\n".$expires."\n".$path;

        $sig = hash_hmac("sha1", $hmac_body, $temp_url_key);

        $url = $storage_url."/".$container."/".$object."?temp_url_sig=".$sig."&temp_url_expires=".$expires;
        if($filename) $url .=  "&filename=".$filename;

        return $url;
    }
    
}
