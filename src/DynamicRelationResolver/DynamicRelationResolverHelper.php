<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\DynamicRelationResolver;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPStan\Reflection\ClassReflection;

final class DynamicRelationResolverHelper
{
    // @phpstan-ignore missingType.generics
    public static function resolveRelation(
        ClassReflection $classReflection,
        string $relationName,
    ): ?Relation {
        $className = $classReflection->getName();

        if (! is_subclass_of($className, Model::class)) {
            return null;
        }

        $native = $classReflection->getNativeReflection();

        if ($native->isAbstract()) {
            return null;
        }

        /** @var Model $model */
        $model = $native->newInstanceWithoutConstructor();

        /** @var mixed $resolver */
        $resolver = $model->relationResolver($className, $relationName);

        if (! $resolver instanceof Closure) {
            return null;
        }

        $relation = $resolver($model);

        if (! $relation instanceof Relation) {
            return null;
        }

        return $relation;
    }
}
