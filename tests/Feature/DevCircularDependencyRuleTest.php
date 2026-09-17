<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Support\DevCircularDependencyRuleTestCase;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;

uses(DevCircularDependencyRuleTestCase::class);

it('reports a cycle that only a require-dev edge closes', function (): void {
    $this->analyse(
        [FixturePaths::devCircularFile('a/src/Thing.php')],
        [[
            'Circular dependency detected between modules: acme/a → acme/b → acme/a. This creates a '
                .'tight coupling between modules and should be resolved by introducing an abstraction '
                .'or rethinking the module boundaries. A `require-dev` edge can be what closes it, in '
                .'which case the import that creates it is reported on its own line by '
                .'CircularDependencyImportRule.',
            -1,
        ]],
    );
});
