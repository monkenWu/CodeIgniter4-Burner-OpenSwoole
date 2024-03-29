<?php

namespace Monken\CIBurner\OpenSwoole\Psr;

use InvalidArgumentException;
use OpenSwoole\Http\Request as SwooleRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * OpenSwoole Psr Server Request
 *
 * Convert the Swoole namespace to OpenSwoole.
 * Fork from imefisto/psr-swoole-native repository.
 *
 * @see https://github.com/imefisto/psr-swoole-native
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    public $attributes = [];

    public function __construct(
        SwooleRequest $swooleRequest,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $uploadedFileFactory
    ) {
        parent::__construct($swooleRequest, $uriFactory, $streamFactory);
        $this->uploadedFileFactory = $uploadedFileFactory;
    }

    public function getServerParams()
    {
        return array_merge($_SERVER ?? [], array_change_key_case($this->swooleRequest->server ?? [], CASE_UPPER));
    }

    public function getCookieParams()
    {
        return $this->cookies ?? ($this->swooleRequest->cookie ?? []);
    }

    public function withCookieParams(array $cookies)
    {
        $new          = clone $this;
        $new->cookies = $cookies;

        return $new;
    }

    public function getQueryParams()
    {
        return $this->query ?? ($this->swooleRequest->get ?? []);
    }

    public function withQueryParams(array $query)
    {
        $new        = clone $this;
        $new->query = $query;

        return $new;
    }

    public function getUploadedFiles()
    {
        if (isset($this->files)) {
            return $this->files;
        }

        $files = [];

        foreach ($this->swooleRequest->files as $name => $fileData) {
            $files[$name] = $this->uploadedFileFactory->createUploadedFile(
                $this->streamFactory->createStreamFromFile($fileData['tmp_name']),
                $fileData['size'],
                $fileData['error'],
                $fileData['name'],
                $fileData['type']
            );
        }

        return $files;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new        = clone $this;
        $new->files = $uploadedFiles;

        return $new;
    }

    public function getParsedBody()
    {
        if (property_exists($this, 'parsedBody')) {
            return $this->parsedBody;
        }

        if (! empty($this->swooleRequest->post)) {
            return $this->swooleRequest->post;
        }

        return null;
    }

    public function withParsedBody($data)
    {
        if (! \is_object($data) && ! \is_array($data) && null !== $data) {
            throw new InvalidArgumentException('Unsupported argument type');
        }

        $new             = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value)
    {
        $new                    = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        unset($new->attributes[$name]);

        return $new;
    }
}
