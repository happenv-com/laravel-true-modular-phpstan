<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Extended by two modules at once: each contributes its own accessors.
 */
final class Task extends Model {}
