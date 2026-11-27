<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes;

/**
 * One attribute a model extension adds to another module's model.
 *
 * `attribute` is the name Eloquent sees (`remaining_hours`), `method` is the
 * accessor that computes it (`getRemainingHoursAttribute`). Both are kept because
 * the mapping cannot be reversed without guessing, and it is `method` that carries
 * the type.
 */
final readonly class ModelExtensionAttribute
{
    /** @param class-string $extension */
    public function __construct(
        public string $attribute,
        public string $extension,
        public string $method,
    ) {}
}
