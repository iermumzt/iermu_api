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
        $activity = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'activity_info WHERE platform="2" AND status="'.$status.'" AND lang="'.API_LANGUAGE.'" AND starttime<'.$this->base->time.' AND endtime>'.$this->base->time);
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

    function get_banner_list($status, $list_type, $page, $count) {
        $sql = API_DBTABLEPRE.'banner_info WHERE status="'.$status.'" AND lang="'.API_LANGUAGE.'"';
        if ($list_type == 'page') {
            $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.$sql);
            $pages = $this->base->page_get_page($page, $count, $total);

            $sql .= ' LIMIT '.$pages['start'].','.$count;
            $result['page'] = $pages['page'];
        }

        $banner = $this->db->fetch_all('SELECT bid,title,weburl,`option`,type FROM '.$sql);
        $n = count($banner);
        for ($i = 0; $i < $n; $i++) { 
            $banner[$i]['files'] = $this->get_banner_file_by_bid($banner[$i]['bid'], $status);
        }

        $result['count'] = $n;
        $result['list'] = $banner;

        return $result;
    }

    function get_banner_file_by_bid($bid, $status) {
        $files = array();

        if ($bid) {
            $baseurl = $status ? API_QINIU_IMG_BASEURL : API_TEST_IMG_BASEURL;
            $arr = $this->db->fetch_all('SELECT * FROM '.API_DBTABLEPRE.'banner_file WHERE bid="'.$bid.'"');
            for ($i = 0, $n = count($arr); $i < $n; $i++) { 
                $type = $arr[$i]['type'];
                $url = $baseurl . $arr[$i]['pathname'] . $arr[$i]['filename'];

                $files[] = array('type' => $type, 'url' => $url);
            }
        }
        
        return $files;
    }

    function get_article_info($aid) {
        return $this->db->fetch_first('SELECT title,content,`option`,lastupdate AS time FROM '.API_DBTABLEPRE.'article_info WHERE aid="'.$aid.'" AND status>=1 AND lang="'.API_LANGUAGE.'"');
    }

    function get_business_article_info($rid, $sn, $sign) {
        $record = $this->db->fetch_first('SELECT a.deviceid,a.uid,a.`option`,a.lastupdate,b.notification_templateid FROM '.API_DBTABLEPRE.'device_business_record a LEFT JOIN '.API_DBTABLEPRE.'device_business_activity b ON a.aid=b.aid WHERE a.rid="'.$rid.'" AND a.sn="'.$sn.'" AND a.status>=1 AND b.notification_type="10"');
        if (!$record || md5($record['deviceid'].$record['uid']) !== $sign)
            return false;

        $article = $this->db->fetch_first('SELECT title,content FROM '.API_DBTABLEPRE.'notification_template WHERE tid="'.$record['notification_templateid'].'" AND status>=1 AND lang="'.API_LANGUAGE.'"');
        if (!$article)
            return false;

        if (!$record['option']) {
            $link = $this->shortLink('https://www.wangxinlicai.com/user/register?type=h5&client_id=db6c30dddd42e4343c82713e&redirect_uri=http%3A%2F%2Fiermu.wangxinlicai.com%2Faccount%2Flogin%3Ffrom_register%3D1&euid='.$sn);
            $option = json_encode(array('link'=>$link));

            $this->db->query('UPDATE '.API_DBTABLEPRE.'device_business_record SET `option`=\''.$option.'\',lastupdate="'.$this->base->time.'" WHERE rid="'.$rid.'"');

            $article['option'] = $option;
            $article['time'] = $this->base->time;
        } else {
            $article['option'] = $record['option'];
            $article['time'] = $record['lastupdate'];
        }

        return $article;
    }

    function shortLink($link) {
        $wconnect = $this->base->load_connect(API_WEIXIN_CONNECT_TYPE);
        
        if ($wconnect) {
            $wtoken = $wconnect->get_weixin_token();

            if ($wtoken && $wtoken['access_token']) {
                $ch = curl_init();
        
                $curl_opts = array(
                    CURLOPT_CONNECTTIMEOUT  => 3,
                    CURLOPT_TIMEOUT         => 20,
                    CURLOPT_USERAGENT       => 'iermu api server/1.0',
                    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_HEADER          => false,
                    CURLOPT_FOLLOWLOCATION  => false,
                    CURLOPT_URL             => 'https://api.weixin.qq.com/cgi-bin/shorturl?access_token='.$wtoken['access_token'],
                    CURLOPT_POSTFIELDS      => json_encode(array('action'=>'long2short', 'long_url'=>$link))
                );

                curl_setopt_array($ch, $curl_opts);
                $result = curl_exec($ch);
                curl_close($ch);

                if (($data = json_decode($result, true)) !== false && !empty($data['short_url']))
                    return $data['short_url'];
            }
        }

        return $link;
    }
}