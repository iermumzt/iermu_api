<?php
/**
 * Captcha class
 */
class SimpleOsd
{
    /** Width of the image */
    public $width = 300;

    /** Height of the image */
    public $height = 300;

    /**
     * Path for resource files (fonts, words, etc.)
     *
     * "resources" by default. For security reasons, is better move this
     * directory to another location outise the web server
     *
     */
    public $resourcesPath = 'watermark';
    /** Sessionname to store the original text */
    // public $sessionVar = 'captcha';

    /** Background color in RGB-array */
    public $backgroundColor = array(122, 46, 155);

    //背景颜色
    public $bgcolor = array(0, 0, 0);

    //文本颜色
    public $textcolor = array(255,255,255);

    //文本颜色
    public $fontSize = 20;

    /** Shadow color in RGB-array or null */
    //阴影效果
    public $shadowColor = null; //array(0, 0, 0);

    //透明度

    public $alpha = 50;

    /**
     * Font configuration
     *
     * - font: TTF file
     * - spacing: relative pixel space between character
     * - minSize: min font size
     * - maxSize: max font size
     */
    public $fonts = array(
        'kt'   => array('spacing' => -3,   'minSize' => 50, 'maxSize' => 50, 'font' => 'kt_GB2312.ttf')
    );

    /** Debug? */
    public $debug = false;

    /** Image format: bmp or png */
    public $imageFormat = 'bmp';

    /** GD image */
    public $im;

    public function __construct($config = array())
    {
        $this->resourcesPath = __DIR__ . '/' . $this->resourcesPath;
    }

    public function createImage($text)
    {
        if (empty($fontcfg)) {
            $fontcfg = $this->fonts['kt'];
        }

        // 导入字体文件 《楷体》
        $font = $this->resourcesPath . '/fonts/' . $fontcfg['font'];

        $text_string    = $text;
        $font_ttf        = $font;
        $font_size        = $this->fontSize;
        $text_angle        = 0;
        $text_padding    = 0; // Img padding - around text

        //$the_box        = $this->calculateTextBox($text_string, $font_ttf, $font_size, $text_angle);
        $the_box        = $this->calculateTextBox1($text_string, $font_ttf, $font_size, $text_angle);
        //var_dump($the_box);
        //var_dump($the_box1);
        //die;
        $imgWidth    = $the_box["width"] + $text_padding;
        //确保转16位bmp不出错
        $imgHeight    = $the_box["height"] + $text_padding;
        $this->width = $imgWidth;
        $this->height = $imgHeight;

        // 创建画布
        $im  =  imagecreatetruecolor ( $this->width ,  $this->height );

        $this->im = $im;

        // Create some colors
        $bgcolor  =  imagecolorallocatealpha ( $im ,  $this->bgcolor[0] ,  $this->bgcolor[1] ,  $this->bgcolor[2] , 0);//背景颜色
        $textcolor  =  imagecolorallocatealpha ( $im ,  $this->textcolor[0] ,  $this->textcolor[1] ,  $this->textcolor[2]  ,$this->alpha); //字体效果
        
        //填充背景
        imagefilledrectangle ( $im ,  0 ,  0 ,  $this->width ,  $this->height ,  $bgcolor );
        imagettftext($im,
            $font_size,
            $text_angle,
            $the_box["left"] + ($this->width / 2) - ($the_box["width"] / 2),
            $the_box["top"] + ($this->height / 2) - ($the_box["height"] / 2),
            $textcolor,
            $font_ttf,
            $text_string);
        //转换为bpm格式
        $result = $this->imageBmp($im, 1 , 0);
        return $result;
    }

    /**
     * Creates the image type Bmp
     */
    protected function imageBmp(&$im, $bit = 1, $compression = 0)
    {
        if (!in_array($bit, array(1, 4, 8, 16, 24, 32)))
        {
            $bit = 8;
        }
        else if ($bit == 32) // todo:32 bit
        {
            $bit = 24;
        }
        $bits = pow(2, $bit);

        // 调整调色板
        imagetruecolortopalette($im, true, $bits);
        $width  = imagesx($im);
        $height = imagesy($im);
        $colors_num = imagecolorstotal($im);
        if ($bit <= 8)
        {
            // 颜色索引
            $rgb_quad = '';
            for ($i = 0; $i < $colors_num; $i ++)
            {
                $colors = imagecolorsforindex($im, $i);
                $rgb_quad .= chr($colors['blue']) . chr($colors['green']) . chr($colors['red']) . "\\0";
            }

            // 位图数据
            $bmp_data = '';

            // 非压缩
            if ($compression == 0 || $bit < 8)
            {
                if (!in_array($bit, array(1, 4, 8)))
                {
                    $bit = 8;
                }
                $compression = 0;

                // 每行字节数必须为4的倍数，补齐。
                $extra = '';
                //$padding = 8 - ceil($width / (8 / $bit)) % 8;
                //if ($padding % 8 != 0)
                //{
                //    $extra = str_repeat("\\0", $padding);
                //}

                for ($j = $height - 1; $j >= 0; $j --)
                {
                    $i = 0;
                    while ($i < $width)
                    {
                        $bin = 0;
                        $limit = $width - $i < 8 / $bit ? (8 / $bit - $width + $i) * $bit : 0;

                        for ($k = 8 - $bit; $k >= $limit; $k -= $bit)
                        {
                            $index = imagecolorat($im, $i, $j);
                            $bin |= $index << $k;
                            $i ++;
                        }

                        $bmp_data .= chr($bin);
                    }

                    $bmp_data .= $extra;
                }
            }
            // RLE8 压缩
            else if ($compression == 1 && $bit == 8)
            {
                for ($j = $height - 1; $j >= 0; $j --)
                {
                    $last_index = "\\0";
                    $same_num   = 0;
                    for ($i = 0; $i <= $width; $i ++)
                    {
                        $index = imagecolorat($im, $i, $j);
                        if ($index !== $last_index || $same_num > 255)
                        {
                            if ($same_num != 0)
                            {
                                $bmp_data .= chr($same_num) . chr($last_index);
                            }

                            $last_index = $index;
                            $same_num = 1;
                        }
                        else
                        {
                            $same_num ++;
                        }
                    }

                    $bmp_data .= "\\0\\0";
                }

                $bmp_data .= "\\0\\1";
            }
            $size_quad = strlen($rgb_quad);
            $size_data = strlen($bmp_data);
        }
        else
        {
            // 每行字节数必须为4的倍数，补齐。
            $extra = '';
            $padding = 4 - ($width * ($bit / 8)) % 4;
            if ($padding % 4 != 0)
            {
                $extra = str_repeat("\\0", $padding);
            }
            // 位图数据
            $bmp_data = '';
            for ($j = $height - 1; $j >= 0; $j --)
            {
                for ($i = 0; $i < $width; $i ++)
                {
                    $index  = imagecolorat($im, $i, $j);
                    $colors = imagecolorsforindex($im, $index);
                    if ($bit == 16)
                    {
                        $bin = 0 << $bit;

                        $bin |= ($colors['red'] >> 3) << 10;
                        $bin |= ($colors['green'] >> 3) << 5;
                        $bin |= $colors['blue'] >> 3;

                        $bmp_data .= pack("v", $bin);
                    }
                    else
                    {
                        $bmp_data .= pack("c*", $colors['blue'], $colors['green'], $colors['red']);
                    }

                    // todo: 32bit;
                }
                $bmp_data .= $extra;
            }
            $size_quad = 0;
            $size_data = strlen($bmp_data);
            $colors_num = 0;
        }
        // 位图文件头
        $file_header = "BM" . pack("V3", 54 + $size_quad + $size_data, 0, 54 + $size_quad);

        // 位图信息头
        $info_header = pack("V3v2V*", 0x28, $width, $height, 1, $bit, $compression, $size_data, 0, 0, $colors_num, 0);
        $status_data = 'ok';
        $dataWidth = $width;
        $dataHeight = $height;
        if(!$bmp_data){
            $status_data = 'error';
            $size_data = 0;
            $dataWidth = 0;
            $dataHeight = 0;
        }
        header("Content-type: image/bmp");
        header("Data-status: ".$status_data);
        header("Data-width: ".$dataWidth);
        header("Data-height: ".$dataHeight);
        //返回bmp文件信息
        $filesinfo = $file_header . $info_header.$rgb_quad.$bmp_data;
        header("Data-size: ".strlen($filesinfo));
        return $filesinfo;
    }
    /**
     * cleanup
     */
    protected function cleanup()
    {
        imagedestroy($this->im);
    }

    //获取文本边框大小（画布大小）

    function calculateTextBox($text,$fontFile,$fontSize,$fontAngle) {
        /************
        simple function that calculates the *exact* bounding box (single pixel precision).
        The function returns an associative array with these keys:
        left, top:  coordinates you will pass to imagettftext
        width, height: dimension of the image you have to create
         *************/
        $rect = imagettfbbox($fontSize,$fontAngle,$fontFile,$text);
        $minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
        $maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
        $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
        $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));

        return array(
            "left"   => abs($minX) - 1,
            "top"    => abs($minY) - 1,
            "width"  => $maxX - $minX,
            "height" => $maxY - $minY,
            "box"    => $rect
        );
    }

    function calculateTextBox1($text , $font_file , $font_size, $font_angle) {
        $box   = imagettfbbox($font_size, $font_angle, $font_file, $text);
        if( !$box )
            return false;
        $min_x = min( array($box[0], $box[2], $box[4], $box[6]) );
        $max_x = max( array($box[0], $box[2], $box[4], $box[6]) );
        $min_y = min( array($box[1], $box[3], $box[5], $box[7]) );
        $max_y = max( array($box[1], $box[3], $box[5], $box[7]) );
        $width  = ( $max_x - $min_x );
        $height = ( $max_y - $min_y );
        $left   = abs( $min_x ) + $width;
        $top    = abs( $min_y ) + $height;
        // to calculate the exact bounding box i write the text in a large image
        $img     = @imagecreatetruecolor( $width << 2, $height << 2 );
        $white   =  imagecolorallocate( $img, 255, 255, 255 );
        $black   =  imagecolorallocate( $img, 0, 0, 0 );
        imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $black);
        // for sure the text is completely in the image!
        imagettftext( $img, $font_size,
            $font_angle, $left, $top,
            $white, $font_file, $text);
        // start scanning (0=> black => empty)
        $rleft  = $w4 = $width<<2;
        $rright = 0;
        $rbottom   = 0;
        $rtop = $h4 = $height<<2;
        for( $x = 0; $x < $w4; $x++ )
            for( $y = 0; $y < $h4; $y++ )
                if( imagecolorat( $img, $x, $y ) ){
                    $rleft   = min( $rleft, $x );
                    $rright  = max( $rright, $x );
                    $rtop    = min( $rtop, $y );
                    $rbottom = max( $rbottom, $y );
                }
        // destroy img and serve the result
        imagedestroy( $img );
        return array( "left"   => $left - $rleft,
            "top"    => $top  - $rtop,
            "width"  => $rright - $rleft + 1,
            "height" => $rbottom - $rtop + 1 );
    }

}
