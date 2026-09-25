<?php

declare(strict_types=1);

namespace InterfaceApi\ScheduleJob\App\Module\Cron\Response\CronTaskManager;

use InterfaceApi\ScheduleJob\App\Module\Common\Http\BaseListResponse;
use InterfaceApi\ScheduleJob\App\Module\Cron\Dto\CronTaskManager\CronNodeGroupListDataDto;
use InterfaceApi\Support\ApiProperty;
use InvalidArgumentException;

class CronNodeGroupListResponse extends BaseListResponse
{
    #[ApiProperty(description: '节点分组列表 data')]
    protected CronNodeGroupListDataDto $data;

    /**
     * @param CronNodeGroupListDataDto|list<\InterfaceApi\ScheduleJob\App\Module\Cron\Dto\CronTaskManager\CronAgentNodeGroupRowDto|array<string, mixed>> $list
     */
    public function __construct(CronNodeGroupListDataDto|array $list)
    {
        $this->data = $list instanceof CronNodeGroupListDataDto
            ? $list
            : CronNodeGroupListDataDto::fromItems($list);
    }

    public function getData(): CronNodeGroupListDataDto
    {
        return $this->data;
    }

    /**
     * @param CronNodeGroupListDataDto $data
     * @return $this
     */
    public function setData($data): static
    {
        if (!$data instanceof CronNodeGroupListDataDto) {
            throw new InvalidArgumentException('data must be CronNodeGroupListDataDto');
        }
        $this->data = $data;

        return $this;
    }
}
