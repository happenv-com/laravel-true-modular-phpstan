<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fixture model. Its `posts` and `profile` relations are registered at runtime
 * via {@see Model::resolveRelationUsing()} in the test bootstrap, exactly the way
 * one true-module would extend another module's model — so they are invisible to
 * native reflection and must be supplied by the dynamic relation resolver.
 */
final class User extends Model {}
