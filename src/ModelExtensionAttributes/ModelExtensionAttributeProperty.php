<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

/**
 * The property that stands for a model extension's accessor.
 *
 * Modelled on `DynamicRelationPropertyReflection`, with one deliberate difference:
 * `isWritable()` returns `true`. The attribute can be written through Eloquent's
 * attribute bag, and code does that (a test building a model with the attribute
 * already set, for one); `false` would produce new errors instead of removing old
 * ones.
 */
final readonly class ModelExtensionAttributeProperty implements PropertyReflection
{
    public function __construct(private ClassReflection $declaringClass, private Type $type) {}

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function getReadableType(): Type
    {
        return $this->type;
    }

    public function getWritableType(): Type
    {
        return $this->type;
    }

    public function canChangeTypeAfterAssignment(): bool
    {
        return false;
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }
}
