<?php

declare(strict_types=1);

namespace Acme\Sale;

use Acme\Catalog\Product;

final class DisallowedUse
{
    public function product(): ?Product
    {
        return null;
    }
}
