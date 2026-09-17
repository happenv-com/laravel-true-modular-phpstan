<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Support\CircularDependencyImportRuleTestCase;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;

uses(CircularDependencyImportRuleTestCase::class);

it('reports the test import that closes the cycle', function (): void {
    $this->analyse(
        [FixturePaths::devCircularFile('a/tests/Feature/ClosesTheCycleTest.php')],
        [[
            'This import puts "acme/a" inside a dependency cycle: it uses "Acme\B\Widget" from module '
                .'"acme/b", which depends on "acme/a" again. The edge exists only because of this '
                .'`require-dev` declaration, so it can be removed — replace "Acme\B\Widget" with a '
                .'fixture this module owns, or move the test to "acme/b", whichever actually owns the subject.',
            7,
        ]],
    );
});

it('stays silent on a require edge, which no test author can take back', function (): void {
    // acme/b requires acme/a in its SHIPPED code and is itself reached from acme/a, so this
    // import sits on the very same cycle. Reporting it would be advice nobody can act on:
    // the fix is a boundary decision, not moving a test.
    $this->analyse([FixturePaths::devCircularFile('b/src/ReachesBackOnRequire.php')], []);
});

it('stays silent on an import that leaves the module', function (): void {
    $this->analyse([FixturePaths::devCircularFile('a/src/Thing.php')], []);
});
