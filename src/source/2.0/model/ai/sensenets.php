<?php
//深网本地存储
!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/ai/ai.php';

class sensenetsai extends ai {
    public $domain;
    public $ai_type;

    public function __construct(&$base, $service){
        $this->sensenetsai_config($base, $service);
    }
    public function sensenetsai_config(&$base, $service){
        parent::__construct($base, $service);
        $ai_config = unserialize($service['ai_config']);
        $this->domain = $ai_config['domain'];
        $this->ai_type = $service["ai_type"];
        if(!$this->domain || !$this->ai_type)
            return false;
    }

    /**
     * 发起http post请求(REST API), 并获取REST请求的结果
     * @param string $url
     * @param string $param
     * @return - http response body if succeeds, else false.
     */
    function request_post($url = '', $param = '', $header = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }

        $postUrl = $url;
        $curlPost = $param;
        // 初始化curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $postUrl);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        if($header){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($curl,CURLOPT_BINARYTRANSFER,true); 
        // 要求结果为字符串且输出到屏幕上
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // post提交方式
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        // var_dump(curl_errno($curl));
        // var_dump(curl_error($curl));
        // 运行curl
        $data = curl_exec($curl);
        curl_close($curl);

        return $data;
    }

    function face_register($path, $uid, $user_info='', $group=5, $imageid=0, $from = "file"){
        $baseid = intval($group); //目标库 id，整数
        $group = $this->db->fetch_first("SELECT * FROM ".API_DBTABLEPRE."ai_face_group WHERE group_id=$group");
        if(!$group || !$group['name'])
            return false;

        $basename = $group['name']; //目标库名称

        // 默认使用dynamic库
        $type = "dynamic"; //目标库类型:static:静态库，dynamic:动态库
        if($group['ai_group_type'] == 'static') $type = "static";
        
        // $getfeature = 0; //是否返回特征信息，1:返回特征，0:不返回，默认为 0 
        // $qualitythreshold = 75; //人脸检测质量阈值， 默认为 0，整数 0-100
        // $from = "net";//表示图片来源方式，file:表示文件上传，net:表示网络链接
        $params = "?baseid=".$baseid."&basename=".$basename."&type=".$type."&from=".$from;
        $url = $this->domain."base/person/import".$params;
        $personId1 = intval($uid); //人员 id，必须有, 整数
        $imageId1 = intval($imageid); //图片 id， 必须有，整数
        $image1 = $path; //File, 上传的文件， 支持 jpg, png, jpeg, bmp
        $this->base->log('face_register sensenets personId1', 'personId1='.$personId1);

        if($from == "file"){
            // $fp      = fopen($path, 'rb');
            // $content = fread($fp, filesize($path)); //二进制数据
            // new CurlFile($file)
            // $_FILES["file"]

            $bodys = array(
                'personId1' => $personId1,
                'imageId1' => $imageId1,
                'image1' => new CurlFile($image1)
            );
            $header = array("ContentType:multipart/form-data");
        }else{
            $bodys = array(
                "imagedatas" => array(
                    array(
                        'personId' => $personId1,
                        'imageId' => $imageId1,
                        'uri' => $image1
                        // "uri" => "http://bpic.588ku.com/element_origin_min_pic/00/00/07/2157907f9e3baed.jpg"
                    )
                ),
            );
            $bodys = json_encode($bodys);
            $header = array("ContentType:application/json");
        }
        $res = $this->request_post($url, $bodys, $header);
        $res = json_decode($res, true);
        $this->base->log('face_register sensenets', 'info='.json_encode($res));
        if($res['errcode']!= 0){
            $this->_error($res);
        }
        if(isset($res['content']['success'][0]) && $res['errcode']==0){
            return $res['content']['success'][0]['personId']; //$personId1;
        }
        return false;
    }
    function face_identfy($filepath, $groupid=1, $type ="static"){
        if($type == "static"){
            return $this->face_identfy_static($filepath, $groupid);
        }else{
            return $this->face_identfy_dynamic($filepath, $groupid);
        }
    }
    //人脸识别 返回识别到的user_id  静态库检索
    function face_identfy_static($filepath, $groupid=1, $from = "file") {
        $score = 75;
        $groupname = $this->db->result_first("SELECT name FROM ".API_DBTABLEPRE."ai_face_group WHERE group_id=$groupid");
        if(!$groupname)
            return false;
        $bases = intval($groupid).",".$groupname;
        $params = "?bases=".$bases."&limit=10&score=".$score."&from=".$from;
        $url = $this->domain."face/image/search".$params;

        if($from == "file"){
            $header = array("ContentType:multipart/form-data");
            $bodys = array(
                "image" => new CurlFile($filepath) //$_FILES["image"]
            );
        }else{
            $header = array("ContentType:application/json");
            $bodys = array(
                "uri"=> $filepath,
                "rect" => ""
            );
            $bodys = json_encode($bodys);
        }
        $res = $this->request_post($url, $bodys, $header);
        $res = json_decode($res,true);
        $this->base->log('face_identfy sensenets static', 'info='.json_encode($res));
        if($res['errcode']!= 0){
            $this->_error($res);
        }
        return $this->faceinfo_byidentfy($res);
    }
    //人脸识别 返回识别到的user_id  静态库检索
    function face_identfy_dynamic($filepath, $groupid=1, $from="file") {
        $score = 75;
        $groupname = $this->db->result_first("SELECT name FROM ".API_DBTABLEPRE."ai_face_group WHERE group_id=$groupid");
        if(!$groupname)
            return false;
        $bases = intval($groupid).",".$groupname;
        $params = "?bases=".$bases."&limit=10&score=".$score."&from=".$from;
        $url = $this->domain."face/image/watch".$params;

        if($from == "file"){
            $header = array("ContentType:multipart/form-data");
            $bodys = array(
                'image' => new CurlFile($filepath)
            );
        }else{
            $header = array("ContentType:application/json");
            $bodys = array(
                "uri"=> $filepath,
            );
            $bodys = json_encode($bodys);
        }
        $res = $this->request_post($url, $bodys);
        $res = json_decode($res, true);
        $this->base->log('face_identfy sensenets dynamic', 'info='.json_encode($res));
        if($res['errcode']!= 0){
            $this->_error($res);
        }
        return $this->faceinfo_byidentfy($res);

    }
    function faceinfo_byidentfy($rets){
        $scores = 0;
        if($rets['content']['result']){
            if(count($rets['content']['result'])==0)
                return true;
            $result = $rets['content']['result'];
            $sen_scores = $result[0]['score'];
            if($sen_scores >= 77){
                $scores = $sen_scores;
                $ai_user_id = $result[0]['personId'];
                $_face_id = $this->db->result_first('SELECT _face_id FROM '.API_DBTABLEPRE.'ai_face_user WHERE user_id="'.$ai_user_id.'" AND ai_id ="'.$this->ai_id.'"');
                //处理 非app上传人脸
                if(!$_face_id){
                    $this->db->query("INSERT INTO ".API_DBTABLEPRE."ai_face VALUES ()");
                    $_face_id = $this->db->insert_id();
                }
                return array('ai_user_id' => $ai_user_id, '_face_id' => $_face_id, 'scores' => $scores);
            }
        }
        return false;
    }
    //人脸更新
    function face_update() {
        $token = '#####调用鉴权接口获取的token#####';
        $url = 'https://aip.baidubce.com/rest/2.0/face/v2/faceset/user/update?access_token=' . $token;
        $img = file_get_contents('########本地文件路径########');
        $img = base64_encode($img);
        $bodys = array(
            "images" => $img,
            "uid" => "testuid",
            "user_info" => "ww",
            "group_id" => "group1",
            "action_type" => true
        );

        $res = $this->request_post($url, $bodys);

    }
    //人脸删除
    function face_del($uid, $imageid=0) {
        $params = "?baseid=5&basename=swaintest&type=static&personid=".intval($uid)."&imageid=".intval($imageid);
        $url = $this->domain."base/person/delete".$params;
        $res = $this->request_post($url, $bodys);
        return json_decode($res);
    }
    //人脸信息查询
    function face_info() {
        $token = '#####调用鉴权接口获取的token#####';
        $url = 'https://aip.baidubce.com/rest/2.0/face/v2/faceset/user/get?access_token=' . $token;
        $bodys['uid'] = 'uid';
        $bodys['group_id'] = 'gid';
        $res = $this->request_post($url, $bodys);

    }
    //人脸检测
    function face_detect($img, $from = "file"){
        // attr: 是否返回属性信息, 1: 返回特征， 0:不返回，默认 0 
        // getfeature: 是否返回特征信息，1:返回特征，0:不返回，默认 0 
        // from: 表示图片来源方式，file:表示文件上传，net:表示网络连接
        $params = "?attr=1&getfeature=1&from=".$from;
        $url = $this->domain."face/detect".$params;

        if($from == "file"){
            $header = array("ContentType:multipart/form-data");
            $bodys = array(
                "image1" => new CurlFile($img),
            );
        }else{
            $header = array("ContentType:application/json");
            $bodys = array(
                "imagedatas" => array(
                    array(
                        'uri' => $img
                    )
                ),
            );
            $bodys = json_encode($bodys);
        }

        $res = $this->request_post($url, $bodys, $header);
        $dectect = json_decode($res,true);
        $this->base->log('face_dectect sensenets', 'info='.json_encode($dectect));

        if($dectect['content']['fail'] && $dectect['content']['fail'][0]['failcode'] != 0){
            $ret['errcode'] = $dectect['content']['fail'][0]['failcode'];
            $ret['errmsg'] = $dectect['content']['fail'][0]['failmsg'];
            $this->_error($ret);
        }
        if($dectect['errcode']!= 0){
            $this->_error($dectect);
        }
        $result = $dectect['content']['success'][0]['result'][0];
        /*
        $score = intval($result['quality']);
        if($score < 85)
            $this->base->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_BLUR);
            */
        $attribute = $result['attribute'];
        if($attribute){
            $result['age'] = intval($attribute['age']);
            $result['glasses'] = intval($attribute['eyeglass']);
            $result['gender'] = ($attribute['gender']==0) ? "male":"female";
            $result['expression'] = intval($attribute['smile']);
            $result['beauty'] = intval($attribute['attractive']);
            if($attribute['sunglass']==1){
                $result['glasses']=2;
            }
            $result['race'] = 0; //种族
        }
        return $result;
    }

    function _error($error, $extras=NULL) {
        if($error && $error['errcode']) {
            $error_code = $error['errcode'];

            if(isset($errors[$error_code])) {
                $api_status = $errors[$error_code][0];
                $api_error = $errors[$error_code][1];
            } else {
                $api_status = API_HTTP_BAD_REQUEST;
                $api_error = $error['errcode'].':'.$error['errmsg'];
            }
        } else {
            $api_status = API_HTTP_INTERNAL_SERVER_ERROR;
            $api_error = CONNECT_ERROR_API_FAILED;
        }
        if($extras === NULL) $extras = array();
        $extras['ai_type'] = $this->ai_type;
        $this->base->error($api_status, $api_error, NULL, NULL, NULL, $extras);
    }

}
