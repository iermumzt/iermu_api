<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class wokanjiaconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->wokanjiaconnect($base, $_config);
    }

    function wokanjiaconnect(&$base, $_config) {
        parent::__construct($base, $_config);
    }
    
}
