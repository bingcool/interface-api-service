<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Generator;

use InterfaceApi\Support\Generator\GeneratorException;
use InterfaceApi\Tests\ContractScaffold;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientWriterResponseModeTest extends TestCase
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

    public function testJsonClientKeepsCovertPropertyAndBusinessParser(): void
    {
        $fqcn = $this->scaffold->writeInterface('JsonApiInterface', <<<'PHP'
    #[Route(method: 'POST', path: '/v1/chat')]
    public function chat(ChatStreamRequest $request): ChatEnvelopeResponse;

    #[Route(method: 'POST', path: '/v1/ping')]
    public function ping(): void;
PHP);
        $source = file_get_contents($this->scaffold->generate($fqcn));
        self::assertIsString($source);
        self::assertStringContainsString('CovertProperty::toCovertDeepProperty($result, ChatEnvelopeResponse::class);', $source);
        self::assertStringContainsString('$result = $this->parseResponseByHeaders($response);', $source);
        self::assertStringContainsString('$this->parseResponseByHeaders($response);', $source);
        self::assertStringContainsString("json_encode(\$request->toDeepArray()", $source);
        self::assertStringNotContainsString("parseResponseByHeaders(\$response, 'sse')", $source);
        self::assertStringNotContainsString('mergeStreamClientOptions', $source);
    }

    public function testGeneratesSseChunkedAndDownloadClients(): void
    {
        $fqcn = $this->scaffold->writeInterface('MixedApiInterface', <<<'PHP'
    #[StreamResponse]
    #[Route(method: 'POST', path: '/v1/chat/stream')]
    public function chatStream(ChatStreamRequest $request): array;

    #[ChunkedResponse]
    #[Route(method: 'GET', path: '/v1/export')]
    public function export(ChatStreamRequest $request): string;

    #[DownloadResponse]
    #[Route(method: 'GET', path: '/v1/file')]
    public function download(ChatStreamRequest $request): array;
PHP);
        $source = (string) file_get_contents($this->scaffold->generate($fqcn));

        self::assertStringContainsString("\$requestDefaults['headers']['Accept'] = 'text/event-stream';", $source);
        self::assertStringContainsString('mergeStreamClientOptions($requestDefaults, $options);', $source);
        self::assertStringContainsString("return \$this->parseResponseByHeaders(\$response, 'sse');", $source);
        self::assertStringContainsString("return \$this->parseResponseByHeaders(\$response, 'chunked');", $source);
        self::assertStringContainsString("return \$this->parseResponseByHeaders(\$response, 'download');", $source);
        self::assertStringContainsString("\$requestDefaults['body'] = json_encode(\$request->toDeepArray()", $source);
        self::assertStringContainsString("\$requestDefaults['query'] = \$request->toDeepArray();", $source);
        self::assertStringNotContainsString('CovertProperty', $source);
        self::assertStringContainsString('public function chatStream(ChatStreamRequest $request, array $options = []): array', $source);
        self::assertStringContainsString('public function export(ChatStreamRequest $request, array $options = []): string', $source);
        self::assertStringContainsString('public function download(ChatStreamRequest $request, array $options = []): array', $source);

        $download = $this->methodSource($source, 'download');
        self::assertStringContainsString('mergeClientOptions', $download);
        self::assertStringNotContainsString('mergeStreamClientOptions', $download);
        $chunked = $this->methodSource($source, 'export');
        self::assertStringContainsString('mergeStreamClientOptions', $chunked);
        self::assertStringNotContainsString('mergeClientOptions', $chunked);
    }

    #[DataProvider('invalidSignatures')]
    public function testRejectsInvalidSpecialResponseSignatures(string $method, string $fragment): void
    {
        $fqcn = $this->scaffold->writeInterface('BadApiInterface' . bin2hex(random_bytes(2)), $method);

        try {
            $this->scaffold->generate($fqcn);
            self::fail('Expected GeneratorException');
        } catch (GeneratorException $e) {
            self::assertStringContainsString($fragment, $e->getMessage());
        }
    }

    /** @return list<array{0: string, 1: string}> */
    public static function invalidSignatures(): array
    {
        return [
            ["    #[StreamResponse]\n    #[Route(method: 'POST', path: '/a')]\n    public function stream(): string;", 'sse response must return array'],
            ["    #[StreamResponse]\n    #[Route(method: 'POST', path: '/a')]\n    public function stream(): ChatResponse;", 'sse response must return array'],
            ["    #[StreamResponse]\n    #[Route(method: 'POST', path: '/a')]\n    public function stream(): void;", 'sse response cannot return void'],
            ["    #[ChunkedResponse]\n    #[Route(method: 'GET', path: '/a')]\n    public function stream(): ChatResponse;", 'chunked response must return string'],
            ["    #[ChunkedResponse]\n    #[Route(method: 'GET', path: '/a')]\n    public function stream(): void;", 'chunked response cannot return void'],
            ["    #[DownloadResponse]\n    #[Route(method: 'GET', path: '/a')]\n    public function download(): ChatResponse;", 'download response must return array'],
            ["    #[DownloadResponse]\n    #[Route(method: 'GET', path: '/a')]\n    public function download(): void;", 'download response cannot return void'],
            ["    #[StreamResponse]\n    #[ChunkedResponse]\n    #[Route(method: 'POST', path: '/a')]\n    public function stream(): array;", 'only one response mode is allowed'],
            ["    #[StreamResponse]\n    #[DownloadResponse]\n    #[Route(method: 'POST', path: '/a')]\n    public function stream(): array;", 'only one response mode is allowed'],
            ["    #[ChunkedResponse]\n    #[DownloadResponse]\n    #[Route(method: 'GET', path: '/a')]\n    public function stream(): string;", 'only one response mode is allowed'],
            ["    #[StreamResponse]\n    #[ChunkedResponse]\n    #[DownloadResponse]\n    #[Route(method: 'POST', path: '/a')]\n    public function stream(): array;", 'only one response mode is allowed'],
        ];
    }

    private function methodSource(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        self::assertNotFalse($start);
        $end = strpos($source, "\n    }", $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }
}
