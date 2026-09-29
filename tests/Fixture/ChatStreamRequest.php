<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Fixture;

use InterfaceApi\Support\BaseRequest;

class ChatStreamRequest extends BaseRequest
{
    public string $prompt = '';
}
