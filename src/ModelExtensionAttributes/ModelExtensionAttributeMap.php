<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes;

use Happenv\LaravelTrueModular\ModelExtension\AttributeResolversBag;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The attributes modules add to other modules' models through model extensions.
 *
 * The source of truth is the module registry filled while the application boots,
 * not file names, so an extension class named anything at all still counts.
 *
 * ONLY `get<X>Attribute()` accessors are collected. Relation methods are skipped on
 * purpose: the Dynamic Relation Resolver already describes them, and two extensions
 * declaring the same property would be a conflict, not redundancy.
 *
 * When several extensions of one model provide the same attribute, the one that wins
 * is the one laravel-true-modular's `AttributeResolversBag` asks first at runtime: the
 * extension registered first. That is the order the providers are INITIALISED in
 * (dependency order), not the order they registered their modules in, so it is read
 * from the bag the booted application filled. Without that order (nothing booted, or
 * a bag this code does not recognise) the modules' declaration order is used.
 *
 * This class knows nothing about PHPStan: all the logic lives here precisely so it
 * can be tested without PHPStan's container.
 */
final class ModelExtensionAttributeMap
{
    /** @var array<class-string, array<string, ModelExtensionAttribute>>|null */
    private ?array $map = null;

    public function __construct(private readonly ModuleManifestRepository $modules) {}

    /**
     * @param  class-string  $model
     */
    public function find(string $model, string $attribute): ?ModelExtensionAttribute
    {
        return $this->all()[$model][$attribute] ?? null;
    }

    /**
     * @return array<class-string, array<string, ModelExtensionAttribute>>
     */
    public function all(): array
    {
        return $this->map ??= $this->build();
    }

    /**
     * @return array<class-string, array<string, ModelExtensionAttribute>>
     */
    private function build(): array
    {
        /** @var array<class-string, list<class-string>> $extensions */
        $extensions = [];

        foreach ($this->modules->all() as $module) {
            foreach ($module->modelExtensions as $model => $extension) {
                $extensions[$model][] = $extension;
            }
        }

        $runtimeOrder = $this->runtimeOrder();
        $map = [];

        foreach ($extensions as $model => $modelExtensions) {
            foreach ($this->inRuntimeOrder($modelExtensions, $runtimeOrder[$model] ?? []) as $extension) {
                foreach ($this->accessorsOf($extension) as $attribute) {
                    // The first extension to provide an attribute answers for it, as at runtime.
                    $map[$model][$attribute->attribute] ??= $attribute;
                }
            }
        }

        return $map;
    }

    /**
     * $extensions sorted by their position in $runtimeOrder. The sort is stable, so
     * extensions the runtime order does not list keep their declaration order, after
     * the ones it does.
     *
     * @param  list<class-string>  $extensions
     * @param  list<string>  $runtimeOrder
     * @return list<class-string>
     */
    private function inRuntimeOrder(array $extensions, array $runtimeOrder): array
    {
        $position = array_flip($runtimeOrder);

        usort($extensions, static fn (string $a, string $b): int => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX));

        return $extensions;
    }

    /**
     * The extensions of each model in the order `AttributeResolversBag::resolve()` asks
     * them, as the booted application registered them.
     *
     * The bag exposes no reader for that order, so it is read from its registry
     * directly. Anything unexpected there yields no order rather than a wrong one.
     *
     * @return array<string, list<string>>
     */
    private function runtimeOrder(): array
    {
        try {
            $resolvers = (new ReflectionProperty(AttributeResolversBag::class, 'resolvers'))->getValue();
        } catch (ReflectionException) {
            return [];
        }

        if (! is_array($resolvers)) {
            return [];
        }

        $order = [];

        foreach ($resolvers as $model => $byExtension) {
            if (is_string($model) && is_array($byExtension)) {
                $order[$model] = array_values(array_filter(array_keys($byExtension), is_string(...)));
            }
        }

        return $order;
    }

    /**
     * @param  class-string  $extension
     * @return list<ModelExtensionAttribute>
     */
    private function accessorsOf(string $extension): array
    {
        $found = [];

        foreach ((new ReflectionClass($extension))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $extension) {
                continue;
            }

            if (preg_match('/^get(.+)Attribute$/', $method->getName(), $matches) !== 1) {
                continue;
            }

            $found[] = new ModelExtensionAttribute(
                Str::snake($matches[1]),
                $extension,
                $method->getName(),
            );
        }

        return $found;
    }
}
