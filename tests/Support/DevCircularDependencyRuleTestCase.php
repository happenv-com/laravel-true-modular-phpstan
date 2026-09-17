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
 * The same rule as {@see CircularDependencyRuleTestCase}, pointed at the pair whose
 * cycle exists only in `require-dev` and asked to walk those edges.
 *
 * @extends RuleTestCase<CircularDependencyRule>
 */
class DevCircularDependencyRuleTestCase extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CircularDependencyRule(FixturePaths::devCircularBaseDir(), includeDevDependencies: true);
    }

    /**
     * @return array<int, Collector<Node, mixed>>
     */
    protected function getCollectors(): array
    {
        return [new CircularDependencyCollector];
    }
}
