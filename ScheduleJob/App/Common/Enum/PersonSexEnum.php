<?php

declare(strict_types=1);

namespace InterfaceApi\ScheduleJob\App\Common\Enum;

use InterfaceApi\Support\Enum\Concerns\InteractsWithBackedEnumLabel;
use InterfaceApi\Support\Enum\BaseIntEnum;

enum PersonSexEnum: int implements BaseIntEnum
{
    use InteractsWithBackedEnumLabel;

    case UNKNOWN = 0;
    case MALE = 1;
    case FEMALE = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::UNKNOWN => 'Unknown',
            self::MALE => 'Male',
            self::FEMALE => 'Female',
        };
    }
}

