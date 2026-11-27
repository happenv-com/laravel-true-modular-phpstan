<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Bags;

/**
 * A bag with neither extensionsFor() nor the registry: no order can be read from it.
 */
final class BagOfUnknownShape {}
