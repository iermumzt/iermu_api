<?php
/**
 * Created by PhpStorm.
 * User: Punkqin
 * Date: 2017/9/18
 * Time: 14:52
 */
!defined('IN_API') && exit('Access Denied');

class hackathonmodel{
    var $base;
    var $db;

    function __construct(&$base) {
        $this->storemodel($base);
    }
    function storemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
    }

    /**
     * 上传文件到七牛
     * @param $uid
     * @param $deviceid
     * @param $text
     * @return array|bool
     */
    function upload($params) {
        if(!$params)
            return false;
        //人脸注册图片存储至七牛
        $storageid = 11;
        $storage = $this->base->load_storage($storageid);
        if (!$storage)
            return false;
        //获取uploadtoken
        $ip = $_SERVER['REMOTE_ADDR'];
        if(!$ip){
            return false;
        }
        $result = $storage->upload_hack_token($params);

        if(!$result)
            return false;
        //检测后缀名合法性
        $allowtype = array('doc','docx');
        $filename = $params['file']['name'];
        if(!$this->checkFileType($filename , $allowtype)){
            return false;
        }
        $file_temp = fopen($params['file']['tmp_name'], 'rb');
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
        $ret = $upload->putFile($result['uploadToken'], $result['filepath'], $params['file']['tmp_name']);
        if(!$ret[0]['key'] || $ret[0]['key'] != $result['filepath']){
            return false;
        }
        $data = "name = '".$params['name']."',email = '".$params['email']."', phone ='".$params['phone']."',mimetype ='".$params['file']['type']."', pathname = '".$result['pathname']."', filename = '".$result['filename']."',uploadname = '".$params['file']['name']."', size = '".$size."', storageid = '".$storageid."',ip = '".$ip."', dateline = '".time()."'";
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."hackathon_apply SET $data");
        $hackid = $this->db->insert_id();
        if(!$hackid){
            return false;
        }
        return array(
            'hack_id'  => $hackid
        );
    }

    /**
     * 获取附件地址
     */
    function getrealdoc($hackid){
        $storageid = 11;
        $storage = $this->base->load_storage($storageid);
        if (!$storage)
            return false;
        $result = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'hackathon_apply WHERE apply_id='.$hackid.' AND storageid = '.$storageid . ' ORDER BY dateline desc');
        if(!$result){
            return false;
        }
        $doc_url = $result['pathname'].$result['filename'];
        //获取水印实际地址
        $url = $storage->aiface_image($doc_url);
        if(!$url || empty($url)){
            //文件地址丢失
            return false;
        }
        return array(
            'doc_url' => $url,
        );
    }

    /* 检查上传的文件是否是合法的类型 */
    function checkFileType($filename , $allowtype) {
        $filetype = end(explode('.', $filename));
        if (in_array(strtolower($filetype), $allowtype)) {
            return true;
        }else {
            return false;
        }
    }

    function getca(){
        include_once API_SOURCE_ROOT.'lib/captcha.class.php';
        $ca = new SimpleCaptcha();
        $data = $ca->createImage();
        return $data;
    }
    /*
     * 检测验证码
     */
    function checkcode($maxwidth , $slidertime , $sliderwidth){
        if(!isset($slidertime) || $slidertime <= 0 ){
            return false;
        }
        if(!isset($sliderwidth) || !isset($maxwidth)){
            return false;
        }
        if($sliderwidth <= 0){
            return false;
        }
        return true;
    }
}