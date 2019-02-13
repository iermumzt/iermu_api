<?php

/**
 * 主要涉及了资源上传接口的实现
 *
 * @link http://developer.qiniu.com/docs/v6/api/reference/up/
 */
final class UploadManager
{
    private $config;
    public $SDK_VER = '7.1.3';

    public $BLOCK_SIZE = 4194304; //4*1024*1024 分块上传块大小，该参数为接口规格，不能修改

    public $RS_HOST  = 'http://rs.qbox.me';               // 文件元信息管理操作Host
    public $RSF_HOST = 'http://rsf.qbox.me';              // 列举操作Host
    public $API_HOST = 'http://api.qiniu.com';            // 数据处理操作Host
    public $UC_HOST  = 'http://uc.qbox.me';              // Host

    public $zone;
    public $url;
    public $headers;
    public $body;
    public $method;
    public $scheme = 'http';

    public $upToken;
    public $key;
    public $inputStream;
    public $size;
    public $params;
    public $mime;
    public $contexts;
    public $host;
    public $currentUrl;

    public function __construct()
    {
    }

    /**
     * 上传二进制流到七牛
     *
     * @param $upToken    上传凭证
     * @param $key        上传文件名
     * @param $data       上传二进制流
     * @param $params     自定义变量，规格参考
     *                    http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     * @param $mime       上传数据的mimeType
     * @param $checkCrc   是否校验crc32
     *
     * @return array    包含已上传文件的信息，类似：
     *                                              [
     *                                                  "hash" => "<Hash string>",
     *                                                  "key" => "<Key string>"
     *                                              ]
     */
    public function put(
        $upToken,
        $key,
        $data,
        $params = null,
        $mime = 'application/octet-stream',
        $checkCrc = false
    ) {
        $params = $this->trimParams($params);
        //return FormUploader::put(
        //    $upToken,
        //    $key,
        //    $data,
        //    $this->config,
        //    $params,
        //    $mime,
        //    $checkCrc
        //);
        return $this->Formput($upToken, $key, $data, $this->config,$params, $mime, $checkCrc);
    }


    /**
     * 上传文件到七牛
     *
     * @param $upToken    上传凭证
     * @param $key        上传文件名
     * @param $filePath   上传文件的路径
     * @param $params     自定义变量，规格参考
     *                    http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     * @param $mime       上传数据的mimeType
     * @param $checkCrc   是否校验crc32
     *
     * @return array    包含已上传文件的信息，类似：
     *                                              [
     *                                                  "hash" => "<Hash string>",
     *                                                  "key" => "<Key string>"
     *                                              ]
     */
    public function putFile($upToken, $key, $filePath, $params = null, $mime = 'application/octet-stream', $checkCrc = false) {
        $file = fopen($filePath, 'rb');
        if ($file === false) {
            echo 444;
            return false;
        }
        $params = $this->trimParams($params);
        $stat = fstat($file);
        $size = $stat['size'];
        if ($size <= $this->BLOCK_SIZE) {
            $data = fread($file, $size);
            fclose($file);
            if ($data === false) {
                return false;
            }
            return $this->Formput($upToken, $key, $data, $params, $mime, $checkCrc);
        }
    }

    public function Formput($upToken, $key, $data, $params, $mime, $checkCrc) {
        $fields = array('token' => $upToken);
        if ($key === null) {
            $fname = 'filename';
        } else {
            $fname = $key;
            $fields['key'] = $key;
        }
        if ($checkCrc) {
            $fields['crc32'] = $this->crc32_data($data);
        }
        if ($params) {
            foreach ($params as $k => $v) {
                $fields[$k] = $v;
            }
        }

        list($ak, $bucket) = $this->unmarshalUpToken($upToken);
        list($upHosts, $err) = $this->getUpHosts($ak, $bucket);
        list($upHost, $err) = array($upHosts[0], $err);

        if ($err != null) {
            return array(null, $err);
        }
        $response = $this->multipartPost($upHost, $fields, 'file', $fname, $data, $mime);
        if (!$response->ok()) {
            return array(null, new Error($upHost, $response));
        }
        //swain
        return array($response->json(), null);
    }

    public function trimParams($params)
    {
        if ($params === null) {
            return null;
        }
        $ret = array();
        foreach ($params as $k => $v) {
            $pos = strpos($k, 'x:');
            if ($pos === 0 && !empty($v)) {
                $ret[$k] = $v;
            }
        }
        return $ret;
    }
    /**
     * 计算文件的crc32检验码:
     */
    function crc32_file($file)
    {
        $hash = hash_file('crc32b', $file);
        $array = unpack('N', pack('H*', $hash));
        return sprintf('%u', $array[1]);
    }
   /**
     * 计算输入流的crc32检验码
     */
    function crc32_data($data)
    {
        $hash = hash('crc32b', $data);
        $array = unpack('N', pack('H*', $hash));
        return sprintf('%u', $array[1]);
    }

    function unmarshalUpToken($uptoken)
    {
        $token = explode(':', $uptoken);
        if (count($token) !== 3) {
            return false;
        }

        $ak = $token[0];
        $policy = $this->base64_urlSafeDecode($token[2]);
        $policy = json_decode($policy, true);

        $scope = $policy['scope'];
        $bucket = $scope;

        if (strpos($scope, ':')) {
            $scopes = explode(':', $scope);
            $bucket = $scopes[0];
        }

        return array($ak, $bucket);
    }
    function base64_urlSafeDecode($str)
    {
        $find = array('-', '_');
        $replace = array('+', '/');
        return base64_decode(str_replace($find, $replace, $str));
    }
    public function getUpHosts($ak, $bucket)
    {
        list($bucketHosts, $err) = $this->getBucketHosts($ak, $bucket);
        if ($err !== null) {
            return array(null, $err);
        }

        $upHosts = $bucketHosts['upHosts'];
        return array($upHosts, null);
    }
    public function getBucketHosts($ak, $bucket)
    {
        $key = $this->scheme . ":$ak:$bucket";
        $bucketHosts = $this->getBucketHostsFromCache($key);
        if (count($bucketHosts) > 0) {
            return array($bucketHosts, null);
        }

        list($hosts, $err) = $this->bucketHosts($ak, $bucket);
        if ($err !== null) {
            return array(null , $err);
        }

        $schemeHosts = $hosts[$this->scheme];
        $bucketHosts = array(
            'upHosts' => $schemeHosts['up'],
            'ioHost' => $schemeHosts['io'],
            'deadline' => time() + $hosts['ttl']
        );

        $this->setBucketHostsToCache($key, $bucketHosts);
        return array($bucketHosts, null);
    }

    private function getBucketHostsFromCache($key)
    {
        $ret = array();
        if (count($this->hostCache) === 0) {
            $this->hostCacheFromFile();
        }

        if (!array_key_exists($key, $this->hostCache)) {
            return $ret;
        }

        if ($this->hostCache[$key]['deadline'] > time()) {
            $ret = $this->hostCache[$key];
        }

        return $ret;
    }
    private function bucketHosts($ak, $bucket)
    {
        $url = $this->UC_HOST . '/v1/query' . "?ak=$ak&bucket=$bucket";
        $ret = $this->get($url);
        if (!$ret->ok()) {
            return array(null, new Error($url, $ret));
        }
        $r = ($ret->body === null) ? array() : $ret->json();
        return array($r, null);
    }
    private function setBucketHostsToCache($key, $val)
    {
        $this->hostCache[$key] = $val;
        $this->hostCacheToFile();
        return;
    }
    private function hostCacheToFile()
    {
        $path = $this->hostCacheFilePath();
        file_put_contents($path, json_encode($this->hostCache), LOCK_EX);
        return;
    }

    private function hostCacheFromFile()
    {

        $path = $this->hostCacheFilePath();
        if (!file_exists($path)) {
            return;
        }

        $bucketHosts = file_get_contents($path);
        $this->hostCache = json_decode($bucketHosts, true);
        return;
    }

    private function hostCacheFilePath()
    {
        return sys_get_temp_dir() . '/.qiniu_phpsdk_hostscache.json';
    }
    //client
    public function multipartPost(
        $url,
        $fields,
        $name,
        $fileName,
        $fileBody,
        $mimeType = null,
        array $headers = array()
    ) {
        $data = array();
        $mimeBoundary = md5(microtime());

        foreach ($fields as $key => $val) {
            array_push($data, '--' . $mimeBoundary);
            array_push($data, "Content-Disposition: form-data; name=\"$key\"");
            array_push($data, '');
            array_push($data, $val);
        }

        array_push($data, '--' . $mimeBoundary);
        $mimeType = empty($mimeType) ? 'application/octet-stream' : $mimeType;
        $fileName = $this->escapeQuotes($fileName);
        array_push($data, "Content-Disposition: form-data; name=\"$name\"; filename=\"$fileName\"");
        array_push($data, "Content-Type: $mimeType");
        array_push($data, '');
        array_push($data, $fileBody);

        array_push($data, '--' . $mimeBoundary . '--');
        array_push($data, '');

        $body = implode("\r\n", $data);
        $contentType = 'multipart/form-data; boundary=' . $mimeBoundary;
        $headers['Content-Type'] = $contentType;
        $this->request('POST', $url, $headers, $body);
        return $this->sendRequest();
    }
    private function escapeQuotes($str)
    {
        $find = array("\\", "\"");
        $replace = array("\\\\", "\\\"");
        return str_replace($find, $replace, $str);
    }
    private function parseHeaders($raw)
    {
        $headers = array();
        $headerLines = explode("\r\n", $raw);
        foreach ($headerLines as $line) {
            $headerLine = trim($line);
            $kv = explode(':', $headerLine);
            if (count($kv) >1) {
                $headers[$kv[0]] = trim($kv[1]);
            }
        }
        return $headers;
    }
    private function userAgent()
    {
        $sdkInfo = "QiniuPHP/" . $this->SDK_VER;

        $systemInfo = php_uname("s");
        $machineInfo = php_uname("m");

        $envInfo = "($systemInfo/$machineInfo)";

        $phpVer = phpversion();

        $ua = "$sdkInfo $envInfo PHP/$phpVer";
        return $ua;
    }
    public function get($url, array $headers = array())
    {
        $this->request('GET', $url, $headers);
        return $this->sendRequest();
    }
    public function sendRequest()
    {
        $t1 = microtime(true);
        $ch = curl_init();
        $options = array(
            CURLOPT_USERAGENT => self::userAgent(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_CUSTOMREQUEST  => $this->method,
            CURLOPT_URL => $this->url
        );

        // Handle open_basedir & safe mode
        if (!ini_get('safe_mode') && !ini_get('open_basedir')) {
            $options[CURLOPT_FOLLOWLOCATION] = true;
        }

        if (!empty($this->headers)) {
            $headers = array();
            foreach ($this->headers as $key => $val) {
                array_push($headers, "$key: $val");
            }
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

        if (!empty($this->body)) {
            $options[CURLOPT_POSTFIELDS] = $this->body;
        }
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $t2 = microtime(true);
        $duration = round($t2-$t1, 3);
        $ret = curl_errno($ch);
        include_once API_SOURCE_ROOT.'lib/Response.class.php';
        if ($ret !== 0) {
            $r = new Response(-1, $duration, array(), null, curl_error($ch));
            curl_close($ch);
            return $r;
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = $this->parseHeaders(substr($result, 0, $header_size));
        $body = substr($result, $header_size);
        curl_close($ch);
        return new Response($code, $duration, $headers, $body, null);
    }
    public function request($method, $url, array $headers = array(), $body = null)
    {
        $this->method = strtoupper($method);
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
    }

    //ResumeUploader
    public function ResumeUploader(
        $upToken,
        $key,
        $inputStream,
        $size,
        $params,
        $mime
    ) {
        $this->upToken = $upToken;
        $this->key = $key;
        $this->inputStream = $inputStream;
        $this->size = $size;
        $this->params = $params;
        $this->mime = $mime;
        $this->contexts = array();

        list($ak, $bucket) = $this->unmarshalUpToken($uptoken);
        list($upHosts, $err) = $this->getUpHosts($ak, $bucket);
        list($upHost, $err) = array($upHosts[0], $err);

        if ($err != null) {
            throw new \Exception($err, 1);
        }
        $this->host = $upHost;
    }

    /**
     * 上传操作
     */
    public function upload()
    {
        $uploaded = 0;
        while ($uploaded < $this->size) {
            $blockSize = $this->blockSize($uploaded);
            $data = fread($this->inputStream, $blockSize);
            if ($data === false) {
                throw new \Exception("file read failed", 1);
            }
            $crc = $this->crc32_data($data);
            $response = $this->makeBlock($data, $blockSize);
            $ret = null;
            if ($response->ok() && $response->json() != null) {
                $ret = $response->json();
            }
            if ($response->statusCode < 0) {
                list($bakHost, $err) = $this->getBackupUpHostByToken($this->upToken);
                if ($err != null) {
                    return array(null, $err);
                }
                $this->host = $bakHost;
            }
            if ($response->needRetry() || !isset($ret['crc32']) || $crc != $ret['crc32']) {
                $response = $this->makeBlock($data, $blockSize);
                $ret = $response->json();
            }

            if (! $response->ok() || !isset($ret['crc32'])|| $crc != $ret['crc32']) {
                return array(null, new Error($this->currentUrl, $response));
            }
            array_push($this->contexts, $ret['ctx']);
            $uploaded += $blockSize;
        }
        return $this->makeFile();
    }

    public function getBackupUpHostByToken($uptoken)
    {
        list($ak, $bucket) = $this->unmarshalUpToken($uptoken);
        list($upHosts, $err) = $this->getUpHosts($ak, $bucket);

        $upHost = isset($upHosts[1]) ? $upHosts[1] : $upHosts[0];
        return array($upHost, $err);
    }
    private function blockSize($uploaded)
    {
        if ($this->size < $uploaded + $this->BLOCK_SIZE) {
            return $this->size - $uploaded;
        }
        return  $this->BLOCK_SIZE;
    }
    private function makeBlock($block, $blockSize)
    {
        $url = $this->host . '/mkblk/' . $blockSize;
        return $this->post($url, $block);
    }
    private function post($url, $data)
    {
        $this->currentUrl = $url;
        $headers = array('Authorization' => 'UpToken ' . $this->upToken);
        return $this->Clientpost($url, $data, $headers);
    }
    public function Clientpost($url, $body, array $headers = array())
    {
        $this->request('POST', $url, $headers, $body);
        return $this->sendRequest();
    }
    private function makeFile()
    {
        $url = $this->fileUrl();
        $body = implode(',', $this->contexts);
        $response = $this->post($url, $body);
        if ($response->needRetry()) {
            $response = $this->post($url, $body);
        }
        if (! $response->ok()) {
            return array(null, new Error($this->currentUrl, $response));
        }
        return array($response->json(), null);
    }

}
