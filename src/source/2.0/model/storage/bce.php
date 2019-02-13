<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

class bcestorage  extends storage {

    function __construct(&$base, $service) {
        $this->bcestorage($base, $service);
    }

    function bcestorage(&$base, $service) {
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
        
        return $container;
    }
    
}
