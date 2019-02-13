<?php
//error_reporting(0);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
// @set_magic_quotes_runtime(0);

// 全局宏定义
define('IN_API', TRUE);
define('API_ROOT', dirname(__FILE__).'/');
define('BASE_API', strtolower(($_SERVER['HTTPS'] == 'on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'))));
define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());

// 处理全局变量
unset($GLOBALS, $_ENV, $HTTP_GET_VARS, $HTTP_POST_VARS, $HTTP_COOKIE_VARS, $HTTP_SERVER_VARS, $HTTP_ENV_VARS);
$_GET       = daddslashes($_GET, 1, TRUE);
$_POST      = daddslashes($_POST, 1, TRUE);
$_COOKIE    = daddslashes($_COOKIE, 1, TRUE);
$_SERVER    = daddslashes($_SERVER);
$_FILES     = daddslashes($_FILES);
$_REQUEST   = daddslashes($_REQUEST, 1, TRUE);

//版本号处理
$version = get_api_version();
$v = $version['v'];
$url = $version['url'];

//数据目录
define('API_PUB_DATADIR', API_ROOT.'data/');
define('API_SOURCE_ROOT', API_ROOT.'source/'.$v.'/');
define('API_DATADIR', API_SOURCE_ROOT.'data/');
define('API_LIBDIR', API_SOURCE_ROOT.'lib/');

//配置文件
require API_DATADIR.'config.inc.php';
require API_DATADIR.'errors.php';
require API_LIBDIR.'log.class.php';
require API_LIBDIR.'router.class.php';

// log记录 ----------- init request params
log::init();

if ($url) {
    include API_DATADIR.'routes.php';

    $route = router::parse($url);
    if ($route) {
        $ext = $route['url']['ext'];
        $m = $route['controller'];
        $a = $route['action'];

        // log记录 ----------- init params
        log::$controller = $m;
        log::$action = $a;
    }
}

if (empty($ext) || $ext != 'xml') {
    $ext = 'json';
}
if (empty($m) && empty($a)) {
    apierror(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
}

if (!API_OPEN) {
    apierror(API_HTTP_SERVICE_UNAVAILABLE, API_CLOSE);
}

require API_SOURCE_ROOT.'model/base.php';
require API_SOURCE_ROOT.'model/admin.php';

$mods = array('oauth2', 'app', 'user', 'device', 'home', 'server', 'connect', 'youeryun', 'chart', 'ad', 
        'util', 'search', 'recommend', 'push', 'log', 'factory', 'partner', 'web', 'store', 'gome', 
        'service', 'qrcode', 'map', 'audio', 'multiscreen','face', 'event', 'merge', 'statistics' , 
        'watermark', 'hackathon', 'meeting', 'sim' , 'api');


if (in_array($m, $mods)) {
    include API_SOURCE_ROOT."control/$m.php";

    $classname = $m.'control';
    $control = new $classname();
    $method = 'on'.$a;

    if ($route)
        $control->route = $route;
    
    if (method_exists($control, $method) && $a{0} != '_') {
        $data = $control->$method();
    } elseif (method_exists($control, '_call')) {
        $data = $control->_call('on'.$a, '');
    } else {
        apierror(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    }

    echo is_array($data) ? $control->response($data, 1, $ext) : $data;
    exit;
} else {
    apierror(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
}

function daddslashes($string, $force = 0, $strip = FALSE) {
    if (!MAGIC_QUOTES_GPC || $force) {
        if (is_array($string)) {
            foreach ($string as $key => $val) {
                $string[$key] = daddslashes($val, $force, $strip);
            }
        } else {
            $string = addslashes($strip ? stripslashes($string) : $string);
        }
    }

    return $string;
}

function getgpc($k, $var = 'R') {
    switch ($var) {
        case 'G': $var = &$_GET; break;
        case 'P': $var = &$_POST; break;
        case 'C': $var = &$_COOKIE; break;
        case 'R': $var = &$_REQUEST; break;
    }

    return isset($var[$k]) ? $var[$k] : NULL;
}

function apierror($http_status_code, $error, $error_description = '', $error_uri = '', $type = '', $extras = '', $style = '') {
    $validtypes = array('xml', 'json');
    if (!$type || !in_array($type, $validtypes))
        $type = API_ERROR_DEFAULT_TYPE;

    //error code处理
    if ($error && $p = strpos($error, ':')) {
        $error_code = intval(substr($error, 0, $p));
        $error = substr($error, $p+1);
    } else {
        $error_code = intval(substr($http_status_code, 0, strpos($http_status_code, ' ')));
    }

    if ($style == 'gome') {
        $result['code'] = $error_code;
        $result['desc'] = $error;
    } else {
        $result['error_code'] = $error_code;
        $result['error_msg'] = $error;
    }

    if (API_ERROR_DISPLAY_ERROR && $error_description)
        $result['error_description'] = $error_description;

    if (API_ERROR_DISPLAY_ERROR && $error_uri)
        $result['error_uri'] = $error_uri;

    if ($extras && is_array($extras))
        $result = array_merge($result, $extras);

    $result['request_id'] = request_id();

    // log记录 ----------- access_log
    log::access_log($http_status_code, $result);

    header('HTTP/1.1 ' . $http_status_code);
    if ($type == 'json') {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($result);
    } else {
        include_once API_LIBDIR.'xml.class.php';

        header('Content-Type: application/xml');
        header('Cache-Control: no-store');
        echo xml_serialize(array('error'=>$result));
    }
    exit;
}

function get_api_version() {
    include_once API_ROOT.'./source/version.php';

    $url = getgpc('url');
    $versions = array('1.0', '1.1', '2.0');

    if (substr($url, 0, 1) != '/') {
        $url = '/'.$url;
    }

    $v = get_header_version();
    if ($v && in_array($v, $versions)) {
        if($v = '1.1') $v = '2.0';
    } elseif (substr($url, 0, 3) == '/v1' || substr($url, 0, 9) == '/rest/2.0') {
        $v = '1.0';
    } elseif (substr($url, 0, 3) == '/v2' || substr($url, 0, 7) == '/oauth2' || substr($url, 0, 10) == '/oauth/2.0') {
        $v = '2.0';
    } else {
        $v = API_SERVER_VERSION;
    }
    if(substr($url, 0,4) == '/api'){
        $v = '2.0';
    }

    if (substr($url, 0, 9) == '/rest/2.0') {
        $url = substr($url, 9);
    } elseif (substr($url, 0, 3) == '/v1' || substr($url, 0, 3) == '/v2') {
        $url = substr($url, 3);
    }

    return array('v' => $v, 'url' => $url);
}

function get_header_version() {
    $accept = '';

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (array_key_exists('Accept', $headers)) {
            $accept = $headers['Accept'];
        }
    } elseif (isset($_SERVER['HTTP_ACCEPT'])) {
        $accept = $_SERVER['HTTP_ACCEPT'];
    }

    if ($accept && preg_match('/\s*version\s*=(.+)/', $accept, $matches)) {
        return $matches[1];
    }

    return '';
}

// Request Log
function request_time() {
    if (!isset($_ENV['request_time']))
        $_ENV['request_time'] = get_millisecond();

    return $_ENV['request_time'];
}

function request_id() {
    if (!isset($_ENV['request_id']))
        $_ENV['request_id'] = time().rand(100, 999);

    return $_ENV['request_id'];
}

function request_url() {
    if (!isset($_ENV['request_url']))
        $_ENV['request_url'] = ($_SERVER['HTTPS'] === 'on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].explode('?', $_SERVER['REQUEST_URI'])[0];

    return $_ENV['request_url'];
}

// 获取当前时间(毫秒)
function get_millisecond() {
    $mtime = explode(' ', microtime());
    $time = $mtime[1] + $mtime[0];

    return intval($time * 1000);
}
