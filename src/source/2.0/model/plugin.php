<?php

!defined('IN_API') && exit('Access Denied');

class pluginmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->pluginmodel($base);
    }

    function pluginmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function get_plugins() {
        include_once API_SOURCE_ROOT.'./lib/xml.class.php';
        $arr = array();
        $dir = API_ROOT.'./plugin';
        $d = opendir($dir);
        while($f = readdir($d)) {
            if($f != '.' && $f != '..' && $f != '.svn' && is_dir($dir.'/'.$f)) {
                $s = file_get_contents($dir.'/'.$f.'/plugin.xml');
                $arr1 = xml_unserialize($s);
                $arr1['dir'] = $f;
                unset($arr1['lang']);
                $arr[] = $arr1;
            }
        }
        $arr = $this->orderby_tabindex($arr);
        return $arr;
    }

    function get_plugin($pluginname) {
        $f = file_get_contents(API_ROOT."./plugin/$pluginname/plugin.xml");
        include_once API_SOURCE_ROOT.'./lib/xml.class.php';
        return xml_unserialize($f);
    }

    function get_plugin_by_name($pluginname) {
        $dir = API_ROOT.'./plugin';
        $s = file_get_contents($dir.'/'.$pluginname.'/plugin.xml');
        return xml_unserialize($s, TRUE);
    }

    function orderby_tabindex($arr1) {
        $arr2 = array();
        $t = array();
        foreach($arr1 as $k => $v) {
            $t[$k] = $v['tabindex'];
        }
        asort($t);
        $arr3 = array();
        foreach($t as $k => $v) {
            $arr3[$k] = $arr1[$k];
        }
        return $arr3;
    }

    function cert_get_file() {
        return API_ROOT.'./data/tmp/api_'.substr(md5(API_KEY), 0, 16).'.cert';
    }

    function cert_dump_encode($arr, $life = 0) {
        $s = "# API Setting Dump\n".
        "# Version: API ".API_SERVER_VERSION."\n".
        "# Time: ".$this->time."\n".
        "# Expires: ".($this->time + $life)."\n".
        "# From: ".BASE_API."\n".
        "#\n".
        "# This file was BASE64 encoded\n".
        "# --------------------------------------------------------\n\n\n".
        wordwrap(base64_encode(serialize($arr)), 50, "\n", 1);
        return $s;
    }

    function cert_dump_decode($certfile) {
        $s = @file_get_contents($certfile);
        if(empty($s)) {
            return array();
        }
        preg_match("/# Expires: (.*?)\n/", $s, $m);
        if(empty($m[1]) || $m[1] < $this->time) {
            unlink($certfile);
            return array();
        }
        $s = preg_replace("/(#.*\s+)*/", '', $s);
        $arr = daddslashes(unserialize(base64_decode($s)), 1);
        return $arr;
    }
}
