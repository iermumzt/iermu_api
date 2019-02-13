<?php

!defined('IN_API') && exit('Access Denied');

class utilcontrol extends base {

    function __construct() {
        $this->utilcontrol();
    }

    function utilcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');  
    }

    // 获取m3u8文件状态
    function onfilestatus() {
        $this->init_input();
        $uri = $this->input('uri');

        $result = array();
        $result['code'] = 404;
        $result['status'] = 0;

        $curl = curl_init($uri);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        $temp = curl_exec($curl);
        if ($temp !== false) {
            $result['code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($result['code'] == 200) {
                $result['status'] = 1;
            }
        }
        curl_close($curl);

        return $result;
    }
}
