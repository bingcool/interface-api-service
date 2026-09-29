<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Support;

use GuzzleHttp\Psr7\Response;
use InterfaceApi\Support\ClientException;
use InterfaceApi\Support\CovertProperty;
use InterfaceApi\Tests\Fixture\ChatEnvelopeResponse;
use InterfaceApi\Tests\Fixture\TagDto;
use PHPUnit\Framework\TestCase;

final class BaseClientApiResponseParserTest extends TestCase
{
    private ParserProbeClient $client;

    protected function setUp(): void
    {
        $this->client = new ParserProbeClient();
    }

    public function testJsonSuccessDecodesBusinessEnvelopeAndNestedDto(): void
    {
        $payload = $this->client->parse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'id' => 7,
                'name' => 'chat',
                'tags' => [
                    ['label' => 'a'],
                    ['label' => 'b'],
                ],
            ],
        ], JSON_THROW_ON_ERROR)));

        $dto = CovertProperty::toCovertDeepProperty($payload, ChatEnvelopeResponse::class);
        self::assertInstanceOf(ChatEnvelopeResponse::class, $dto);
        self::assertSame(7, $dto->data->id);
        self::assertSame('chat', $dto->data->name);
        self::assertContainsOnlyInstancesOf(TagDto::class, $dto->data->tags);
        self::assertSame(['a', 'b'], array_map(static fn (TagDto $tag): string => $tag->label, $dto->data->tags));
    }

    public function testJsonBusinessCodeFailure(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('nope');
        $this->client->parse(new Response(200, ['Content-Type' => 'application/json'], '{"code":1,"msg":"nope"}'));
    }

    public function testJsonNon2xx(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Unexpected HTTP status: 500');
        $this->client->parse(new Response(500, ['Content-Type' => 'application/json'], '{"code":0}'));
    }

    public function testJsonInvalidBody(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid JSON');
        $this->client->parse(new Response(200, ['Content-Type' => 'application/json'], '{'));
    }

    public function testSseParserCoversEventsCommentsAndJsonData(): void
    {
        $raw = implode("\n", [
            ': heartbeat',
            '',
            'event: delta',
            'id: 1',
            'data: hello',
            'data: world',
            '',
            'event: done',
            'id: 2',
            'data: {"ok":true}',
            '',
            'data: plain',
            '',
        ]);

        $events = $this->client->parse(new Response(200, ['Content-Type' => 'text/event-stream'], $raw), 'sse');

        self::assertSame([
            ['event' => 'delta', 'id' => '1', 'data' => "hello\nworld"],
            ['event' => 'done', 'id' => '2', 'data' => ['ok' => true]],
            ['event' => 'message', 'id' => null, 'data' => 'plain'],
        ], $events);
    }

    public function testChunkedForceTypeReturnsRawBodyWithoutBusinessCheck(): void
    {
        $raw = '{"code":99,"msg":"ignored"}';
        $body = $this->client->parse(new Response(200, ['Content-Type' => 'application/json'], $raw), 'chunked');

        self::assertSame($raw, $body);
    }

    public function testDownloadParserReadsFilenameAndContentType(): void
    {
        $quoted = $this->client->parse(new Response(200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="a.txt"',
        ], 'abc'), 'download');
        self::assertSame([
            'content' => 'abc',
            'filename' => 'a.txt',
            'contentType' => 'text/plain',
        ], $quoted);

        $encoded = $this->client->parse(new Response(200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "inline; filename*=UTF-8''%E4%B8%AD%E6%96%87.txt",
        ], 'bin'), 'download');
        self::assertSame('中文.txt', $encoded['filename']);
        self::assertSame('application/octet-stream', $encoded['contentType']);
        self::assertSame('bin', $encoded['content']);

        $missing = $this->client->parse(new Response(200, [
            'Content-Type' => 'application/pdf',
        ], '%PDF'), 'download');
        self::assertNull($missing['filename']);
        self::assertSame('application/pdf', $missing['contentType']);
        self::assertSame('%PDF', $missing['content']);
    }
}
