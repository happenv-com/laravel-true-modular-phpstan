<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\DynamicRelationResolver;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

final class DynamicRelationPropertiesClassReflectionExtension implements PropertiesClassReflectionExtension
{
    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        $relation = DynamicRelationResolverHelper::resolveRelation($classReflection, $propertyName);

        return $relation instanceof Relation;
    }

    /**
     * @throws ShouldNotHappenException
     */
    public function getProperty(ClassReflection $classReflection, string $propertyName): DynamicRelationPropertyReflection
    {
        $relation = DynamicRelationResolverHelper::resolveRelation($classReflection, $propertyName);

        if (! $relation instanceof Relation) {
            throw new ShouldNotHappenException(sprintf(
                'Dynamic relation "%s" not found on model "%s".',
                $propertyName,
                $classReflection->getName(),
            ));
        }

        /** @var Model $related */
        $related = $relation->getRelated();

        $type = $this->resolvePropertyType($relation, $related);

        return new DynamicRelationPropertyReflection(
            $classReflection,
            $propertyName,
            $type,
        );
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relation
     */
    private function resolvePropertyType(Relation $relation, Model $related): Type
    {
        // To-one relationships return nullable model
        if ($relation instanceof HasOne
            || $relation instanceof BelongsTo
            || $relation instanceof MorphOne
            || $relation instanceof HasOneThrough
        ) {
            return new UnionType([
                new ObjectType($related::class),
                new NullType,
            ]);
        }

        // To-many relationships return collection
        return new GenericObjectType(
            EloquentCollection::class,
            [
                new ObjectType('int'),
                new ObjectType($related::class),
            ],
        );
    }
}
