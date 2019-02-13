<?php

!defined('IN_API') && exit('Access Denied');

class base {

    var $time;
    var $onlineip;
    var $db;
    var $redis;
    var $redlock;
    var $view;
    var $user = array();
    var $connect_user = array();
    var $settings = array();
    var $cache = array();
    var $app = array();
    var $lang = array();
    var $input = array();
    var $route = array();
    
    var $netid;
    
	var $uid;
    var $connect_type;
    var $connect_uid;
    var $appid;
    var $client_id;
    
    var $auth_status = 0;
    var $partner_push = 0;
    
    var $partner_id;
    var $partner;
    var $connect_domain;
    var $client_partner_type;
    var $partner_admin;
    
    var $error_hooks = array();

    var $connect_error = true;

    function __construct() {
        $this->base();
    }

    function base() {
        $this->init_var();
        $this->init_db();
        $this->init_cache();
        $this->init_template();
        //$this->init_mail();
        //$this->cron();
        $this->init_net();
    }

    function init_var() {
        $this->time = time();
        $cip = getenv('HTTP_CLIENT_IP');
        $xip = getenv('HTTP_X_FORWARDED_FOR');
        $rip = getenv('REMOTE_ADDR');
        $srip = $_SERVER['REMOTE_ADDR'];
        if($cip && strcasecmp($cip, 'unknown')) {
            $this->onlineip = $cip;
        } elseif($xip && strcasecmp($xip, 'unknown')) {
            $this->onlineip = $xip;
        } elseif($rip && strcasecmp($rip, 'unknown')) {
            $this->onlineip = $rip;
        } elseif($srip && strcasecmp($srip, 'unknown')) {
            $this->onlineip = $srip;
        }
        preg_match("/[\d\.]{7,15}/", $this->onlineip, $match);
        $this->onlineip = $match[0] ? $match[0] : 'unknown';

        // cid
		define('API_CID', $this->cid());
        $this->setcookie('cid', API_CID, 1800);
        
        // display
        $display = getgpc('display');
        if(!in_array($display, array('mobile', 'page'))) $display = '';
        define('API_DISPLAY', $display);
        
        // app
        $app = getgpc('app');
        if($app) {
            define('API_APP', $app);
        }
        
        // language
        $language = getgpc('lang');
        if(!$language) $language = $_COOKIE['language'];
        if(!$language || !in_array($language, array('zh-Hans', 'en', 'ja', 'ko'))) $language = 'zh-Hans';
        if($language != 'zh-Hans') {
            $this->setcookie('language', $language, 86400);
        } else {
            //$this->setcookie('language', '');
        }
        define('API_LANGUAGE', $language);

        include_once API_ROOT.'./view/locale/'.API_LANGUAGE.'/main.lang.php';
        $this->lang = &$lang;

        // log记录 ----------- init params
        log::$request_ip = $this->onlineip;
        log::$language = $language;
    }

    function init_cache() {
        $this->settings = $this->cache('settings');
        if(PHP_VERSION > '5.1') {
            $timeoffset = intval($this->settings['timeoffset'] / 3600);
            @date_default_timezone_set('Etc/GMT'.($timeoffset > 0 ? '-' : '+').(abs($timeoffset)));
        }
    }
    
    /*
    function init_input($getagent = '') {
        $input = getgpc('input', 'R');
        if($input) {
            $input = $this->authcode($input, 'DECODE', $this->app['authkey']);
            parse_str($input, $this->input);
            $this->input = daddslashes($this->input, 1, TRUE);
            $agent = $getagent ? $getagent : $this->input['agent'];

            if(($getagent && $getagent != $this->input['agent']) || (!$getagent && md5($_SERVER['HTTP_USER_AGENT']) != $agent)) {
                exit('Access denied for agent changed');
            } elseif($this->time - $this->input('time') > 3600) {
                exit('Authorization has expired');
            }
        }
        if(empty($this->input)) {
            exit('Invalid input');
        }
    }
    */

    function init_input($var='R') {
        switch($var) {
            case 'G': $input = $_GET; break;
            case 'P': $input = $_POST; break;
            case 'R': $input = $_REQUEST; break;
        }
        
        foreach($input as $k=>$v) {
            if($k != 'url') {
                $this->input[$k] = $v;
            }
        }
        
        if($this->route) {
            foreach($this->route as $k=>$v) {
                if($v && $k != 'controller' && $k != 'named' && 
                    $k != 'action' && $k != 'plugin' && $k != 'url') {
                    $this->input[$k] = get_magic_quotes_gpc()?$v:addslashes($v);
                }
            }
        }
        
        if(empty($this->input)) {
            //$this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
        }

        // log记录 ----------- init params
        log::$request_args = $this->input;
        log::$deviceid = $this->input['deviceid'];
    }

    function init_db() {
        require_once API_SOURCE_ROOT.'lib/db.class.php';
        $this->db = new apiserver_db();
        $this->db->connect(API_DBHOST, API_DBUSER, API_DBPW, API_DBNAME, API_DBCHARSET, API_DBCONNECT, API_DBTABLEPRE);
        
        if(extension_loaded('redis')) {
            $this->redis = new Redis();
            $this->redis->connect(API_RDHOST, API_RDPORT);
            if(defined('API_RDPW') && API_RDPW) {
                $this->redis->auth(API_RDPW);
            }
            
            require_once API_SOURCE_ROOT.'lib/redlock.php';
            $servers = [
                [API_RDHOST, API_RDPORT, 0, API_RDPW]
            ];
            $this->redlock = new RedLock($servers);
        }
    }
    
    function init_net() {
        $this->netid = intval(getgpc('netid'));
        
        if(isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        } else {
            $host = $_SERVER['HTTP_HOST'];
        }
        
        if(!$host) return;
        
        $nets = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."net");
        if(is_array($nets)) {
            foreach($nets as $net) {
                if($net['host'] && preg_match($net['host'], $host)) {
                    $this->netid = $net['netid'];
                    return;
                }
            }
        }
    }
    
    function init_user() {
        /*
        if(isset($_COOKIE['api_auth'])) {
            @list($uid, $username, $agent) = explode('|', $this->authcode($_COOKIE['api_auth'], 'DECODE', ($this->input ? $this->app['appauthkey'] : API_KEY)));
            if($agent != md5($_SERVER['HTTP_USER_AGENT'])) {
                $this->setcookie('api_auth', '');
            } else {
                @$this->user['uid'] = $uid;
                @$this->user['username'] = $username;
            }
        }
        */

        $this->uid = '251277';
        $this->user = $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid='251277'");
        $this->client_id = '12a333';
        $this->appid = 1;
        $this->auth_status = 1;
        $this->partner_push = 1;
        
        // $this->partner_id = $_ENV['oauth2']->partner_id;
        // $this->partner = $_ENV['oauth2']->partner;
        // $this->connect_domain = $_ENV['oauth2']->connect_domain;
        // $this->client_partner_type = $_ENV['oauth2']->client_partner_type;
        
        // if(!defined('API_APP') && $_ENV['oauth2']->app && $_ENV['oauth2']->app['code']) {
        //     define('API_APP', $_ENV['oauth2']->app['code']);
        // }


  //       if(API_AUTH_TYPE == 'token') {
  //           $this->load('oauth2');
  //           $_ENV['oauth2']->verifyAccessToken('', FALSE, FALSE, FALSE, FALSE);
  //           $this->uid = $_ENV['oauth2']->uid;
  //           $this->user = $_ENV['oauth2']->user;
  //   		$this->client_id = $_ENV['oauth2']->client_id;
  //   		$this->appid = $_ENV['oauth2']->appid;
  //           $this->auth_status = $_ENV['oauth2']->auth_status;
  //           $this->partner_push = $_ENV['oauth2']->partner_push;
            
  //           $this->partner_id = $_ENV['oauth2']->partner_id;
  //           $this->partner = $_ENV['oauth2']->partner;
  //           $this->connect_domain = $_ENV['oauth2']->connect_domain;
  //           $this->client_partner_type = $_ENV['oauth2']->client_partner_type;
            
  //           if(!defined('API_APP') && $_ENV['oauth2']->app && $_ENV['oauth2']->app['code']) {
  //               define('API_APP', $_ENV['oauth2']->app['code']);
  //           }
		// } elseif(API_AUTH_TYPE == 'cookie') {
		// 	$this->check_login();
  //           if($this->user && $this->user['uid']) {
  //               $this->uid = $this->user['uid'];
  //           } else {
  //               $this->uid = 0;
  //           }
            
  //           // connect_user
  //           $this->check_connect_login();
  //           if($this->connect_user && $this->connect_user['connect_uid']) {
  //               $this->connect_uid = $this->connect_user['connect_uid'];
  //               $this->connect_type = $this->connect_user['connect_type'];
  //               if($this->connect_user['uid']) {
  //                   $this->set_login($this->connect_user['uid']);
  //                   $this->uid = $this->connect_user['uid'];
  //                   $this->user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid='".$this->connect_user['uid']."'");
  //               }
  //           } else {
  //               $this->connect_uid = 0;
  //           }
            
  //   		$this->client_id = API_AUTH_CLIENTID;
  //   		$this->appid = API_AUTH_APPID;
		// }
        
        $this->init_partner();

        // log记录 ----------- init params
        log::$appid = $this->appid;
        log::$client_id = $this->client_id;
        log::$uid = $this->uid;
        
        return $this->uid ? true : false;
    }

    function init_partner() {
        //partner
        if($this->uid) {
            $org = $this->db->fetch_first("SELECT o.* FROM ".API_DBTABLEPRE."member_org m LEFT JOIN ".API_DBTABLEPRE."org o ON m.org_id=o.org_id where m.uid ='".$this->uid."'");
            if($org && $org['org_id'] && $org['connect_type']){
                $partner_id = $this->db->result_first("SELECT partner_id FROM ".API_DBTABLEPRE."connect WHERE connect_type='".$org['connect_type']."'");
                if($partner_id){
                    $partner = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."partner WHERE partner_id='".$partner_id."'");
                    if($partner && $partner['partner_id']) {
                        $this->partner_id = $partner['partner_id'];
                        $this->partner = $partner;
                        $this->connect_domain = $partner['connect_domain'];

                        if($partner['push_partner_device']) {
                            $this->partner_push = 1;
                        }
                    }
                }
            }
            if($this->client_id && $this->partner_id && $this->client_partner_type==1){
                $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE username='$this->partner_id' AND uid = '$this->uid'");
                if($user){
                    $this->partner_admin = 1;
                }
            }
        }
    }
    
    function user_error() {
        $error = API_ERROR_TOKEN;
        $extras = array();
        if($this->auth_status < 0) {
            $error = API_ERROR_REVOKE_TOKEN;
            $extras['type'] = intval($this->auth_status);
        }
        $this->error(API_HTTP_FORBIDDEN, $error, NULL, NULL, NULL, $extras);
    }

    function init_template() {
        $charset = API_CHARSET;
        require_once API_SOURCE_ROOT.'lib/template.class.php';
        $this->view = new template();
        $this->view->assign('dbhistories', $this->db->histories);
        $this->view->assign('charset', $charset);
        $this->view->assign('dbquerynum', $this->db->querynum);
        $this->view->assign('user', $this->user);
    }

    function init_mail() {
        if($this->mail_exists() && !getgpc('inajax')) {
            $this->load('mail');
            $_ENV['mail']->send();
        }
    }

    function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {

        $ckey_length = 4;   // 随机密钥长度 取值 0-32;
        // 加入随机密钥，可以令密文无任何规律，即便是原文和密钥完全相同，加密结果也会每次不同，增大破解难度。
        // 取值越大，密文变动规律越大，密文变化 = 16 的 $ckey_length 次方
        // 当此值为 0 时，则不产生随机密钥

        $key = md5($key ? $key : API_KEY);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if($operation == 'DECODE') {
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }

    }

    function page($num, $perpage, $curpage, $mpurl) {
        $multipage = '';
        $mpurl .= strpos($mpurl, '?') ? '&' : '?';
        if($num > $perpage) {
            $page = 10;
            $offset = 2;

            $pages = @ceil($num / $perpage);

            if($page > $pages) {
                $from = 1;
                $to = $pages;
            } else {
                $from = $curpage - $offset;
                $to = $from + $page - 1;
                if($from < 1) {
                    $to = $curpage + 1 - $from;
                    $from = 1;
                    if($to - $from < $page) {
                        $to = $page;
                    }
                } elseif($to > $pages) {
                    $from = $pages - $page + 1;
                    $to = $pages;
                }
            }

            $multipage = ($curpage - $offset > 1 && $pages > $page ? '<a href="'.$mpurl.'page=1" class="first"'.$ajaxtarget.'>1 ...</a>' : '').
            ($curpage > 1 && !$simple ? '<a href="'.$mpurl.'page='.($curpage - 1).'" class="prev"'.$ajaxtarget.'>&lsaquo;&lsaquo;</a>' : '');
            for($i = $from; $i <= $to; $i++) {
                $multipage .= $i == $curpage ? '<strong>'.$i.'</strong>' :
                '<a href="'.$mpurl.'page='.$i.($ajaxtarget && $i == $pages && $autogoto ? '#' : '').'"'.$ajaxtarget.'>'.$i.'</a>';
            }

            $multipage .= ($curpage < $pages && !$simple ? '<a href="'.$mpurl.'page='.($curpage + 1).'" class="next"'.$ajaxtarget.'>&rsaquo;&rsaquo;</a>' : '').
            ($to < $pages ? '<a href="'.$mpurl.'page='.$pages.'" class="last"'.$ajaxtarget.'>... '.$realpages.'</a>' : '').
            (!$simple && $pages > $page && !$ajaxtarget ? '<kbd><input type="text" name="custompage" size="3" onkeydown="if(event.keyCode==13) {window.location=\''.$mpurl.'page=\'+this.value; return false;}" /></kbd>' : '');

            $multipage = $multipage ? '<div class="pages">'.(!$simple ? '<em>&nbsp;'.$num.'&nbsp;</em>' : '').$multipage.'</div>' : '';
        }
        return $multipage;
    }

    function page_get_start($page, $ppp, $totalnum) {
        $totalpage = ceil($totalnum / $ppp);
        $page =  max(1, min($totalpage, intval($page)));
        return ($page - 1) * $ppp;
    }
    
    function page_get_page($page, $ppp, $totalnum) {
        $page = intval($page);
        $ppp = intval($ppp);
        $totalnum = intval($totalnum);
        
        $totalpage = ceil($totalnum / $ppp);
        $page =  max(1, min($totalpage, intval($page)));
        $start = ($page - 1) * $ppp;
        
        $page = array(
            'total' => $totalnum,
            'prev' => ($page - 1)>0?($page - 1):-1,
            'current' => $page,
            'next' => ($page + 1)>$totalpage?-1:($page + 1)
        );
        
        return array(
            'start' => $start,
            'page' => $page
        );
    }
    //加载model类
    function load($model, $base = NULL, $source = '') {
        $base = $base ? $base : $this;
        if(empty($_ENV[$model])) {
            if(file_exists(API_ROOT.$source."model/$model.php")) {
                require_once API_ROOT.$source."model/$model.php";
            } else {
                require_once API_SOURCE_ROOT."model/$model.php";
            }
            eval('$_ENV[$model] = new '.$model.'model($base);');
        }
        return $_ENV[$model];
    }

    function get_setting($k = array(), $decode = FALSE) {
        $return = array();
        $sqladd = $k ? "WHERE k IN (".$this->implode($k).")" : '';
        $settings = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."settings $sqladd");
        if(is_array($settings)) {
            foreach($settings as $arr) {
                $return[$arr['k']] = $decode ? unserialize($arr['v']) : $arr['v'];
            }
        }
        return $return;
    }

    function set_setting($k, $v, $encode = FALSE) {
        $v = is_array($v) || $encode ? addslashes(serialize($v)) : $v;
        $this->db->query("REPLACE INTO ".API_DBTABLEPRE."settings SET k='$k', v='$v'");
    }

    function get_api_setting($k = array(), $decode = FALSE) {
        $return = array();
        $sqladd = $k ? "WHERE k IN (".$this->implode($k).")" : '';
        $settings = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."settings $sqladd");
        if(is_array($settings)) {
            foreach($settings as $arr) {
                $return[$arr['k']] = $decode ? unserialize($arr['v']) : $arr['v'];
            }
        }
        return $return;
    }

	function showmessage($type = '', $message, $vars = array(), $redirect = '', $timeout = 0, $showtimeout = false) {
		include_once API_ROOT.'view/locale/'.API_LANGUAGE.'/messages.lang.php';
		if(isset($lang[$message])) {
			$message = $lang[$message] ? str_replace(array_keys($vars), array_values($vars), $lang[$message]) : $message;
		}
        $this->view->assign('type', $type);
		$this->view->assign('message', $message);
		$this->view->assign('redirect', $redirect);
        $this->view->assign('timeout', $timeout);
        $this->view->assign('showtimeout', $showtimeout);
		$this->view->display('message');
		exit;
	}
    
	function cid() {
		return substr(md5(substr($this->time, 0, -4).API_KEY), 16);
	}
    
    function submitcheck() {
		return (@getgpc('cid', 'P') !== NULL || @getgpc('cid', 'G') !== NULL) ? true : false;
	}
    
	function clientcheck() {
		return (@getgpc('cid', 'P') == API_CID || @getgpc('cid', 'G') == API_CID) ? true : false;
	}

    function date($time, $type = 3) {
        $format[] = $type & 2 ? (!empty($this->settings['dateformat']) ? $this->settings['dateformat'] : 'Y-n-j') : '';
        $format[] = $type & 1 ? (!empty($this->settings['timeformat']) ? $this->settings['timeformat'] : 'H:i') : '';
        return gmdate(implode(' ', $format), $time + $this->settings['timeoffset']);
    }

    function implode($arr) {
        return "'".implode("','", (array)$arr)."'";
    }

    function set_home($uid, $dir = '.') {
        $uid = sprintf("%09d", $uid);
        $dir1 = substr($uid, 0, 3);
        $dir2 = substr($uid, 3, 2);
        $dir3 = substr($uid, 5, 2);
        !is_dir($dir.'/'.$dir1) && mkdir($dir.'/'.$dir1, 0777);
        !is_dir($dir.'/'.$dir1.'/'.$dir2) && mkdir($dir.'/'.$dir1.'/'.$dir2, 0777);
        !is_dir($dir.'/'.$dir1.'/'.$dir2.'/'.$dir3) && mkdir($dir.'/'.$dir1.'/'.$dir2.'/'.$dir3, 0777);
    }

    function get_home($uid) {
        $uid = sprintf("%09d", $uid);
        $dir1 = substr($uid, 0, 3);
        $dir2 = substr($uid, 3, 2);
        $dir3 = substr($uid, 5, 2);
        return $dir1.'/'.$dir2.'/'.$dir3;
    }

    function get_avatar($uid, $size = 'big', $type = '') {
        $size = in_array($size, array('big', 'middle', 'small')) ? $size : 'big';
        $uid = abs(intval($uid));
        $uid = sprintf("%09d", $uid);
        $dir1 = substr($uid, 0, 3);
        $dir2 = substr($uid, 3, 2);
        $dir3 = substr($uid, 5, 2);
        $typeadd = $type == 'real' ? '_real' : '';
        return  BASE_API.'/'.$dir1.'/'.$dir2.'/'.$dir3.'/'.substr($uid, -2).$typeadd."_avatar_$size.jpg";
    }

    function &cache($cachefile) {
        static $_CACHE = array();
        if(!isset($_CACHE[$cachefile])) {
            $cachepath = API_DATADIR.'./cache/'.$cachefile.'.php';
            if(!file_exists($cachepath)) {
                $this->load('cache');
                $_ENV['cache']->updatedata($cachefile);
            } else {
                include_once $cachepath;
            }
        }
        return $_CACHE[$cachefile];
    }

    function input($k) {
        return isset($this->input[$k]) ? (is_array($this->input[$k]) ? $this->input[$k] : trim($this->input[$k])) : NULL;
    }

    function serialize($s, $htmlon = 0) {
        include_once API_SOURCE_ROOT.'./lib/xml.class.php';
        return xml_serialize($s, $htmlon);
    }

    function unserialize($s) {
        include_once API_SOURCE_ROOT.'./lib/xml.class.php';
        return xml_unserialize($s);
    }

    function cutstr($string, $length, $dot = ' ...') {
        if(strlen($string) <= $length) {
            return $string;
        }

        $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);

        $strcut = '';
        if(strtolower(API_CHARSET) == 'utf-8') {

            $n = $tn = $noc = 0;
            while($n < strlen($string)) {

                $t = ord($string[$n]);
                if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
                    $tn = 1; $n++; $noc++;
                } elseif(194 <= $t && $t <= 223) {
                    $tn = 2; $n += 2; $noc += 2;
                } elseif(224 <= $t && $t < 239) {
                    $tn = 3; $n += 3; $noc += 2;
                } elseif(240 <= $t && $t <= 247) {
                    $tn = 4; $n += 4; $noc += 2;
                } elseif(248 <= $t && $t <= 251) {
                    $tn = 5; $n += 5; $noc += 2;
                } elseif($t == 252 || $t == 253) {
                    $tn = 6; $n += 6; $noc += 2;
                } else {
                    $n++;
                }

                if($noc >= $length) {
                    break;
                }

            }
            if($noc > $length) {
                $n -= $tn;
            }

            $strcut = substr($string, 0, $n);

        } else {
            for($i = 0; $i < $length; $i++) {
                $strcut .= ord($string[$i]) > 127 ? $string[$i].$string[++$i] : $string[$i];
            }
        }

        $strcut = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $strcut);

        return $strcut.$dot;
    }

    function setcookie($key, $value, $life = 0, $httponly = false) {
        (!defined('API_COOKIEPATH')) && define('API_COOKIEPATH', '/');
        (!defined('API_COOKIEDOMAIN')) && define('API_COOKIEDOMAIN', '');

        if($value == '' || $life < 0) {
            $value = '';
            $life = -1;
        }
        
        $life = $life > 0 ? $this->time + $life : ($life < 0 ? $this->time - 31536000 : 0);
        $path = $httponly && PHP_VERSION < '5.2.0' ? API_COOKIEPATH."; HttpOnly" : API_COOKIEPATH;
        //$secure = $_SERVER['SERVER_PORT'] == 443 ? 1 : 0;
        $secure = 0;
        if(PHP_VERSION < '5.2.0') {
            setcookie($key, $value, $life, $path, API_COOKIEDOMAIN, $secure);
        } else {
            setcookie($key, $value, $life, $path, API_COOKIEDOMAIN, $secure, $httponly);
        }
        
        $_COOKIE[$key] = $value;
    }

    function mail_exists() {
        $mailexists = $this->db->fetch_first("SELECT value FROM ".API_DBTABLEPRE."vars WHERE name='mailexists'");
        if(empty($mailexists)) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function dstripslashes($string) {
        if(is_array($string)) {
            foreach($string as $key => $val) {
                $string[$key] = $this->dstripslashes($val);
            }
        } else {
            $string = stripslashes($string);
        }
        return $string;
    }

    function send_json_headers() {
        header("Content-Type: application/json");
        header("Cache-Control: no-store");
    }

    function send_xml_headers() {
        header("Content-Type: application/xml");
        header("Cache-Control: no-store");
    }

    //返回数据
    function response($data, $htmlon=0, $type=NULL) {
        $validtypes = array('xml', 'json');
        if(!$type || !in_array($type,$validtypes)) 
            $type = API_RESPONSE_DEFAULT_TYPE;
        
        $data['request_id'] = request_id();
        
        // log记录 ----------- access_log
        log::access_log(API_HTTP_OK, $data);
        
        header("HTTP/1.1 " . API_HTTP_OK);
        if($type == 'json') {
            $callback = getgpc('callback');
            $this->send_json_headers();
            $data = json_encode($data);
            if($callback) $data = $callback.'('.$data.')';
            echo $data;
        } else {
            $this->send_xml_headers();
            echo $this->serialize($data, $htmlon);  
        }
        exit;
    }

    //错误输出
    function error($http_status_code, $error, $error_description=NULL, $error_uri=NULL, $type=NULL, $extras=NULL) {
        $this->log('error', $http_status_code.'  '.$error);
        if($this->error_hooks) {
            foreach($this->error_hooks as $type=>$params) {
                $this->log('error_hook', $type.'  '.json_encode($params));
                if($type == 'update_authcode_register') {
                    $authcode = $params['authcode'];
                    $deviceid = $params['deviceid'];
                    $this->load('device');
                    $_ENV['device']->update_authcode_register($authcode, $deviceid, $error, $extras);
                }
            }
        }
        apierror($http_status_code, $error, $error_description, $error_uri, $type, $extras);
    }
    
    //关键字处理
    function keyword($str) {
        $str = str_replace("and","",$str);
        $str = str_replace("execute","",$str);
        $str = str_replace("update","",$str);
        $str = str_replace("count","",$str);
        $str = str_replace("chr","",$str);
        $str = str_replace("mid","",$str);
        $str = str_replace("master","",$str);
        $str = str_replace("truncate","",$str);
        $str = str_replace("char","",$str);
        $str = str_replace("declare","",$str);
        $str = str_replace("select","",$str);
        $str = str_replace("create","",$str);
        $str = str_replace("delete","",$str);
        $str = str_replace("insert","",$str);
        $str = str_replace("'","",$str);
        //$str = str_replace("\"","",$str);
        //$str = str_replace(" ","",$str);
        $str = str_replace("or","",$str);
        $str = str_replace("=","",$str);
        $str = str_replace("%20","",$str);
        $str = addslashes($str);
        return $str;
    }
    
    //加载model类
    function load_connect($connect_type, $base = NULL, $source = '') {
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."connect WHERE connect_type='$connect_type'");
        if($connect) {
            $base = $base ? $base : $this;
            $model = $connect['connect_model'];
            if(empty($_ENV[$model.'connect'])) {
                if(file_exists(API_ROOT.$source."model/connect/$model.php")) {
                    require_once API_ROOT.$source."model/connect/$model.php";
                } else if(file_exists(API_SOURCE_ROOT."model/connect/$model.php")) {
                    require_once API_SOURCE_ROOT."model/connect/$model.php";
                } else {
                    return false;
                }
                $config = unserialize($connect['connect_config']);
                $config['connect_type'] = $connect_type;
                eval('$_ENV[$model.\'connect\'] = new '.$model.'connect($base, $config);');
            }
            return $_ENV[$model.'connect'];
        }
        return null;
    }
    
    function load_connect_by_key($connect_key, $base = NULL, $source = '') {
        $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."connect WHERE connect_key='$connect_key'");
        if($connect) {
            $base = $base ? $base : $this;
            $model = $connect['connect_model'];
            if(empty($_ENV[$model.'connect'])) {
                if(file_exists(API_ROOT.$source."model/connect/$model.php")) {
                    require_once API_ROOT.$source."model/connect/$model.php";
                } else if(file_exists(API_SOURCE_ROOT."model/connect/$model.php")) {
                    require_once API_SOURCE_ROOT."model/connect/$model.php";
                } else {
                    return false;
                }
                $config = unserialize($connect['connect_config']);
                $config['connect_type'] = $connect['connect_type'];
                eval('$_ENV[$model.\'connect\'] = new '.$model.'connect($base, $config);');
            }
            return $_ENV[$model.'connect'];
        }
        return null;
    }
    
    function _gen_client_sign($client_id, $expire=0) {
        if(!$client_id || !$expire)
            return "";
        
        if($expire < $this->time)
            return "";
        
        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
        if(!$client) 
            return "";
        
        $appid = $client['appid'];
        $ak = $client['client_id'];
        $sk = $client['client_secret'];
        $rsign = md5($appid.$expire.$ak.$sk);
        
        return $appid.'-'.$ak.'-'.$rsign;
    }
    
    function _check_client_sign($sign, $expire, $cstr) {
        if (!$sign || !$expire)
            return false;
        
        if ($expire < $this->time)
            return false;
        
        $sarr = explode('-', $sign);
        if (!is_array($sarr) || count($sarr) !== 3)
            return false;

        $appid = $sarr[0];
        $ak = $sarr[1];
        $rsign = $sarr[2];

        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$ak'");
        if (!$client || $rsign !== md5($appid.$expire.$ak.$client['client_secret'].$cstr))
            return false;

        // log记录 ----------- init params
        log::$appid = $appid;
        log::$client_id = $ak;
        
        return $client;
    }
    
    function _check_partner_token($partner_token) {
        if (!$partner_token)
            return false;
        
        $arr = explode('\.', $partner_token);
        if (!is_array($arr) || count($arr) !== 4)
            return false;

        $client_id = $arr[0];
        $data = $arr[1];
        $expire = $arr[2];
        $rsign = $arr[3];
        
        if ($expire < $this->time)
            return false;
        
        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id' AND partner_type>0");
        if (!$client || $rsign !== md5($client_id.$client['client_secret'].$expire.$data))
            return false;

        // log记录 ----------- init params
        log::$appid = $client['appid'];
        log::$client_id = $client_id;

        $partner = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'partner WHERE partner_id="'.$client['partner_id'].'"');
        if (!$partner)
            return false;

        $connect_user = json_decode($this->base64_url_decode($data), true);
        if (!$connect_user || !$connect_user['uid']) {
            $connect_uid = $data;
            $connect_user = array();
        } else {
            $connect_uid = $connect_user['uid'];
        }

        $partner['connect_uid'] = $connect_uid;
        $partner['connect_user'] = $connect_user;

        return $partner;
    }
    
    function _check_partner_sign($sign, $expire, $cstr) {
        if (!$sign || !$expire)
            return false;
        
        if ($expire < $this->time)
            return false;
        
        $sarr = explode('-', $sign);
        if (!is_array($sarr) || count($sarr) !== 3)
            return false;

        $appid = $sarr[0];
        $ak = $sarr[1];
        $rsign = $sarr[2];

        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$ak' AND partner_type>0");
        if (!$client || $rsign !== md5($appid.$expire.$ak.$client['client_secret'].$cstr))
            return false;

        // log记录 ----------- init params
        log::$appid = $appid;
        log::$client_id = $ak;

        return $client;
    }
    
    function _gen_device_sign($deviceid, $client_id, $time) {
        if(!$deviceid || !$client_id || !$time)
            return false;
        
        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id' AND type='1'");
        if(!$client)
            return false;
        
        return md5($deviceid.$client['client_secret'].$time);
    }
    
    function _check_device_sign($sign, $deviceid, $client_id, $time) {
        if (!$sign || !$deviceid || !$client_id || !$time)
            return false;
        
        $client = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id' AND type='1'");
        if (!$client || $sign !== md5($deviceid.$client['client_secret'].$time))
            return false;

        // log记录 ----------- init params
        log::$appid = $client['appid'];
        log::$client_id = $client_id;

        return $client;
    }
    
    function get_partner_by_partner_id($partner_id) {
        if(!$partner_id)
            return false;
        
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."partner WHERE partner_id='$partner_id'");
    }
    
    function log($action, $extra = '') {
        $time = time();
        //$log = htmlspecialchars($this->onlineip."\t".$this->time."\t$action\t$extra");
        $log = $this->onlineip."\t".request_id()."\t".date("Y-m-d H:i:s", $time)."\t$action\t$extra";
        $logfile = API_ROOT.'./data/logs/'.gmdate('Ymd', $time).'.php';
        
        if(file_exists($logfile)) {
            $newfile = false;
        } else {
            $newfile = true;
        }
        
        if($fp = @fopen($logfile, 'a')) {
            @flock($fp, 2);
            if($newfile) {
                @fwrite($fp, "<?PHP exit;?>\n");
            }
            @fwrite($fp, str_replace(array('<?', '?>', '<?php'), '', $log)."\n");
            @fclose($fp);
        }
    }
    
    function sid_encode($uid) {
        //$ip = $this->onlineip;
        //$agent = $_SERVER['HTTP_USER_AGENT'];
        $ip = '';
        $agent = '';
        $authkey = md5($ip.$agent.API_KEY);
        $check = substr(md5($ip.$agent), 0, 8);
        return rawurlencode($this->authcode("$uid\t$check", 'ENCODE', $authkey, 86400));
    }

    function sid_decode($sid) {
        //$ip = $this->onlineip;
        //$agent = $_SERVER['HTTP_USER_AGENT'];
        $ip = '';
        $agent = '';
        $authkey = md5($ip.$agent.API_KEY);
        $s = $this->authcode(rawurldecode($sid), 'DECODE', $authkey, 86400);
        if(empty($s)) {
            return FALSE;
        }
        @list($uid, $check) = explode("\t", $s);
        if($check == substr(md5($ip.$agent), 0, 8)) {
            return $uid;
        } else {            
            return FALSE;
        }
    }
    
    function rid_encode($uid) {
        //$ip = $this->onlineip;
        //$agent = $_SERVER['HTTP_USER_AGENT'];
        $ip = '';
        $agent = '';
        $authkey = md5(API_KEY.$agent.$ip);
        $check = substr(md5($agent.$ip), 0, 8);
        return rawurlencode($this->authcode("$uid\t$check", 'ENCODE', $authkey, 86400));
    }

    function rid_decode($rid) {
        //$ip = $this->onlineip;
        //$agent = $_SERVER['HTTP_USER_AGENT'];
        $ip = '';
        $agent = '';
        $authkey = md5(API_KEY.$agent.$ip);
        $s = $this->authcode(rawurldecode($rid), 'DECODE', $authkey, 86400);
        if(empty($s)) {
            return FALSE;
        }
        @list($uid, $check) = explode("\t", $s);
        if($check == substr(md5($agent.$ip), 0, 8)) {
            return $uid;
        } else {            
            return FALSE;
        }
    }

    function cid_encode($connect_type, $connect_uid) {
        //$ip = $this->onlineip;
        //$agent = $_SERVER['HTTP_USER_AGENT'];
        $ip = '';
        $agent = '';
        $authkey = md5(API_KEY.$agent.$ip);
        $check = substr(md5($agent.$ip), 0, 8);
        return rawurlencode($this->authcode("$connect_type\t$connect_uid\t$check", 'ENCODE', $authkey, 86400));
    }

    function cid_decode($cid) {
        //$ip = $this->onlineip;
        //$agent = $_SERVER['HTTP_USER_AGENT'];
        $ip = '';
        $agent = '';
        $authkey = md5(API_KEY.$agent.$ip);
        $s = $this->authcode(rawurldecode($cid), 'DECODE', $authkey, 86400);
        if(empty($s)) {
            return FALSE;
        }
        @list($connect_type, $connect_uid, $check) = explode("\t", $s);
        if($check == substr(md5($agent.$ip), 0, 8)) {
            $result = array('connect_type' => $connect_type, 'connect_uid' => $connect_uid);
            return $result;
        } else {            
            return FALSE;
        }
    }
    
    function set_login($uid) {
        $sid = $this->sid_encode($uid);
        $this->setcookie(API_COOKIE_SID, $sid, 86400);
        return $sid;
    }
    
    function set_relogin($uid) {
        $rid = $this->rid_encode($uid);
        $this->setcookie(API_COOKIE_RID, $rid, 86400);
        return $rid;
    }

    function set_connect_login($connect_type, $connect_uid) {
        $cid = $this->cid_encode($connect_type, $connect_uid);
        $this->setcookie(API_COOKIE_CID, $cid, 86400);
        return $cid;
    }

    function set_userinfo($uname, $uid) {
        $this->setcookie(API_COOKIE_UNAME, $uname, 86400);
        $this->setcookie(API_COOKIE_UID, $uid, 86400);
    }
    
    function check_login() {
        $this->cookie_status = isset($_COOKIE[API_COOKIE_SID]) ? 1 : 0;
        $sid = $this->cookie_status ? getgpc(API_COOKIE_SID, 'C') : rawurlencode(getgpc(API_COOKIE_SID, 'R'));
        
        // 20160913 add sid
        if(!$sid) $sid = getgpc('sid', 'R');
        
        $this->view->sid = $this->sid_decode($sid) ? $sid : '';
        $this->view->assign(API_COOKIE_SID, $this->view->sid);
    
        $uid = $this->sid_decode($this->view->sid);
        $this->log('check_login', $this->view->sid." ".$uid);
        if($uid) {
            $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid='$uid'");
            if($user) {
                $this->user = $user;
                $this->user['username'] = $user['username'];
                $this->view->sid = $this->sid_encode($uid);
                $this->setcookie(API_COOKIE_SID, $this->view->sid, 86400);
            }
            $this->view->assign('user', $this->user);
        } else {
            $this->setcookie(API_COOKIE_SID, '');
        }
    }

    function check_connect_login() {
        $cid = isset($_COOKIE[API_COOKIE_CID]) ? getgpc(API_COOKIE_CID, 'C') : rawurlencode(getgpc(API_COOKIE_CID, 'R'));
        $data = $this->cid_decode($cid);
        if($data) {
            $connect_type = $data['connect_type'];
            $connect_uid = $data['connect_uid'];
            if($connect_type > 0) {
                $connect = $this->load_connect($connect_type);
                if($connect && $connect->_support_connect_login()) {
                    $connect_user = $connect->get_connect_login_user($connect_uid);
                    if($connect_user && $connect_user['connect_uid']) {
                        $this->connect_user = $connect_user;
                    }
                }
            }
        } else {
            $this->setcookie(API_COOKIE_CID, '');
        }
    }
    
    function check_relogin() {
        $rid = isset($_COOKIE[API_COOKIE_RID]) ? getgpc(API_COOKIE_RID, 'C') : rawurlencode(getgpc(API_COOKIE_RID, 'R'));
        if(!$rid)
            return FALSE;
        $uid = $this->rid_decode($rid);
        if($uid) {
            $this->check_login();
            if($this->user && ($this->user['uid'] != $uid)) {
                $this->user = array();
            }
        }
        $this->setcookie(API_COOKIE_RID, '');
    }
    
    function logout() {
        $this->setcookie(API_COOKIE_SID, '');
        $this->setcookie(API_COOKIE_RID, '');
        $this->setcookie(API_COOKIE_UNAME, '');
        $this->setcookie(API_COOKIE_UID, '');
        $this->setcookie(API_COOKIE_CID, '');
    }
    
	function redirect_url($force_redirect=false) {
		$redirect = getgpc('redirect');
		if(!$redirect) {
            if($force_redirect) {
    			$redirect = $_SERVER['HTTP_REFERER']?$_SERVER['HTTP_REFERER']:API_DEFAULT_REDIRECT;
            } else {
                return '';
            }
		}
		return $redirect;
	}
	
	function redirect($url='') {
		if(!$url) $url = $this->redirect_url(true);
		header('Location:'.$url);
		exit();
	}
    
    function is_support_timezone($timezone) {
        if(!$timezone) return false;
        $timezone_ids = DateTimeZone::listIdentifiers();
        $support_ids = array('Asia/Rangoon');
        $timezone_ids = array_merge($timezone_ids, $support_ids);
        return in_array($timezone, $timezone_ids)?true:false;
    }
    
    function get_timezone_rule_from_timezone_id($timezone, $use_dst_timezone=FALSE) {
        if(!$timezone || !$this->is_support_timezone($timezone)) {
            $timezone = 'Asia/Shanghai';
        }
    
        $dtz = new DateTimeZone($timezone);
    
        $dt_now = new DateTime('now', $dtz);
        $now = $dt_now->getTimestamp();
        $year = $dt_now->format('Y'); 
    
        $dt_start = new DateTime(($year-1).'-01-01', $dtz);
        $dt_end = new DateTime(($year+2).'-01-01', $dtz);
        $start = $dt_start->getTimestamp();
        $end = $dt_end->getTimestamp();
    
        $list = $dtz->getTransitions($start, $end);
        if(!$list)
            return false;
    
        $offset = 0;
        $dst = 0;
        $dst_offset = 0;
        $dst_start = 0;
        $dst_end = 0;
        
        $now_pass = 0;
        $now_dst = 0;
        $now_ts = 0;
        $now_offset = 0;
    
        foreach($list as $data) {
            if(!$now_pass) {
                if($data['ts'] > $now) {
                    $now_pass = 1;
                } else {
                    $now_dst = $data['isdst']?1:0;
                    $now_ts = $data['ts'];
                    $now_offset = $data['offset'];
                }
            }
        
            if($now_pass) {
                if(!$now_ts)
                    return false;
            
                if($now_dst) {
                    if(!$dst_start) {
                        $dst = 1;
                        $dst_start = $now_ts;
                        $dst_offset = $now_offset;
                    }
                
                    if($data['isdst']) {
                        $dst_start = $data['ts'];
                        $dst_offset = $data['offset'];
                    } else {
                        $dst_end = $data['ts'];
                        $offset = $data['offset'];
                        break;
                    }
                } else {
                    if(!$offset) {
                        $offset = $now_offset;
                    }
                
                    if($data['isdst']) {
                        $dst = 1;
                        $dst_start = $data['ts'];
                        $dst_offset = $data['offset'];
                    } else {
                        if($dst && $dst_start && $data['ts'] > $dst_start) {
                            $dst_end = $data['ts'];
                            break;
                        }
                    }
                }
            }
        }
        
        if(!$offset)
            $offset = $now_offset;
        
        if($dst && $dst_start && !$dst_end) {
            $dst_end = $end;
        }
        
        // 夏令时
        if($use_dst_timezone && $dst && $dst_start && $dst_start < $this->time && $dst_end > $this->time) {
            $info = $this->get_timezone_info_by_offset($dst_offset);
            $offset = $dst_offset;
        } else {
            $info = $this->get_timezone_info_by_offset($offset);
        }
        
        if(!$info)
            return false;
        
        $rule = array();
        if($dst && $dst_start) {
            $dst_offset = $dst_offset - $offset;
            $dst_offset = $dst_offset<0?($dst_offset|0x8000):$dst_offset;
            $rule = array(
                'timezone_id' => $timezone,
                'timezone' => $info['timezone'],
                'offset' => $offset,
                'dst' => $dst,
                'dst_offset' => $dst_offset,
                'dst_start' => $dst_start + $offset,
                'dst_end' => $dst_end + $offset
            );
        } else {
            $dst = 0;
            $rule = array(
                'timezone_id' => $timezone,
                'timezone' => $info['timezone'],
                'offset' => $offset,
                'dst' => $dst
            );
        }
    
        return $rule;
    }
    
    //UInt8，高两位值0~3分别表示0,15,30,45分钟，低6位表示时区整数
    //低6位时区整数如果>13，用13减去该值，得到负数连同负分钟数进行运算
    //时区范围为0~13，-1~-12；时区设置有15/30/45分钟的 (20140214)
    function get_timezone_info_by_offset($offset) {
        $hour = (int)($offset/3600);
        if($hour>14 || $hour<-12)
            return false;
    
        $sec = abs($offset%3600);
        $min = (int)($sec/60);
        $quarter = (int)($sec/900);
    
        $tz = $quarter << 6;
        $tz = $tz|($hour >= 0?($hour>13?$hour+36:$hour):(13-$hour));
    
        $info = array(
            'timezone' => $tz,
            'offset' => $offset,
            'hour' => $hour,
            'min' => $min
        );
        return $info;
    }

    function get_timezone_info_by_timezone($tz) {
        if($tz < 0 || $tz > 255)
            return false;

        $hour = intval($tz & 0x3F);
        if($hour > 49) {
            $hour = $hour - 36; 
        } else if($hour > 13) {
            $hour = 13 - $hour;
        }

        if($hour < -12 || $hour > 14)
            return false;

        $quarter = intval(($tz & 0xC0) >> 6);
        $min = $quarter*15;
    
        $offset = ($hour<0?-1:1)*(abs($hour)*3600 + $min*60);
    
        $info = array(
            'timezone' => $tz,
            'offset' => $offset,
            'hour' => $hour,
            'min' => $min
        );
        return $info;
    }
    
    function time_format($time, $format, $timezone) {
        if(!$timezone || !$this->is_support_timezone($timezone)) {
            $timezone = 'Asia/Shanghai';
        }
    
        $dtz = new DateTimeZone($timezone);
        $dt = new DateTime('now', $dtz);
        $dt->setTimestamp($time);
        return $dt->format($format);
    }
    
    function day_start_time($time, $timezone) {
        if(!$timezone || !$this->is_support_timezone($timezone)) {
            $timezone = 'Asia/Shanghai';
        }
        
        $dtz = new DateTimeZone($timezone);
        $dt = new DateTime('now', $dtz);
        $dt->setTimestamp($time);
        $dt->setTime(0, 0, 0);
        return $dt->getTimestamp();
    }
    
    function day_end_time($time, $timezone) {
        if(!$timezone || !$this->is_support_timezone($timezone)) {
            $timezone = 'Asia/Shanghai';
        }
        
        $dtz = new DateTimeZone($timezone);
        $dt = new DateTime('now', $dtz);
        $dt->setTimestamp($time);
        $dt->setTime(23, 59, 59);
        return $dt->getTimestamp();
    }
    
    function get_storage_service($storageid) {
        $_CACHE['storage_services'] = $this->cache('storage_services');
        return $_CACHE['storage_services'][$storageid] ? $_CACHE['storage_services'][$storageid] : array();
    }
    
    function get_storage_services() {
        // update storage services cache:
        // $this->load('cache');
        // $_ENV['cache']->updatedata('storage_services');
        $storages = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'storage_service');
        return $storages ? $storages : array();
    }
    
    function storage_temp_url($storageid, $params) {
        //$container, $object, $filename='', $expires_in=0, $local=false
        if(!$storageid || !$params)
            return "";
        
        $storage = $this->load_storage($storageid);
        if(!$storage)
            return "";
        
        return $storage->temp_url($params);
    }
    
    // 加载storage类
    function load_storage($storageid, $base = NULL, $source = '') {
        $service = $this->get_storage_service($storageid);
        if($service) {
            $base = $base ? $base : $this;
            $model = $service['storage_type'];
            if(empty($_ENV['storage_'.$storageid])) {
                if(file_exists(API_ROOT.$source."model/storage/$model.php")) {
                    require_once API_ROOT.$source."model/storage/$model.php";
                } else {
                    require_once API_SOURCE_ROOT."model/storage/$model.php";
                }
                eval('$_ENV[\'storage_\'.$storageid] = new '.$model.'storage($base, $service);');
            }
            return $_ENV['storage_'.$storageid];
        }
        return null;
    }

    function ai_id() {
        if($this->partner && $this->partner['config']) {
            $partner_config = json_decode($this->partner['config'], true);
            if(isset($partner_config['ai']) && isset($partner_config['ai']['ai_id']) ){
                return $partner_config['ai']['ai_id'];
            }
        }
        return 1;
    }

    //加载ai类
    function load_aiserver($aiid, $base = NULL, $source = ''){
        $service = $this->get_ai_service($aiid);
        if($service) {
            $base = $base ? $base : $this;
            $model = $service['ai_type'];
            if(empty($_ENV['aiserver_'.$aiid])) {
                if(file_exists(API_ROOT.$source."model/ai/$model.php")) {
                    require_once API_ROOT.$source."model/ai/$model.php";
                } else {
                    require_once API_SOURCE_ROOT."model/ai/$model.php";
                }
                eval('$_ENV[\'aiserver_\'.$aiid] = new '.$model.'ai($base, $service);');
            }
            return $_ENV['aiserver_'.$aiid];
        }
        return null;
    }
    function get_ai_service($ai_id){
        $service = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."ai_service WHERE ai_id='$ai_id'");
        return $service;
    }

    function get_pay_service($payid) {
        $_CACHE['pay_services'] = $this->cache('pay_services');
        return $_CACHE['pay_services'][$payid] ? $_CACHE['pay_services'][$payid] : array();
    }
    
    function get_pay_services() {
        // update pay services cache:
        // $this->load('cache');
        // $_ENV['cache']->updatedata('pay_services');
        $pays = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'pay_service');
        return $pays ? $pays : array();
    }
    
    // 加载pay类
    function load_pay($payid, $base = NULL, $source = '') {
        $service = $this->get_pay_service($payid);
        if($service) {
            $base = $base ? $base : $this;
            $model = $service['pay_type'];
            if(empty($_ENV['pay_'.$payid])) {
                if(file_exists(API_ROOT.$source."model/pay/$model.php")) {
                    require_once API_ROOT.$source."model/pay/$model.php";
                } else {
                    require_once API_SOURCE_ROOT."model/pay/$model.php";
                }
                eval('$_ENV[\'pay_\'.$payid] = new '.$model.'pay($base, $service);');
            }
            return $_ENV['pay_'.$payid];
        }
        return null;
    }
    
	// 加载notify
	function load_notify($sendemail = 0, $sendsms = 0, $appid = NULL, $base = NULL, $source = '') {
        $appid = $appid?$appid:API_AUTH_APPID;
        $sql = "SELECT * FROM ".API_DBTABLEPRE."notify_service WHERE appid='$appid' AND status>0";
        if($sendemail) $sql .= " AND sendemail='1' AND notifyid='2'";
        if($sendsms) $sql .= " AND sendsms='1' AND notifyid='2'";
		$service = $this->db->fetch_first($sql);
		if($service) {
            $base = $base ? $base : $this;
            $model = $service['notify_type'];
            $notifyid = $service['notifyid'];
    		if(empty($_ENV['notify_'.$notifyid])) {
    			if(file_exists(API_ROOT.$source."model/notify/$model.php")) {
    				require_once API_ROOT.$source."model/notify/$model.php";
    			} else {
    				require_once API_SOURCE_ROOT."model/notify/$model.php";
    			}
                $service['notify_config'] = unserialize($service['notify_config']);
    			eval('$_ENV[\'notify_\'.$notifyid] = new '.$model.'notify($base, $service);');
    		}
    		return $_ENV['notify_'.$notifyid];
        }
        return null;
	}
    
    function url($url, $params=array(), $force_redirect=false) {
        if(!$url)
            return '';
        
        if(!defined('API_REDIRECT_URL')) {
            define("API_REDIRECT_URL", $this->redirect_url($force_redirect));
        }
        
        if(!defined('API_URL_EXTRA')) {
            $extra = array();
            if(defined('API_DISPLAY') && API_DISPLAY != '')
                $extra['display'] = API_DISPLAY;
            if(defined('API_LANGUAGE') && API_LANGUAGE != 'zh-Hans')
                $extra['lang'] = API_LANGUAGE;
            if(defined('API_APP') && API_APP != '')
                $extra['app'] = API_APP;
            define("API_URL_EXTRA", http_build_query($extra, '', '&'));
        }
        
        $extra = array();
        if($params)
            $extra[] = http_build_query($params, '', '&');
        if(API_URL_EXTRA)
            $extra[] = API_URL_EXTRA;
        if(API_REDIRECT_URL)
            $extra[] = 'redirect='.API_REDIRECT_URL;
        
        if($extra) {
            $extra = join('&', $extra);
            $delimiter = strpos($url, '?') === false ? '?' : '&';
            $url = $url.$delimiter.$extra;
        }
        
        return $url;
    }
    
    function base64_url_encode($input)
    {
        return strtr(base64_encode($input), '+/', '-_');
    }
    
    function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
    
    // 把用户输入的文本转义（主要针对特殊符号和emoji表情）
    function userTextEncode($str) {
        if (!is_string($str)) 
            return $str;

        if (!$str || $str === 'undefined')
            return '';

        // 暴露出unicode
        $text = json_encode($str);

        // 将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i", function($str) {
            return mysql_real_escape_string(addslashes($str[0]));
        }, $text); 

        return json_decode($text);
    }

    // 解码上面的转义
    function userTextDecode($str) {
        //暴露出unicode
        $text = json_encode($str);

        //将两条斜杠变成一条，其他不动
        $text = preg_replace_callback('/\\\\\\\\/i', function($str) {
            return '\\';
        }, $text);
        
        return json_decode($text);
    }

    // 获取字符串可视的字符个数
    function dstrlen($str) {
        if(strtolower(API_CHARSET) != 'utf-8') {
            return strlen($str);
        }
        $count = 0;
        for($i = 0; $i < strlen($str); $i++){
            $value = ord($str[$i]);
            if($value > 127) {
                if($value >= 192 && $value <= 223)
                    $i++;
                elseif($value >= 224 && $value <= 239) 
                    $i = $i + 2;
                elseif($value >= 240 && $value <= 247) 
                    $i = $i + 3;
            }
            $count++;
        }
        return $count;
    }

    function compressAndEncrypt($str) {
        $compressed = gzdeflate($str, 9);
        if (!$compressed)
            return false;

        $encrypted = $this->authcode($compressed, 'ENCODE');

        if (!$encrypted)
            return false;

        return strtr($encrypted, '+/', '-_');
    }

    function decryptAndUncompress($str) {
        $decrypted = $this->authcode(strtr($str, '-_', '+/'));
        if (!$decrypted)
            return false;

        $uncompressed = gzinflate($decrypted);
        if (!$uncompressed)
            return false;

        return $uncompressed;
    }
    
    function n16to32($a){  
        for($a = md5( $a, true ),  
            $s = '0123456789ABCDEFGHIJKLMNOPQRSTUV',  
            $d = '',  
            $f = 0;  
            $f < 8;  
            $g = ord( $a[ $f ] ),  
            $d .= $s[ ( $g ^ ord( $a[ $f + 8 ] ) ) - $g & 0x1F ],  
            $f++  
        );  
        return $d;  
    }
    
    function register_error_hook($type, $params=array()) {
        if(!$type || !in_array($type, array('update_authcode_register'))) return false;
        $this->error_hooks[$type] = $params;
        return true;
    }

    function get_ip_info($ip) {
        if(!$ip)
            return false;
        
        require_once API_SOURCE_ROOT.'lib/ipdb/IP.class.php';
        $info = IP::find($ip);
        return $info;
    }
    
    function seccode_authkey() {
        return md5(API_KEY.$_SERVER['HTTP_USER_AGENT'].$this->onlineip);
    }
    
    function seccode_init() {
        include_once API_SOURCE_ROOT.'lib/captcha.class.php';
        $captcha = new SimpleCaptcha();
        $rand = $captcha->getRandomCaptchaText(4);
        // $rand = $captcha->getDictionaryCaptchaText();
        // $rand = $captcha->getCaptchaText();
        
        $authkey = $this->seccode_authkey();
        $seccodeinit = rawurlencode($this->authcode($rand, 'ENCODE', $authkey, 180));
        return array(
            'seccodehidden' => $seccodeinit,
            'seccodeurl' => BASE_API.'/v2/util/seccode?cid='.API_CID.'&code='.$seccodeinit.'&'.$this->time
        );
    }
    
    function decode_seccode($seccodehidden) {
        if(!$seccodehidden)
            return false;
        
        $authkey = $this->seccode_authkey();
        return $this->authcode($seccodehidden, 'DECODE', $authkey);
    }

    function get_default_stronge() {
        $appid = $this->appid;
        $storageid = $this->db->result_first("SELECT defaultstorageid FROM ".API_DBTABLEPRE."oauth_app WHERE appid='".$appid."'");
        if(!$storageid)
            return 11;
        return $storageid;
    }
    //百度获取pcs参数
    function is_pcs_request(){
        $this->init_input();
        if(API_BAIDU_PCS){
            return true;
        }
        $pcs = $this->input('pcs');
        if($pcs){
            return true;
        }else{
            return false;
        }
    }

}
