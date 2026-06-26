<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\DynamicRelationResolver;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

final readonly class DynamicRelationPropertyReflection implements PropertyReflection
{
    public function __construct(
        private ClassReflection $declaringClass,
        // @phpstan-ignore property.onlyWritten
        private string $name,
        private Type $readableType,
    ) {}

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
        // Możesz ustawić na false, jeśli nie chcesz pozwalać na zapis.
        return false;
    }

    public function getReadableType(): Type
    {
        return $this->readableType;
    }

    public function getWritableType(): Type
    {
        return $this->readableType;
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
