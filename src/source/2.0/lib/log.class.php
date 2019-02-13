<?php
    !defined('IN_API') && exit('Access Denied');

    class log {
        // request
        static $request_time;
        static $request_id;
        static $request_ip;
        static $request_ua;
        static $request_url;
        static $request_method;
        static $request_args;
        // debug
        static $language;
        static $controller;
        static $action;
        static $appid;
        static $client_id;
        static $uid;
        static $deviceid;

        static function init() {
            log::$request_time = request_time();
            log::$request_id = request_id();
            log::$request_ip = $_SERVER['REMOTE_ADDR'];
            log::$request_ua = $_SERVER['HTTP_USER_AGENT'];
            log::$request_url = request_url();
            log::$request_method = $_SERVER['REQUEST_METHOD'];
            log::$request_args = $_REQUEST;
        }

        static function access_log($response_code, $response_data) {
            // 处理敏感参数
            log::remove_privacy(log::$request_args);

            $response_time = get_millisecond() - log::$request_time;

            $debug = array_pop(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            $debug_info = '"file:'.$debug['file'].' line:'.$debug['line'].'"';

            $access_log = log::$request_id.' | '.log::$request_ip.' | '.log::$request_ua.' | '.log::$request_url.' | '.log::$request_method.' | '.json_encode(log::$request_args, JSON_UNESCAPED_UNICODE).' | '.log::$language.' | '.log::$controller.' | '.log::$action.' | '.log::$appid.' | '.log::$client_id.' | '.log::$uid.' | '.log::$deviceid.' | '.$response_code.' | '.json_encode($response_data, JSON_UNESCAPED_UNICODE).' | '.$response_time.' | '.$debug_info;

            SeasLog::setLogger(LOG_ACCESS_PATH);
            SeasLog::info($access_log);

            SeasLog::setLogger(LOG_DEBUG_PATH);
            SeasLog::info($access_log);
        }
        
        static function debug_log($cloud_server, $request_url, $request_args, $response_code, $response_data, $debug_info) {
            $response_time = get_millisecond() - log::$request_time;

            $debug_log = log::$request_id.' | '.$request_url.' | '.json_encode($request_args, JSON_UNESCAPED_UNICODE).' | '.$cloud_server.' | '.$response_code.' | '.$response_data.' | '.$response_time.' | '.$debug_info;

            SeasLog::setLogger(LOG_DEBUG_PATH);
            SeasLog::debug($debug_log);
        }

        static function error_log($tag, $request, $response, $errno, $error) {
            $response_time = get_millisecond() - log::$request_time;

            $error_log = log::$request_id.' | '.$tag.' | '.$request.' | '.$response.' | '.$errno.' | '.$error;

            SeasLog::setLogger(LOG_DEBUG_PATH);
            SeasLog::error($error_log);
        }

        static function remove_privacy(&$input) {
            if (isset($input['password'])) {
                unset($input['password']);
            }
        }
    }