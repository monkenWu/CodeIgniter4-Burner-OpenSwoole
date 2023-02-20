<?php

namespace Monken\CIBurner\OpenSwoole\Psr;

use Monken\CIBurner\OpenSwoole\Psr\ServerRequest as PsrRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PsrFactory
{
    protected static Psr17Factory $uriFactory;
    protected static Psr17Factory $streamFactory;
    protected static Psr17Factory $uploadedFileFactory;
    protected static ResponseMerger $responseMerger;

    /**
     * Init Worker
     *
     * @return void
     */
    public static function init()
    {
        self::$uriFactory          = new Psr17Factory();
        self::$streamFactory       = new Psr17Factory();
        self::$uploadedFileFactory = new Psr17Factory();
        self::$responseMerger      = new ResponseMerger();
    }

    /**
     * Convert Swoole-Request to psr7-Request
     */
    public static function toPsrRequest(Request $swooleRequest): ServerRequestInterface
    {
        return (new PsrRequest(
            $swooleRequest,
            self::$uriFactory,
            self::$streamFactory,
            self::$uploadedFileFactory
        ))->withUploadedFiles($swooleRequest->files ?? []);
    }

    public static function toOpenSwooleResponse(ResponseInterface $psrResponse, Response $swooleResponse): Response
    {
        return self::$responseMerger->toOpenSwoole(
            $psrResponse,
            $swooleResponse
        );
    }
}
