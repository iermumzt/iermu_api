<?php

!defined('IN_API') && exit('Access Denied');

class meetingmodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->meetingmodel($base);
    }

    function meetingmodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }


    function face_upload($uid, $file, $black='') {
        if(!$file)
            return false;
        //检测人脸存在
        $dectect = $this->face_dectect($file);
        if(!$dectect)
            return false;
        $dectect_result = json_encode($dectect_result);
        // if($dectect['result_num']!=1){
        //     return $dectect['result_num'];
        // }
        // $dectect_result = $dectect['result'][0];
        // if($dectect_result['face_probability'] < 0.7){
        //     return 'not_probability';
        // }
        // $a = -20;
        // if($dectect_result['yaw']>50 || $dectect_result['yaw']< $a)
        //     return 'not_probability';
        
        //人脸注册图片存储至七牛
        $storageid = 11;
        $storage = $this->base->load_storage($storageid);
        if (!$storage)
            return false;
        //获取uploadtoken
        $face = $storage->upload_face_token($uid, $black);
        if(!$face)
            return false;

        $file_temp = fopen($file, 'rb');
        $stat = fstat($file_temp);
        $size = $stat['size'];
        fclose($file_temp);
        if(!$size)
            $size = 0;

        if($size > 4194304){
            return false;
        }
        
        include_once API_SOURCE_ROOT.'lib/qiniuUpload.class.php';
        $upload = new UploadManager();
        $ret = $upload->putFile($face['uploadToken'], $face['filepath'], $file);
        if(!$ret[0]['key'] || $ret[0]['key'] != $face['filepath']){
            return false;
        }
        // $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_face VALUES 0");
        // $faceid = $this->db->insert_id();

        $data = "source_type = '1', source_id ='".$uid."', pathname = '".$face['pathname']."', filename = '".$face['filename']."', size = '".$size."', ai_result = '".$dectect_result."', storageid = '".$storageid."', dateline = '".time()."'";
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_face_image SET $data");
        $imageid = $this->db->insert_id();
        // if($imageid){
        // 	//update
        // 	$this->db->query("UPDATE ".API_DBTABLEPRE."ai_face_image SET $data WHERE image_id = '$imageid'");
        // }
        if(!$imageid){
            return false;
        }
        $image_url = $storage->aiface_image($face['filepath']);
        return array(
                'image_id'  => $imageid,
                'image_url' => $image_url,
            );
    }

    function faceregister($imgid, $fname, $remarks, $uid='0',$mid){
    	if(!$imgid || !$fname)
    		return false;

        //获取可用用户群租
        $group = $this->get_baidu_group();
        if(!$group)
            return false;
        $facepath = $this->get_imagepath_by_imageid($imgid);
        if(!$facepath)
            return false;

        $ai_ret = $this->baidu_face_uid($facepath, $fname, $remarks);
        if(!$ai_ret)
            return false;
        $bai_user_id = $ai_ret['bai_user_id'];
        $_face_id     = $ai_ret['_face_id'];
        $scores      = $ai_ret['scores'];

        $check = $this->check_meeting_member( $mid , $_face_id);
        if(!$check){
            $result = array(
                'error_code' => '400291',
                'error_msg' => 'the face has registered',
            );
            return $result;
        }

        $this->register_todo($bai_user_id, $imgid, $scores, $fname, $remarks, $_face_id, $group);
        //member 信息
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_meeting_member SET uid="'.$uid.'", _face_id="'.$_face_id.'", image_id="'.$imgid.'", name="'.$fname.'", remark="'.$remarks.'", mid="'.$mid.'", signin_type=1, dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'",source=0');
        $face_id = $this->db->insert_id();
        $list = array();
        $list[] = $this->imageinfo_by_id($face_id);
        return array(
            'list' => $list,
        );
  		
    }

    //监测改人脸是否已经存在此次会议中

    function check_meeting_member( $mid , $_face_id){
        if(!$mid || !$_face_id){
            return false;
        }
        $result = $this->db->result_first('SELECT _face_id FROM '.API_DBTABLEPRE.'ai_meeting_member WHERE mid="'.$mid.'" AND _face_id="'.$_face_id.'"');
        if(!$result){
            return true;
        }
        return false;
    }
    //更新
    function faceupdate( $uid , $data='',$meeting_member_id='',$name='',$remark='', $imgid=''){
        if(!$uid)
            return false;
        if($data){
            $imageinfo = array();
            foreach ($data as $k => $meeting_member){
                $face_id = $meeting_member['meeting_member_id'];
                $fname = $meeting_member['name'];
                $remarks = $meeting_member['remark'];
                $faceinfo = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_meeting_member WHERE mmid="'.$face_id.'" AND uid="'.$uid.'"');
                if(!$faceinfo)
                    continue;
                $group = $this->get_baidu_group();
                if(!$group)
                    return false;
                $bai_user_id_old = $this->db->result_first('SELECT user_id FROM '.API_DBTABLEPRE.'ai_face_user WHERE _face_id="'.$faceinfo['_face_id'].'" AND ai_id = 1');
                $sql_data = "lastupdate='".$this->base->time."'";
                if(isset($fname))
                    $sql_data .= ", name = '$fname'";
                if(isset($remarks))
                    $sql_data .= ", remark = '$remarks'";
                $result = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_meeting_member SET $sql_data WHERE mmid = '$face_id'");
                $imageinfo['imageinfo'][] = $this->imageinfo_by_id($face_id);
            }
            return $imageinfo;
        }else{
            if(!$meeting_member_id)
                return false;
            $faceinfo = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_meeting_member WHERE mmid="'.$meeting_member_id.'" AND uid="'.$uid.'"');
            if(!$faceinfo)
                return NULL;

            $group = $this->get_baidu_group();
            if(!$group)
                return false;

            // $bai_user_id_old = $this->db->result_first('SELECT user_id FROM '.API_DBTABLEPRE.'ai_face_user WHERE _face_id="'.$_face_id.'" AND ai_id = 1');
            $_face_id = '';
            if($imgid){
                $ret = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_meeting_member WHERE mmid="'.$meeting_member_id.'" AND image_id="'.$imgid.'"');
                //新图片 进行识别
                if(!$ret){
                    $facepath = $this->get_imagepath_by_imageid($imgid);
                    if(!$facepath)
                        return false;

                    $ai_ret = $this->baidu_face_uid($facepath, $name, $remark);
                    $bai_user_id = $ai_ret['bai_user_id'];
                    $_face_id = $ai_ret['_face_id'];
                    $scores = $ai_ret['scores'];
                    $mmid =  $this->db->result_first('SELECT mmid FROM '.API_DBTABLEPRE.'ai_meeting_member WHERE _face_id="'.$_face_id.'"');
                    if($mmid != $meeting_member_id){
                        $check = $this->uid_aiuid_check($uid, $bai_user_id);
                        if(!$check){
                            $result = array(
                                'error_code' => '400291',
                                'error_msg' => 'the face has registered',
                            );
                            return $result;
                        }
                    }
                    $this->register_todo($bai_user_id, $imgid, $scores, $name, $remark, $_face_id, $group);
                }
            }



            $data = "lastupdate='".$this->base->time."'";
            if(isset($name))
                $data .= ", name = '$name'";
            if(isset($remark))
                $data .= ", remark = '$remark'";
            if($imgid)
                $data .= ", image_id = '$imgid'";
            if($_face_id)
                $data .= ", _face_id = '$_face_id'";

            $result = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_meeting_member SET $data WHERE mmid = '$meeting_member_id'");

            return $this->imageinfo_by_id($meeting_member_id);
        }
    }

    //检测是否存在人脸
    function face_dectect($facepath){
        $aiid = 1;
        $aiserver = $this->base->load_aiserver($aiid);
        if (!$aiserver)
            return false;
        //检测是否存在人脸
        $dectect = $aiserver->face_detect($facepath);
        return $dectect;
    }
    //返回百度人脸ID
    function baidu_face_uid($facepath, $fname, $remark, $imgid_black_white=''){
        //获取人脸群组
        $group = $this->get_baidu_group();
        $group_all = $this->get_baidu_all_group();
        //对比注册ai图片
        $aiid = 1;
        $aiserver = $this->base->load_aiserver($aiid);
        if (!$aiserver)
            return false;
        // $rets = $aiserver->face_identfy($facepath, $group_all);
        $faceinfo = $aiserver->face_identfy($facepath, $group_all);
        //人脸识别分值高于70返回user_id
        if($faceinfo){
            return $faceinfo;
        }

        // $scores = 0;
        // if($rets){
        //     if($rets['result']){
        //         $baidu_scores = $rets['result'][0]['scores'][0];
        //         if($baidu_scores >= 70){
        //             $scores = $baidu_scores;
        //             $bai_user_id = $rets['result'][0]['uid'];
        //             $_face_id = $this->db->result_first('SELECT _face_id FROM '.API_DBTABLEPRE.'ai_face_user WHERE user_id="'.$bai_user_id.'" AND ai_id =1');
        //             //处理 非app上传人脸
        //             if(!$_face_id){
        //                 $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_face VALUES ()");
        //                 $_face_id = $this->db->insert_id();
        //             }
        //         }
        //     }
        // }
        //注册
        // if(!$bai_user_id){
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_face VALUES ()");
        $_face_id = $this->db->insert_id();
        $uid = 'face_'.$_face_id;
        $user_info = json_encode(array('name'=>$fname, 'remark'=>$remark));
        $bai_user_id = $aiserver->face_register($facepath, $uid, $user_info, $group, "", "");
        $scores = 0;
        if($imgid_black_white){
            $this->add_balck_white($imgid_black_white, $bai_user_id, $group, $_face_id);
        }
        // }
        if (!$bai_user_id || !$_face_id) {
            return false;
        }
        return array(
                'bai_user_id' => $bai_user_id,
                '_face_id'     => $_face_id,
                'scores'      => $scores,
            );
    }

    function facelist($uid, $page, $count, $list_type,$meeting_id='0'){
    	if(!$uid){
    		return false;
    	}
        $table = API_DBTABLEPRE.'ai_meeting_member';
    	if($meeting_id != 0 ){
            $where = 'WHERE uid='.$uid.' AND mid='.$meeting_id;
        }else{
            $where = 'WHERE uid='.$uid;
        }
        $select = 'mmid, image_id, name, remark, mid,signin_status';
        $order = 'ORDER BY CONVERT(name USING gbk)';

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
    		foreach($data as $value) {
                $image = array();
	            $image['meeting_member_id'] = strval($value['mmid']);
                $image["image_url"] = $this->get_imagepath_by_imageid($value["image_id"]);
	            $image['name'] = $value['name'] ? $value['name'] : "";
                $image['remark'] = $value['remark'] ? $value['remark'] : "";
                $image["signin_status"] = intval($value["signin_status"]);
                $image['meeting_id'] = strval($value['mid']);
	            $list[] = $image;
	            $count++;
	        }
    	}
        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }
    //获取app上传的人脸图片
    function appfacelist($uid, $page, $count, $list_type='all', $name = ''){
        if(!$uid){
            return false;
        }
        $table = API_DBTABLEPRE.'member_face';
        $where = 'WHERE uid='.$uid.' AND cluster=0';
        $select = 'face_id, image_id, name, remark, event_push, cluster,_face_id';
        $order = 'ORDER BY dateline DESC';
        if($name)
            $where .= " AND name LIKE '%".$name."%'";

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

            foreach($data as $value) {
                $image = array();
                $image['face_id'] = strval($value['face_id']);
                $image["image_url"] = $this->get_imagepath_by_imageid($value["image_id"]);
                $image['name'] = $value['name'] ? $value['name'] : "";
                $image['remark'] = $value['remark'] ? $value['remark'] : "";
                $image["event_push"] = intval($value["event_push"]);
                $image['cluster'] = intval($value['cluster']);
                $list[] = $image;
                $count++;
            }
        }

        $result['count'] = count($list);
        $result['list'] = $list;
        return $result;
    }

    function facenamelist($uid){
        $result = $this->db->fetch_all("SELECT mmid as meeting_id,name FROM ".API_DBTABLEPRE."ai_meeting_member  WHERE uid = $uid");
        $count = count($result);
        $result = array('count' => $count, 'list' => $result );
        return $result;
    }

    function imageinfo_by_id($face_id){
    	$table = API_DBTABLEPRE.'ai_meeting_member m LEFT JOIN '.API_DBTABLEPRE.'ai_face_image a ON a.image_id=m.image_id';
        $where = 'WHERE m.mmid='.$face_id;
        $select = 'm.mmid as meeting_member_id, m.mid as meeting_id,m.signin_status,a.image_id, m.uid as uid, a.pathname, a.filename, m.name, m.remark';

        $value = $this->db->fetch_first("SELECT $select FROM $table $where");
        $result = array();
        if($value){
            $result['meeting_member_id'] = $value['meeting_member_id'];
            $result['uid'] = $value['uid'];
            $result['meeting_id'] = strval($value['meeting_id']);
            $result['signin_status'] = intval($value['signin_status']);
            $result['name'] = $value['name']?$value['name']:"";
            $result['remark'] = $value['remark']?$value['remark']:"";
        }
        return $result;
    }

    function face_del($meeting_member_id_arr, $uid){
        if(empty($meeting_member_id_arr)){
            return false;
        }
        foreach ($meeting_member_id_arr as $data){
            $face_id = $data;
            if(!$face_id)
                return false;
            $rets = $this->db->fetch_first('SELECT image_id, uid, _face_id FROM '.API_DBTABLEPRE.'ai_meeting_member WHERE mmid="'.$face_id.'" AND uid="'.$uid.'"');
            if(!$rets['image_id']){
                return NULL;
            }
            $_face_id = $rets['_face_id'];
            $deviceid = "SELECT deviceid from ".API_DBTABLEPRE."device WHERE uid = '$uid'";
            $image_id = "SELECT `image_id` from ".API_DBTABLEPRE."ai_event where deviceid in ($deviceid) AND _face_id = '$_face_id'";

            $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_face_image WHERE image_id in ($image_id) AND source_type = 0");
            $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_event WHERE deviceid in ($deviceid) AND _face_id = '$_face_id'");
            // $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_face_image WHERE image_id='$image_id' AND source_id = '$uid'");
            // $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_face_user_image WHERE image_id='$image_id'");
            $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_meeting_member WHERE mmid='$face_id'");
        }
        return array();

    }

    function register_todo($bai_user_id, $imgid, $scores, $name, $remark, $_face_id, $group){
        $scores = $scores * 100;
  		$data = "ai_id = 1, ai_type ='baidu', ai_user_id = '".$bai_user_id."', ai_user_score = '".$scores."', _face_id = '$_face_id'";
  		$this->db->query("UPDATE ".API_DBTABLEPRE."ai_face_image SET $data WHERE image_id = '".$imgid."'");

  		//@todo update or insert
  		$rets = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_user WHERE user_id="'.$bai_user_id.'" AND _face_id="'.$_face_id.'" AND ai_id = 1');
  		if($rets){
  			$this->db->query("UPDATE ".API_DBTABLEPRE."ai_face_user SET lastupdate='".$this->base->time."' WHERE _face_id = '$_face_id' AND user_id = '$bai_user_id' AND ai_id = 1");
  		}else{
  			$this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user SET user_id="'.$bai_user_id.'", _face_id="'.$_face_id.'", ai_id= 1 , ai_type="baidu", dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');
  		}

  		//bai_user_image
  		$ret = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_user_image WHERE image_id="'.$imgid.'" AND user_id= "'.$bai_user_id.'"');
  		if(!$ret){
  			$this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user_image SET user_id="'.$bai_user_id.'", image_id="'.$imgid.'", dateline="'.$this->base->time.'"');
  		}

  		//分组信息
  		if(!$this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_group_user WHERE user_id="'.$bai_user_id.'" AND group_id="'.$group.'"')){
  			$this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_group_user SET user_id="'.$bai_user_id.'", group_id="'.$group.'", dateline="'.$this->base->time.'"');
            $this->db->query('UPDATE '.API_DBTABLEPRE.'ai_face_group SET num=num+2,  lastupdate="'.$this->base->time.'" WHERE group_id="'.$group.'"');
  		}

    }

    function uid_aiuid_check($uid, $aiuid){
        if(!$uid || !$aiuid)
            return false;
        $ret = $this->db->fetch_first('SELECT a.mmid FROM '.API_DBTABLEPRE.'ai_meeting_member a LEFT JOIN '.API_DBTABLEPRE.'ai_face_user b ON b._face_id=a._face_id WHERE a.uid="'.$uid.'" AND b.user_id="'.$aiuid.'" AND b.ai_id = 1');
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
        $facepath = $storage->aiface_image($imageinfo['pathname'].$imageinfo['filename']);
        return $facepath;
    }
    function get_baidu_group(){
        $group = $this->db->result_first("SELECT group_id FROM ".API_DBTABLEPRE."ai_face_group WHERE status=1 AND ai_id=1 AND `num`<`limit`");
        return $group;
    }
    function get_baidu_all_group(){
        $group = $this->db->fetch_all("SELECT group_id FROM ".API_DBTABLEPRE."ai_face_group WHERE ai_id=1");
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
    //注册人脸添加黑白照片
    function add_balck_white($imageid, $baidu_uid, $group, $_face_id){
        $facepath = $this->get_imagepath_by_imageid($imageid);
        if(!$facepath)
            return false;
        $aiid = 1;
        $aiserver = $this->base->load_aiserver($aiid);
        if($aiserver->face_register($facepath, $baidu_uid, '', $group, $imageid)){
            $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user_image SET user_id="'.$baidu_uid.'", image_id="'.$imageid.'",dateline="'.$this->base->time.'"');

            $data = "ai_id = 1, ai_type='baidu', ai_user_id= '".$baidu_uid."', _face_id='$_face_id'";
            $this->db->query("UPDATE ".API_DBTABLEPRE."ai_face_image SET $data WHERE image_id = '".$imageid."'");

            $ret = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'ai_face_user_image WHERE image_id="'.$imageid.'" AND user_id= "'.$baidu_uid.'"');
            if(!$ret){
                $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_face_user_image SET user_id="'.$baidu_uid.'", image_id="'.$imageid.'", dateline="'.$this->base->time.'"');
            }
            return true;
        }
        return false;
    }
    //app端拉去人脸到wall
     function addappmember($uid ,$face_arr,$meeting_id){
         if(!$uid || !$meeting_id || !$face_arr)
             return false;
         $meeting_member_info = array();
         $have_registered = array();
         foreach ($face_arr as $face_id){
             $data = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."ai_member_face WHERE face_id=".$face_id);
             if(!$data)
                 continue;
             $_face_id_arr = $this->db->fetch_first("SELECT _face_id from ".API_DBTABLEPRE."ai_member_face_bind WHERE face_id = '$face_id'");
             $_face_id = $_face_id_arr['_face_id'];
             $image_id = $data['image_id'];
             $fname = $data['name'];
             $remarks = $data['remark'];
             //查询meeting是否存在人脸信息
             $result = $this->db->fetch_all("SELECT mmid FROM ".API_DBTABLEPRE."ai_meeting_member WHERE uid=".$uid." AND mid=".$meeting_id." AND _face_id=".$_face_id);

             if(!$result){
                 //member 信息
                 $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_meeting_member SET uid="'.$uid.'", _face_id="'.$_face_id.'", image_id="'.$image_id.'", name="'.$fname.'", remark="'.$remarks.'", mid="'.$meeting_id.'", signin_type=1, dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'",source=1');
                 $meeting_member_id = $this->db->insert_id();
                 $meeting_member_info['list'][] = $this->imageinfo_by_id($meeting_member_id);
                 $have_registered[] = 0;
             }else{
                 $have_registered[] = 1;
             }
         }
         if(in_array(1,$have_registered) && !in_array(0,$have_registered)){
             $meeting_member_info['msg'] = 'Face has been registered';
         }
         return $meeting_member_info;
     }

    //用户签到状态修改
    function signinmember($uid, $meeting_member_id,$signinstatus='0'){
        if(!$uid || !$meeting_member_id)
            return false;
        $result = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."ai_meeting_member WHERE uid=".$uid." AND mmid=".$meeting_member_id);
        if(!$result){
            return false;
        }
        if($signinstatus){
            $signin_time = $this->base->time;
        }else{
            $signin_time = 0;
        }
        $result = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_meeting_member SET signin_status='".$signinstatus."',signin_time='".$signin_time."' WHERE mmid=$meeting_member_id");
        if(!$result){
            return false;
        }
        $res = array(
            'meeting_member_id' => strval($meeting_member_id)
        );
        return $res;
    }

    //会议列表

    function meetinglist($uid){
        if(!$uid)
            return false;
        $result = $this->db->fetch_all("SELECT mid as meeting_id,title,intro,dateline as time FROM ".API_DBTABLEPRE."ai_meeting WHERE uid=".$uid);
        $total = count($result);
        if(!$result){
            return false;
        }
        $data = array(
            'total' => $total,
            'list' => $result
        );
        return $data;
    }

    //增加新的会议

    function meetingadd($uid,$title,$intro=''){
        if(!$uid || !$title)
            return false;
        if(!$this->check_meeting_name($uid,$title)){
            return array(
                'msg' => 'Title already exists',
                'error' => strval(400023)
            );
        }
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_meeting SET uid=$uid,title='".$title."',intro='".$intro."',dateline='".$this->base->time."',lastupdate='".$this->base->time."'");
        $meeting_id = $this->db->insert_id();
        if(!$meeting_id){
            return false;
        }
        return array(
            'meeting_id' => strval($meeting_id)
        );
    }

    //更新会议信息

    function meetingupdate($uid,$meeting_id,$title,$intro='',$device_ids=''){
        if(!$uid || !$title || !$meeting_id)
            return false;
        $result = $this->db->query("UPDATE ".API_DBTABLEPRE."ai_meeting SET title='".$title."',intro='".$intro."',lastupdate='".$this->base->time."' WHERE mid=$meeting_id");
        if(!$result){
            return false;
        }
        //删除表内信息
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_meeting_device WHERE mid='".$meeting_id."'");
        if($device_ids){
            $device_arr = explode(',' , $device_ids);
            foreach ($device_arr as $value){
                $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_meeting_device SET mid=$meeting_id,deviceid='".$value."',dateline='".$this->base->time."'");
            }
        }
        return array(
            'meeting_id' => strval($meeting_id),
            'title' => $title,
            'intro' => $intro
        );
    }

    //删除会议信息

    function meetingdrop($uid,$meeting_id){
        if(!$uid || !$meeting_id)
            return false;
        //删除ai_meeting表内信息
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_meeting WHERE mid='".$meeting_id."'");
        //删除ai_meeting_member表内信息
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_meeting_member WHERE mid='".$meeting_id."'");
        //删除ai_meeting_device表内信息
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_meeting_device WHERE mid='".$meeting_id."'");
        return array();
    }

    //添加设备到该会议

    function adddevice($uid,$device_arr,$meeting_id){
        if(!$uid || !$device_arr){
            return false;
        }
        foreach ($device_arr as $device_id){
            $result = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."ai_meeting_device WHERE mid=".$meeting_id." AND deviceid=".$device_id);
            if(!$result){
                $this->db->query('INSERT INTO '.API_DBTABLEPRE.'ai_meeting_device SET mid="'.$meeting_id.'", deviceid="'.$device_id.'", dateline="'.$this->base->time.'"');
            }
        }
        return array(
            'list' => $device_arr
        );
    }


    //删除设备该会议

    function dropdevice($uid,$device_ids,$meeting_id){
        if(!$uid || !$device_ids || !$meeting_id){
            return false;
        }
        $this->db->query("DELETE FROM ".API_DBTABLEPRE."ai_meeting_device WHERE deviceid in ($device_ids) AND mid = $meeting_id");
        return array();
    }

    //会议下的设备

    function listdevice($uid,$meeting_id){
        if(!$uid || !$meeting_id){
            return false;
        }

        $meeting = $this->db->fetch_first("SELECT mid as meeting_id,title,intro,dateline FROM ".API_DBTABLEPRE."ai_meeting WHERE mid=".$meeting_id);
        $result = $this->db->fetch_all("SELECT mid as meeting_id,deviceid,dateline FROM ".API_DBTABLEPRE."ai_meeting_device WHERE mid=".$meeting_id);
        $data = array();
        foreach ($result as $k=>$value){
            $data[$k]['meeting_id'] = $value['meeting_id'];
            $data[$k]['deviceid'] = $value['deviceid'];
            $data[$k]['dateline'] = $value['dateline'];
            $check = $this->in_use_device($uid,$meeting_id,$value['deviceid']);
            $data[$k]['in_use_device'] = $check ? 1 : 0;
        }
        return array(
            'meeting'=>$meeting,
            'list' => $data
        );
    }

    //是否该设备在其他会议中被使用
    function in_use_device($uid,$meeting_id,$deviceid){
        if(!$uid || !$meeting_id || !$deviceid){
            return false;
        }
        $result = $this->db->fetch_all("SELECT * FROM ".API_DBTABLEPRE."ai_meeting as m LEFT JOIN ".API_DBTABLEPRE."ai_meeting_device as md ON m.mid=md.mid WHERE m.mid<>'".$meeting_id."' AND md.deviceid='".$deviceid."'");
        if($result){
            return true;
        }
        return false;
    }

    //获取会议下所有签到人员信息表格

    function getexcel($uid,$meeting_id){
        if(!$uid || !$meeting_id){
            return false;
        }
        $meeting = $this->db->fetch_first("SELECT mid as meeting_id,title,intro,dateline FROM ".API_DBTABLEPRE."ai_meeting WHERE mid=".$meeting_id);
        $result = $this->db->fetch_all("SELECT name,remark,signin_time,signin_status FROM ".API_DBTABLEPRE."ai_meeting_member WHERE mid='".$meeting_id."' AND uid='".$uid."'");
        //生成excel
        $this->createexcel($result , $meeting);
    }

    function createexcel($data,$meeting){
        error_reporting(0);
        if(!$meeting){
            return false;
        }
        $name = $meeting['title'].'_'.date('Y-m-d').'.xls';
        include_once API_SOURCE_ROOT.'lib/PHPExcel.php';
        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();

// Set document properties
        $objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
            ->setLastModifiedBy("Maarten Balliauw")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("Test result file");


// Add some data
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '姓名')
            ->setCellValue('B1', '备注')
            ->setCellValue('C1', '签到时间')
            ->setCellValue('D1', '签到状态');
        //加粗居中
        $objPHPExcel->getActiveSheet()->getStyle('A1:C1')->applyFromArray(
            array(
                'font' => array (
                    'bold' => true
                ),
                'alignment' => array(
                    'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
                )
            )
        );
        if ($data){
            foreach ($data as $k => $v){
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('A'.($k+2), $v['name'])
                    ->setCellValue('B'.($k+2), $v['remark'])
                    ->setCellValue('C'.($k+2), $v['signin_time'] ? $this->base->time_format($v['signin_time'],'Y-m-d H:i:s') : 0)
                    ->setCellValue('D'.($k+2), $v['signin_status'] == 0 ? '未签到' : '已签到');
            }
        }
// Rename worksheet
        $objPHPExcel->getActiveSheet()->setTitle($meeting['title']);


// Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);


// Redirect output to a client’s web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.$name.'"');
        header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

// If you're serving to IE over SSL, then the following may be needed
        header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
        header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header ('Pragma: public'); // HTTP/1.0

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    function getmembercount($uid,$meeting_id){
        if(!$uid || !$meeting_id){
            return false;
        }
        $result_app_member = $this->db->fetch_first("SELECT count(*) as num FROM ".API_DBTABLEPRE."ai_meeting_member WHERE mid=".$meeting_id." AND source=1");
        $result_local_member = $this->db->fetch_first("SELECT count(*) as num FROM ".API_DBTABLEPRE."ai_meeting_member WHERE mid=".$meeting_id." AND source=0");
        return array(
            'app_member_count' => $result_app_member['num'],
            'local_member_count' => $result_local_member['num']
        );
    }

    function check_meeting_name($uid,$name) {
        if(!$name)
            return false;
        $result = $this->db->fetch_first("SELECT mid FROM ".API_DBTABLEPRE."ai_meeting WHERE uid=".$uid." AND title='".$name."'");
        if($result) {
            return FALSE;
        } else {
            return TRUE;
        }
    }
}
