<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\CircularDependencyCollector;
use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\CircularDependencyRule;
use PhpParser\Node;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CircularDependencyRule>
 */
class CircularDependencyRuleTestCase extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CircularDependencyRule(FixturePaths::circularBaseDir());
    }

    /**
     * @return array<int, Collector<Node, mixed>>
     */
    protected function getCollectors(): array
    {
        return [new CircularDependencyCollector];
    }
}
