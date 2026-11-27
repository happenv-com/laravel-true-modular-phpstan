<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModelExtensionAttributesTypeTestCase;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModelExtensionFixtures;

uses(ModelExtensionAttributesTypeTestCase::class);

beforeEach(function (): void {
    ModelExtensionFixtures::boot();
});

afterEach(function (): void {
    ModelExtensionFixtures::shutDown();
});

/**
 * Each `assertType()` in the fixture is one expectation: accessor types from native
 * signatures and PHPDoc, accessors from two modules on one model, the accessor
 * winning over a stale `@property` tag (the reason the resolver is opt-in), and a
 * relation-only extension still typed by the Dynamic Relation Resolver.
 */
it('types contributed attributes from their accessors', function (): void {
    $asserts = $this::gatherAssertTypes(FixturePaths::modelExtensionFile('attribute-types.php'));

    expect($asserts)->toHaveCount(5);

    foreach ($asserts as $assert) {
        $this->assertFileAsserts(...$assert);
    }
});
