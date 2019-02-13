<?php

!defined('IN_API') && exit('Access Denied');

class webmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->webmodel($base);
    }

    function webmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function get_new_poster($status) {
        $activity = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'activity_info WHERE platform="2" AND status="'.$status.'" AND starttime<'.$this->base->time.' AND endtime>'.$this->base->time);
        if (!$activity)
            return false;

        $files = array();
        $baseurl = $status ? API_QINIU_IMG_BASEURL : API_TEST_IMG_BASEURL;
        $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'activity_file WHERE aid="'.$activity['aid'].'"');
        for ($i = 0, $n = count($arr); $i < $n; $i++) { 
            $type = $arr[$i]['type'];
            $url = $baseurl . $arr[$i]['pathname'] . $arr[$i]['filename'];

            $files[] = array('type' => $type, 'url' => $url);
        }

        $result = array();
        $result['title'] = $activity['title'];
        $result['weburl'] = $activity['weburl'];
        $result['option'] = $activity['option'];
        $result['starttime'] = $activity['starttime'];
        $result['endtime'] = $activity['endtime'];
        $result['files'] = $files;

        return $result;
    }
}
