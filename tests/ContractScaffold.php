<?php

declare(strict_types=1);

namespace InterfaceApi\Tests;

use InterfaceApi\Support\Generator\ClientWriter;

final class ContractScaffold
{
    public string $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir() . '/interface-api-client-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0775, true);
    }

    public function destroy(): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function writeInterface(string $interfaceShort, string $methods): string
    {
        $namespace = 'InterfaceApi\\Tests\\Runtime' . bin2hex(random_bytes(3)) . '\\Module\\Chat\\Interface';
        $source = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use InterfaceApi\Support\ChunkedResponse;
use InterfaceApi\Support\DownloadResponse;
use InterfaceApi\Support\Route;
use InterfaceApi\Support\RouteGroup;
use InterfaceApi\Support\StreamResponse;
use InterfaceApi\Tests\Fixture\ChatEnvelopeResponse;
use InterfaceApi\Tests\Fixture\ChatResponse;
use InterfaceApi\Tests\Fixture\ChatStreamRequest;

#[RouteGroup(prefix: '/api')]
interface {$interfaceShort}
{
{$methods}
}

PHP;
        $dir = $this->root . '/Module/Chat/Interface';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $interfaceShort . '.php';
        file_put_contents($path, $source);
        require_once $path;

        return $namespace . '\\' . $interfaceShort;
    }

    public function generate(string $interfaceFqcn): string
    {
        return (new ClientWriter())->write($interfaceFqcn, 'demo-service');
    }
}
