<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use PHPStan\Testing\TypeInferenceTestCase;

/**
 * Type inference with the relation resolver and the opt-in model extension attribute
 * resolver wired exactly as a consumer includes them.
 */
class ModelExtensionAttributesTypeTestCase extends TypeInferenceTestCase
{
    /**
     * @return array<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__.'/../../rules/dynamic-relations.neon',
            __DIR__.'/../../rules/model-extension-attributes.neon',
        ];
    }
}
