<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\DefaultExtensionRuleTestCase;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModelExtensionFixtures;

uses(DefaultExtensionRuleTestCase::class);

beforeEach(function (): void {
    ModelExtensionFixtures::boot();
});

afterEach(function (): void {
    ModelExtensionFixtures::shutDown();
});

/**
 * The attribute resolver is opt-in: the default `extension.neon` still resolves the
 * relation, but leaves every accessor-backed attribute as it was before.
 */
it('is not part of the default extension.neon', function (): void {
    $undefined = fn (string $model, string $property, int $line): array => [
        sprintf('Access to an undefined property %s::$%s.', $model, $property),
        $line,
        'Learn more: <fg=cyan>https://phpstan.org/blog/solving-phpstan-access-to-undefined-property</>',
    ];

    $this->analyse([FixturePaths::modelExtensionFile('UsesContributedAttributes.php')], [
        $undefined(Task::class, 'remaining_hours', 16),
        $undefined(Task::class, 'elapsed_hours', 17),
        $undefined(Task::class, 'billable_amount', 18),
        $undefined(Task::class, 'internal_note', 20),
        $undefined(Task::class, 'currency', 21),
        $undefined(Task::class, 'overdue', 22),
        $undefined(Project::class, 'remaining_hours', 23),
    ]);
});
