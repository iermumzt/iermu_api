<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/connect/connect.php';

class greenliveconnect  extends connect {

    function __construct(&$base, $_config) {
        $this->greenliveconnect($base, $_config);
    }

    function greenliveconnect(&$base, $_config) {
        parent::__construct($base, $_config);
    }
    
}
