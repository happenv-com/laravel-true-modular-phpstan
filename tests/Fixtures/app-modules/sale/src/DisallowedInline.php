<?php

declare(strict_types=1);

namespace Acme\Sale;

final class DisallowedInline
{
    public function make(): object
    {
        return new \Acme\Catalog\Product();
    }
}
