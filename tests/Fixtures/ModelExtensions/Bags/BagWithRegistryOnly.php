<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Bags;

use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskBudgetExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskEstimateExtension;

/**
 * The shape of AttributeResolversBag before it had extensionsFor(): the order lives
 * only in its private registry, keyed by model and then by extension.
 */
final class BagWithRegistryOnly
{
    /** @var array<class-string, array<class-string, true>> */
    private static array $resolvers = [
        Task::class => [TaskBudgetExtension::class => true, TaskEstimateExtension::class => true],
    ];
}
