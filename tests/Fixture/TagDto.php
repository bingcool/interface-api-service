<?php

declare(strict_types=1);

namespace InterfaceApi\Tests\Fixture;

use InterfaceApi\Support\AbstractDto;

class TagDto extends AbstractDto
{
    public string $label = '';
}
