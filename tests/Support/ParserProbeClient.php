<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use InterfaceApi\Support\BaseClientApi;
use Psr\Http\Message\ResponseInterface;

final class ParserProbeClient extends BaseClientApi
{
    public function __construct()
    {
        parent::__construct(new Client(['handler' => HandlerStack::create()]), 'http://127.0.0.1');
    }

    public function parse(ResponseInterface $response, ?string $forceType = null): mixed
    {
        return $this->parseResponseByHeaders($response, $forceType);
    }

    /**
     * @param array<string, mixed> $requestDefaults
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function mergeJson(array $requestDefaults, array $options = []): array
    {
        return $this->mergeClientOptions($requestDefaults, $options);
    }

    /**
     * @param array<string, mixed> $requestDefaults
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function mergeStream(array $requestDefaults, array $options = []): array
    {
        return $this->mergeStreamClientOptions($requestDefaults, $options);
    }
}
