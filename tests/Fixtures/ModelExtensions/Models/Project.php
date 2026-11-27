<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Owned by one module, extended by another: its `milestones` relation comes from a
 * model extension that adds relations only.
 */
final class Project extends Model {}
