<?php

declare(strict_types=1);

namespace InterfaceApi\ScheduleJob\App\Module\Cron\Request\CronTaskManager;

use InterfaceApi\Support\ApiProperty;
use InterfaceApi\Support\BaseRequest;
use InterfaceApi\Support\StringToInt;
use InterfaceApi\Support\ValidationRule;

class ExecutionCancelRequest extends BaseRequest
{
    #[ApiProperty(description: 'cron_task_log.id')]
    #[ValidationRule(rule: 'required|int', message: 'id 不能为空')]
    #[StringToInt]
    protected int $id = 0;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }
}
