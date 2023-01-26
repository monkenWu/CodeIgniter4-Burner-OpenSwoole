<?php

namespace Monken\CIBurner\OpenSwoole;

$opt = getopt('f:r::s::');
require_once $opt['f'];

define('BURNER_DRIVER', 'OpenSwoole');

use CodeIgniter\Config\Factories;
use CodeIgniter\Events\Events;
use Exception;
use Monken\CIBurner\OpenSwoole\Cache\SwooleTable;
use Monken\CIBurner\OpenSwoole\Psr\ResponseMerger;
use Monken\CIBurner\OpenSwoole\Psr\ServerRequest as PsrRequest;
use Monken\CIBurner\OpenSwoole\Websocket\Pool as WebsocketPool;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server as HttpServer;
use OpenSwoole\Server;
use OpenSwoole\Timer;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server as WebSocketServer;
use Psr\Http\Message\ServerRequestInterface;

class Worker
{
    protected static Psr17Factory $uriFactory;
    protected static Psr17Factory $streamFactory;
    protected static Psr17Factory $uploadedFileFactory;
    protected static ResponseMerger $responseMerger;
    protected static HttpServer|WebSocketServer $server;
    protected static ?Frame $frame = null;

    /**
     * Init Worker
     *
     * @return void
     */
    public static function init(HttpServer|WebSocketServer $server)
    {
        self::$uriFactory          = new Psr17Factory();
        self::$streamFactory       = new Psr17Factory();
        self::$uploadedFileFactory = new Psr17Factory();
        self::$responseMerger      = new ResponseMerger();
        self::$server              = $server;
    }

    /**
     * get OpenSwoole Server Instance
     */
    public static function getServer(): HttpServer|WebSocketServer
    {
        return self::$server;
    }

    /**
     * Burner handles CodeIgniter4 entry points
     * and will automatically execute the Swoole-Server-end Sending Response.
     *
     * @return void
     */
    public static function httpProcesser(Request $swooleRequest, Response $swooleResponse)
    {
        $response = \Monken\CIBurner\App::run(self::requestFactory($swooleRequest));

        self::$responseMerger->toOpenSwoole(
            $response,
            $swooleResponse
        )->end();

        Events::trigger('burnerAfterSendResponse', self::$server);

        \Monken\CIBurner\App::clean();
    }

    /**
     * Please pass the Swoole-Request object in the Swoole Webscoket Open-Event to initialize the worker.
     *
     * @return void
     */
    public static function setWebsocket(Request $swooleRequest)
    {
        WebsocketPool::instance()->setRequest($swooleRequest->fd, $swooleRequest);
    }

    /**
     * Remove connection from pool upon close
     *
     * @return void
     */
    public static function unsetWebsocket(int $fd)
    {
        WebsocketPool::instance()->deleteRequest($fd);
    }

    /**
     * Burner handles CodeIgniter4 entry points.
     * Use this function in the Swoole Websocket Message-Event.
     *
     * @return void
     */
    public static function websocketProcesser(Frame $frame)
    {
        $websocketRequest = WebsocketPool::instance()->getRequest($frame->fd);
        if ($websocketRequest !== null) {
            self::$frame = $frame;
            \Monken\CIBurner\App::run($websocketRequest, true);
            \Monken\CIBurner\App::clean();
            self::$frame = null;
        }
    }

    /**
     * get current OpenSwoole Websocket-Frame Instance
     */
    public static function getFrame(): Frame
    {
        if (self::$frame === null) {
            throw new Exception('You must start the burner through websocketProcesser to get the Frame instance.');
        }

        return self::$frame;
    }

    /**
     * Push message to client.
     *
     * @param mixed    $data
     * @param int|null $fd   If not passed in, it will be pushed to the current fd
     *
     * @return void
     */
    public static function push($data, ?int $fd, int $opcode = 1)
    {
        if (self::$server->isEstablished($fd)) {
            self::$server->push(
                $fd ?? self::$frame->fd,
                $data,
                $opcode
            );
            Events::trigger('burnerAfterPushMessage', self::$server, $fd);
        }
    }

    /**
     * Push messages to all client.
     *
     * @param callable $messageProcesser $messageProcesser(int $fd)
     *
     * @return void
     */
    public static function pushAll(callable $messageProcesser, int $opcode = 1)
    {
        foreach (self::$server->connections as $fd) {
            if (self::$server->isEstablished($fd)) {
                $message = $messageProcesser($fd);
                if (null === $message) {
                    continue;
                }
                if (is_array($message)) {
                    self::push($message['message'], $fd, $message['opcode']);
                } else {
                    self::push($message, $fd, $opcode);
                }
            }
        }
        Events::trigger('burnerAfterPushAllMessage', self::$server);
    }

    /**
     * Convert Swoole-Request to psr7-Request
     */
    public static function requestFactory(Request $swooleRequest): ServerRequestInterface
    {
        return (new PsrRequest(
            $swooleRequest,
            self::$uriFactory,
            self::$streamFactory,
            self::$uploadedFileFactory
        ))->withUploadedFiles($swooleRequest->files ?? []);
    }
}
/** @var \Config\OpenSwoole */
$openSwooleConfig = Factories::config('OpenSwoole');

// handle command parameters
$isRestart = $opt['r'] ?? false;
if (isset($opt['s'])) {
    $openSwooleConfig->config['daemonize'] = ($opt['s'] === 'daemon');
    Integration::writeIsDaemon();
}

// handle cache
if ($openSwooleConfig->fastCache) {
    $swooleTable = new SwooleTable($openSwooleConfig);
}
// handle websocket pool
if ($openSwooleConfig->httpDriver === 'OpenSwoole\WebSocket\Server') {
    $websocketPool = new WebsocketPool($openSwooleConfig);
}

$server = new ($openSwooleConfig->httpDriver)(
    $openSwooleConfig->listeningIp,
    $openSwooleConfig->listeningPort,
    $openSwooleConfig->mode,
    $openSwooleConfig->type
);
Worker::init($server);
$server->set($openSwooleConfig->config);
$server->on('Start', static function (Server $server) use ($openSwooleConfig, $isRestart) {
    Integration::writeMasterPid($server->master_pid);

    if ($isRestart === false) {
        fwrite(STDOUT, sprintf(
            'Swoole %s server is started at %s:%d %s',
            explode('\\', $openSwooleConfig->httpDriver)[1],
            $openSwooleConfig->listeningIp,
            $openSwooleConfig->listeningPort,
            PHP_EOL . PHP_EOL
        ));
    } else {
        fwrite(STDOUT, sprintf(
            'Swoole %s server is restarted.%s',
            explode('\\', $openSwooleConfig->httpDriver)[1],
            PHP_EOL . PHP_EOL
        ));
    }

    $isDaemonize = $openSwooleConfig->config['daemonize'] ?? false;
    if ($openSwooleConfig->autoReload && ($isDaemonize !== true)) {
        Timer::tick(1000, static function () use ($openSwooleConfig, $server) {
            FileMonitor::checkFilesChange(
                $openSwooleConfig,
                $server
            );
        });
    }

    if ($openSwooleConfig->fastCache) {
        SwooleTable::instance()->initTtlRecycler();
    }

    $openSwooleConfig->serverStart($server);
});
$openSwooleConfig->server($server);

$isDaemonize = $openSwooleConfig->config['daemonize'] ?? false;
if ($isDaemonize) {
    fwrite(STDOUT, sprintf(
        'Swoole server in daemon mode. %s',
        PHP_EOL
    ));
}

$server->start();
