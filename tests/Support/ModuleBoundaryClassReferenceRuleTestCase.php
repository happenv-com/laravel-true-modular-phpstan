<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\ModuleBoundaryClassReferenceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ModuleBoundaryClassReferenceRule>
 */
class ModuleBoundaryClassReferenceRuleTestCase extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ModuleBoundaryClassReferenceRule(FixturePaths::moduleBaseDir());
    }
}
