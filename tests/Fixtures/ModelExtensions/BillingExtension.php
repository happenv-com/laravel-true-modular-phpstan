<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;

/**
 * A shared parent of the billing module's extensions. Its accessors are not
 * declared on any registered extension, so none of them is contributed.
 */
abstract class BillingExtension extends ModelExtension
{
    public function getCurrencyAttribute(): string
    {
        return 'EUR';
    }
}
