<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModelExtension\ExtensionAttributeResolver;
use Happenv\LaravelTrueModular\ModuleProvider\Module;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes\ModelExtensionAttribute;
use Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes\ModelExtensionAttributeMap;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\InvoiceBillingExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Invoice;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Task;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskBillingExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\TaskSchedulingExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModelExtensionFixtures;

/**
 * The map is the only place the extension's logic lives; the PHPStan adapter is a
 * thin facade over it. It is driven by a real module registry, because that is the
 * source of truth while PHPStan runs.
 */
function attributeMap(): ModelExtensionAttributeMap
{
    return new ModelExtensionAttributeMap(ModelExtensionFixtures::modules());
}

it('finds an attribute a module contributes to another module\'s model', function (): void {
    $attribute = attributeMap()->find(Task::class, 'remaining_hours');

    expect($attribute)
        ->toBeInstanceOf(ModelExtensionAttribute::class)
        ->and($attribute?->attribute)->toBe('remaining_hours')
        ->and($attribute?->extension)->toBe(TaskSchedulingExtension::class)
        ->and($attribute?->method)->toBe('getRemainingHoursAttribute');
});

it('maps every accessor on the extension, not just the first', function (): void {
    expect(attributeMap()->find(Task::class, 'elapsed_hours'))
        ->toBeInstanceOf(ModelExtensionAttribute::class);
});

it('returns null for an attribute no extension contributes', function (): void {
    expect(attributeMap()->find(Task::class, 'no_such_attribute'))->toBeNull();
});

it('returns null for an attribute on a model the extension does not touch', function (): void {
    expect(attributeMap()->find(Project::class, 'remaining_hours'))->toBeNull();
});

/**
 * Relations are the Dynamic Relation Resolver's. If the map collected them too, two
 * extensions would declare the same property. acme/scheduling is the witness: it adds
 * `Project::milestones` as a relation and Task's attributes as accessors, through
 * two extensions of the same module.
 */
it('ignores relation methods', function (): void {
    expect(attributeMap()->all())->not->toHaveKey(Project::class);
});

it('collects the accessors of every module, including several extending one model', function (): void {
    $map = attributeMap()->all();

    expect($map)->toHaveKeys([Task::class, Invoice::class])
        ->and($map[Task::class])->toHaveKeys(['remaining_hours', 'elapsed_hours', 'billable_amount'])
        ->and($map[Task::class]['billable_amount']->extension)->toBe(TaskBillingExtension::class)
        ->and($map[Invoice::class]['total_due']->extension)->toBe(InvoiceBillingExtension::class);
});

it('ignores non-public accessors, public methods that are not accessors and inherited accessors', function (): void {
    expect(array_keys(attributeMap()->all()[Task::class]))
        ->not->toContain('internal_note')
        ->not->toContain('overdue')
        ->not->toContain('currency');
});

/**
 * The map names an attribute the way laravel-true-modular resolves it at runtime,
 * so every name it hands PHPStan is one the application really answers.
 */
it('names each attribute so the runtime resolver finds its accessor', function (): void {
    $notFound = new stdClass;

    foreach (attributeMap()->all() as $model => $attributes) {
        foreach ($attributes as $name => $attribute) {
            $resolved = (new ExtensionAttributeResolver($attribute->extension))->resolve(new $model, $name, $notFound);

            expect($resolved)->not->toBe($notFound, "{$model}::\${$name} does not resolve at runtime.");
        }
    }
});

it('builds the map once', function (): void {
    $modules = new ModuleManifestRepository;
    $map = new ModelExtensionAttributeMap($modules);

    expect($map->all())->toBe([]);

    $modules->register((new Module)->name('acme/scheduling')->hasModelExtensions(Task::class, TaskSchedulingExtension::class));

    expect($map->all())->toBe([]);
});

it('is empty when no module is registered', function (): void {
    expect((new ModelExtensionAttributeMap(new ModuleManifestRepository))->all())->toBe([]);
});
