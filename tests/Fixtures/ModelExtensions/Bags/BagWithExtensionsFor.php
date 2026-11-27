<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Bags;

use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskBudgetExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskEstimateExtension;

/**
 * The current shape of laravel-true-modular's AttributeResolversBag: a public
 * extensionsFor(). Its private registry deliberately disagrees, so a test can tell
 * which of the two was read.
 */
final class BagWithExtensionsFor
{
    /** @var array<class-string, array<class-string, true>> */
    private static array $resolvers = [
        Task::class => [TaskEstimateExtension::class => true, TaskBudgetExtension::class => true],
    ];

    /** @return list<class-string> */
    public static function extensionsFor(string $model): array
    {
        return $model === Task::class ? [TaskBudgetExtension::class, TaskEstimateExtension::class] : [];
    }
}
