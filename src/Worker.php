<?php

namespace Monken\CIBurner\OpenSwoole;

$opt = getopt('f:r::s::');
require_once $opt['f'];

define('BURNER_DRIVER', 'OpenSwoole');

use CodeIgniter\Config\Factories;
use CodeIgniter\Events\Events;
use Exception;
use Monken\CIBurner\OpenSwoole\Cache\OpenSwooleHandler;
use Monken\CIBurner\OpenSwoole\Cache\SwooleTable;
use Monken\CIBurner\OpenSwoole\Psr\PsrFactory;
use Monken\CIBurner\OpenSwoole\Websocket\Pool as WebsocketPool;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server as HttpServer;
use OpenSwoole\Server;
use OpenSwoole\Timer;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server as WebSocketServer;

class Worker
{
    protected static HttpServer|WebSocketServer $server;
    protected static ?Frame $frame                 = null;
    protected static ?OpenSwooleHandler $fastCache = null;

    /**
     * Init Worker
     *
     * @param HttpServer|WebSocketServer $server
     * @return void
     */
    public static function init(HttpServer|WebSocketServer $server)
    {
        self::$server = $server;
    }

    /**
     * get OpenSwoole Server Instance
     *
     * @return HttpServer|WebSocketServer
     */
    public static function getServer(): HttpServer|WebSocketServer
    {
        return self::$server;
    }

    /**
     * Burner handles CodeIgniter4 entry points
     * and will automatically execute the Swoole-Server-end Sending Response.
     *
     * @param Request $swooleRequest
     * @param Response $swooleResponse
     * @return void
     */
    public static function httpProcesser(Request $swooleRequest, Response $swooleResponse)
    {
        $response = \Monken\CIBurner\App::run(PsrFactory::toPsrRequest($swooleRequest));
        PsrFactory::toOpenSwooleResponse($response, $swooleResponse)->end();
        Events::trigger('burnerAfterSendResponse', self::$server);
        \Monken\CIBurner\App::clean();
    }

    /**
     * Please pass the Swoole-Request object in the Swoole Webscoket Open-Event to initialize the worker.
     *
     * @param Request $swooleRequest
     * @return void
     */
    public static function setWebsocket(Request $swooleRequest)
    {
        WebsocketPool::instance()->setRequest($swooleRequest->fd, $swooleRequest);
    }

    /**
     * Remove connection from pool upon close
     *
     * @param integer $fd
     * @return void
     */
    public static function unsetWebsocket(int $fd)
    {
        WebsocketPool::instance()->deleteRequest($fd);
    }

    /**
     * Burner handles websocket use CodeIgniter4 entry points.
     *
     * @param Frame $frame
     * @param callable|null $notFoundHandler(Server $server, Frame $frame) If the request record for this Fram is not found in the Websocket Pool, then this Handler will be executed.
     * @return void
     */
    public static function websocketProcesser(Frame $frame, ?callable $notFoundHandler = null)
    {
        $websocketRequest = WebsocketPool::instance()->getRequest($frame->fd);
        if ($websocketRequest !== null) {
            self::$frame = $frame;
            \Monken\CIBurner\App::run($websocketRequest, true);
            \Monken\CIBurner\App::clean();
            self::$frame = null;
        } else {
            if ($notFoundHandler !== null) {
                $notFoundHandler(self::$server, $frame);
            }
        }
    }

    /**
     * Get current OpenSwoole Websocket-Frame Instance
     *
     * @param boolean $nullable If nullable is true, no error will be thrown if the Frame cannot be found.
     * @return Frame|null
     */
    public static function getFrame(bool $nullable = false): Frame|null
    {
        if (self::$frame === null) {
            if ($nullable) {
                return null;
            }

            throw new Exception('You must start the burner through websocketProcesser to get the Frame instance.');
        }

        return self::$frame;
    }

    /**
     * Push message to client.
     *
     * @param mixed    $data
     * @param int|null $fd     If not passed in, it will be pushed to the current fd
     * @param int      $opcode Swoole Websocket opcode
     * @param mixed    $data
     */
    public static function push($data, ?int $fd, int $opcode = 1): bool
    {
        $fd ??= self::$frame->fd;

        if (null === $fd) {
            return false;
        }

        if (self::$server->isEstablished($fd)) {
            $pushResult = self::$server->push($fd, $data, $opcode);
            Events::trigger('burnerAfterPushMessage', self::$server, $fd, $pushResult);

            return true;
        }

        return false;
    }

    /**
     * Push message to all client.
     *
     * @param string|callable $message If it is a string, it will be sent directly. If it is a callable, it will be executed and the return value will be sent.
     * @param array|null $fds
     * @param integer $opcode
     * @return void
     */
    public static function pushAll(string|callable $message, ?array $fds = null, int $opcode = 1)
    {
        $fds ??= self::$server->connections;

        foreach ($fds as $fd) {
            if (self::$server->isEstablished($fd)) {
                if (is_callable($message)) {
                    $resultMessage = $message($fd);
                } else {
                    $resultMessage = $message;
                }

                if (null === $resultMessage) {
                    continue;
                }

                if (is_array($resultMessage)) {
                    self::push($resultMessage['message'], $fd, $resultMessage['opcode']);
                } else {
                    self::push($resultMessage, $fd, $opcode);
                }
            }
        }
        Events::trigger('burnerAfterPushAllMessage', self::$server, $fds);
    }

    /**
     * Get the shared fast cache instance.
     *
     * @return OpenSwooleHandler|null if fast cache is disabled, return null.
     */
    public static function getFastCache(): ?OpenSwooleHandler
    {
        /** @var \Config\OpenSwoole */
        $openSwooleConfig = Factories::config('OpenSwoole');
        if ($openSwooleConfig->fastCache === false) {
            return null;
        }

        if (self::$fastCache === null) {
            $cacheConfig     = Factories::config('Cache');
            self::$fastCache = new OpenSwooleHandler($cacheConfig);
            self::$fastCache->initialize();
        }

        return self::$fastCache;
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
// init burner
\Monken\CIBurner\App::setConfig(config('Burner'));

$server = new ($openSwooleConfig->httpDriver)(
    $openSwooleConfig->listeningIp,
    $openSwooleConfig->listeningPort,
    $openSwooleConfig->mode,
    $openSwooleConfig->type
);

PsrFactory::init();
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
        SwooleTable::instance()->initTtlRecycler($openSwooleConfig->fastCacheConfig['ttlRecyclerTimer']);
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
