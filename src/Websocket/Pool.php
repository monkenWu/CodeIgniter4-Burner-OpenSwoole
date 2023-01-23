<?php

namespace Monken\CIBurner\OpenSwoole\Websocket;

use Config\OpenSwoole as Config;
use Exception;
use Monken\CIBurner\OpenSwoole\Worker;
use Nyholm\Psr7\ServerRequest as PsrRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Table;
use Psr\Http\Message\ServerRequestInterface;

class Pool
{
    /**
     * swoole table shared instance
     */
    protected static ?Table $table = null;

    /**
     * self clss shard instance
     *
     * @var \Monken\CIBurner\OpenSwoole\Websocket\Pool|null
     */
    protected static ?Pool $instance = null;

    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        if (null === self::$instance) {
            self::$instance = $this;
            $this->initTable();
        }
    }

    /**
     * Init Swoole Table
     *
     * @return void
     */
    public function initTable()
    {
        if ((null === self::$table) === false) {
            self::$table->destroy();
            self::$table = null;
        }
        self::$table = new Table($this->config->websocketPoolSize);
        self::$table->column('fd', Table::TYPE_INT, 8);
        self::$table->column('request', Table::TYPE_STRING, 10240);
        self::$table->create();
    }

    /**
     * Store websocket user request
     */
    public function setRequest(int $fd, Request $request): bool
    {
        $psr7Request = Worker::requestFactory($request);
        $body        = $request->rawContent();
        if (is_string($body) === false) {
            $body = null;
        }
        $data = [
            'method'          => $psr7Request->getMethod(),
            'uri'             => $psr7Request->getUri(),
            'body'            => $body,
            'protocolVersion' => $psr7Request->getProtocolVersion(),
            'serverParams'    => $psr7Request->getServerParams(),
            'cookieParams'    => $psr7Request->getCookieParams(),
            'parsedBody'      => $psr7Request->getParsedBody(),
            'headers'         => $psr7Request->getHeaders(),
            'queryParams'     => $psr7Request->getQueryParams(),
            'uploadedFiles'   => $psr7Request->getUploadedFiles(),
        ];
        var_dump(serialize($data));

        return self::$table->set($fd, ['fd' => $fd, 'request' => serialize($data)]);
    }

    /**
     * Get Websocket user request
     */
    public function getRequest(int $fd): ?ServerRequestInterface
    {
        $data = self::$table->get($fd);
        if (isset($data['fd']) === false || isset($data['request']) === false) {
            return null;
        }
        $requestData = unserialize($data['request']);

        return (new PsrRequest(
            $requestData['method'],
            $requestData['uri'],
            $requestData['headers'],
            $requestData['body'],
            $requestData['protocolVersion'],
            $requestData['serverParams']
        ))->withQueryParams($requestData['queryParams'])
            ->withCookieParams($requestData['cookieParams'])
            ->withParsedBody($requestData['parsedBody'])
            ->withUploadedFiles($requestData['uploadedFiles']);
    }

    /**
     * Delete Websocket user request
     */
    public function deleteRequest(int $fd): bool
    {
        return self::$table->del($fd);
    }

    public function cleanTable()
    {
        foreach (self::$table as $cacheItem) {
            self::$table->del($cacheItem['fd']);
        }
    }

    /**
     * Get Swoole Table Shared Instance
     */
    public function getTable(): Table
    {
        return self::$table;
    }

    /**
     * Get Shared Instance
     *
     * @return \Monken\CIBurner\OpenSwoole\Websocket\Pool
     */
    public static function instance(): Pool
    {
        if (config('OpenSwoole')->httpDriver !== 'OpenSwoole\WebSocket\Server') {
            throw new Exception('You must use httpDriver: "OpenSwoole\WebSocket\Server" in "\Config\OpenSwoole".');
        }

        return self::$instance;
    }
}
