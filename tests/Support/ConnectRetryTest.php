<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use InterfaceApi\Support\BaseClientApi;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ConnectRetryTest extends TestCase
{
    public function testHttpErrorIsNotRetried(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'fail'),
            new Response(200, [], 'should-not-run'),
        ]);
        $probe = $this->probe($mock);

        try {
            $probe->send('GET');
            self::fail('Expected HTTP 500 to propagate');
        } catch (RequestException $e) {
            self::assertTrue($e->hasResponse());
            self::assertSame(500, $e->getResponse()?->getStatusCode());
        }

        self::assertSame(0, $probe->backoffs);
        self::assertSame(1, $mock->count());
    }

    public function testConnectFailureRetriesThenSucceeds(): void
    {
        $probe = $this->probe(new MockHandler([
            new ConnectException('refused', new Request('GET', 'http://127.0.0.1/ping')),
            new Response(200, [], 'ok'),
        ]));

        $response = $probe->send('GET');

        self::assertSame('ok', (string) $response->getBody());
        self::assertSame(1, $probe->backoffs);
    }

    public function testPostConnectFailureIsNotRetriedByDefault(): void
    {
        $probe = $this->probe(new MockHandler([
            new ConnectException('refused', new Request('POST', 'http://127.0.0.1/ping')),
            new Response(200, [], 'should-not-run'),
        ]));

        $this->expectException(ConnectException::class);
        $probe->send('POST');
    }

    public function testTransportFailureWithoutResponseIsRetried(): void
    {
        $probe = $this->probe(new MockHandler([
            new RequestException('reset', new Request('GET', 'http://127.0.0.1/ping')),
            new Response(200, [], 'ok'),
        ]));

        $response = $probe->send('GET');

        self::assertSame('ok', (string) $response->getBody());
        self::assertSame(1, $probe->backoffs);
    }

    private function probe(MockHandler $mock): BaseClientApi
    {
        $stack = HandlerStack::create($mock);

        return new class(new Client(['handler' => $stack, 'http_errors' => true])) extends BaseClientApi {
            public int $backoffs = 0;

            public function __construct(ClientInterface $http)
            {
                parent::__construct($http, 'http://127.0.0.1');
            }

            public function send(string $method): ResponseInterface
            {
                return $this->requestWithConnectRetry($method, $this->uri('/ping'), ['http_errors' => true]);
            }

            protected function waitConnectRetryBackoff(int $attempt): void
            {
                $this->backoffs++;
            }

            protected function logConnectRetry(
                TransferException $e,
                string $method,
                string $uri,
                int $attempt,
                int $maxRetries,
                string $failedHost,
                int $failedPort,
                string $nextHost,
                int $nextPort,
            ): void {
            }
        };
    }
}
