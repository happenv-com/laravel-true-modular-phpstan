<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModelExtensionAttributesRuleTestCase;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModelExtensionFixtures;

uses(ModelExtensionAttributesRuleTestCase::class);

beforeEach(function (): void {
    ModelExtensionFixtures::boot();
});

afterEach(function (): void {
    ModelExtensionFixtures::shutDown();
});

/**
 * With `rules/model-extension-attributes.neon` included next to the relation
 * resolver, the accessors and relations model extensions contribute are no longer
 * undefined properties, and everything that is not contributed still is.
 */
it('removes the undefined-property errors for contributed attributes only', function (): void {
    $undefined = fn (string $model, string $property, int $line): array => [
        sprintf('Access to an undefined property %s::$%s.', $model, $property),
        $line,
        'Learn more: <fg=cyan>https://phpstan.org/blog/solving-phpstan-access-to-undefined-property</>',
    ];

    $this->analyse([FixturePaths::modelExtensionFile('UsesContributedAttributes.php')], [
        $undefined(Task::class, 'internal_note', 20),
        $undefined(Task::class, 'currency', 21),
        $undefined(Task::class, 'overdue', 22),
        $undefined(Project::class, 'remaining_hours', 23),
    ]);
});
