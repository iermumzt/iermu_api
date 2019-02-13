<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class gomeconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->gomeconnect($base, $_config);
    }

    function gomeconnect(&$base, $_config) {
        parent::__construct($base, $_config);
    }
    
}
