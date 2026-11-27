<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;

/**
 * Provides `budget` as a label. So does TaskEstimateExtension, as a number of
 * hours: only one of them can answer at runtime.
 */
final class TaskBudgetExtension extends ModelExtension
{
    public function getBudgetAttribute(): string
    {
        return 'one week';
    }
}
