<?php

!defined('IN_API') && exit('Access Denied');

class audiocontrol extends base {

    function __construct() {
        $this->audiocontrol();
    }

    function audiocontrol() {
        parent::__construct();
        $this->load('audio');
    }
    
    function onlistalbum() {
        $this->init_input();
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));
        $orderby = $this->input('orderby');
        
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['audio']->listalbum($page, $count, $orderby);
        if(!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, AUDIO_ERROR_LIST_ALBUM_FAILED);
        
        return $result;
    }
    
    function onalbum() {
        $this->init_input();
        $albumid = $this->input('albumid');

        $result = $_ENV['audio']->album($albumid);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, AUDIO_ERROR_GET_ALBUM_FAILED);
        
        return $result;
    }
    
    function ontrack() {
        $this->init_input();
        $trackid = $this->input('trackid');

        $result = $_ENV['audio']->track($trackid);
        if (!$result)
            $this->error(API_HTTP_BAD_REQUEST, AUDIO_ERROR_GET_TRACK_FAILED);
        
        return $result;
    }
    
}