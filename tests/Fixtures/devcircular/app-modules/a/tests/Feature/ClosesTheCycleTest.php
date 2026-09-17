<?php

declare(strict_types=1);

namespace Acme\A\Tests\Feature;

use Acme\B\Widget;

final class ClosesTheCycleTest
{
    public function subject(): string
    {
        return Widget::class;
    }
}
