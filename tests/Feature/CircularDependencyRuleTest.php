<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Support\CircularDependencyRuleTestCase;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;

uses(CircularDependencyRuleTestCase::class);

it('reports a circular dependency between two modules', function (): void {
    $this->analyse(
        [FixturePaths::circularFile('a/src/Foo.php')],
        [[
            'Circular dependency detected between modules: acme/a → acme/b → acme/a. This creates a tight coupling between modules and should be resolved by introducing an abstraction or rethinking the module boundaries.',
            -1,
        ]],
    );
});
