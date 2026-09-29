<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Fixture;

use InterfaceApi\Support\BaseResponse;

class ChatResponse extends BaseResponse
{
    public string $reply = '';
}
