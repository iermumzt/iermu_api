<?php

!defined('IN_API') && exit('Access Denied');

if(!function_exists('file_put_contents')) {
    function file_put_contents($filename, $s) {
        $fp = @fopen($filename, 'w');
        @fwrite($fp, $s);
        @fclose($fp);
    }
}

class cachemodel {

    var $db;
    var $base;
    var $map;

    function __construct(&$base) {
        $this->cachemodel($base);
    }

    function cachemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
        $this->map = array(
            'settings' => array('settings'),
            'plugins' => array('plugins'),
            'apps' => array('apps'),
            'storage_services' => array('storage_services'),
            'pay_services' => array('pay_services')
        );
    }

    //public
    function updatedata($cachefile = '') {
        if($cachefile) {
            foreach((array)$this->map[$cachefile] as $modules) {
                $s = "<?php\r\n";
                foreach((array)$modules as $m) {
                    $method = "_get_$m";
                    $s .= '$_CACHE[\''.$m.'\'] = '.var_export($this->$method(), TRUE).";\r\n";
                }
                $s .= "\r\n?>";
                @file_put_contents(API_DATADIR."./cache/$cachefile.php", $s);
            }
        } else {
            foreach((array)$this->map as $file => $modules) {
                $s = "<?php\r\n";
                foreach($modules as $m) {
                    $method = "_get_$m";
                    $s .= '$_CACHE[\''.$m.'\'] = '.var_export($this->$method(), TRUE).";\r\n";
                }
                $s .= "\r\n?>";
                @file_put_contents(API_DATADIR."./cache/$file.php", $s);
            }
        }
    }

    function updatetpl() {
        $tpl = dir(API_DATADIR.'view');
        while($entry = $tpl->read()) {
            if(preg_match("/\.php$/", $entry)) {
                @unlink(API_DATADIR.'view/'.$entry);
            }
        }
        $tpl->close();
    }

    //private
    function _get_apps() {
        $this->base->load('app');
        $apps = $_ENV['app']->get_apps();
        $apps2 = array();
        if(is_array($apps)) {
            foreach($apps as $v) {
                $apps2[$v['client_id']] = $v;
            }
        }
        return $apps2;
    }

    //private
    function _get_settings() {
        return $this->base->get_setting();
    }

    //private
    function _get_plugins() {
        $this->base->load('plugin');
        return $_ENV['plugin']->get_plugins();
    }
    
    //private
    function _get_storage_services() {
        $storages = $this->base->get_storage_services();
        $storages2 = array();
        if(is_array($storages)) {
            foreach($storages as $v) {
                $v['storage_config'] = unserialize($v['storage_config']);
                $storages2[$v['storageid']] = $v;
            }
        }
        return $storages2;
    }

    //private
    function _get_pay_services() {
        $pays = $this->base->get_pay_services();
        $pays2 = array();
        if(is_array($pays)) {
            foreach($pays as $v) {
                $v['pay_config'] = unserialize($v['pay_config']);
                $pays2[$v['payid']] = $v;
            }
        }
        return $pays2;
    }
}
