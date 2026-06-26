<?php

declare(strict_types=1);

namespace Acme\Outside;

use Acme\Catalog\Product;

final class Plain
{
    public function product(): ?Product
    {
        return null;
    }
}
