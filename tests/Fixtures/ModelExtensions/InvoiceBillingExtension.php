<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

final class InvoiceBillingExtension extends BillingExtension
{
    public function getTotalDueAttribute(): float
    {
        return 0.0;
    }
}
