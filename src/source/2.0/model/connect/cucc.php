<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class cuccconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->cuccconnect($base, $_config);
    }

    function cuccconnect(&$base, $_config) {
        parent::__construct($base, $_config);
    }
    
}
