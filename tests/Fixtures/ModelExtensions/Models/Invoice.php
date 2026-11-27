<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Documents an attribute a model extension also provides, with a type that has
 * drifted from the accessor's.
 *
 * @property string $total_due
 */
final class Invoice extends Model {}
