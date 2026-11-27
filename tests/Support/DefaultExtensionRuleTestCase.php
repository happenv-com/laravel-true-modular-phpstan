<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use PHPStan\Rules\Properties\AccessPropertiesCheck;
use PHPStan\Rules\Properties\AccessPropertiesRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * PHPStan's own undefined-property rule, run with only the default `extension.neon`
 * — what a consumer gets from extension-installer without opting into anything.
 *
 * @extends RuleTestCase<AccessPropertiesRule>
 */
class DefaultExtensionRuleTestCase extends RuleTestCase
{
    /**
     * @return array<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__.'/../../extension.neon',
            __DIR__.'/report-magic-properties.neon',
        ];
    }

    protected function getRule(): Rule
    {
        return new AccessPropertiesRule(self::getContainer()->getByType(AccessPropertiesCheck::class));
    }
}
