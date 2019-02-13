<?php

!defined('IN_API') && exit('Access Denied');

class factorycontrol extends base {

    function __construct() {
        $this->factorycontrol();
        $this->load('device');
    }

    function factorycontrol() {
        parent::__construct();
    }

    function onuploaddevicelist() {
        $this->init_input();
        $expire = $this->input['expire'];
        $sign = $this->input['sign'];
        $list = stripcslashes(rawurldecode($this->input['list']));

        if (!$expire || !$sign || !$list)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $factoryid = $this->_check_sign($sign, $expire);
        if ($factoryid === false)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $list = json_decode($list, true);

        $created = 0;
        $updated = 0;
        foreach ($list as $deviceinfo) {
            $deviceid = $deviceinfo['id'];
            $cloud = $deviceinfo['cloud'];
            $platform = $deviceinfo['platform'];
            $mainversion = $deviceinfo['main'];
            $subversion = $deviceinfo['sub'];
            $dateline = $deviceinfo['create'];
            $update = $deviceinfo['update'];

            $firmware = intval($mainversion) * 1000 + intval($subversion);

            $setarr = array();
            $setarr[] = 'cloud='.$cloud;
            $setarr[] = 'platform='.$platform;
            $setarr[] = 'firmware='.$firmware;
            $setarr[] = 'lastupdate='.$update;
            $setarr[] = 'factoryid='.$factoryid;

            $localinfo = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'device_factory WHERE deviceid="'.$deviceid.'"');
            if ($localinfo) {
                $sets = implode(',', $setarr);
                $this->db->query('UPDATE '.API_DBTABLEPRE."device_factory SET $sets WHERE deviceid='".$deviceid."'");
                $updated++;
            } else {
                $setarr[] = 'dateline='.$dateline;
                $setarr[] = 'deviceid="'.$deviceid.'"';
                $sets = implode(',', $setarr);
                $this->db->query('INSERT INTO '.API_DBTABLEPRE."device_factory SET $sets");
                $created++;
            }
        }

        $result = array();
        $result['created'] = $created;
        $result['updated'] = $updated;

        return $result;
    }

    function onsensorcode() {
        $this->init_input();
        $actionid = $this->input['actionid'];
        $type = $this->input['type'];
        $factory = $this->input['factory'];
        $model = $this->input['model'];
        $expire = $this->input['expire'];
        $sign = $this->input['sign'];
        
        if (!is_numeric($actionid) || !is_numeric($type) || !is_numeric($factory) || !is_numeric($model) || !$expire || !$sign)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);
        
        $factoryid = $this->_check_sign($sign, $expire);
        if ($factoryid === false)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);
        
        $form = $this->input['form'];
        if(!$form || !in_array($form, array('bar', 'qr'))) {
            $form = 'bar';
        }
        
        if($form == 'qr') {
            $json = $_ENV['device']->generate_433_json($actionid, $type, $factory, $model);
            if (!$json)
                $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GENERATE_SENSORCODE_FAILED);        

            $encoded = $this->compressAndEncrypt($json);
            if (!$encoded)
                $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GENERATE_SENSORCODE_FAILED);

            require API_SOURCE_ROOT.'lib/phpqrcode.php';
            QRcode::png($encoded, false, QR_ECLEVEL_M, 1);
        } else {
            $dpi = $this->input['dpi'];
            $width = $this->input['width'];
            $height = $this->input['height'];
            
            if(!$dpi) $dpi = 300;
            if(!$width) $width = 25;
            if(!$height) $height = 10;
            
            $code = $_ENV['device']->generate_433_code($actionid, $type, $factory, $model);
            if (!$code)
                $this->error(API_HTTP_INTERNAL_SERVER_ERROR, DEVICE_ERROR_GENERATE_SENSORCODE_FAILED); 
            
            require API_SOURCE_ROOT.'lib/barcode/BarcodeGenerator.php';
            require API_SOURCE_ROOT.'lib/barcode/BarcodeGeneratorPNG.php';

            $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
            header ('Content-type: image/png');
            echo $generator->getBarcode($code, $generator::TYPE_CODE_128, ($width/25.4)*$dpi/90, ($height/25.4)*$dpi);
        }
        
        exit();
    }

    function _check_sign($sign, $expire) {
        if(!$sign || !$expire)
            return false;

        if($expire < $this->time)
            return false;

        $sarr = split('-', $sign);
        if($sarr && is_array($sarr) && count($sarr)==3) {
            $factoryid = $sarr[0];
            $ak = $sarr[1];
            $rsign = $sarr[2];
            $sk = $this->db->result_first("SELECT factory_secret FROM ".API_DBTABLEPRE."factory WHERE factory_key='$ak'");
            if($sk) {
                if($rsign == md5($factoryid.$expire.$ak.$sk)) {
                    return $factoryid;
                }
            }
        }

        return false;
    }
}