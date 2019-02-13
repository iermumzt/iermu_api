<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

class baidustorage  extends storage {

    function __construct(&$base, $service) {
        $this->baidustorage($base, $service);
    }

    function baidustorage(&$base, $service) {
        parent::__construct($base, $service);
    }
    
}
