<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Invoice;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;

use function PHPStan\Testing\assertType;

function attributeTypes(Task $task, Project $project, Invoice $invoice): void
{
    // From the accessor's native return type.
    assertType('int', $task->remaining_hours);

    // From the accessor's PHPDoc, which is narrower than its native type.
    assertType('int<0, max>', $task->elapsed_hours);

    // From a second module extending the same model.
    assertType('float', $task->billable_amount);

    // The accessor wins over the model's own @property tag.
    assertType('float', $invoice->total_due);

    // A relation-only extension is still the Dynamic Relation Resolver's.
    assertType('Illuminate\Database\Eloquent\Collection<int, Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Milestone>', $project->milestones);
}
