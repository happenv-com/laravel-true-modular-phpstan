<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

final class TaskBillingExtension extends BillingExtension
{
    public function getBillableAmountAttribute(): float
    {
        return 0.0;
    }
}
