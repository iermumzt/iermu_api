<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

class diskstorage  extends storage {

    function __construct(&$base, $service) {
        $this->diskstorage($base, $service);
    }

    function diskstorage(&$base, $service) {
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
        
        $storage_url =  $this->storage_config['url'];
        $url = $storage_url."/".$container."/".$object;
        
        return $url;
    }
    
}
