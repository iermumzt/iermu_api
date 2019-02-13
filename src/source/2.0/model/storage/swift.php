<?php

!defined('IN_API') && exit('Access Denied');

require_once API_SOURCE_ROOT.'model/storage/storage.php';

require_once API_SOURCE_ROOT.'lib/ceph-swift-client/vendor/autoload.php';

class swiftstorage  extends storage {
    public $client;

    function __construct(&$base, $service) {
        $this->swiftstorage($base, $service);
    }

    function swiftstorage(&$base, $service) {
        parent::__construct($base, $service);
    }

    function get_client() {
        if(!$this->client) {
            $config = [
                'host' => $this->storage_config['host'],
                'auth-user' => $this->storage_config['auth_user'],
                'auth-key' => $this->storage_config['auth_key'],
                'temp-url-key' => $this->storage_config['temp_url_key']
            ];
            $this->client = $client = new \Liushuangxi\Ceph\SwiftClient($config);
        }
        return $this->client;
    }

    function upload($container, $object, $file){
        $client = $this->get_client();
        if(!$client->container()->isExistContainer($container))
            $client->container()->createContainer($container, "");
        $client->object()->createObject($container, $file, $object);

        $face['pathname'] = $container;
        $face['filename'] = $object;
        return $face;
    }

    function aiface_image($key){
        $expires_in = 6000;

        if(!$key)
            return "";
        
        $url = "";

        $client = $this->get_client();
        if(!$client)
            return "";
        $url = $client->url()->tempUrl($key, $expires_in);

        return $url;
    }
    
    function temp_url($params) {
        $container = $params['container'];
        $object = $params['object'];
        $filename = $params['filename'];
        $expires_in = $params['expires_in'];
        $local = $params['local'];

        if(!$container || !$object)
            return "";
        
        $url = "";
        
        if($local && $this->storage_config['storage_url_local']) {
            $storage_url =  $this->storage_config['storage_url_local'];
        } else {
            $storage_url =  $this->storage_config['storage_url'];
        }
        
        $temp_url_key =   $this->storage_config['temp_url_key'];
        $temp_url_prefix =   $this->storage_config['temp_url_prefix'];
        $temp_url_validtime =   $this->storage_config['temp_url_validtime'];

        if(!$storage_url || !$temp_url_key) {
           return "";
        }

        if(!$expires_in)
           $expires_in = $temp_url_validtime?$temp_url_validtime:STORAGE_TEMP_URL_DEFAULT_VALIDTIME;

        /*
        $method = "GET";
        $expires = $this->base->time + $expires_in;
        $path = $temp_url_prefix.$container."/".$object;

        $hmac_body = $method."\n".$expires."\n".$path;

        $sig = hash_hmac("sha1", $hmac_body, $temp_url_key);

        $url = $storage_url."/".$container."/".$object."?temp_url_sig=".$sig."&temp_url_expires=".$expires;
        */
        $client = $this->get_client();
        if(!$client)
            return "";
        
        $key = $container."/".$object;
        $url = $client->url()->tempUrl($key, $expires_in);
        if($filename) $url .=  "&filename=".$filename;

        return $url;
    }
    function faceupload($uid, $file, $black=""){
        $filepath = "face_".$uid;
        if($black){
            $filename = "image_".time()."_black.jpg";
        }else{
            $filename = "image_".time().".jpg";
        }
        $face = $this->upload($filepath, $filename, $file);
        if($face)
            return $face;
    }
    
}
