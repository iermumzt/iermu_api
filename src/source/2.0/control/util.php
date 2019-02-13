<?php

!defined('IN_API') && exit('Access Denied');

class utilcontrol extends base {

    function __construct() {
        $this->utilcontrol();
    }

    function utilcontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('user');
        $this->load('device');
        $this->load('oauth2');  
    }

    // 获取m3u8文件状态
    function onfilestatus() {
        $this->init_input();
        $uri = rawurldecode($this->input('uri'));

        $result = array();
        $result['code'] = 404;
        $result['status'] = 0;

        $curl = curl_init($uri);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        $temp = curl_exec($curl);
        if ($temp !== false) {
            $result['code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($result['code'] == 200) {
                $result['status'] = 1;
            }
        }
        curl_close($curl);

        return $result;
    }
    
    function onqrcode() {
        $this->init_input();
        
        //二维码内容数据
        $data = stripcslashes(rawurldecode($this->input('data')));
        
        //容错率：L(7%)、M(15%)、Q(25%)、H(30%)，默认M，了解：http://baike.baidu.com/view/4144600.htm
        $level = strval($this->input('l'));
        if(!$level || !in_array($level, array('L', 'M', 'Q', 'H'))) $level = 'M';
        
        //二维码宽高（包含间距），为保证二维码更易识别，请尽量保持二维码为正方形，即宽高大致相等，默认200*200
        $size = intval($this->input('s'));
        if(!$size) $size = 200;
        
        //二维码图片边缘间距值，值越大，间距越宽，可自由调整，默认1
        $margin = $this->input('m');
        $margin = ($margin === NULL)?1:intval($margin);
        
        //是否添加logo
        $logo = 1;
        
        require API_SOURCE_ROOT.'lib/saeqrcode.class.php';
        
        $qr = new SaeQRcode();
        
        //设置二维码生成参数
        $qr->data   = $data;
        $qr->level  = $level;
        $qr->width  = $size;
        $qr->height = $size;
        $qr->margin = $margin;
        if($logo) {
            $qr->icon = API_ROOT.'images/qrlogo.png';
        }
        
        //生成二维码图片，成功返回文件绝对地址（放在了QR_TMP_PATH），失败返回false
        $file = $qr->build();
        if (!$file) {
         	var_dump($qr->errno(), $qr->errmsg());
         	exit;
        }

        //直接输出图片
        header('Content-Type: image/png');
        $data = file_get_contents($file);
        @unlink($file);
        exit($data);
    }
    
    function onseccodeinit() {
        return $this->seccode_init();
    }
    
    function onseccode() {
        $code = getgpc('code');
        $seccode = $this->decode_seccode($code);
        if(!$seccode){
            $seccode = 'eror';
        }

        @header("Expires: -1");
        @header("Cache-Control: no-store, private, post-check=0, pre-check=0, max-age=0", FALSE);
        @header("Pragma: no-cache");
        // include_once PASSPORT_SOURCE_ROOT.'lib/seccode.class.php';
        // $code = new seccode();
        // $code->code = $seccode;
        // $code->type = 0;
        // $code->width = 110;
        // $code->height = 40;
        // $code->background = 1;
        // $code->adulterate = 1;
        // $code->ttf = 1;
        // $code->angle = 1;
        // $code->color = 1;
        // $code->size = 0;
        // $code->shadow = 1;
        // $code->animator = 0;
        // $code->fontpath = PASSPORT_ROOT.'images/fonts/';
        // $code->datapath = PASSPORT_ROOT.'images/';
        // $code->includepath = '';
        // $code->display();
        
        include_once API_SOURCE_ROOT.'lib/captcha.class.php';
        $captcha = new SimpleCaptcha();
        $captcha->width = 135;
        $captcha->height = 40;
        $captcha->scale = 2;
        $captcha->maxRotation = 3;
        $captcha->colors = array(array(27, 78, 181));
        $captcha->fonts = array(
            'Antykwa'   => array('spacing' => 1.5, 'minSize' => 16, 'maxSize' => 17, 'font' => 'AntykwaBold.ttf'),
            'Duality'   => array('spacing' => 1.5, 'minSize' => 18, 'maxSize' => 19, 'font' => 'Duality.ttf'),
            'Heineken'  => array('spacing' => 1.5, 'minSize' => 18, 'maxSize' => 19, 'font' => 'Heineken.ttf'),
            'Jura'      => array('spacing' => 1.5, 'minSize' => 18, 'maxSize' => 19, 'font' => 'Jura.ttf'),
            'StayPuft'  => array('spacing' => 1.5, 'minSize' => 18, 'maxSize' => 19, 'font' => 'StayPuft.ttf'),
            'VeraSans'  => array('spacing' => 1.5, 'minSize' => 14, 'maxSize' => 15, 'font' => 'VeraSansBold.ttf'),
        );
        
        $captcha->createImage($seccode);
    }
    
    function oncheckseccode() {
        $seccodehidden = rawurldecode(getgpc('seccodehidden', 'G'));
        $seccode = strtoupper(getgpc('seccode', 'G'));

        $seccodehidden = $this->decode_seccode($seccodehidden);

        if(empty($seccodehidden) || strtoupper($seccodehidden) !== $seccode) {
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_SECCODE);
        }
           
        return array('seccode' => $seccode);
    }
    
}
