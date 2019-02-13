<?php

namespace Liushuangxi\Ceph;

/**
 * Class SwiftObject
 * @package Liushuangxi\Ceph
 */
class SwiftObject
{
    /**
     * @var SwiftClient
     */
    public $client = null;

    /**
     * SwiftObject constructor.
     *
     * http://docs.ceph.org.cn/radosgw/swift/objectops/
     *
     * @param $client SwiftClient
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @param $container
     * @param $file
     * @param string $object
     * @return bool|string
     */
    public function createObject($container, $file, $object = '')
    {
        if (empty($object)) {
            $object = md5(uniqid('ceph', true)) . "." . pathinfo($file, PATHINFO_EXTENSION);
        } else {
            $object = trim($object, '/');
        }

        try {
            $response = $this->client->request(
                'PUT',
                $container . "/" . $object,
                [
                    'body' => @file_get_contents($file),
                ]
            );

            if ($this->isExistObject($container, $object)) {
                return $object;
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * @param $container
     * @param $object
     * @return bool
     */
    public function isExistObject($container, $object)
    {
        $container = trim($container, "/");
        $object = trim($object, "/");

        $response = $this->client->request(
            'HEAD',
            $container . "/" . $object
        );

        if (!is_null($response) && !empty($response->getHeaders())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $container
     * @param $object
     * @return bool
     */
    public function deleteObject($container, $object)
    {
        $container = trim($container, "/");
        $object = trim($object, "/");

        $response = $this->client->request(
            'DELETE',
            $container . "/" . $object
        );

        return !$this->isExistObject($container, $object);
    }

    /**
     * @param $container
     * @param $object
     * @param $values
     * @return bool
     */
    public function updateMetas($container, $object, $values)
    {
        $headers = [];
        foreach ($values as $key => $value) {
            $headers = [
                "X-Object-Meta-$key" => $value
            ];
        }

        $response = $this->client->request(
            'POST',
            $container . "/" . $object,
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
     * @param $container
     * @param $object
     * @return array
     */
    public function getMeta($container, $object)
    {
        $response = $this->client->request(
            'HEAD',
            $container . "/" . $object
        );

        if (!is_null($response) && !empty($response->getHeaders())) {
            $data = [];
            foreach ($response->getHeaders() as $key => $value) {
                if (strpos($key, 'X-Object-Meta-') === 0) {
                    $data[substr($key, 14)] = $value[0];
                }
            }
            return $data;
        } else {
            return [];
        }
    }
}
