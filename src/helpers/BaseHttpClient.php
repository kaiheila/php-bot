<?php
/**
 * @author zlh <root@rooot.me>
 * @datetime 2020/10/23-14:28
 */

/**
 * @author zlh <root@rooot.me>
 * @datetime 2020/7/12-22:23
 */

namespace kaiheila\api\helpers;

class BaseHttpClient
{
    const GET = 'get';
    const POST = 'post';
    const URLENCODE_FORMAT = 1;
    const JSON_FORMAT = 2;

    protected $path;
    protected $query = '';
    protected $body;
    protected $timeout = 5;
    protected $headers = [
        'Cache-Control' => 'no-cache',
        'User-Agent' => 'kaiheila-ws/0.0.1',
    ];

    protected $fullUrl;

    /**
     * @var \Swoole\Coroutine\Http\Client
     */
    protected $client;

    /**
     * BaseHttpClient constructor.
     * @param $url
     * @throws \Exception
     */
    public function __construct($url)
    {
        $parsed = parse_url($url);

        if (empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new \Exception('Invalid URL!');
        }

        $host = $parsed['host'];
        $scheme = $parsed['scheme'];

        if (!empty($parsed['port'])) {
            $port = $parsed['port'];
        } elseif ($parsed['scheme'] == 'https') {
            $port = 443;
        } else {
            $port = 80;
        }

        $this->path = $parsed['path'] ?? '';

        if (!empty($parsed['query'])) {
            $this->query = '?'. $parsed['query'];
        }

        $this->client = new \Swoole\Coroutine\Http\Client($host, $port, $scheme == 'http' ? false : true);
        $this->client->set(['timeout' => $this->timeout]);
    }

    /**
     * @param string $method
     * @return mixed
     * @throws \Exception
     */
    public function send($method = self::GET)
    {
        if (!in_array($method, [self::GET, self::POST])) {
            throw new \Exception('Method not allowed!');
        }

        $this->fullUrl = '/' . ltrim($this->path, '/') . $this->query;
        $this->client->setHeaders($this->headers);

        switch (strtolower($method)) {
            case self::POST:
                $this->client->post($this->fullUrl, $this->body);
                break;
            case self::GET:default:
                $this->client->get($this->fullUrl);
                break;
        }

        $res = $this->client->body;
        $statusCode = $this->client->getStatusCode();

        $this->client->close();

        // connection failed (-1) | request timeout (-2) | server_reset (-3)
        if ($statusCode < 0) {
            return swoole_strerror($this->client->errCode);
        }

        return $res;
    }

    /**
     * @return BaseHttpClient
     */
    public function setTimeOut(int $timeout)
    {
        $this->client->set(['timeout' => $timeout]);
        return $this;
    }

    /**
     * @return BaseHttpClient
     */
    public function setHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * @return BaseHttpClient
     */
    public function setQuery(array $query)
    {
        $this->query = '?'. http_build_query($query);
        return $this;
    }

    /**
     * @param $data
     * @param int $contentType
     * @return BaseHttpClient
     * @throws \Exception
     */
    public function setBody($data, $contentType = self::JSON_FORMAT)
    {
        if (!is_string($data) && !is_array($data)) {
            throw new \Exception('Invalid http body!');
        }

        switch ($contentType) {
            case self::JSON_FORMAT:
                $this->headers['content-type'] = 'application/json';
                $this->body = json_encode($data, JSON_UNESCAPED_UNICODE);
                break;
            case self::URLENCODE_FORMAT:default:
                $this->headers['content-type'] = 'application/x-www-form-urlencoded';
                $this->body = $data;
                break;
        }

        return $this;
    }
}
