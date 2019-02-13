<?php

!defined('IN_API') && exit('Access Denied');

class usermodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->usermodel($base);
    }

    function usermodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    function get_user_by_uid($uid) {
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid='$uid'");
        return $arr;
    }

    function get_user_by_username($username) {
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE username='$username'");
        return $arr;
    }

    function get_user_by_domain($domain, $username) {
    	$user_id=$this->db->fetch_first("SELECT a.uid FROM ".API_DBTABLEPRE."member_connect a LEFT JOIN ".API_DBTABLEPRE."org b ON a.connect_type=b.connect_type WHERE b.domain='$domain' AND a.connect_uid='$username'");
        if(!$user_id)
            return FALSE;
        $user = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid=".$user_id['uid']);
		return $user;
    }

    function get_user_by_email($email) {
        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE email='$email'");
        return $arr;
    }
    
	function get_user_by_mobile($mobile, $countrycode='') {
        if(!$countrycode) $countrycode = '+86';
		$arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE mobile='$mobile' AND countrycode='$countrycode'");
		return $arr;
	}

    // 检查是否是系统管理员
    function get_admin_by_uid($uid) {
        $arr = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'admins WHERE uid="'.$uid.'"');
        return $arr;
    }

    // 检查是否是开发者
    function get_dev_by_uid($uid) {
        $arr = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'member_dev WHERE uid="'.$uid.'"');
        return $arr;
    }
    
    function check_username($username) {
        if(strtolower(API_CHARSET) != 'utf-8')
            return false;
    
        $len = $this->dstrlen($username, 1);
        if($len > 20 || $len < 4 || !preg_match("/^[\x{0800}-\x{4e00}\x{4e00}-\x{9fa5}A-Za-z]{1}[\x{0800}-\x{4e00}\x{4e00}-\x{9fa5}A-Za-z0-9\._]+$/u", $username)) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function dstrlen($str, $dscale=1) {
        $count = 0;
        for($i = 0; $i < strlen($str); $i++){
            $start = $i;
            $size = 1;
        
            $value = ord($str[$i]);
            if($value > 127) {
                $count += ($dscale - 1);
                if($value >= 192 && $value <= 223) {
                    $i++;
                    $size = 2;
                } elseif($value >= 224 && $value <= 239) {
                    $i = $i + 2;
                    $size = 3;
                } elseif($value >= 240 && $value <= 247) {
                    $i = $i + 3;
                    $size = 4;
                }
                
            }
        
            $c = substr($str, $start, $size);
            if(preg_match("/^[\x{4e00}-\x{9fa5}]{1}$/u", $c)) {
                $count++;
            }
        
            $count++;
        }
        return $count;
    }

    function check_mergeuser($username) {
        return '';
    }

    function check_usernamecensor($username) {
        //$_CACHE['badwords'] = $this->base->cache('badwords');
        $badwords = $this->get_badwords();
        $censorusername = $this->base->get_api_setting('censorusername');
        $censorusername = $censorusername['censorusername'];
        $censorexp = '/^('.str_replace(array('\\*', "\r\n", ' '), array('.*', '|', ''), preg_quote(($censorusername = trim($censorusername)), '/')).')$/i';
        $usernamereplaced = isset($badwords['findpattern']) && !empty($badwords['findpattern']) ? @preg_replace($badwords['findpattern'], $badwords['replace'], $username) : $username;
        if(($usernamereplaced != $username) || ($censorusername && preg_match($censorexp, $username))) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function check_usernameexists($username) {
        $data = $this->db->result_first("SELECT username FROM ".API_DBTABLEPRE."members WHERE (username='$username' OR email='$username')");
        return $data;
    }

    function check_emailformat($email) {
        return strlen($email) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $email);
    }

    function check_emailaccess($email) {
        $setting = $this->base->get_api_setting(array('accessemail', 'censoremail'));
        $accessemail = $setting['accessemail'];
        $censoremail = $setting['censoremail'];
        $accessexp = '/('.str_replace("\r\n", '|', preg_quote(trim($accessemail), '/')).')$/i';
        $censorexp = '/('.str_replace("\r\n", '|', preg_quote(trim($censoremail), '/')).')$/i';
        if($accessemail || $censoremail) {
            if(($accessemail && !preg_match($accessexp, $email)) || ($censoremail && preg_match($censorexp, $email))) {
                return FALSE;
            } else {
                return TRUE;
            }
        } else {
            return TRUE;
        }
    }

    function check_emailexists($email, $username = '') {
        $sqladd = $username !== '' ? "AND username<>'$username'" : '';
        $email = $this->db->result_first("SELECT email FROM  ".API_DBTABLEPRE."members WHERE (email='$email' OR username='$email') $sqladd");
        return $email;
    }

    function check_login($username, $password, &$user) {
        $user = $this->get_user_by_username($username);
        if(empty($user['username'])) {
            return -1;
        } elseif($user['password'] != md5(md5($password).$user['salt'])) {
            return -2;
        }
        return $user['uid'];
    }

    function add_user($username, $password, $email, $countrycode='', $mobile='', $uid = 0, $questionid = '', $answer = '', $regip = '') {
        $regip = empty($regip) ? $this->base->onlineip : $regip;//获取ip
        $salt = substr(uniqid(rand()), -6);
        if($password) {
            $password = md5(md5($password).$salt);
            $pwdstatus = 1;
        } else {
            $password = '';
            $pwdstatus = 0;
        }
        $sqladd = $uid ? "uid='".intval($uid)."'," : '';
        $sqladd .= $questionid > 0 ? " secques='".$this->quescrypt($questionid, $answer)."'," : " secques='',";
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."members SET $sqladd username='$username', password='$password', pwdstatus='$pwdstatus', email='$email', countrycode='$countrycode', mobile='$mobile', regip='$regip', regdate='".$this->base->time."', salt='$salt'");
        $uid = $this->db->insert_id();
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."memberfields SET uid='$uid'");
        return $uid;
    }
    
    function change_password($uid, $password) {
        if(!$uid || !$password) return false;
        $user = $this->get_user_by_uid($uid);
        if(!$user) return false;
        $salt = $user['salt'];
        $password = md5(md5($password).$salt);
        $this->db->query("UPDATE ".API_DBTABLEPRE."members SET password='$password', pwdstatus=1 WHERE uid='$uid'");
        
        // 重置Token信息
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."oauth_access_token WHERE uid='$uid' AND expires<'".$this->base->time."'");
        $this->db->query("UPDATE ".API_DBTABLEPRE."oauth_access_token SET status='-1' WHERE uid='$uid' AND status>0");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."oauth_refresh_token WHERE uid='$uid'");
        
        return $uid;
    }

    function check_passwordformat($password) {
        return preg_match('/^[\x21-\x7e]{6,20}$/', $password);
    }

    function check_passwordstrength($password) {
        if (preg_match('/^[0-9]{6,8}$/', $password))
            return false;

        preg_match('/([\x21-\x7e]{2})\1{2,}|([\x21-\x7e]{3})\2{1,}/', $password, $matches);
        if (!$matches)
            return true;

        $n = count($matches);
        $temp = $matches[$n - 1];
        return !$this->isSerial($temp);
    }

    function isSerial($str) {
        if (preg_match('/^(?:[\x30-\x39]+|[\x41-\x5e]+|[\x61-\x7e]+)$/', $str))
            return false;

        $diff = ord($str[0]) - ord($str[1]);
        if (abs($diff) > 1)
            return false;

        $len = strlen($str);
        $serial = 2;
        for ($i = 1, $n = $len - 1; $i < $n; $i++) { 
            if (ord($str[$i]) - ord($str[$i + 1]) !== $diff)
                break;

            $serial++;
        }

        return ($len === $serial);
    }

    function get_total_num($sqladd = '') {
        $data = $this->db->result_first("SELECT COUNT(*) FROM ".API_DBTABLEPRE."members $sqladd");
        return $data;
    }   

    function get_list($page, $ppp, $totalnum, $sqladd) {
        $start = $this->base->page_get_start($page, $ppp, $totalnum);
        $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."members $sqladd LIMIT $start, $ppp");
        return $data;
    }

    function name2id($usernamesarr) {
        $usernamesarr = daddslashes($usernamesarr, 1, TRUE);
        $usernames = $this->base->implode($usernamesarr);
        $query = $this->db->query("SELECT uid FROM ".API_DBTABLEPRE."members WHERE username IN($usernames)");
        $arr = array();
        while($user = $this->db->fetch_array($query)) {
            $arr[] = $user['uid'];
        }
        return $arr;
    }

    function id2name($uidarr) {
        $arr = array();
        $query = $this->db->query("SELECT uid, username FROM ".API_DBTABLEPRE."members WHERE uid IN (".$this->base->implode($uidarr).")");
        while($user = $this->db->fetch_array($query)) {
            $arr[$user['uid']] = $user['username'];
        }
        return $arr;
    }

    function quescrypt($questionid, $answer) {
        return $questionid > 0 && $answer != '' ? substr(md5($answer.md5($questionid)), 16, 8) : '';
    }

    function get_badwords() {
        $data = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."badwords");
        $return = array();
        if(is_array($data)) {
            foreach($data as $k => $v) {
                $return['findpattern'][$k] = $v['findpattern'];
                $return['replace'][$k] = $v['replacement'];
            }
        }
        return $return;
    }
    
    //获取用户的头像
    function get_avatar($uid) {
        $user = $this->get_user_by_uid($uid);
        if($user) {
            if($user['avatar'])
                return API_USER_AVATAR_API.'avatar/'.$uid.'/'.$user['avatar'];
            
            if($user['connect_avatar'])
                return $user['connect_avatar'];
                    
            $connect = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE uid='$uid' AND avatar<>'' ORDER BY lastupdate DESC");
            if($connect) {
                $connect_type = $connect['connect_type'];
                if($connect_type > 0) {
                    $client = $this->base->load_connect($connect_type);
                    $connect_avatar = $client->avatar($connect['avatar']);
                    if($connect_avatar) {
                        $this->db->query("UPDATE ".API_DBTABLEPRE."members SET connect_avatar='$connect_avatar' WHERE uid='$uid'");
                        return $connect_avatar;
                    }
                }
            }
        }
        
        return API_USER_AVATAR_API.'avatar/noupload.jpg';
    }
    
    //获取系统时间
    function get_servertime() {
        return gmdate('Y-m-d H:i:s \U\T\CP');
        //return "2023-02-27 04:07:37 GMT";
    }
    
    //获取用户的信息
    function _format_user($uid) {
        $user = array();

        $arr = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."members WHERE uid='$uid'");
		if(!$arr) {
			$username = $email = $countrycode = $mobile = $emailstatus = $mobilestatus = $avatarstatus = $pwdstatus = '';
		} else {
			$username = $arr['username'];
			$email = $arr['email'];
            $countrycode = $arr['countrycode'];
			$mobile = $arr['mobile'];
			$emailstatus = $arr['emailstatus'];
			$mobilestatus = $arr['mobilestatus'];
			$avatarstatus = $arr['avatarstatus'];
            $pwdstatus = $arr['pwdstatus'];
		}
        
		$user = array(
			'uid' => strval($uid),
			'username' => strval($username),
            'email' => $this->_format_email($email),
            'countrycode' => strval($countrycode),
			'mobile' => $this->_format_mobile($mobile),
			'avatar' => $this->get_avatar($uid),
			'emailstatus' => strval($emailstatus),
			'mobilestatus' => strval($mobilestatus),
			'avatarstatus' => strval($avatarstatus),
            'pwdstatus' => strval($pwdstatus)
		);

        // 组织用户
        $domain = $this->db->result_first("SELECT o.domain FROM ".API_DBTABLEPRE."org o LEFT JOIN ".API_DBTABLEPRE."member_org m ON o.org_id=m.org_id WHERE m.uid='$uid'");
        if($domain) $user['domain'] = strval($domain);

		return $user;
    }
    
    function _check_sign($sign, $data, $client_id, $expire) {
        if(API_AUTH_TYPE == 'token') {
            if(!$sign || !$data || !$client_id || !$expire)
                return false;
        
            $client_secret = $this->db->result_first("SELECT client_secret FROM ".API_DBTABLEPRE."oauth_client WHERE client_id='$client_id'");
            if($client_secret && $sign == md5($data.$client_secret.$expire)) {
                return true;
            }
        } else if(API_AUTH_TYPE == 'cookie') {
            return $this->base->clientcheck();
        }
        
        return false;
    }
    
    function get_user_by_connect_uid($connect_type, $connect_uid) {
        $uid = $this->db->result_first("SELECT uid FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='$connect_type' AND connect_uid='$connect_uid'");
        return $uid ? $this->get_user_by_uid($uid) : array();
    }
    
    function get_connect_by_connect_uid($connect_type, $connect_uid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='$connect_type' AND connect_uid='$connect_uid'");
    }
    
    function get_connect_by_uid($connect_type, $uid) {
        return $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."member_connect WHERE connect_type='$connect_type' AND uid='$uid'");
    }
    
    function add_connect_user($uid, $connect_type, $connect_token) {
        $expires = $this->base->time + intval($connect_token['expires_in']);
        $data = serialize($connect_token);
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_connect SET uid='$uid', connect_type='$connect_type', connect_uid='".$connect_token['uid']."', username='".$connect_token['uname']."', avatar='".$connect_token['portrait']."', access_token='".$connect_token['access_token']."', refresh_token='".$connect_token['refresh_token']."', expires='$expires', data='$data', dateline='".$this->base->time."', lastupdate='".$this->base->time."', status='1'");
    }
    
    function update_connect_user($uid, $connect_type, $connect_token) {
        $connect_uid = $connect_token['uid'];
        $expires = $this->base->time + intval($connect_token['expires_in']);
        $data = serialize($connect_token);
        $this->db->query("UPDATE ".API_DBTABLEPRE."member_connect SET username='".$connect_token['uname']."', avatar='".$connect_token['portrait']."', access_token='".$connect_token['access_token']."', refresh_token='".$connect_token['refresh_token']."', expires='$expires', data='$data', lastupdate='".$this->base->time."', status='1' WHERE uid='$uid' AND connect_type='$connect_type' AND connect_uid='$connect_uid'");
    }

    // 分享排行榜
    function sharecharts($page, $count) {
        $list = $this->db->fetch_all('SELECT a.*,(@rank:=@rank+1) AS rank FROM (SELECT c.uid,d.username,COUNT(c.deviceid) AS sharenum FROM '.API_DBTABLEPRE.'device_share c LEFT JOIN '.API_DBTABLEPRE.'members d ON c.uid=d.uid JOIN '.API_DBTABLEPRE.'device e ON c.deviceid=e.deviceid WHERE c.share_type&0x1!=0 AND e.status&0x4!=0 AND e.reportstatus=0 GROUP BY c.uid ORDER BY sharenum DESC) a,(SELECT @rank:=0) b');

        $total_count = count($list);
        $page = $this->base->page_get_page($page, $count, $total_count);
        $list = array_slice($list, $page['start'], $count);

        $result = array();
        $result['page'] = $page['page'];
        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    // 获取用户分类列表
    function list_category($ctype, $uid) {
        switch ($ctype) {
            case 0:
                $list = $this->db->fetch_all('SELECT a.cid,IFNULL(d.title,a.title) AS category FROM '.API_DBTABLEPRE.'category a LEFT JOIN (SELECT b.cid,COUNT(*) AS total FROM '.API_DBTABLEPRE.'device_category b GROUP BY b.cid) c ON a.cid=c.cid LEFT JOIN '.API_DBTABLEPRE.'category_lang d ON a.cid=d.cid AND d.lang="'.API_LANGUAGE.'" WHERE a.ctype="0" AND c.total>0 ORDER BY a.recommend DESC');
                break;
            
            default:
                $list = $this->db->fetch_all('SELECT cid,title AS category FROM '.API_DBTABLEPRE.'category WHERE ctype="1" AND uid="'.$uid.'"');
                break;
        }
        
        $result = array();
        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    // 获取用户分类id
    function get_cid_by_category($uid, $category) {
        $cid = $this->db->result_first('SELECT cid FROM '.API_DBTABLEPRE.'category WHERE uid="'.$uid.'" AND ctype="1" AND title="'.$category.'"');
        if (!$cid)
            $cid = 0;

        return $cid;
    }

    // 添加用户分类
    function add_category($uid, $category) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'category SET uid="'.$uid.'", ctype="1", title="'.$category.'", dateline="'.$this->base->time.'", lastupdate="'.$this->base->time.'"');
        $cid = $this->db->insert_id();
        if (!$cid)
            $cid = 0;

        return $cid;
    }

    // 删除用户分类
    function drop_category($uid, $cid) {
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'category WHERE cid="'.$cid.'" AND ctype="1" AND uid="'.$uid.'"');

        return true;
    }

    // 检查用户分类
    function check_category($uid, $cid) {
        $result = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'category WHERE cid="'.$cid.'" AND ctype="1" AND uid="'.$uid.'"');
        if(!$result)
            return false;

        return true;
    }

    // 更新用户分类名称
    function update_category_by_cid($cid, $category) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'category SET title="'.$category.'" WHERE cid="'.$cid.'"');

        return true;
    }

    // 猜你喜欢接口
    function guess_user($support_type, $sharenum, $page, $count) {
        $this->base->load('device');

        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'members');
        $offset = rand(1, floor($total/$count));

        $page = $this->base->page_get_page($offset, $count, $total);

        $num = 0;
        $list = array();
        $query = $this->db->query('SELECT uid,username FROM '.API_DBTABLEPRE.'members LIMIT '.$page['start'].','.$count);
        while($data = $this->db->fetch_array($query)) {
            $data['avatar'] = $this->get_avatar($data['uid']);
            $share_list = $_ENV['device']->get_share_by_uid($data['uid'], $support_type, $sharenum);

            $num++;
            $list[] = array_merge($data, $share_list);
        }

        $result['page'] = $page['page'];
        $result['count'] = $num;
        $result['list'] = $list;
        return $result;
    }

    // 搜索用户列表
    function search_user($keyword, $sharenum, $page, $count) {
        $this->base->load('search');
        $this->base->load('device');

        $list = array();
        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'members WHERE username LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%"');
        $page = $this->base->page_get_page($page, $count, $total);
        $limit = 'LIMIT '.$page['start'].', '.$count;

        $query = $this->db->query('SELECT uid,REPLACE(username,"'.$keyword.'","<span class=hl_keywords>'.$keyword.'</span>") AS username FROM '.API_DBTABLEPRE.'members WHERE username LIKE "%'.$_ENV['search']->_parse_keyword($keyword).'%" '.$limit);
        while($data = $this->db->fetch_array($query)) {
            $data['avatar'] = $this->get_avatar($data['uid']);
            $share_list = $_ENV['device']->get_share_by_uid($data['uid'], $support_type, $sharenum);

            $list[] = array_merge($data, $share_list);
        }

        $result = array();
        $result['page'] = $page['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    // 获取用户评论列表
    function list_comment($uk, $list_type, $page, $count, $st, $et) {
        $result = array();

        $table = API_DBTABLEPRE.'home_comment a LEFT JOIN '.API_DBTABLEPRE.'members b ON a.uid=b.uid';
        $where = ' WHERE a.delstatus=0 AND a.uk="'.$uk.'"';

        if($list_type === 'timeline') {
            if($st) $where .= ' AND a.dateline>'.$st;
            if($et) $where .= ' AND a.dateline<'.$et;
        }

        $total = $this->db->result_first('SELECT count(*) FROM '.$table.$where);

        if($list_type === 'page') {
            $page = $this->base->page_get_page($page, $count, $total);

            $start = $page['start'];
            $result['page'] = $page['page'];
        } else {
            $start = 0;
        }

        $limit = ' LIMIT '.$start.','.$count;
        $orderbysql = ' ORDER BY a.dateline DESC';

        $list = array();
        if($total) {
            $query = $this->db->query('SELECT a.cid,a.reply_cid,a.parent_cid,a.uid,a.ip,a.comment,a.dateline FROM '.$table.$where.$orderbysql.$limit);
            while($data = $this->db->fetch_array($query)) {
                $user = $this->_format_user($data['uid']);

                $data['username'] = $user['username'];
                $data['avatar'] = $user['avatar'];

                $list[] = $data;
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;

        return $result;
    }

    // 保存用户评论
    function save_comment($uid, $uk, $comment, $parent_cid, $client_id, $appid) {
        $reply_cid = 0;
        if($parent_cid) {
            $reply_cid = $this->db->result_first("SELECT reply_cid FROM ".API_DBTABLEPRE."home_comment WHERE cid='$parent_cid'");
            if(!$reply_cid) $reply_cid = $parent_cid;
        }
        
        $regip = $this->base->onlineip;
        $regtime = $this->base->time;
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'home_comment SET uid="'.$uid.'", uk="'.$uk.'", comment="'.$comment.'", reply_cid="'.$reply_cid.'", parent_cid="'.$parent_cid.'", ip="'.$regip.'", dateline="'.$regtime.'", lastupdate="'.$regtime.'", client_id="'.$client_id.'", appid="'.$appid.'"');

        $result = array();
        $result['comment'] = array(
            'cid' => $this->db->insert_id(),
            'reply_cid' => strval($reply_cid),
            'parent_cid' => strval($parent_cid),
            'ip' => $regip,
            'comment' => $comment,
            'dateline' => $regtime
        );

        $result['commentnum'] = $this->add_comment($uk);

        return $result;
    }

    // 增加用户评论数
    function add_comment($uk) {
        $commentnum = $this->db->result_first('SELECT commentnum FROM '.API_DBTABLEPRE.'members WHERE uid="'.$uk.'"');

        $this->db->query('UPDATE '.API_DBTABLEPRE.'members SET commentnum='.(++$commentnum).' WHERE uid="'.$uk.'"');

        return $commentnum;
    }

    // 更新用户名
    function update($uid, $username) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'members SET username="'.$username.'" WHERE uid="'.$uid.'"');
        return true;
    }

    // 补全用户资料
    function complete($uid, $username, $email, $password, $salt) {
        if($password) {
            $password = md5(md5($password).$salt);
            $pwdstatus = 1;
        } else {
            $password = '';
            $pwdstatus = 0;
        }
        $this->db->query('UPDATE '.API_DBTABLEPRE.'members SET username="'.$username.'",email="'.$email.'",password="'.$password.'",pwdstatus="'.$pwdstatus.'",salt="'.$salt.'" WHERE uid="'.$uid.'"');
        return true;
    }

    // 获取访客记录列表
    function list_view($uk, $appid, $page, $count) {
        $total = $this->db->result_first('SELECT count(*) FROM '.API_DBTABLEPRE.'home_view WHERE uk="'.$uk.'"');
        $page = $this->base->page_get_page($page, $count, $total);

        $list = array();
        if($total) {
            $data = $this->db->fetch_all('SELECT uid,lastupdate FROM '.API_DBTABLEPRE.'home_view WHERE uk="'.$uk.'" AND delstatus=0 ORDER BY lastupdate DESC LIMIT '.$page['start'].', '.$count);
            foreach($data as $value) {
                $user = $this->_format_user($value['uid']);
                $share = array(
                    'uid' => $user['uid'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                    'lastupdate' => $value['lastupdate']
                );
                $list[] = $share;
            }
        }

        $result = array();
        $result['page'] = $page['page'];
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    // 保存访客记录
    function save_view($uid, $uk, $client_id, $appid) {
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'home_view SET uid="'.$uid.'",uk="'.$uk.'",num=1,delstatus=0,ip="'.$this->base->onlineip.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'",client_id="'.$client_id.'",appid="'.$appid.'" ON DUPLICATE KEY UPDATE num=num+1,delstatus=0,ip="'.$this->base->onlineip.'",lastupdate="'.$this->base->time.'",client_id="'.$client_id.'",appid="'.$appid.'"');

        return true;
    }

    // 增加访客记录
    function add_view($uk) {
        $viewnum = $this->db->result_first('SELECT viewnum FROM '.API_DBTABLEPRE.'members WHERE uid="'.$uk.'"');

        $this->db->query('UPDATE '.API_DBTABLEPRE.'members SET viewnum='.(++$viewnum).' WHERE uid="'.$uk.'"');

        $result = array();
        $result['uk'] = $uk;
        $result['viewnum'] = $viewnum;

        return $result;
    }

    // 删除访客记录列表
    function drop_view($uid, $list) {
        $num = 0;
        $uk_list = array();
        foreach ($list as $value) {
            if($value) {
                if(!$this->drop_view_by_pk($uid, $value))
                    return false;

                $num++;
                $uk_list[] = $value;
            }
        }

        $result = array();
        $result['count'] = $num;
        $result['list'] = $uk_list;
        return $result;
    }

    // 删除访客记录
    function drop_view_by_pk($uid, $uk) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'home_view SET delstatus=1 WHERE `uid`="'.$uid.'" AND `uk`="'.$uk.'"');
        return true;
    }
    
    function _format_email($email) {
        $pos = strpos($email, '@');
        if($pos) {
            $pre = substr($email, 0, $pos);
            $sub = substr($email, $pos);
            if(strlen($pre) < 4) {
                $email = substr($pre, 0, 2).'...';
            } else {
                $email = substr($pre, 0, 2).'...'.substr($pre, -1);
            }
            $email .= $sub;
        }
        return $email;
    }
    
    function _format_mobile($mobile) {
        if(!$mobile) return '';
        return substr($mobile, 0, 3).'*****'.substr($mobile, -3);
    }

    function get_cctv_identity_card($uid) {
        if (!$uid)
            return false;

        $user = $this->db->fetch_first('SELECT uid FROM '.API_DBTABLEPRE.'cctv_user WHERE uid="'.$uid.'"');
        if (!$user)
            return false;

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'cctv_user_login SET uid="'.$uid.'",loginip="'.$this->base->onlineip.'",logintime="'.$this->base->time.'"');

        return $user;
    }
    
    function check_countrycode($countrycode) {
        $supprot = array('+86');
        return in_array($countrycode, $supprot)?true:false;
    }
    
    function check_mobileformat($countrycode, $mobile) {
        if($countrycode == '+86') {
            if(strlen($mobile) == 11 && preg_match("/^1[34578]\d{9}$/", $mobile)) return true;
        }
        return false;
    }

    function check_mobileaccess($countrycode, $mobile) {
        return true;
    }

    function check_mobileexists($countrycode, $mobile, $uid) {
        $sqladd = $uid ? "AND uid<>'$uid'" : '';
        $mobile = $this->db->result_first("SELECT mobile FROM  ".API_DBTABLEPRE."members WHERE countrycode='$countrycode' AND mobile='$mobile' $sqladd");
        return $mobile;
    }
    
    function lastsendverifytime($uid, $type, $send, $form, $countrycode, $mobile, $email, $retry_in=60) {
        if(!$send || !in_array($send, array('email', 'sms')))
            return false;
        
        if($send == 'email' && !$email)
            return false;
        
        if($send == 'sms' && !$mobile)
            return false;
        
        if($this->base->redis && $this->base->redlock) {
            if($send == 'sms') {
                $t_key = API_RDKEYPRE.'sendverify_sms_'.$countrycode.'_'.$mobile.'_lasttime';
            } elseif($send == 'email') {
                $t_key = API_RDKEYPRE.'sendverify_email_'.$email.'_lasttime';
            }
            
            $t_lock = $t_key.'_lock';
            
            $retry = 10;
            while($retry > 0) {
                $this->base->log('lastsendverifytime', 'lock='.$t_lock.' retry='.$retry);
                $lock = $this->base->redlock->lock($t_lock, 1000);
                if($lock) break;
                $retry--;
                usleep(200000);
                continue;
            }
        
            if(!$lock) 
                return false;
            
            $this->base->log('lastsendverifytime', 'lock '.$t_lock.' success');
            
            $lastverifytime = $this->base->redis->get($t_key);
            
            if($lastverifytime === false) {
                $lastverifytime = 0;
            }
            
            $this->base->log('lastsendverifytime', 'lastverifytime='.$lastverifytime);
            
            if($lastverifytime + $retry_in <= $this->base->time) {
                $this->base->redis->set($t_key, $this->base->time);
                $this->base->log('lastsendverifytime', 'update='.$this->base->time);
            }
            
            // 解除锁
            $this->base->redlock->unlock($lock);
        
            $this->base->log('lastsendverifytime', 'unlock '.$t_lock.' success');
        } else {
            $lastverifytime = $this->db->result_first("SELECT dateline FROM  ".API_DBTABLEPRE."member_verifycode WHERE uid='$uid' AND type='$type' AND send='$send'  AND form='$form' AND countrycode='$countrycode' AND mobile='$mobile' AND email='$email' ORDER BY dateline desc");
        }
        return $lastverifytime;
    }

    function sendverifyneedseccode($uid, $type, $send, $form, $countrycode, $mobile, $email) {
        if(!$send || !in_array($send, array('email', 'sms')))
            return false;
        
        if($send !== 'sms' && !$mobile)
            return false;
        
        // 5分钟内大于3次
        $limit = $this->base->time - 300;
        $count = $this->db->result_first("SELECT count(*) FROM  ".API_DBTABLEPRE."member_verifycode WHERE send='sms' AND countrycode='$countrycode' AND mobile='$mobile' AND dateline>'$limit'");
        if($count && $count>3)
            return true;

        return false;
    }
    
    function get_verifycode_by_type($code, $type) {
        return $this->db->fetch_first("SELECT * FROM  ".API_DBTABLEPRE."member_verifycode WHERE code='$code' AND type='$type' AND usedate=0");
    }
    
    function verifycode($uid, $type, $operation, $send, $form, $countrycode, $mobile, $email, $notifyid, $client_id, $appid) {
        if(!$type || !$notifyid) 
            return false;
        
        // 清理过期数据
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."member_verifycode WHERE (expiredate=0 AND usedate>0 AND usedate<'".($this->base->time - 24*3600)."') OR (expiredate>0 AND expiredate<'".($this->base->time - 24*3600)."')");
        
        $retry = 3;
        while($retry > 0) {
            if($form == 'code') {
                $code = mt_rand(100000, 999999);
                $expires_in = 30*60;
                $expiretime = '30分钟';
            } else {
                $code = md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
                if($type == 'resetpwd') {
                    $expires_in = 24*3600;
                    $expiretime = '24小时';
                } else {
                    $expires_in = 0;
                    $expiretime = '';
                }
            }
            
            if($this->get_verifycode_by_type($code, $type)) {
                $retry--;
            } else {
                break;
            }
        }
        
        if(!$code)
            return false;
        
        if($expires_in) {
            $expiredate = $this->base->time + $expires_in;
        } else {
            $expiredate = 0;
        }
        
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_verifycode SET code='$code', type='$type', operation='$operation', uid='$uid', email='$email', countrycode='$countrycode', mobile='$mobile', send='$send', form='$form', notifyid='$notifyid', expiredate='$expiredate', dateline='".$this->base->time."', client_id='$client_id', appid='$appid'");
        $codeid = $this->db->insert_id();
        if(!$codeid)
            return false;
        
        return array(
            'codeid' => $codeid,
            'code' => $code,
            'type' => $type,
            'operation' => $operation,
            'send' => $send,
            'form' => $form,
            'uid' => $uid,
            'email' => $email,
            'countrycode' => $countrycode,
            'mobile' => $mobile,
            'expires_in' => $expires_in,
            'expiretime' => $expiretime
        );
    }
    
    function use_verifycode($codeid) {
        if(!$codeid) return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."member_verifycode SET usedate='".$this->base->time."' WHERE codeid='$codeid'");
    }
    
    function authcode($uid, $operation, $client_id, $appid) {
        if(!$uid || !$operation)
            return false;
        
        // 清理过期数据
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."member_authcode WHERE (expiredate=0 AND usedate>0 AND usedate<'".($this->base->time - 24*3600)."') OR (expiredate>0 AND expiredate<'".($this->base->time - 24*3600)."')");
        
        $retry = 3;
        while($retry > 0) {
            $code = md5(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
            if($this->get_authcode_by_code($uid, $code)) {
                $retry--;
            } else {
                break;
            }
        }
        
        if(!$code)
            return false;
        
        $expires_in = 30*60;
        $expiredate = $this->base->time + $expires_in;
        
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."member_authcode SET code='$code', operation='$operation', uid='$uid', expiredate='$expiredate', dateline='".$this->base->time."', client_id='$client_id', appid='$appid'");
        
        return $this->get_authcode_by_code($uid, $code);
    }
    
    function get_authcode_by_code($uid, $code) {
        return $this->db->fetch_first("SELECT * FROM  ".API_DBTABLEPRE."member_authcode WHERE uid='$uid' AND code='$code'");
    }
    
    function use_authcode($codeid) {
        if(!$codeid) return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."member_authcode SET usedate='".$this->base->time."' WHERE codeid='$codeid'");
    }
    
    function sendverify($user, $type, $operation, $send, $form, $countrycode, $mobile, $email, $client_id, $appid) {
        if(!$type || !$send)
            return false;
        
        $uid = ($user&&$user['uid'])?$user['uid']:0;
        if($send == 'email') {
            $notify_service = $this->base->load_notify(1);
        } else if($send == 'sms') {
            $notify_service = $this->base->load_notify(0, 1);
        }
        
        if(!$notify_service)
            return false;
        
        $notifyid = $notify_service->notifyid;
        
        $verifycode = $this->verifycode($uid, $type, $operation, $send, $form, $countrycode, $mobile, $email, $notifyid, $client_id, $appid);
        if(!$verifycode)
            return false;
        
        $params = array();
        if($send == 'email' && $type == 'updateemail') {
            $params = array(
                'name' => $user['username'],
                'operation' => '绑定本邮箱',
                'code' => $verifycode['code'],
                'expiretime' => $verifycode['expiretime']
            );
        } else if($send == 'email' && $type == 'resetpwd') {
            $params = array(
                'name' => $user['username'],
                'resetpwd_url' => $this->base->url(BASE_PASSPORT.'/resetpwd/reset', array('code' => $verifycode['code']))
            );
        } else if($send == 'email' && $type == 'checkauth') {
            $this->base->log('sendverify', 'operation='.$operation);
            if($operation == 'updateemail') {
                $op = '修改绑定邮箱';
            } else if($operation == 'updatemobile') {
                $op = '修改绑定手机号';
            } else {
                return false;
            }
            
            $params = array(
                'name' => $user['username'],
                'operation' => $op,
                'code' => $verifycode['code'],
                'expiretime' => $verifycode['expiretime']
            );
        } else if($send == 'email' && $type == 'activeemail') {
            $params = array(
                'name' => $user['username'],
                'active_code' => $verifycode['code'],
                'active_url' => $this->base->url(PASSPORT_API.'/account/email/active?code='.$verifycode['code'])
            );
        }
        
        $this->base->log('sendverify', 'params='.json_encode($params));
        
        $ret = $notify_service->sendverify($verifycode, $params);
        if(!$ret)
            return false;
        
        return true;
    }
    
    function update_mobile($uid, $countrycode, $mobile, $mobilestatus) {
        if(!$uid || !$countrycode || !$mobile)
            return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."members SET countrycode='$countrycode', mobile='$mobile', mobilestatus='$mobilestatus' WHERE uid='$uid'");
        return true;
    }
    
    function update_email($uid, $email, $emailstatus) {
        if(!$uid || !$email)
            return false;
        $this->db->query("UPDATE ".API_DBTABLEPRE."members SET email='$email', emailstatus='$emailstatus' WHERE uid='$uid'");
        return true;
    }

    function get_remarkname_by_pk($uid, $uk) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'member_remarkname WHERE uid="'.$uid.'" AND uk="'.$uk.'"');
    }
    
    function save_remarkname($uid, $uk, $remarkname) {
        $arr = $this->get_remarkname_by_pk($uid, $uk);
        if ($arr) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'member_remarkname SET remarkname="'.$remarkname.'",lastupdate="'.$this->base->time.'" WHERE uid="'.$uid.'" AND uk="'.$uk.'"');
        } else {
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'member_remarkname SET uid="'.$uid.'",uk="'.$uk.'",remarkname="'.$remarkname.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');
        }

        return true;
    }

    function drop_remarkname($uid, $uk) {
        $this->db->query('DELETE FROM '.API_DBTABLEPRE.'member_remarkname WHERE uid="'.$uid.'" AND uk="'.$uk.'"');

        return true;
    }

    function check_connect_user($connect_type, $uid) {
        $client = $this->base->load_connect($connect_type);
        if($client) {
            $client->get_connect_info($uid);
        }
        return $this->get_connect_by_uid($connect_type, $uid);
    }
}
