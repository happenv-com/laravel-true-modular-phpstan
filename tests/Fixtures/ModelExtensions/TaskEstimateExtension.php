<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;

/**
 * Provides `budget` as a number of hours. So does TaskBudgetExtension, as a label:
 * only one of them can answer at runtime.
 */
final class TaskEstimateExtension extends ModelExtension
{
    public function getBudgetAttribute(): int
    {
        return 40;
    }
}
