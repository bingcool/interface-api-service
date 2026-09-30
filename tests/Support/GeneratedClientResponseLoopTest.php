<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InterfaceApi\Support\ClientException;
use InterfaceApi\Tests\ContractScaffold;
use InterfaceApi\Tests\Fixture\ChatEnvelopeResponse;
use InterfaceApi\Tests\Fixture\ChatStreamRequest;
use InterfaceApi\Tests\Fixture\TagDto;
use PHPUnit\Framework\TestCase;

final class GeneratedClientResponseLoopTest extends TestCase
{
    private ContractScaffold $scaffold;

    protected function setUp(): void
    {
        $this->scaffold = new ContractScaffold();
    }

    protected function tearDown(): void
    {
        $this->scaffold->destroy();
    }

    public function testGeneratedJsonClientMapsEnvelopeToNestedDto(): void
    {
        $client = $this->client(<<<'PHP'
    #[Route(method: 'POST', path: '/v1/chat')]
    public function chat(ChatStreamRequest $request): ChatEnvelopeResponse;
PHP, 'JsonLoopApiInterface', [new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'id' => 3,
                'name' => 'nested',
                'tags' => [['label' => 'x']],
            ],
        ], JSON_THROW_ON_ERROR))]);

        $request = new ChatStreamRequest();
        $request->prompt = 'hi';
        $dto = $client->chat($request);

        self::assertInstanceOf(ChatEnvelopeResponse::class, $dto);
        self::assertSame(3, $dto->data->id);
        self::assertSame('nested', $dto->data->name);
        self::assertInstanceOf(TagDto::class, $dto->data->tags[0]);
        self::assertSame('x', $dto->data->tags[0]->label);
    }

    public function testGeneratedJsonClientRejectsBusinessCode(): void
    {
        $client = $this->client(<<<'PHP'
    #[Route(method: 'POST', path: '/v1/chat')]
    public function chat(ChatStreamRequest $request): ChatEnvelopeResponse;
PHP, 'JsonFailApiInterface', [new Response(200, ['Content-Type' => 'application/json'], '{"code":2,"msg":"denied"}')]);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('denied');
        $client->chat(new ChatStreamRequest());
    }

    public function testGeneratedSseClientSendsAcceptAndParsesEvents(): void
    {
        $raw = "event: delta\nid: 9\ndata: {\"token\":\"ab\"}\n\n";
        [$client, $history] = $this->clientWithHistory(<<<'PHP'
    #[StreamResponse]
    #[Route(method: 'POST', path: '/v1/chat/stream')]
    public function chatStream(ChatStreamRequest $request): array;
PHP, 'SseLoopApiInterface', [new Response(200, ['Content-Type' => 'application/json'], $raw)]);

        $request = new ChatStreamRequest();
        $request->prompt = 'stream';
        $events = $client->chatStream($request);

        self::assertSame([
            ['event' => 'delta', 'id' => '9', 'data' => ['token' => 'ab']],
        ], $events);
        $sent = $history->items[0]['request'];
        self::assertSame('text/event-stream', $sent->getHeaderLine('Accept'));
        self::assertSame('swoolefy-api-sdk', $sent->getHeaderLine('x-user-agent'));
        self::assertSame('application/json', $sent->getHeaderLine('Content-Type'));
        self::assertStringContainsString('stream', (string) $sent->getBody());
    }

    public function testGeneratedChunkedClientReturnsRawBody(): void
    {
        $raw = '{"code":1,"msg":"not-json-envelope"}';
        $client = $this->client(<<<'PHP'
    #[ChunkedResponse]
    #[Route(method: 'GET', path: '/v1/export')]
    public function export(ChatStreamRequest $request): string;
PHP, 'ChunkedLoopApiInterface', [new Response(200, ['Content-Type' => 'application/json'], $raw)]);

        $body = $client->export(new ChatStreamRequest());
        self::assertSame($raw, $body);
    }

    public function testGeneratedDownloadClientReturnsFilePayload(): void
    {
        $client = $this->client(<<<'PHP'
    #[DownloadResponse]
    #[Route(method: 'GET', path: '/v1/file')]
    public function download(ChatStreamRequest $request): array;
PHP, 'DownloadLoopApiInterface', [new Response(200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="a.txt"',
        ], 'file-body')]);

        $file = $client->download(new ChatStreamRequest());
        self::assertSame('file-body', $file['content']);
        self::assertSame('a.txt', $file['filename']);
        self::assertSame('text/plain', $file['contentType']);
    }

    /**
     * @param list<Response> $responses
     */
    private function client(string $methods, string $interfaceShort, array $responses): object
    {
        [$client] = $this->clientWithHistory($methods, $interfaceShort, $responses);

        return $client;
    }

    /**
     * @param list<Response> $responses
     * @return array{0: object, 1: \stdClass}
     */
    private function clientWithHistory(string $methods, string $interfaceShort, array $responses): array
    {
        $fqcn = $this->scaffold->writeInterface($interfaceShort, $methods);
        $path = $this->scaffold->generate($fqcn);
        require_once $path;

        $clientClass = preg_replace('/Interface$/', '', $fqcn);
        self::assertIsString($clientClass);
        $clientClass = str_replace('\\Interface\\', '\\Client\\', $clientClass);

        $history = new \stdClass();
        $history->items = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history->items));
        $http = new Client(['handler' => $stack]);

        return [new $clientClass($http, 'http://127.0.0.1'), $history];
    }
}
