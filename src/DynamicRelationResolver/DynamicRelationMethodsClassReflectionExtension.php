<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\DynamicRelationResolver;

use Illuminate\Database\Eloquent\Relations\Relation;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;

final class DynamicRelationMethodsClassReflectionExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $relation = DynamicRelationResolverHelper::resolveRelation($classReflection, $methodName);

        return $relation instanceof Relation;
    }

    /**
     * @throws ShouldNotHappenException
     */
    public function getMethod(ClassReflection $classReflection, string $methodName): DynamicRelationMethodReflection
    {
        $relation = DynamicRelationResolverHelper::resolveRelation($classReflection, $methodName);

        if (! $relation instanceof Relation) {
            throw new ShouldNotHappenException(sprintf(
                'Dynamic relation "%s" not found on model "%s".',
                $methodName,
                $classReflection->getName(),
            ));
        }

        $returnType = new ObjectType($relation::class);

        return new DynamicRelationMethodReflection(
            $classReflection,
            $methodName,
            $returnType,
        );
    }
}
