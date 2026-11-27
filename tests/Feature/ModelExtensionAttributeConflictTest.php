<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModelExtension\AttributeResolversBag;
use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes\ModelExtensionAttributeMap;
use Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes\ModelExtensionAttributeProperties;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Bags\BagOfUnknownShape;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Bags\BagWithExtensionsFor;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Bags\BagWithRegistryOnly;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskBudgetExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskEstimateExtension;
use Illuminate\Container\Container;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\VerbosityLevel;

/**
 * Two modules provide `budget` on the same model, with different types. At runtime
 * `AttributeResolversBag` answers with the extension registered FIRST, and extensions
 * are registered while providers initialise, in module dependency order, which need
 * not be the order the modules were declared in. PHPStan must type the attribute from
 * the accessor that really runs.
 *
 * The tests against the real bag go through `AttributeResolversBag::extensionsFor()`
 * on laravel-true-modular versions that have it and through the bag's registry on
 * older ones; the fixture bags below pin each path on any installed version.
 */
function conflictingModules(): ModuleManifestRepository
{
    $modules = new ModuleManifestRepository;

    $modules->register((new Module)->name('acme/estimates')->hasModelExtensions(Task::class, TaskEstimateExtension::class));
    $modules->register((new Module)->name('acme/budgets')->hasModelExtensions(Task::class, TaskBudgetExtension::class));

    return $modules;
}

afterEach(function (): void {
    Container::setInstance(null);
    AttributeResolversBag::flush();
});

it('types the attribute from the accessor the runtime resolver answers with', function (array $initialisationOrder): void {
    foreach ($initialisationOrder as $extension) {
        AttributeResolversBag::addExtension(Task::class, $extension);
    }

    $container = new Container;
    $container->instance(ModuleManifestRepository::class, conflictingModules());
    Container::setInstance($container);

    $runtime = get_debug_type(AttributeResolversBag::resolve(new Task, 'budget'));

    $analysed = (new ModelExtensionAttributeProperties(PHPStanTestCase::createReflectionProvider()))
        ->getProperty(PHPStanTestCase::createReflectionProvider()->getClass(Task::class), 'budget')
        ->getReadableType()
        ->describe(VerbosityLevel::precise());

    expect($analysed)->toBe($runtime);
})->with([
    'initialised in declaration order' => [[TaskEstimateExtension::class, TaskBudgetExtension::class]],
    'initialised in a different order' => [[TaskBudgetExtension::class, TaskEstimateExtension::class]],
]);

it('follows the runtime order rather than the declaration order', function (): void {
    AttributeResolversBag::addExtension(Task::class, TaskBudgetExtension::class);
    AttributeResolversBag::addExtension(Task::class, TaskEstimateExtension::class);

    expect((new ModelExtensionAttributeMap(conflictingModules()))->find(Task::class, 'budget')?->extension)
        ->toBe(TaskBudgetExtension::class);
});

it('falls back to the first declared extension when no application has booted', function (): void {
    expect((new ModelExtensionAttributeMap(conflictingModules()))->find(Task::class, 'budget')?->extension)
        ->toBe(TaskEstimateExtension::class);
});

describe('reading the runtime order', function (): void {
    it('uses extensionsFor() when the bag provides it', function (): void {
        expect((new ModelExtensionAttributeMap(conflictingModules(), BagWithExtensionsFor::class))->find(Task::class, 'budget')?->extension)
            ->toBe(TaskBudgetExtension::class);
    });

    it('reads the bag\'s registry when the bag has no extensionsFor()', function (): void {
        expect((new ModelExtensionAttributeMap(conflictingModules(), BagWithRegistryOnly::class))->find(Task::class, 'budget')?->extension)
            ->toBe(TaskBudgetExtension::class);
    });

    it('falls back to the declaration order when the bag offers neither', function (): void {
        expect((new ModelExtensionAttributeMap(conflictingModules(), BagOfUnknownShape::class))->find(Task::class, 'budget')?->extension)
            ->toBe(TaskEstimateExtension::class);
    });
});
