<?php

declare(strict_types=1);

namespace Acme\Sale;

final class AllowedInline
{
    public function make(): object
    {
        return new \Acme\Core\Customer();
    }
}
