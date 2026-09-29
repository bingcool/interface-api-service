<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Fixture;

use InterfaceApi\Support\AbstractDto;
use InterfaceApi\Support\ArrayList;

class ChatDataDto extends AbstractDto
{
    public int $id = 0;

    public string $name = '';

    /** @var list<TagDto> */
    #[ArrayList(TagDto::class)]
    public array $tags = [];
}
