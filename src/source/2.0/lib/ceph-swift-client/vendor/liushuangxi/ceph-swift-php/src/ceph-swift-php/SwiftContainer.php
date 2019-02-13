<?php

namespace Liushuangxi\Ceph;

/**
 * Class SwiftContainer
 * @package Liushuangxi\Ceph
 */
class SwiftContainer
{
    /**
     * @var SwiftClient
     */
    public $client = null;

    /**
     * SwiftContainer constructor.
     *
     * http://docs.ceph.org.cn/radosgw/swift/containerops/
     *
     * @param $client SwiftClient
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @return mixed|\Psr\Http\Message\StreamInterface
     */
    public function listContainers()
    {
        return $this->listObjects('');
    }

    /**
     * @param $container
     * @param array $params
     * @return mixed|\Psr\Http\Message\StreamInterface
     */
    public function listObjects($container, $params = ['format' => 'json'])
    {
        $response = $this->client->request(
            'GET',
            $container,
            [
                'query' => $params
            ]
        );

        if (isset($params['format']) && $params['format'] == 'json') {
            return @json_decode($response->getBody(), true);
        } else {
            return $response->getBody();
        }
    }

    /**
     * @param $container
     * @param array $headers
     * @return bool
     */
    public function createContainer($container, $headers = [])
    {
        if (empty($headers)) {
            $headers = [
                'X-Container-Read' => $this->client->config['auth-user'],
                'X-Container-Write' => $this->client->config['auth-user']
            ];
        }

        $response = $this->client->request(
            'PUT',
            $container,
            [
                'headers' => $headers
            ]
        );

        if (is_null($response)) {
            return false;
        }

        return $this->isExistContainer($container);
    }

    /**
     * @param $container
     * @return bool
     */
    public function isExistContainer($container)
    {
        $response = $this->client->request(
            'HEAD',
            $container
        );

        if (!is_null($response) && !empty($response->getHeaders())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $container
     * @return bool
     */
    public function deleteContainer($container)
    {
        $response = $this->client->request(
            'DELETE',
            $container
        );

        return !$this->isExistContainer($container);
    }

    /**
     * @param $container
     * @param string $read
     * @param string $write
     * @return bool
     */
    public function updateACLs($container, $read = '', $write = '')
    {
        $headers = [];
        if (!empty($read)) {
            $headers['X-Container-Read'] = $read;
        }
        if (!empty($write)) {
            $headers['X-Container-Write'] = $write;
        }

        $response = $this->client->request(
            'POST',
            $container,
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
     * @param $values
     * @return bool
     */
    public function updateMetas($container, $values)
    {
        $headers = [];
        foreach ($values as $key => $value) {
            $headers = [
                "X-Container-Meta-$key" => $value
            ];
        }

        $response = $this->client->request(
            'POST',
            $container,
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
}
