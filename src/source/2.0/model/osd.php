<?php
/**
 * Created by PhpStorm.
 * User: Punkqin
 * Date: 2017/9/18
 * Time: 14:52
 */
!defined('IN_API') && exit('Access Denied');

class osdmodel{
    var $base;
    var $db;

    function __construct(&$base) {
        $this->storemodel($base);
    }
    function storemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
        $this->base->load('device');
    }

    /**
     * 创建水印，上传七牛
     * @param $uid
     * @param $deviceid
     * @param $text
     * @return array|bool
     */
    function uploadwatermark( $deviceid ,$uid)
    {
        if(!$deviceid || !$uid)
            return false;

        $text = $this->getosdtext($deviceid ,$uid);
        include_once API_SOURCE_ROOT.'lib/watermark.class.php';
        $watermark = new SimpleWatermark();
        $data = $watermark->createImage($text);
        //图片存储至七牛
        $storageid = 11;
        $storage = $this->base->load_storage($storageid);
        if (!$storage)
            return false;
        //获取uploadtoken
        $file = $storage->upload_watermark_token($deviceid);
        if(!$file)
            return false;

        $uptoken = $file['uploadToken'];
        $key = $file['filepath'];

        include_once API_SOURCE_ROOT.'lib/qiniuUpload.class.php';
        $upload = new UploadManager();
        $ret = $upload->put($uptoken, $key, $data);

        if(!$ret[0]['key'] || $ret[0]['key'] != $file['filepath']){
            return false;
        }
        //获取水印实际地址
        $image_url = $storage->aiface_image($file['filepath']);

        //获取文件大小
        $size =  strlen($data);

        //加入water_image表记录
        $data = "deviceid ='".$deviceid."' , uid ='".$uid."', pathname = '".$file['pathname']."', filename = '".$file['filename']."', size = '".$size."', storageid = '".$storageid."', dateline = '".time()."', description = '".$text."'";
        $this->db->query("INSERT INTO ".API_DBTABLEPRE."watermark_image SET $data");
        $imageid = $this->db->insert_id();
        if(!$imageid){
            return false;
        }
        //获取水印实际地址
        return array(
            'image_url' => $image_url,
        );
        return $image_url;
    }

    /**
     * 获取水印地址(加入存储)
     */
    //function getrealwatermark($deviceid , $uid){
    //    if(!$deviceid || !$uid)
    //        return false;
    //
    //    $storageid = 11;
    //    $storage = $this->base->load_storage($storageid);
    //    if (!$storage)
    //        return false;
    //    $result = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'watermark_image WHERE uid='.$uid.' AND deviceid='.$deviceid . ' AND storageid = '.$storageid . ' ORDER BY dateline desc');
    //
    //    //若不存在则上传水印
    //    if(!$result){
    //        $res = $this->uploadwatermark($deviceid ,$uid);
    //        if (!$res){
    //            return false;
    //        }
    //        return $res;
    //    }
    //    $image_url = $result['pathname'].$result['filename'];
    //    //获取水印实际地址
    //    $image_url = $storage->aiface_image($image_url);
    //    if(!$image_url || empty($image_url)){
    //        //文件地址丢失
    //        return false;
    //    }
    //    return array(
    //        'image_url' => $image_url,
    //    );
    //}

    /**
     * 获取水印文字等信息（取设备description）
     */
    function getosdtext($deviceid){
        if(!$deviceid)
            return false;

        $device = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device WHERE deviceid='.$deviceid);
        if(!$device){
            return false;
        }
        return $device['desc'];
    }

    /**
     * 删除水印操作
     */

    function dropwatermark($deviceid , $uid){
        if(!$deviceid || !$uid)
            return false;

        $result = $this->db->query("DELETE FROM ".API_DBTABLEPRE."watermark_image WHERE uid='".$uid."' AND deviceid=".$deviceid);
         if(!$result){
            return false;
         }
         return true;
    }

    /**
     * 动态获取水印（不加入存储）
     */

    function getosd($deviceid , $type ,$alpha,$textcolor,$fontsize){
        if(!$deviceid)
            return false;

        $text = $this->getosdtext($deviceid);
        include_once API_SOURCE_ROOT.'lib/osd.class.php';
        $osd = new SimpleOsd();
        if(isset($alpha) && !empty($alpha)) {
            $osd->alpha = $alpha;
        }
        if(isset($textcolor) && !empty($textcolor)){
            $osd->textcolor =$textcolor;
        }
        if(isset($fontsize) && !empty($fontsize)){
            $osd->fontSize = $fontsize;
        }
        $data = $osd->createImage($text);
        return $data;
    }
}