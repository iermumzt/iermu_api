<?php

!defined('IN_API') && exit('Access Denied');

class aifacemodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->aifacemodel($base);
    }

    function aifacemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }


    function face_upload($uid, $file, $black='') {
        if(!$file)
            return false;
        //检测人脸存在
        $dectect_result = "";
        if(!$black){
            $dectect = $this->face_dectect($file);
            if(!$dectect)
                return false;
            $dectect_result = json_encode($dectect);
            $param['age'] = round($dectect['age']);
            $param['beauty'] = round($dectect['beauty']); //'美丑打分， 范围0-100， 越大表示越美'
            $param['expression'] = intval($dectect['expression']); //'表情， 0， 不笑；1， 微笑； 2， 大笑'
            $param['gender'] = strval($dectect['gender'])=="male" ? 1:2; //'性别，1男，2女'
            $param['glasses'] = intval($dectect['glasses']); //'是否带眼镜， 0-无眼镜， 1-普通眼镜， 2-墨镜'
            if($dectect['race']){
                //'种族，yellow1white2black3arabs4
                switch ($dectect['race']) {
                case 'yellow':
                    $param['race'] = 1;
                    break;
                case 'white':
                    $param['race'] = 2;
                    break;
                case 'black':
                    $param['race'] = 3;
                    break;
                case 'arabs':
                    $param['race'] = 4;
                    break;
                default:
                    $param['race'] = 1;
                    break;
                }
            }
        }

        $file_temp = fopen($file, 'rb');
        $stat = fstat($file_temp);
        $size = $stat['size'];
        fclose($file_temp);
        if(!$size)
            $size = 0;

        if($size > 4194304){
            return false;
        }

        
        //人脸注册图片存储
        $storageid = $this->base->get_default_stronge();
        $storage = $this->base->load_storage($storageid);
        
        if (!$storage)
            return false;
        $face = $storage->faceupload($uid, $file, $black);

        if(!$face)
            return false;

        $data = "source_type = '1', source_id ='".$uid."', pathname = '".$face['pathname']."', filename = '".$face['filename']."', size = '".$size."', ai_result = '".$dectect_result."', storageid = '".$storageid."', dateline = '".time()."'";
        if(isset($param['age']))
            $data .= ', age="'.$param['age'].'"';
        if(isset($param['beauty']))
            $data .= ', beauty="'.$param['beauty'].'"';
        if(isset($param['expression']))
            $data .= ', expression="'.$param['expression'].'"';
        if(isset($param['gender']))
            $data .= ', gender="'.$param['gender'].'"';
        if(isset($param['glasses']))
            $data .= ', glasses="'.$param['glasses'].'"';
        if(isset($param['race']))
            $data .= ', race="'.$param['race'].'"';
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_face_image SET $data");
        $imageid = $this->db->insert_id();
        if(!$imageid){
            return false;
        }
        if(substr($face['pathname'], -1)!= "/"){
            $face['pathname'] .= "/";
        }
        $filepath = $face['pathname'].$face['filename'];
        $image_url = $storage->aiface_image($filepath);
        $param['image_id'] = $imageid;
        $param['image_url'] = $image_url;
        return $param;
    }

    function faceregister($imgid, $fname, $remarks, $uid='0', $imgid_black_white, $img_result){
        if(!$imgid || !$fname)
            return false;

        //获取可用用户群租
        $group = $this->get_group();
        if(!$group)
            return false;
        $facepath = $this->get_imagepath_by_imageid($imgid);
        if(!$facepath)
            return false;

        $ai_ret = $this->face_ai_uid($facepath, $fname, $remarks, $imgid_black_white, $imgid);
        if(!$ai_ret)
            return false;
        $ai_user_id = $ai_ret['ai_user_id'];
        $_face_id     = $ai_ret['_face_id'];
        $scores      = $ai_ret['scores'];
        $check = $this->uid_aiuid_check($uid, $ai_user_id);
        if(!$check){
            $result = array(
                'error_code' => '400291',
                'error_msg' => 'the face has registered',
            );
            return $result;
        }

        $this->register_todo($ai_user_id, $imgid, $scores, $fname, $remarks, $_face_id, $group);
        //member 信息
        $ai_id = $this->base->ai_id();
        $data = 'uid="'.$uid.'", image_id="'.$imgid.'", name="'.$fname.'", remark="'.$remarks.'", dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'", cluster = 0, ai_id = "'.$ai_id.'"';
        if($img_result){
            if(isset($img_result['age']))
                $data .= ', age="'.$img_result['age'].'"';

            if(isset($img_result['beauty']))
                $data .= ', beauty="'.$img_result['beauty'].'"';

            if(isset($img_result['expression']))
                $data .= ', expression="'.$img_result['expression'].'"';

            if(isset($img_result['gender']))
                $data .= ', gender="'.$img_result['gender'].'"';

            if(isset($img_result['glasses']))
                $data .= ', glasses="'.$img_result['glasses'].'"';
            
            if(isset($img_result['race']))
                $data .= ', race="'.$img_result['race'].'"';
        }

        $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_member_face SET $data");

        $face_id = $this->db->insert_id();
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_member_face_bind SET face_id="'.$face_id.'", _face_id="'.$_face_id.'"');

        return $this->imageinfo_by_id($face_id);
        
    }
    //更新
    function faceupdate($face_id, $imgid, $fname, $remarks, $uid='0', $imgid_black_white, $img_result){
        if(!$face_id)
            return false;
        $faceinfo = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_member_face WHERE face_id="'.$face_id.'" AND uid="'.$uid.'"');
        if(!$faceinfo)
            return NULL;

        $group = $this->get_group();
        if(!$group)
            return false;

        $data = "lastupdate='".$this->base->time."'";
        $_face_id = '';
        if($imgid){
            $ret = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_member_face WHERE face_id="'.$face_id.'" AND image_id="'.$imgid.'"');
            //新图片 进行识别
            if(!$ret){
                $facepath = $this->get_imagepath_by_imageid($imgid);
                if(!$facepath)
                    return false;

                $ai_ret = $this->face_ai_uid($facepath, $fname, $remarks, $imgid_black_white, $imgid);
                $ai_user_id = $ai_ret['ai_user_id'];
                $_face_id = $ai_ret['_face_id'];
                $scores = $ai_ret['scores'];

                // $_face_id_bind =  $this->db->result_first('SELECT _face_id FROM '.API_DBTABLEPRE.'ai_member_face_bind WHERE face_id="'.$face_id.'"');
                $face_id_bind =  $this->db->result_first('SELECT a.face_id FROM '.API_DBTABLEPRE.'ai_member_face_bind a LEFT JOIN '.API_DBTABLEPRE.'ai_member_face b on b.face_id = a.face_id WHERE a._face_id="'.$_face_id.'" AND b.uid = "'.$uid.'"');
                if($face_id_bind != $face_id){
                    $check = $this->uid_aiuid_check($uid, $ai_user_id);
                    if(!$check){
                        $result = array(
                            'error_code' => '400291',
                            'error_msg' => 'the face has registered',
                        );
                        return $result;
                    }
                    //删除关联
                    $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id='$face_id'");
                }

                $this->register_todo($ai_user_id, $imgid, $scores, $fname, $remarks, $_face_id, $group);
            }
        }

        $strange = $this->db->result_first('SELECT cluster FROM '.API_DBTABLEPRE.'ai_member_face WHERE face_id="'.$face_id.'"');
        if($strange > 0 && $imgid){
            $data .= ", cluster = -1";
        }
        if($strange >0 && !$imgid){
            $data .= ", cluster = 2";
        }
        
        
        if(isset($fname))
            $data .= ", name = '$fname'"; 
        if(isset($remarks))
            $data .= ", remark = '$remarks'";
        if($imgid)
            $data .= ", image_id = '$imgid'";
        if($img_result){
            if(isset($img_result['age']))
                $data .= ', age="'.$img_result['age'].'"';

            if(isset($img_result['beauty']))
                $data .= ', beauty="'.$img_result['beauty'].'"';

            if(isset($img_result['expression']))
                $data .= ', expression="'.$img_result['expression'].'"';

            if(isset($img_result['gender']))
                $data .= ', gender="'.$img_result['gender'].'"';
            
            if(isset($img_result['glasses']))
                $data .= ', glasses="'.$img_result['glasses'].'"';
            
            if(isset($img_result['race']))
                $data .= ', race="'.$img_result['race'].'"';
        }
        if($_face_id && intval($_face_id)>0){
            $ret = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id=$face_id AND _face_id=$_face_id");
            if(!$ret){
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_member_face_bind SET face_id = '$face_id', _face_id = '$_face_id'");
            }
        }

        $result = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face SET $data WHERE face_id = '$face_id'");

        return $this->imageinfo_by_id($face_id);
    }

    //检测是否存在人脸
    function face_dectect($facepath){
        $ai_id = $this->base->ai_id();
        $aiserver = $this->base->load_aiserver($ai_id);

        if (!$aiserver)
            return false;
        //检测是否存在人脸
        $dectect = $aiserver->face_detect($facepath);
        return $dectect;
    }
    //人脸ID 有则返回无则注册
    function face_ai_uid($facepath, $fname, $remark, $imgid_black_white='', $imgid = ""){
        //获取人脸群组
        $group = $this->get_group();
        $group_type = $this->get_group_type();
        $group_all = $this->get_all_group();
        //对比注册ai图片
        $ai_id = $this->base->ai_id();
        $aiserver = $this->base->load_aiserver($ai_id);
        if (!$aiserver)
            return false;
        if(intval($ai_id) == 2){//sensenets
            $facepath = $_FILES["file"]["tmp_name"][0];
        }
        $faceinfo = $aiserver->face_identfy($facepath, $group_all, $group_type);
        //人脸识别分值高于70返回user_id
        if($faceinfo){
            return $faceinfo;
        }

        //注册
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_face VALUES ()");
        $_face_id = $this->db->insert_id();
        $uid = $_face_id;
        $user_info = json_encode(array('name'=>$fname, 'remark'=>$remark));
        $ai_user_id = $aiserver->face_register($facepath, $uid, $user_info, $group, $imgid);
        $scores = 0;
        if($imgid_black_white){
            $this->add_balck_white($imgid_black_white, $ai_user_id, $group, $_face_id);
        }
        return array(
                'ai_user_id' => $ai_user_id,
                '_face_id'     => $_face_id,
                'scores'      => $scores,
            );
    }

    //单个人脸信息
    function faceinfo_by_id($face_id){
        if(!$face_id)
            return false;
        $table = API_DBTABLEPRE.'ai_member_face m LEFT JOIN '.API_DBTABLEPRE.'ai_face_image a ON a.image_id=m.image_id LEFT JOIN '.API_DBTABLEPRE.'ai_face_fileds f on f.face_id=m.face_id';
        $where = 'WHERE m.face_id="'.$face_id.'"';
        $select = 'm.face_id as face_id, a.image_id, m.uid as uid, a.pathname, a.filename, a.storageid, m.name, m.remark, f.id_card, f.area, f.age, f.visits, f.class';

        $value = $this->db->fetch_first("SELECT $select FROM $table $where");
        $result = array();
        if($value){
            if($value['storageid']){
                $storage = $this->base->load_storage($value['storageid']);
                if (!$storage)
                    return false;
                if(substr($value['pathname'], -1)!= "/"){
                    $value['pathname'] .= "/";
                }
                $filepath = $value['pathname'].$value['filename'];
                $result['image_url'] = $storage->aiface_image($filepath);
            }
            $result['face_id'] = $value['face_id'];
            $result['name'] = $value['name']?$value['name']:"";
            $result['remark'] = $value['remark']?$value['remark']:"";
            $result['visits'] = intval($value['visits']);
            $result['id_card'] = $value['id_card'];
            $this->base->load('statistics');
            $result['area'] = $_ENV['statistics']->get_info_by_code($value['area'], 2);
            $result['class'] = $_ENV['statistics']->get_info_by_code($value['class'], 1);
            if($value['age']){
                $result['age'] = $_ENV['statistics']->get_age($value['age']);
            }else{
                $result['age'] = 0;
            }
            
        }
        return $result;
    }

    function facelist($ai_id, $uid, $key, $page, $count, $list_type, $event_push=0, $cluster=0, $st='', $et='', $deviceid=''){
        if(!$uid){
            return false;
        }
        $table = API_DBTABLEPRE.'ai_member_face';
        $where = 'WHERE uid='.$uid;
        $select = 'face_id, image_id, name, remark, event_push, cluster';

        if($ai_id) {
            $where .= " AND ai_id=".$ai_id;
        }
        if($event_push){
            $order = 'ORDER BY name ASC';
            $where .= " AND event_push = 1";
        }else{
            $order = 'ORDER BY concat(dateline,face_id) DESC';
        }
        if($cluster){
            $where .= " AND cluster = 1";
        }else{
            $where .= " AND cluster != 1";
        }
        if ($key !== '') {
            $this->base->load('search');
            $where .= ' AND name LIKE "%'.$_ENV['search']->_parse_keyword($key).'%"';
        }

        //增加聚类筛选功能
        if( ($cluster && ($st || $et)) || $deviceid){
            if($deviceid){
                $deviceid = $deviceid;
            }else{
                $deviceid = "SELECT deviceid from ".API_DBTABLEPRE."device WHERE uid = '$uid'";
            }
            $_face_id = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind m LEFT JOIN ".API_DBTABLEPRE."ai_member_face n on n.face_id=m.face_id WHERE n.uid = '$uid' AND n.cluster =1";
            $wh = "WHERE deviceid in ($deviceid) AND _face_id in ($_face_id)";
            if($st){
                $wh .= " AND time > $st";
                // $where .= " AND dateline > $st";
            }
            if($et){
                $wh .= " AND time < $et";
                // $where .= " AND dateline < $et";
            }
            $ret = $this->db->fetch_all("SELECT _face_id from ".API_DBTABLEPRE."ai_event $wh group by _face_id");
            $face_arr = array();
            foreach ($ret as $k => $v) {
                $_face_id_temp = $v['_face_id'];
                $face_id_temp = $this->db->fetch_all("SELECT face_id from ".API_DBTABLEPRE."ai_member_face_bind WHERE _face_id = $_face_id_temp");
                if(count($face_id_temp) > 0){
                    foreach ($face_id_temp as $v){
                        array_push($face_arr, $v['face_id']);
                    }
                }
            }
            $face_arr = array_unique($face_arr);
            if(count($face_arr)>0){
                $face_str = implode(",",$face_arr);
                $where .= " AND face_id in ($face_str)";
            }else{
                $where .= " AND 1=2";
            }
        }

        $total = $this->db->result_first("SELECT count(*) FROM $table $where");

        $limit = '';
        if($list_type == 'page') {
            $page = $page > 0 ? $page : 1;
            $count = $count > 0 ? $count : 10;

            $pages = $this->base->page_get_page($page, $count, $total);

            $result['page'] = $pages['page'];
            $limit = ' LIMIT '.$pages['start'].', '.$count;
        }

        $list = array();
        if($total){
            $data = $this->db->fetch_all("SELECT $select FROM $table $where $order $limit");
            $this->base->load('statistics');
            foreach($data as $value) {
                $image = array();
                $image['face_id'] = strval($value['face_id']);
                $image["image_url"] = $this->get_imagepath_by_imageid($value["image_id"]);
                $image['name'] = $value['name'] ? $value['name'] : "";
                $image['remark'] = $value['remark'] ? $value['remark'] : "";
                $attr = $this->db->fetch_first("SELECT id_card,area,age,visits,`class` FROM ".API_DBTABLEPRE."ai_face_fileds WHERE face_id = '".$value['face_id']."'");
                
                $image['visits'] = intval($attr['visits']);
                $image['id_card'] = $attr['id_card'];
                $image['area'] = $_ENV['statistics']->get_info_by_code($attr['area'], 2);
                $image['class'] = $_ENV['statistics']->get_info_by_code($attr['class'], 1);
                if($value['age']){
                    $image['age'] = $_ENV['statistics']->get_age($attr['age']);
                }else{
                    $image['age'] = 0;
                }
                $list[] = $image;
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    function facenamelist($uid){
        $result = $this->db->fetch_all("SELECT face_id as face_id,name FROM ".API_DBTABLEPRE."ai_member_face  WHERE uid = $uid");
        $count = count($result);
        $result = array('count' => $count, 'list' => $result );
        return $result;
    }

    function imageinfo_by_id($face_id){
        $table = API_DBTABLEPRE.'ai_member_face m LEFT JOIN '.API_DBTABLEPRE.'ai_face_image a ON a.image_id=m.image_id';
        $where = 'WHERE m.face_id='.$face_id;
        $select = 'm.event_push,m.face_id as face_id, a.image_id, m.uid as uid, a.pathname, a.filename, a.storageid, m.name, m.remark, m.cluster';

        $value = $this->db->fetch_first("SELECT $select FROM $table $where");
        $result = array();
        if($value){
            if($value['storageid']){
                $storage = $this->base->load_storage($value['storageid']);
                if (!$storage)
                    return false;
                if(substr($value['pathname'], -1)!= "/"){
                    $value['pathname'] .= "/";
                }
                $filepath = $value['pathname'].$value['filename'];
                $result['image_url'] = $storage->aiface_image($filepath);
            }
            $result['face_id'] = $value['face_id'];
            // $result['uid'] = $value['uid'];
            $result['event_push'] = intval($value['event_push']);
            if(intval($value['cluster']) > 0){
                $result['cluster'] = intval($value['cluster']);
            }
            $result['name'] = $value['name']?$value['name']:"";
            $result['remark'] = $value['remark']?$value['remark']:"";
        }
        return $result;
    }

    function face_del($face_id, $uid){
        if(!$face_id)
            return false;
        $rets = $this->db->fetch_first('SELECT image_id, uid FROM '.API_DBTABLEPRE.'ai_member_face WHERE face_id="'.$face_id.'" AND uid="'.$uid.'"');
        if(!$rets['image_id']){
            return NULL;
        }
        $_face_id = "SELECT _face_id from ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id = '$face_id'";
        // $_face_id = $rets['_face_id'];
        $deviceid = "SELECT deviceid from ".API_DBTABLEPRE."device WHERE uid = '$uid'";
        $image_id = "SELECT `image_id` from ".API_DBTABLEPRE."ai_event where deviceid in ($deviceid) AND _face_id in ($_face_id)";

        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_face_image WHERE image_id in ($image_id) AND source_type = 0");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_event WHERE deviceid in ($deviceid) AND _face_id in ($_face_id)");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id='$face_id'");
        // $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_face_image WHERE image_id='$image_id' AND source_id = '$uid'");
        // $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_face_user_image WHERE image_id='$image_id'");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id='$face_id'");
        return array(
                'face_id' => strval($face_id),
            );

    }

    function register_todo($ai_user_id, $imgid, $scores, $name, $remark, $_face_id, $group){
        $scores = $scores * 100;

        $ai_id = $this->base->ai_id();
        $ai_service = $this->base->get_ai_service($ai_id);
        if($ai_service){
            $ai_type = $ai_service['ai_type'];
        }else{
            $ai_type = "baidu";
        }

        $data = "ai_id = '$ai_id', ai_type ='$ai_type', ai_user_id = '".$ai_user_id."', ai_user_score = '".$scores."', _face_id = '$_face_id'";
        $this->db->query("UPDATE ".API_DBTABLEPRE."ai_face_image SET $data WHERE image_id = '".$imgid."'");
        //@todo update or insert
        $rets = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_user WHERE user_id="'.$ai_user_id.'" AND _face_id="'.$_face_id.'" AND ai_id = "'.$ai_id.'"');
        if($rets){
            $this->db->query("UPDATE ".API_DBTABLEPRE."ai_face_user SET lastupdate='".$this->base->time."' WHERE _face_id = '$_face_id' AND user_id = '$ai_user_id' AND ai_id = '$ai_id'");
        }else{
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user SET user_id="'.$ai_user_id.'", _face_id="'.$_face_id.'", ai_id= "'.$ai_id.'" , ai_type="'.$ai_type.'", dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');
        }

        //bai_user_image
        $ret = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_user_image WHERE image_id="'.$imgid.'" AND user_id= "'.$ai_user_id.'"');
        if(!$ret){
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user_image SET user_id="'.$ai_user_id.'", image_id="'.$imgid.'", dateline="'.$this->base->time.'"');
        }

        //分组信息
        if(!$this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_group_user WHERE user_id="'.$ai_user_id.'" AND group_id="'.$group.'"')){
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_group_user SET user_id="'.$ai_user_id.'", group_id="'.$group.'", dateline="'.$this->base->time.'"');
            $this->db->query('UPDATE '.API_DBTABLEPRE.'ai_face_group SET num=num+2,  lastupdate="'.$this->base->time.'" WHERE group_id="'.$group.'"');
        }

    }

    function uid_aiuid_check($uid, $aiuid){
        if(!$uid || !$aiuid)
            return false;
        $ai_id = $this->base->ai_id();
        $ret = $this->db->fetch_first('SELECT a.face_id FROM '.API_DBTABLEPRE.'ai_member_face a LEFT JOIN '.API_DBTABLEPRE.'ai_member_face_bind b ON b.face_id=a.face_id LEFT JOIN '.API_DBTABLEPRE.'ai_face_user c ON c._face_id=b._face_id WHERE a.uid="'.$uid.'" AND c.user_id="'.$aiuid.'" AND c.ai_id = "'.$ai_id.'"');
        if($ret){
            return false;
        }else{
            return true;
        }
    }

    function get_imagepath_by_imageid($image_id){
        $imageinfo = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_image WHERE image_id="'.$image_id.'"');
        if(!$imageinfo)
            return false;

        //获取图片
        $storageid = $imageinfo['storageid'];
        // $storageid = 11;
        $storage = $this->base->load_storage($storageid);
        if (!$storage)
            return false;
        if(substr($imageinfo['pathname'], -1)!= "/"){
            $imageinfo['pathname'] .= "/";
        }
        $filepath = $imageinfo['pathname'].$imageinfo['filename'];
        $facepath = $storage->aiface_image($filepath);
        return $facepath;
    }
    function get_group(){
        $ai_id = $this->base->ai_id();
        $group = $this->db->result_first("SELECT group_id FROM ".API_DBTABLEPRE."ai_face_group WHERE status=1 AND ai_id=$ai_id AND `num`<`limit`");
        return $group;
    }
    function get_group_type(){
        $ai_id = $this->base->ai_id();
        $group = $this->db->result_first("SELECT ai_group_type FROM ".API_DBTABLEPRE."ai_face_group WHERE status=1 AND ai_id=$ai_id AND `num`<`limit`");
        return $group;
    }
    function get_all_group(){
        $ai_id = $this->base->ai_id();
        $group = $this->db->fetch_all("SELECT group_id FROM ".API_DBTABLEPRE."ai_face_group WHERE ai_id=$ai_id");
        $grp_all = '';
        foreach ($group as $grp) {
            $grp_all.=$grp['group_id'].',';
        }
        $grp_all = substr($grp_all,0,strlen($grp_all)-1);
        return $grp_all;
    }

    function check_name($name) {
        if(strtolower(API_CHARSET) != 'utf-8')
            return false;
    
        $len = $this->dstrlen($name, 1);
        // $preg = "/[ '.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/";
        $preg = "/^[\x{0800}-\x{4e00}\x{4e00}-\x{9fa5}A-Za-z0-9]{1}[\x{0800}-\x{4e00}\x{4e00}-\x{9fa5}A-Za-z0-9\._]+$/u";
        if($len > 40 || $len < 1 ) {
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

    function event_update($face_id, $event_id, $deviceid){
        if(!$event_id || $face_id==NULL || !$deviceid)
            return false;
        if($face_id=='0'){
            $_face_id = '0';
        }else{
            $_face_id = $this->db->result_first("SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id=$face_id");
        }
        if($_face_id==NULL)
            return false;
        $ret = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_event SET _face_id='".$_face_id."' WHERE event_id = '$event_id' AND deviceid = '$deviceid'");
        if(!$ret)
            return false;
        $result = array('event_id' => $event_id, 'face_id' => $face_id );
        return $result;
    }
    function eventmerge($param, $uid){
        if(!is_array($param))
            return false;
        foreach ($param as $event) {
            if(!$event['event_id'] || !$event['deviceid'])
                return false;
            $result = $this->event_update($event['face_id'], $event['event_id'], $event['deviceid']);
        }
        return $result;
    }

    function event_update2($face_id, $image_id){
        if(!$face_id || !$image_id)
            return false;

        $_face_id = $this->db->result_first("SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id=$face_id");
        if(!$_face_id)
            return false;
        $ret = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_event SET _face_id='".$_face_id."' WHERE image_id = '$image_id'");
        return true;
    }

    //注册人脸添加黑白照片
    function add_balck_white($imageid, $ai_user_id, $group, $_face_id){
        $facepath = $this->get_imagepath_by_imageid($imageid);
        if(!$facepath)
            return false;
        $ai_id = $this->base->ai_id();
        $ai_service = $this->base->get_ai_service($ai_id);
        if($ai_service){
            $ai_type = $ai_service['ai_type'];
        }else{
            $ai_type = "baidu";
        }
        $aiserver = $this->base->load_aiserver($ai_id);
        if(intval($ai_id) == 2){//sensenets
            $facepath = $_FILES["file"]["tmp_name"][1];
        }
        if($aiserver->face_register($facepath, $ai_user_id, '', $group, $imageid)){
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user_image SET user_id="'.$ai_user_id.'", image_id="'.$imageid.'",dateline="'.$this->base->time.'"');
            $ai_id = $this->base->ai_id();
            $data = "ai_id = $ai_id, ai_type='$ai_type', ai_user_id= '".$ai_user_id."', _face_id='$_face_id'";
            $this->db->query("UPDATE ".API_DBTABLEPRE."ai_face_image SET $data WHERE image_id = '".$imageid."'");

            // $ret = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_user_image WHERE image_id="'.$imageid.'" AND user_id= "'.$ai_user_id.'"');
            // if(!$ret){
            //     $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user_image SET user_id="'.$ai_user_id.'", image_id="'.$imageid.'", dateline="'.$this->base->time.'"');
            // }
            return true;
        }
        return false;
    }

    //设置提醒开关
    function event_push($uid, $event_push = 0,$face_id){
        if(!$uid || !in_array($event_push , [0,1])  || !$face_id)
            return false;
        $result = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face SET event_push=$event_push WHERE face_id=$face_id AND uid = $uid");
        if(!$result)
            return false;
        $res = $this->db->fetch_first("SELECT face_id,event_push,name,remark,image_id FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id=".$face_id);
        $image_url = $this->get_imagepath_by_imageid($res['image_id']);
        $date = array(
            'face_id' => $res['face_id'],
            'image_url' => $image_url,
            'event_push' => intval($res['event_push']),
            'name' => $res['name'],
            'remark' => $res['remark'],
        );
        return $date;
    }
    //根据face_id获取人脸事件列表
    function event_list($uid, $face_id, $page, $count, $event_type=0, $deviceid='', $st='', $et=''){
        if(!$uid)
            return false;

        $event_type = $event_type ? $event_type : 0;

        $select = "a.event_id, a.image_id, a._face_id, a.event_type, a.time, a.expiretime, b.deviceid, b.desc";
        $table = API_DBTABLEPRE."ai_event a LEFT JOIN ".API_DBTABLEPRE."device b on b.deviceid = a.deviceid";
        $ai_id = $this->base->ai_id();
        $where = "WHERE b.uid = '".$uid."' AND a.event_type = $event_type";
        if($face_id || $face_id == "0" || $face_id == "-1"){
            if($face_id == "0"){
                $_face_id = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind m LEFT JOIN ".API_DBTABLEPRE."ai_member_face n on n.face_id=m.face_id where n.uid = '$uid'";
                $_face_id_num = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."ai_member_face_bind m LEFT JOIN ".API_DBTABLEPRE."ai_member_face n on n.face_id=m.face_id where n.uid = '$uid' AND n.ai_id = $ai_id");
                if($_face_id_num){
                    $where .= " AND a._face_id not in ($_face_id)";
                }
            }else if($face_id == "-1"){
                //数据优化
                $select .=" , mf.face_id,mf.image_id,mf.name,mf.remark,mf.event_push,mf.cluster,ff.id_card, ff.area, ff.age, ff.visits, ff.class";
                $table .=" LEFT JOIN ".API_DBTABLEPRE."ai_member_face_bind fb on fb._face_id=a._face_id LEFT JOIN ".API_DBTABLEPRE."ai_member_face mf on mf.face_id=fb.face_id LEFT JOIN ".API_DBTABLEPRE."ai_face_fileds ff on ff.face_id=mf.face_id";
                $where .=" AND mf.uid='$uid'";

                $where .= " AND a._face_id !=0";

                // $_face_id = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind m LEFT JOIN ".API_DBTABLEPRE."ai_member_face n on n.face_id=m.face_id where n.uid = '$uid'";
                // $_face_id_num = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."ai_member_face_bind m LEFT JOIN ".API_DBTABLEPRE."ai_member_face n on n.face_id=m.face_id where n.uid = '$uid' AND n.ai_id = $ai_id");
                // if($_face_id_num){
                //     $where .= " AND a._face_id in ($_face_id)";
                // }
            }else{
                //检查事件状态 待确认人无事件删除
                $cluster = $this->db->result_first("SELECT cluster FROM ".API_DBTABLEPRE."ai_member_face where face_id='$face_id'");
                if(intval($cluster)==1){
                    $eventnum = $this->eventnum_byfaceid($face_id, $uid);
                    if(intval($eventnum) == 0){
                        //人脸删除
                        $this->delface_byfaceid($face_id, $uid);
                        return NULL;
                    }
                }
                //数据优化
                $select .=" , mf.face_id,mf.image_id,mf.name,mf.remark,mf.event_push,mf.cluster,ff.id_card, ff.area, ff.age, ff.visits, ff.class";
                $table .=" LEFT JOIN ".API_DBTABLEPRE."ai_member_face_bind fb on fb._face_id=a._face_id LEFT JOIN ".API_DBTABLEPRE."ai_member_face mf on mf.face_id=fb.face_id LEFT JOIN ".API_DBTABLEPRE."ai_face_fileds ff on ff.face_id=mf.face_id";
                $where .=" AND mf.uid='$uid'";

                $_face_id = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind where face_id = '$face_id'";
                $_face_id_num = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."ai_member_face_bind where face_id = '$face_id'");
                if($_face_id_num == 0)
                    return NULL;
                if($_face_id_num)
                    $where .= " AND a._face_id in ($_face_id)"; 
            }
        }
        if($deviceid){
            if(is_array($deviceid)){
                $dev = "'";
                $dev .= join("', '", $deviceid);
                $dev .= "'";
                $where .=" AND b.deviceid in  ($dev)";
            }else{
                $where .=" AND b.deviceid = '$deviceid'";
            }
        }
        if($st){
            $where .=" AND a.time >= $st";
        }
        if($et){
            $where .=" AND a.time <= $et";
        }
        $total = $this->db->result_first("SELECT count(*) FROM $table $where");
        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;
        $pages = $this->base->page_get_page($page, $count, $total);
        $result['page'] = $pages['page'];
        $limit = ' LIMIT '.$pages['start'].', '.$count;

        $order = " ORDER BY a.time DESC";

        $list = array();
        if($total){
            $data = $this->db->fetch_all("SELECT $select FROM $table $where $order $limit");
            $this->base->load('statistics');
            foreach($data as $k=>$v) {
                $event = array();
                $_face_id = "";
                $face_info = "";
                $face = array();
                if( $face_id == ''){
                    //全部事件列表 获取人脸信息
                    if($v['_face_id']){
                        $_face_id = $v['_face_id'];
                        $face_id_sql = "SELECT face_id from ".API_DBTABLEPRE."ai_member_face_bind WHERE _face_id = '$_face_id'";
                        $table1 = API_DBTABLEPRE.'ai_member_face m LEFT JOIN '.API_DBTABLEPRE.'ai_face_fileds f on f.face_id=m.face_id';
                        $where1 = "WHERE m.face_id in ($face_id_sql) AND m.uid= '$uid'";
                        $select1 = 'm.face_id as face_id, m.image_id, m.uid as uid, m.name, m.cluster,m.remark, f.id_card, f.area, f.age, f.visits, f.class';
                        $face_info = $this->db->fetch_first("SELECT $select1 FROM $table1 $where1");

                        if($face_info){
                            $face["face_id"] = strval($face_info['face_id']);
                            $face["name"] = $face_info['name'];
                            $face["remark"] = $face_info['remark'];
                            $face["event_push"] = intval($face_info['event_push']);
                            $face["image_url"] = $this->get_imagepath_by_imageid($face_info["image_id"]);
                            if (intval($face_info['cluster'])>0) {
                                $face['cluster'] = intval($face_info['cluster']);
                            }

                            //minzhengtong
                            $face['visits'] = intval($face_info['visits']);
                            $face['id_card'] = $face_info['id_card'];
                            $face['area'] = $_ENV['statistics']->get_info_by_code($face_info['area'], 2);
                            $face['class'] = $_ENV['statistics']->get_info_by_code($face_info['class'], 1);
                            if($face_info['age']){
                                $face['age'] = $_ENV['statistics']->get_age($face_info['age']);
                            }else{
                                $face['age'] = 0;
                            }
                            $event["face"] = $face;
                        }
                    }
                    // if($v['image_id']){
                    //     $image_attr = $this->db->fetch_first("SELECT age,beauty,expression,gender,glasses,race FROM ".API_DBTABLEPRE."ai_face_image WHERE image_id='".$v["image_id"]."'");
                    //     if($image_attr && $image_attr['age'] > 0){
                    //         $event['attribute']['age'] = intval($image_attr['age']);
                    //         $event['attribute']['beauty'] = intval($image_attr['beauty']);
                    //         $event['attribute']['expression'] = intval($image_attr['expression']);
                    //         $event['attribute']['gender'] = intval($image_attr['gender']);
                    //         $event['attribute']['glasses'] = intval($image_attr['glasses']);
                    //         $event['attribute']['race'] = intval($image_attr['race']);
                    //     }
                    // }
                }else{
                    if($v['face_id']){
                        $face["face_id"] = strval($v['face_id']);
                        $face["name"] = $v['name'];
                        $face["remark"] = $v['remark'];
                        $face["event_push"] = intval($v['event_push']);
                        $face["image_url"] = $this->get_imagepath_by_imageid($v["image_id"]);
                        if (intval($v['cluster'])>0) {
                            $face['cluster'] = intval($v['cluster']);
                        }
                        //minzhengtong
                        $face['visits'] = intval($v['visits']);
                        $face['id_card'] = $v['id_card'];
                        $face['area'] = $_ENV['statistics']->get_info_by_code($v['area'], 2);
                        $face['class'] = $_ENV['statistics']->get_info_by_code($v['class'], 1);
                        if($v['age']){
                            $face['age'] = $_ENV['statistics']->get_age($v['age']);
                        }else{
                            $face['age'] = 0;
                        }
                        $event["face"] = $face;
                    }
                }
                $event["event_id"] = strval($v["event_id"]);
                $event["time"] = intval($v["time"]);
                $event["expiretime"] = intval($v["expiretime"]);
                $event["deviceid"] = strval($v["deviceid"]);
                $event["desc"] = $v["desc"];
                if($v['image_id']){
                    $event["image_url"] = $this->get_imagepath_by_imageid($v["image_id"]);
                    $event["score"] = $this->db->result_first("SELECT ai_user_score FROM ".API_DBTABLEPRE."ai_face_image WHERE image_id='".$v["image_id"]."'");
                }
                $event["event_type"] = intval($v["event_type"]);
                $list[] = $event;
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    //根据face_id 返回事件数量统计
    function eventnum_byfaceid($face_id, $uid){
        if(!$face_id || !$uid)
            return false;
        $_face_id_sql = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id = '$face_id'";
        // $deviceid_sql = "SELECT a.deviceid FROM ".API_DBTABLEPRE."device a LEFT JOIN ".API_DBTABLEPRE."devicefileds b on b.deviceid=a.deviceid WHERE a.uid = '$uid' AND b.ai>0";
        $deviceid_sql = "SELECT deviceid FROM ".API_DBTABLEPRE."device WHERE uid = '$uid'";
        $eventnum = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."ai_event WHERE _face_id in ($_face_id_sql) AND deviceid in ($deviceid_sql) AND event_type = 0");
        return $eventnum;
    }
    //人脸删除
    function delface_byfaceid($face_id, $uid){
        if(!$face_id || !$uid)
            return false;
        if(!$this->db->result_first("SELECT * FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id = '$face_id' AND uid = '$uid'"))
            return false;
        $time = time();
        $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face_merge_list SET status = -2, mergetime = '$time' WHERE face_id = '$face_id'");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id='$face_id'");
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id='$face_id' AND uid = '$uid'");
    }

    function event_del($uid, $event_id, $deviceid){
        if(!$uid || !$event_id || !$deviceid)
            return false;
        //判断删除
        $sql = "SELECT face_id FROM ".API_DBTABLEPRE."ai_member_face WHERE uid = $uid";
        $face_id = $this->db->result_first("SELECT a.face_id FROM ".API_DBTABLEPRE."ai_member_face_bind a LEFT JOIN ".API_DBTABLEPRE."ai_event b on b._face_id = a._face_id WHERE b.event_id='$event_id' AND b.deviceid = '$deviceid' AND a.face_id in ($sql)");
        
        $result = $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_event WHERE event_id=$event_id AND deviceid = $deviceid");

        $cluster = $this->db->result_first("SELECT cluster FROM ".API_DBTABLEPRE."ai_member_face where face_id='$face_id'");
        if(intval($cluster)==1){
            $eventnum = $this->eventnum_byfaceid($face_id, $uid);
            if(intval($eventnum) == 0){
                //人脸删除
                $this->delface_byfaceid($face_id, $uid);
                return NULL;
            }
        }

        if($result)
            return array('event_id' => strval($event_id),'deviceid' => strval($deviceid));
        return false;
    }

    function facecount($uid, $cluster, $day=1, $st='', $et='', $deviceid=''){
        if(!$uid)
            return false;
        $ai_id = $this->base->ai_id();
        $where = " WHERE uid = $uid AND ai_id = $ai_id";
        if($day){
            $st = strtotime(date('Y-m-d'));
            $et = time();
        }
        if($st)
            $where .=" AND dateline > $st";
        if($et)
            $where .=" AND dateline < $et";
        //增加设备筛选
        // if($deviceid){
            $_face_id = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind m LEFT JOIN ".API_DBTABLEPRE."ai_member_face n on n.face_id=m.face_id WHERE n.uid = '$uid' AND n.cluster !=0";
            $wh = "WHERE _face_id in ($_face_id)";
            if($deviceid){
               $wh.= " AND deviceid in ($deviceid)";
            }else{
                $deviceid = "SELECT deviceid from ".API_DBTABLEPRE."device WHERE uid = '$uid'";
                $wh.= " AND deviceid in ($deviceid)";
            }
            
            if($st){
                $wh .= " AND time > $st";
            }
            if($et){
                $wh .= " AND time < $et";
            }
            $ret = $this->db->fetch_all("SELECT _face_id from ".API_DBTABLEPRE."ai_event $wh group by _face_id");
            $face_arr = array();
            foreach ($ret as $k => $v) {
                $_face_id_temp = $v['_face_id'];
                $face_id_temp = $this->db->fetch_all("SELECT face_id from ".API_DBTABLEPRE."ai_member_face_bind WHERE _face_id = $_face_id_temp");
                if(count($face_id_temp) > 0){
                    foreach ($face_id_temp as $v){
                        array_push($face_arr, $v['face_id']);
                    }
                }
            }
            $face_arr = array_unique($face_arr);
            if(count($face_arr)>0){
                $face_str = implode(",",$face_arr);
                $where .= " AND face_id in ($face_str)";
            }else{
                $where .= " AND 1=2";
            }
        // }

        if($cluster){
            $where .=" AND cluster != 0";
        }
        $count = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."ai_member_face $where");
        $result['count'] = $count;
        return $result;
    }

    function eventcount($uid, $cluster, $day=1, $st='', $et='', $deviceid=''){
        if(!$uid)
            return false;
        $ai_id = $this->base->ai_id();
        if($deviceid){
            $deviceid = $deviceid;
        }else{
            $deviceid = "SELECT deviceid from ".API_DBTABLEPRE."device WHERE uid = '$uid'";
        }

        $_face_id = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind m LEFT JOIN ".API_DBTABLEPRE."ai_member_face n on n.face_id=m.face_id WHERE n.uid = '$uid' AND n.cluster !=0 AND n.ai_id = $ai_id";
        $where = " WHERE event_type = 0 AND deviceid in ($deviceid)";
        if($day){
            $st = strtotime(date('Y-m-d'));
            $et = time();
        }
        if($st)
            $where .=" AND time >= $st";
        if($et)
            $where .=" AND time <= $et";
        $where .=" AND _face_id in ($_face_id)";
        $group = "";
        if($cluster){
            $group = "group by _face_id";
            $_face_id_sql = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_event $where $group";

            $count = $this->db->fetch_all("SELECT b.face_id FROM ".API_DBTABLEPRE."ai_member_face_bind a LEFT JOIN ".API_DBTABLEPRE."ai_member_face b on b.face_id = a.face_id where a._face_id in ($_face_id_sql) AND b.uid =$uid group by b.face_id");
            $result['count'] = count($count);
            return $result;
        }else{
            $count = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."ai_event $where $group");
            $result['count'] = count($count);
            return $result;
        }
        
    }

    //人脸聚类相关
    function mergelist($uid, $face_id, $page, $count, $list_type){
        if(!$uid)
            return false;
        if($list_type == 'page') {
            $page = $page > 0 ? $page : 1;
            $count = $count > 0 ? $count : 10;

            $pages = $this->base->page_get_page($page, $count, $total);

            $result['page'] = $pages['page'];
            $limit = ' LIMIT '.$pages['start'].', '.$count;
        }
        if(!$face_id){
            $result = $this->mergelist_all($uid, $page, $count, $list_type);
        }else{
            $result = $this->mergelist_sigle($uid, $face_id, $page, $count, $list_type);
        }
        return $result;
    }
    function mergelist_all($uid, $page, $count, $list_type){
        $ai_id = $this->base->ai_id();
        $total = $this->db->result_first("SELECT count(*) FROM ".API_DBTABLEPRE."ai_member_face_merge WHERE uid = '$uid' AND ai_id = $ai_id AND status = 0");

        $limit = "";
        if($list_type == 'page') {
            $page = $page > 0 ? $page : 1;
            $count = $count > 0 ? $count : 10;

            $pages = $this->base->page_get_page($page, $count, $total);

            $result['page'] = $pages['page'];
            $limit = ' LIMIT '.$pages['start'].', '.$count;
        }

        $list = array();
        if($total){
            $mergelist = $this->db->fetch_all("SELECT merge_id FROM ".API_DBTABLEPRE."ai_member_face_merge WHERE uid = '$uid' AND ai_id = $ai_id AND status = 0 ORDER BY lastupdate DESC $limit");
            foreach ($mergelist as $merge) {
                $face_li = $this->getfacelistbymergeid($merge['merge_id']);
                if($face_li && count($face_li)>0)
                    $list[] = $face_li;
            }
        }
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    function mergelist_sigle($uid, $face_id, $page, $count, $list_type){
        $merge_id = $this->db->result_first("SELECT merge_id FROM ".API_DBTABLEPRE."ai_member_face_merge_list WHERE face_id = '$face_id' AND status = 0");
        $ai_id = $this->base->ai_id();
        $merge_id = $this->db->result_first("SELECT merge_id FROM ".API_DBTABLEPRE."ai_member_face_merge WHERE merge_id = '$merge_id' AND status = 0 AND ai_id = $ai_id");
        $list = array();
        if($merge_id){
            $list = $this->getfacelistbymergeid($merge_id, $face_id);
        }
        // if(count($list)==0)
        //     return NULL;
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    function getfacelistbymergeid($merge_id, $face_id=0){
        $table = API_DBTABLEPRE.'ai_member_face a LEFT JOIN '.API_DBTABLEPRE.'ai_member_face_merge_list b on b.face_id = a.face_id';
        $where = "WHERE b.merge_id=".$merge_id." AND b.status = 0";
        $select = 'a.face_id, a.image_id, a.name, a.remark, a.event_push, a.cluster';
        $face_list = $this->db->fetch_all("SELECT $select FROM $table $where");
        $face_li = array();
        foreach ($face_list as $arr) {
            if($face_id != $arr['face_id']){
                $face['face_id'] = strval($arr['face_id']);
                $face['image_url'] = $this->get_imagepath_by_imageid($arr['image_id']);
                $face['name'] = strval($arr['name']);
                $face['remark'] = strval($arr['remark']);
                if (intval($arr['cluster'])>0) {
                    $face['cluster'] = intval($arr['cluster']);
                }
                $face_li[] = $face;
            }
        }
        //mergeid 下只有一个face 删除merge @todo
        // if(count($face_li)<2){
        //     $time = time();
        //     $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face_merge_list SET status = -2, lastupdate = '$time' WHERE merge_id=$merge_id");
        //     $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face_merge SET status = 2, lastupdate = '$time' WHERE merge_id=$merge_id");
        //     return array();
        // }
        return $face_li;
    }
    function mergeupdate($uid, $face_arr){
        if(!$uid || !$face_arr)
            return false;
        $face_id = 0;
        $count = 0;
        $score = 0;
        $merge = array();
        foreach ($face_arr as $k=>$id) {
            //寻找适合的face_id
            $face = $this->db->fetch_first("SELECT face_id, image_score, cluster FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id = '$id' AND uid = '$uid'");
            $merge_id = $this->db->result_first("SELECT merge_id FROM ".API_DBTABLEPRE."ai_member_face_merge_list WHERE face_id = '$id' AND status != 2");
            if(!$face){
                //删除相关数据
                unset($face_arr[$k]);
            }else{
                if($merge_id)
                    $merge[] = $merge_id;
                if(!$face)
                    return NULL;
                if($face['cluster']!=1){
                    $face_id = $id;
                    $score = 101;
                    $count ++;
                }else{
                    if (intval($face['image_score']) >= intval($score)) {
                        $score = $face['image_score'];
                        $face_id = $id;
                    }
                }
            }
            
        }
        $merge = array_unique($merge);
        if(!$face_id)
            return NULL;
        if($count>1)
            return false;

        $_face_id = $this->db->result_first("SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id = '$face_id'");
        if(!$_face_id)
            return NULL;
        //face_bind  member_face  event表操作
        $merge_str = array();
        foreach ($face_arr as $value) {
            # code...
            if($value != $face_id){
                $sql = "SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id = '$value'";

                $this_face_id = $this->db->fetch_all($sql);
                if(count($this_face_id)>0){
                    foreach ($this_face_id as $_fid) {
                        $_faceid = $_fid['_face_id'];
                        if(intval($_faceid)>0){
                            $merge_str[$value][] = intval($_faceid);
                            if(!$this->db->result_first("SELECT _face_id FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id = '$face_id' AND _face_id = '$_faceid'")){
                                $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_member_face_bind SET face_id="'.$face_id.'", _face_id="'.$_faceid.'"');
                            }
                        }
                    }
                }

                // $this->db->query("UPDATE ".API_DBTABLEPRE."ai_event SET _face_id = '$_face_id' WHERE _face_id IN ($sql)");
                $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id='".$value."'");
                // $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_member_face_bind SET face_id="'.$value.'", _face_id="'.$_face_id.'"');

                $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id='".$value."'");
                $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face_merge_list SET status = -1, mergetime = '$time' WHERE face_id = '$value'");
            }else{
                $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face_merge_list SET status = 1, mergetime = '$time' WHERE face_id = '$value'");
            }
        }
        //merge
        foreach ($merge as $mergeid) {
            $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face_merge SET status = 2, lastupdate = '".$this->base->time."' WHERE merge_id = '$mergeid'");
        }
        //记录添加@todo
        $face_arr = implode(',', $face_arr);
        $merge_str = serialize($merge_str);
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_member_face_merge_record SET uid = "'.$uid.'", face_id="'.$face_id.'", face_arr="'.$face_arr.'", merge_str="'.$merge_str.'", dateline = "'.$this->base->time.'"');
        return array('face_id' => $face_id);
    }
    function mergecancel($uid, $face_id){
        if(!$uid || !$face_id)
            return false;

        $face = $this->db->result_first("SELECT face_id FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id = '$face_id' AND uid = $uid");
        if(!$face)
            return NULL;
        $merge = $this->db->result_first("SELECT merge_id FROM ".API_DBTABLEPRE."ai_member_face_merge_list WHERE face_id = '$face_id'");
        $this->db->query("UPDATE ".API_DBTABLEPRE."ai_member_face_merge SET status = 1, lastupdate = '".$this->base->time."' WHERE merge_id = '$merge'");
        return array("face_id" => $face_id);
    }

}
