<?php

!defined('IN_API') && exit('Access Denied');

class searchmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->searchmodel($base);
    }

    function searchmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    // 根据主键查找关键词
    function get_keyword_by_pk($uid, $keyword, $fileds='*') {
        return $this->db->fetch_first('SELECT '.$fileds.' FROM '.API_DBTABLEPRE.'search_keywords WHERE uid="'.$uid.'" AND keyword="'.$keyword.'"');
    }

    // 保存关键词
    function save_keyword($uid, $keyword, $client_id, $appid) {
        $result = $this->get_keyword_by_pk($uid, $keyword);
        if($result) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'search_keywords SET num=num+1, lastupdate="'.$this->base->time.'",  client_id="'.$client_id.'", appid="'.$appid.'" WHERE uid="'.$uid.'" AND keyword="'.$keyword.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'search_keywords SET uid="'.$uid.'", keyword="'.$keyword.'", num=1, dateline="'.$this->base->time.'", lastupdate="'.$this->base->time.'", client_id="'.$client_id.'", appid="'.$appid.'"');
        }
    }

    // 获取关键词列表
    function list_keyword($uid, $page, $count) {
        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'search_keywords WHERE uid="'.$uid.'"');
            $page = $this->base->page_get_page($page, $count, $total);

            $list = array();
            if($total)
                $list = $this->db->fetch_all('SELECT keyword,num FROM '.API_DBTABLEPRE.'search_keywords WHERE uid="'.$uid.'" ORDER BY lastupdate DESC LIMIT '.$page['start'].','.$count);

        $result = array();
        $result['page'] = $page['page'];
        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    // 删除搜索关键词
    function drop_keyword($uid, $keyword) {
        $where = ' WHERE uid="'.$uid.'"';
        if ($keyword)
            $where .= ' AND keyword="'.$keyword.'"';

        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'search_keywords'.$where);

        return array('uid' => $uid);
    }

    // 处理搜索关键词
    function _parse_keyword($keyword) {
        $sign = array('%');
        for ($i = 0, $n = count($sign); $i < $n; $i++) { 
            $keyword = str_replace($sign[$i], '\\'.$sign[$i], $keyword);
        }
        return $keyword;
    }
}
