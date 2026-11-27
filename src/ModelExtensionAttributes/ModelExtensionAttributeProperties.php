<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Illuminate\Container\Container;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\ShouldNotHappenException;

/**
 * Teaches PHPStan the attributes model extensions add to other modules' models.
 *
 * The second half of what the Dynamic Relation Resolver already does for relations:
 * that one resolves `Model::relationResolver()`, this one the accessors that
 * laravel-true-modular's `AttributeResolver` serves through
 * `handleMissingAttributeViolationUsing()`.
 *
 * The type comes from the accessor's signature through the `ReflectionProvider`, not
 * through PHP reflection, so the method's PHPDoc is honoured too. It is the only
 * source of truth about the type, so there is nothing to duplicate or drift.
 *
 * The module registry lives in the Laravel container, which is filled when the
 * application boots during analysis (Larastan does that), the same precondition the
 * Dynamic Relation Resolver relies on. Without a booted application the registry is
 * empty and this extension claims nothing.
 */
final class ModelExtensionAttributeProperties implements PropertiesClassReflectionExtension
{
    private ?ModelExtensionAttributeMap $map = null;

    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        return $this->map()->find($classReflection->getName(), $propertyName) instanceof ModelExtensionAttribute;
    }

    /**
     * @throws ShouldNotHappenException when PHPStan asks for a property this extension never claimed
     */
    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $attribute = $this->map()->find($classReflection->getName(), $propertyName);

        if (! $attribute instanceof ModelExtensionAttribute) {
            throw new ShouldNotHappenException(sprintf(
                'Model extension attribute "%s" not found on "%s".',
                $propertyName,
                $classReflection->getName(),
            ));
        }

        $method = $this->reflectionProvider->getClass($attribute->extension)->getNativeMethod($attribute->method);

        return new ModelExtensionAttributeProperty(
            $classReflection,
            $method->getVariants()[0]->getReturnType(),
        );
    }

    private function map(): ModelExtensionAttributeMap
    {
        return $this->map ??= new ModelExtensionAttributeMap(
            Container::getInstance()->make(ModuleManifestRepository::class),
        );
    }
}
