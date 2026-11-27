<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use Happenv\LaravelTrueModular\ModelExtension\AttributeResolversBag;
use Happenv\LaravelTrueModular\ModelExtension\DynamicRelations;
use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\InvoiceBillingExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Invoice;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\ProjectSchedulingExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskBillingExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskSchedulingExtension;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Two fixture modules extending each other's models, and the state a booted
 * application leaves behind for PHPStan: the module registry in the container and
 * the extensions' relations registered with Eloquent.
 *
 * - acme/scheduling adds a relation to Project and accessors to Task.
 * - acme/billing adds accessors to Task and Invoice.
 */
final class ModelExtensionFixtures
{
    public static function modules(): ModuleManifestRepository
    {
        $modules = new ModuleManifestRepository;

        $modules->register((new Module)->name('acme/scheduling')->hasModelExtensions([
            Project::class => ProjectSchedulingExtension::class,
            Task::class => TaskSchedulingExtension::class,
        ]));

        $modules->register((new Module)->name('acme/billing')->hasModelExtensions([
            Task::class => TaskBillingExtension::class,
            Invoice::class => InvoiceBillingExtension::class,
        ]));

        return $modules;
    }

    /**
     * What booting the application does for the analysis: the module registry is
     * bound in the container, and every model extension is registered with
     * laravel-true-modular's own DynamicRelations (relations) and AttributeResolversBag
     * (accessors). The bag is filled directly rather than through AttributeResolver,
     * which would also install a missing-attribute hook on every model in the process.
     */
    public static function boot(): void
    {
        $capsule = new Capsule;
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $modules = self::modules();

        foreach ($modules->all() as $module) {
            foreach ($module->modelExtensions as $model => $extension) {
                DynamicRelations::register($model, $extension);
                AttributeResolversBag::addExtension($model, $extension);
            }
        }

        $container = new Container;
        $container->instance(ModuleManifestRepository::class, $modules);

        Container::setInstance($container);
    }

    public static function shutDown(): void
    {
        Container::setInstance(null);
        AttributeResolversBag::flush();
    }
}
