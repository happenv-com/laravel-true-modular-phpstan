<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModelExtensionAttributes;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleManifestRepository;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

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
        $map = [];

        foreach ($this->modules->all() as $module) {
            foreach ($module->modelExtensions as $model => $extension) {
                foreach ($this->accessorsOf($extension) as $attribute) {
                    $map[$model][$attribute->attribute] = $attribute;
                }
            }
        }

        return $map;
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
