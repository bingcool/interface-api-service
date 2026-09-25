<?php

declare(strict_types=1);

namespace InterfaceApi\ScheduleJob\App\Module\Cron\Response\CronTaskManager;

use InterfaceApi\ScheduleJob\App\Module\Common\Http\BasePageResultResponse;
use InterfaceApi\Support\ApiProperty;
use InvalidArgumentException;

class TaskLogsResponse extends BasePageResultResponse
{
    #[ApiProperty(description: '分页 data')]
    protected TaskLogsPageResult $data;

    public function __construct(TaskLogsPageResult $data)
    {
        $this->data = $data;
    }

    public function getData(): TaskLogsPageResult
    {
        return $this->data;
    }

    /**
     * @param TaskLogsPageResult $data
     * @return $this
     */
    public function setData($data): static
    {
        if (!$data instanceof TaskLogsPageResult) {
            throw new InvalidArgumentException('data must be TaskLogsPageResult');
        }
        $this->data = $data;

        return $this;
    }
}
