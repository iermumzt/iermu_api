<?php

namespace Liushuangxi\Ceph;

/**
 * Class SwiftUrl
 * @package Liushuangxi\Ceph
 */
class SwiftUrl
{
    /**
     * @var SwiftClient
     */
    public $client = null;

    /**
     * SwiftUrl constructor.
     *
     * http://docs.ceph.org.cn/radosgw/swift/tempurl/
     *
     * @param $client SwiftClient
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @param $key
     * @param string $key2
     * @return bool
     */
    public function setKey($key, $key2 = '')
    {
        $headers = [
            'X-Account-Meta-Temp-URL-Key' => $key
        ];

        if (!empty($key2)) {
            $headers['X-Account-Meta-Temp-URL-Key-2'] = $key2;
        }

        $response = $this->client->request(
            'POST',
            '',
            [
                'headers' => $headers
            ]
        );

        if (!is_null($response)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $uri
     * @param int $expire
     * @return string
     */
    public function tempUrl($uri, $expire = 60)
    {
        $pos = strrpos($this->client->baseUrl, '/');
        $baseUrl = substr($this->client->baseUrl, 0, $pos);
        $version = substr($this->client->baseUrl, $pos + 1);

        $uri = trim($uri, '/');
        $uri = "/$version/$uri";

        $expire = time() + $expire;

        $sign = hash_hmac(
            'sha1',
            implode("\n", ['GET', $expire, $uri]),
            $this->client->config['temp-url-key']
        );

        $url = $baseUrl . $uri;
        $url .= "?temp_url_sig=$sign&temp_url_expires=$expire";

        return $url;
    }
}
