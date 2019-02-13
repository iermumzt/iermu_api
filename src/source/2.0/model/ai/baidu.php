<?php
!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/ai/ai.php';

class baiduai extends ai {
    public $AppID;
    public $ak;
    public $sk;
    public $token;
    public $ai_type;

    public function __construct(&$base, $service){
        $this->baiduai_config($base, $service);
    }
    public function baiduai_config(&$base, $service){
        parent::__construct($base, $service);

        $ai_config = unserialize($service['ai_config']);
        $this->AppID = $ai_config['AppID'];
        $this->ak = $ai_config['API Key'];
        $this->sk = $ai_config['Secret Key'];
        $this->token = $service['access_token'];
        $this->ai_type = $service["ai_type"];
        if(time() >= $service['expires'] || !$this->token){
            $token = $this->access_token();
            $expires = time()+$token['expires_in'];
            $this->token = $token['access_token'];
            $this->db->query('UPDATE '.API_DBTABLEPRE.'ai_service SET access_token="'.$token['access_token'].'",expires="'.$expires.'", refresh_token="'.$token['refresh_token'].'" WHERE ai_id="'.$this->ai_id.'" AND ai_type="'.$this->ai_type.'"');
        }
    }

    /**
     * 发起http post请求(REST API), 并获取REST请求的结果
     * @param string $url
     * @param string $param
     * @return - http response body if succeeds, else false.
     */
    function request_post($url = '', $param = '')
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
        // 要求结果为字符串且输出到屏幕上
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        // post提交方式
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
        // 运行curl
        $data = curl_exec($curl);
        curl_close($curl);

        return $data;
    }

    function access_token() {
        $url = 'https://aip.baidubce.com/oauth/2.0/token';

        $post_data = array();
        $post_data['grant_type']  = 'client_credentials';
        $post_data['client_id']   = $this->ak;
        $post_data['client_secret'] = $this->sk;

        $res = $this->request_post($url, $post_data);
        if ($res) {
            $res = json_decode($res, true);
            return $res;
        } else {
            return false;
        }
    }

    function face_register($path, $uid, $user_info='', $group='group_1', $imageid=0, $form = "net"){
        $uid = 'face_'.$uid;
        $token = $this->token;
        $url = 'https://aip.baidubce.com/rest/2.0/face/v2/faceset/user/add?access_token=' . $token;
        $img = file_get_contents($path);
        $img = base64_encode($img);
        $bodys = array(
            'uid' => $uid,
            'ser_info' => $user_info,
            'group_id' => $group,
            'images' => $img
        );
        $res = $this->request_post($url, $bodys);
        $res = json_decode($res, true);
        if(isset($res['error_code']) && $res['error_code'] != 0){
            $this->_error($res);
        }
        if($res['log_id']){
            return $uid;
        }
    }
    //人脸识别 返回识别到的user_id
    function face_identfy($filepath, $groupid='group_1') {
        $token = $this->token;
        $url = 'https://aip.baidubce.com/rest/2.0/face/v2/identify?access_token=' . $token;
        $img = file_get_contents($filepath);
        $img = base64_encode($img);
        $bodys = array(
            "images" => $img,
            "group_id" => $groupid
        );
        $res = $this->request_post($url, $bodys);
        $res = json_decode($res, true);
        if(isset($res['error_code']) && $res['error_code'] != 0 && $res['error_code']!='216618'){
            $this->_error($res);
        }
        if(isset($res['error_code']) && $res['error_code']=='216618'){
            //no user in group
            return false;
        }
        $this->base->log('face_identfy baidu', 'info='.json_encode($res));

        $scores = 0;
        if($res['result']){
            $baidu_scores = $res['result'][0]['scores'][0];
            if($baidu_scores >= 70){
                $scores = $baidu_scores;
                $ai_user_id = $res['result'][0]['uid'];
                if(substr($ai_user_id, 0, 4) == '_cms'){
                    $len = strrpos($ai_user_id, '_', 0)-strlen($ai_user_id);
                    $ai_user_id = substr($ai_user_id, 4, $len);
                }
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

        var_dump($res);
    }
    //人脸删除
    function face_del($uid) {
        $token = $this->token;;
        $url = 'https://aip.baidubce.com/rest/2.0/face/v2/faceset/user/delete?access_token=' . $token;
        $bodys = array(
            "uid" => $uid,
        );
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

        var_dump($res);
    }
    //人脸检测
    function face_detect($img){
        $token = $this->token;
        $url = 'https://aip.baidubce.com/rest/2.0/face/v1/detect?access_token=' . $token;
        $img = file_get_contents($img);
        $img = base64_encode($img);
        $bodys = array(
            "image" => $img,
            "face_fields" => "age,beauty,expression,gender,glasses,race,qualities",
            "max_face_num" => 2,
        );
        $res = $this->request_post($url, $bodys);
        $dectect = json_decode($res, true);
        if(isset($dectect['error_code']) && $dectect['error_code'] != 0){
            $this->_error($dectect);
        }

        if($dectect['result_num']!=1){
            if($dectect['result_num']==0)
                $this->base->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_NO_EXIST);
            if($dectect['result_num']==2)
                $this->base->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_MANY);
        }
        $dectect_result = $dectect['result'][0];
        if($dectect_result['face_probability'] < 0.7){
            $this->base->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_BLUR);
        }
        $a = -20;
        if($dectect_result['yaw']>50 || $dectect_result['yaw']< $a)
            $this->base->error(API_HTTP_BAD_REQUEST, USER_ERROR_FACE_TOO_BLUR);
        return $dectect_result;
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
