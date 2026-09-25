<?php

declare(strict_types=1);

namespace InterfaceApi\ScheduleJob\App\Module\Staff\Dto\StaffUser;

use InterfaceApi\Support\AbstractDto;
use InterfaceApi\Support\ApiProperty;

class StaffNodeGroupBriefDto extends AbstractDto
{
    #[ApiProperty(description: '节点组 ID')]
    protected int $id = 0;

    #[ApiProperty(description: '节点组名称')]
    protected string $groupName = '';
}
