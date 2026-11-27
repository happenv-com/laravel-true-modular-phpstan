<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;

/**
 * @return list<mixed>
 */
function readsContributedAttributes(Task $task, Project $project): array
{
    return [
        $task->remaining_hours,
        $task->elapsed_hours,
        $task->billable_amount,
        $project->milestones,
        $task->internal_note,
        $task->currency,
        $task->overdue,
        $project->remaining_hours,
    ];
}
