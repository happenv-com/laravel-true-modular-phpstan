<?php

declare(strict_types=1);

namespace Acme\B;

use Acme\A\Thing;

final class ReachesBackOnRequire
{
    public function subject(): string
    {
        return Thing::class;
    }
}
