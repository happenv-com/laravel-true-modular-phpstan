<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\ModuleBoundaryRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ModuleBoundaryRule>
 */
class ModuleBoundaryRuleTestCase extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ModuleBoundaryRule(FixturePaths::moduleBaseDir());
    }
}
