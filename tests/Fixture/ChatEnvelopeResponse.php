<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Fixture;

use InterfaceApi\Support\BaseResponse;

class ChatEnvelopeResponse extends BaseResponse
{
    public ChatDataDto $data;
}
