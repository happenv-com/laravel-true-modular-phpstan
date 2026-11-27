<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use PHPStan\Rules\Properties\AccessPropertiesCheck;
use PHPStan\Rules\Properties\AccessPropertiesRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * PHPStan's own undefined-property rule, run with the relation resolver and the
 * opt-in model extension attribute resolver wired exactly as a consumer includes them.
 *
 * @extends RuleTestCase<AccessPropertiesRule>
 */
class ModelExtensionAttributesRuleTestCase extends RuleTestCase
{
    /**
     * @return array<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__.'/../../rules/dynamic-relations.neon',
            __DIR__.'/../../rules/model-extension-attributes.neon',
            __DIR__.'/report-magic-properties.neon',
        ];
    }

    protected function getRule(): Rule
    {
        return new AccessPropertiesRule(self::getContainer()->getByType(AccessPropertiesCheck::class));
    }
}
