<?php

!defined('IN_API') && exit('Access Denied');

class audiomodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->audiomodel($base);
    }

    function audiomodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }
    
    function listalbum($page, $count, $orderby) {
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        
        $list = array();
        
        $table = API_DBTABLEPRE.'audio_album';
        $wheresql = '';
        
        $orderbysql = '';
        switch ($orderby) {
            case 'play': $orderbysql=' ORDER BY play_count DESC'; break;
            case 'favorite': $orderbysql=' ORDER BY favorite_count DESC'; break;
            case 'comment': $orderbysql=' ORDER BY comment_count DESC'; break;
            case 'download': $orderbysql=' ORDER BY download_count DESC'; break;
            default: $orderbysql=' ORDER BY recommend DESC';break;
        }
        
        $sqladd = '';
        
        $wheresql .= $sqladd;
        
        $total = $this->db->result_first("SELECT count(*) FROM $table $wheresql");
        
        $pages = $this->base->page_get_page($page, $count, $total);
        $limit = ' LIMIT '.$pages['start'].', '.$count;
        
        if($total) {
            $data = $this->db->fetch_all("SELECT * FROM $table $wheresql $orderbysql $limit");
            foreach($data as $value) {
                $album = $this->_format_album($value);
                $list[] = $album;
            }
        }
        
        $result = array();
        $result['page'] = $pages['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    
    function album($albumid) {
        if(!$albumid)
            return false;
        
        $album = $this->get_album_by_id($albumid);
        if(!$album)
            return false;
        
        $album = $this->_format_album($album);
        
        $track_list = array();
        $data = $this->db->fetch_all("SELECT t.* FROM ".API_DBTABLEPRE."audio_album_track a LEFT JOIN ".API_DBTABLEPRE."audio_track t ON a.trackid=t.trackid WHERE a.albumid='$albumid' ORDER BY a.order_num ASC");
        foreach($data as $value) {
            if($value) {
                $track = $this->_format_track($value);
                if($track) $track_list[] = $track;
            }
        }
        
        $album['track_count'] = count($track_list);
        $album['track_list'] = $track_list;
        return $album;
    }
    
    function get_album_by_id($albumid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."audio_album WHERE albumid='$albumid'");
    }
    
    function _format_album($value) {
        if(!$value || !$value['albumid']) return false;
        return array(
            'albumid' => strval($value['albumid']),
            'source' => strval($value['source']),
            'source_albumid' => strval($value['source_albumid']),
            'title' => strval($value['title']),
            'intro' => strval($value['intro']),
            'cover_url_small' => strval($value['cover_url_small']),
            'cover_url_middle' => strval($value['cover_url_middle']),
            'cover_url_large' => strval($value['cover_url_large']),
            'play_count' => intval($value['play_count']),
            'favorite_count' => intval($value['favorite_count']),
            'comment_count' => intval($value['comment_count']),
            'download_count' => intval($value['download_count'])
        );
    }
    
    function track($trackid) {
        if(!$trackid)
            return false;
        
        $track = $this->get_track_by_id($trackid);
        if(!$track)
            return false;
        
        $track = $this->_format_track($track);
        return $track;
    }
    
    function get_track_by_id($trackid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."audio_track WHERE trackid='$trackid'");
    }
    
    function _format_track($value) {
        if(!$value || !$value['trackid']) return false;
        return array(
            'trackid' => strval($value['trackid']),
            'source' => strval($value['source']),
            'source_trackid' => strval($value['source_trackid']),
            'title' => strval($value['title']),
            'tags' => strval($value['tags']),
            'intro' => strval($value['intro']),
            'cover_url_small' => strval($value['cover_url_small']),
            'cover_url_middle' => strval($value['cover_url_middle']),
            'cover_url_large' => strval($value['cover_url_large']),
            'download_url' => strval($value['download_url']),
            'download_size' => intval($value['download_size']),
            'duration' => intval($value['duration']),
            'play_count' => intval($value['play_count']),
            'favorite_count' => intval($value['favorite_count']),
            'comment_count' => intval($value['comment_count']),
            'download_count' => intval($value['download_count'])
        );
    }
    
}
