<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\CircularDependencyImportRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CircularDependencyImportRule>
 */
class CircularDependencyImportRuleTestCase extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CircularDependencyImportRule(FixturePaths::devCircularBaseDir());
    }
}
