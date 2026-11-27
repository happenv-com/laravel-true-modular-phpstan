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
 * from the bag the booted application filled: through `extensionsFor()` where
 * laravel-true-modular provides it, and from the bag's registry on versions that do
 * not. Without that order (nothing booted, or a bag this code does not recognise) the
 * modules' declaration order is used.
 *
 * This class knows nothing about PHPStan: all the logic lives here precisely so it
 * can be tested without PHPStan's container.
 */
final class ModelExtensionAttributeMap
{
    /** @var array<class-string, array<string, ModelExtensionAttribute>>|null */
    private ?array $map = null;

    /**
     * @param  class-string  $resolversBag  the class whose resolution order to follow; only
     *                                      tests pass anything but laravel-true-modular's bag
     */
    public function __construct(
        private readonly ModuleManifestRepository $modules,
        private readonly string $resolversBag = AttributeResolversBag::class,
    ) {}

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

        $map = [];

        foreach ($extensions as $model => $modelExtensions) {
            foreach ($this->inRuntimeOrder($modelExtensions, $this->runtimeOrderOf($model)) as $extension) {
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

    /** @var array<string, list<string>>|null */
    private ?array $registryOrder = null;

    /**
     * The extensions of $model in the order `AttributeResolversBag::resolve()` asks them,
     * as the booted application registered them.
     *
     * @return list<string>
     */
    private function runtimeOrderOf(string $model): array
    {
        $extensionsFor = [$this->resolversBag, 'extensionsFor'];

        if (method_exists($this->resolversBag, 'extensionsFor') && is_callable($extensionsFor)) {
            $order = $extensionsFor($model);

            return is_array($order) ? array_values(array_filter($order, is_string(...))) : [];
        }

        return ($this->registryOrder ??= $this->registryOrder())[$model] ?? [];
    }

    /**
     * The same order, for laravel-true-modular versions whose bag has no `extensionsFor()`:
     * read from the bag's private registry. Anything unexpected there yields no order
     * rather than a wrong one.
     *
     * @return array<string, list<string>>
     */
    private function registryOrder(): array
    {
        try {
            $resolvers = (new ReflectionProperty($this->resolversBag, 'resolvers'))->getValue();
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
