<?php

!defined('IN_API') && exit('Access Denied');

class multiscreenmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->multiscreenmodel($base);
    }

    function multiscreenmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function list_layout($uid, $list_type, $page, $count) {
        $result = array();

        $sql = API_DBTABLEPRE.'multiscreen_layout WHERE type=0 OR (type=1 AND uid="'.$uid.'")';

        if ($list_type == 'page') {
            $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.$sql);
            $pages = $this->base->page_get_page($page, $count, $total);

            $sql .= ' LIMIT '.$pages['start'].','.$count;
            $result['page'] = $pages['page'];
        }

        $layout = $this->db->fetch_all('SELECT lid,`type`,`rows`,cols,coords,lastupdate AS time FROM '.$sql);

        $result['count'] = count($layout);
        $result['list'] = $layout;

        return $result;
    }

    function add_layout($uid, $rows, $cols, $coords) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'multiscreen_layout SET type=1,uid="'.$uid.'",rows="'.$rows.'",cols="'.$cols.'",coords="'.$coords.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');

        $lid = $this->db->insert_id();

        return $this->get_layout_by_lid($lid);
    }

    function get_layout_by_lid($lid) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'multiscreen_layout WHERE lid="'.$lid.'"');
    }

    function drop_layout($lid) {
        $this->db->query('DELETE a,b,c FROM '.API_DBTABLEPRE.'multiscreen_layout a LEFT JOIN '.API_DBTABLEPRE.'multiscreen_display b ON a.lid=b.lid LEFT JOIN '.API_DBTABLEPRE.'multiscreen_display_category c ON b.did=c.did WHERE a.lid="'.$lid.'"');

        return true;
    }

    function list_display($uid, $list_type, $page, $count) {
        $result = array();

        if ($list_type == 'page') {
            $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.API_DBTABLEPRE.'multiscreen_display WHERE uid="'.$uid.'"');
            $pages = $this->base->page_get_page($page, $count, $total);

            $sql .= ' LIMIT '.$pages['start'].','.$count;
            $result['page'] = $pages['page'];
        }

        $display = $this->db->fetch_all('SELECT a.did,a.cycle,a.type,a.status,b.lid,b.rows,b.cols,b.coords FROM '.API_DBTABLEPRE.'multiscreen_display a LEFT JOIN '.API_DBTABLEPRE.'multiscreen_layout b ON a.lid=b.lid WHERE a.uid="'.$uid.'"');
        $n = count($display);
        for ($i = 0; $i < $n; $i++) {
            if (intval($display[$i]['type']) >= 2) {
                $cids = $this->db->result_first('SELECT GROUP_CONCAT(cid) AS cids FROM '.API_DBTABLEPRE.'multiscreen_display_category WHERE did="'.$display[$i]['did'].'" GROUP BY did');
                $display[$i]['cid'] = $cids ? explode(',', $cids) : array();
            }
        }

        $result['count'] = $n;
        $result['list'] = $display;

        return $result;
    }

    function add_display($uid, $lid, $cycle, $type, $cids) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'multiscreen_display SET uid="'.$uid.'",lid="'.$lid.'",cycle="'.$cycle.'",type="'.$type.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');

        $did = $this->db->insert_id();

        foreach ($cids as $cid) {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'multiscreen_display_category SET did="'.$did.'",cid="'.$cid.'",dateline="'.$this->base->time.'"');
        }

        $this->active_display($uid, $did);

        return $this->get_display_by_did($did);
    }

    function get_display_by_did($did) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'multiscreen_display WHERE did="'.$did.'"');
    }

    function get_display_by_pk($uid, $lid) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'multiscreen_display WHERE uid="'.$uid.'" AND lid="'.$lid.'"');
    }

    function active_display($uid, $did) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'multiscreen_display SET status=0 WHERE uid="'.$uid.'" AND did!="'.$did.'"');
        $this->db->query('UPDATE '.API_DBTABLEPRE.'multiscreen_display SET status=1 WHERE did="'.$did.'"');

        return true;
    }

    function drop_display($did) {
        $this->db->query('DELETE a,b FROM '.API_DBTABLEPRE.'multiscreen_display a LEFT JOIN '.API_DBTABLEPRE.'multiscreen_display_category b ON a.did=b.did WHERE a.did="'.$did.'"');

        return true;
    }
}