<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use RuntimeException;
use Throwable;

/**
 * Resolves module dependencies by reading composer.json files
 * and determining which modules are allowed to be used.
 */
final class ModuleDependencyResolver
{
    private static ?self $instance = null;

    /**
     * Map of PHP namespaces to module names.
     *
     * @var array<string, string>
     */
    private array $namespaceToModuleMap = [];

    /**
     * Map of module namespaces to their file paths.
     *
     * @var array<string, string>
     */
    private array $modulePathMap = [];

    /**
     * Map of module names to their allowed dependencies.
     *
     * @var array<string, array<string, bool>>
     */
    private array $moduleDependencies = [];

    /**
     * Map of module paths to module names (for reverse lookup).
     *
     * @var array<string, string>
     */
    private array $pathToModuleMap = [];

    private bool $initialized = false;

    public function __construct(
        /** @var string Vendor prefix for modules (e.g., 'acme') */
        private readonly string $vendor,
        /** @var string Base directory of the project */
        private readonly string $baseDir
    ) {}

    public static function getInstance(string $vendor, string $baseDir): self
    {
        if (! self::$instance instanceof ModuleDependencyResolver || self::$instance->vendor !== $vendor) {
            self::$instance = new self($vendor, $baseDir);
        }

        return self::$instance;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->loadModulePathsFromAutoload();
        $this->loadModuleDependencies();
        $this->initialized = true;
    }

    /**
     * Get the module name for a given class name.
     *
     * @return string|null The module name (e.g., 'acme/crm') or null if not a module class
     */
    public function getModuleForClass(string $className): ?string
    {
        $this->initialize();

        foreach ($this->namespaceToModuleMap as $namespace => $moduleName) {
            if (str_starts_with($className, $namespace)) {
                return $moduleName;
            }
        }

        return null;
    }

    /**
     * Get the module name for a file path.
     *
     * @return string|null The module name or null if not a module file
     */
    public function getModuleForFile(string $filePath): ?string
    {
        $this->initialize();

        $realPath = realpath($filePath);
        if ($realPath === false) {
            return null;
        }

        foreach ($this->pathToModuleMap as $modulePath => $moduleName) {
            $realModulePath = realpath($modulePath);
            if ($realModulePath !== false && str_starts_with($realPath, $realModulePath.DIRECTORY_SEPARATOR)) {
                return $moduleName;
            }
        }

        return null;
    }

    /**
     * Check if a module is allowed to use another module.
     *
     * @param  string  $sourceModule  The module that wants to use another module
     * @param  string  $targetModule  The module being used
     */
    public function isDependencyAllowed(string $sourceModule, string $targetModule): bool
    {
        $this->initialize();

        // Same module is always allowed
        if ($sourceModule === $targetModule) {
            return true;
        }

        // Check if target is in the allowed dependencies
        return isset($this->moduleDependencies[$sourceModule][$targetModule]);
    }

    /**
     * Get all declared dependencies for a module.
     *
     * @return array<string>
     */
    public function getDependencies(string $moduleName): array
    {
        $this->initialize();

        if (! isset($this->moduleDependencies[$moduleName])) {
            return [];
        }

        return array_keys($this->moduleDependencies[$moduleName]);
    }

    /**
     * Get all modules.
     *
     * @return array<string>
     */
    public function getAllModules(): array
    {
        $this->initialize();

        return array_keys($this->moduleDependencies);
    }

    /**
     * Detect circular dependencies between modules.
     *
     * @return array<array<string>> Array of circular dependency paths
     */
    public function detectCircularDependencies(): array
    {
        $this->initialize();

        $cycles = [];
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($this->moduleDependencies) as $module) {
            $this->detectCyclesDfs($module, $visited, $recursionStack, [], $cycles);
        }

        // Remove duplicate cycles (same cycle starting from different nodes)
        return $this->uniqueCycles($cycles);
    }

    /**
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $recursionStack
     * @param  array<string>  $path
     * @param  array<array<string>>  $cycles
     */
    private function detectCyclesDfs(
        string $current,
        array &$visited,
        array &$recursionStack,
        array $path,
        array &$cycles,
    ): void {
        $visited[$current] = true;
        $recursionStack[$current] = true;
        $path[] = $current;

        foreach ($this->getDependencies($current) as $dependency) {
            $this->visitDependency($dependency, $current, $visited, $recursionStack, $path, $cycles);
        }

        $recursionStack[$current] = false;
    }

    /**
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $recursionStack
     * @param  array<string>  $path
     * @param  array<array<string>>  $cycles
     */
    private function visitDependency(
        string $dependency,
        string $current,
        array &$visited,
        array &$recursionStack,
        array $path,
        array &$cycles,
    ): void {
        if ($dependency === $current) {
            return;
        }

        if (! isset($visited[$dependency])) {
            $this->detectCyclesDfs($dependency, $visited, $recursionStack, $path, $cycles);

            return;
        }

        if (! isset($recursionStack[$dependency])) {
            return;
        }

        $cycleStart = array_search($dependency, $path, strict: true);

        if ($cycleStart === false) {
            return;
        }

        $cycle = array_slice($path, $cycleStart);
        $cycle[] = $dependency;
        $cycles[] = $cycle;
    }

    /**
     * Remove duplicate cycles by normalizing them.
     *
     * @param  array<array<string>>  $cycles
     * @return array<array<string>>
     */
    private function uniqueCycles(array $cycles): array
    {
        $normalized = [];

        foreach ($cycles as $cycle) {
            $cycleWithoutDuplicate = array_slice($cycle, 0, -1);
            $normalizedCycle = $this->rotateToSmallest($cycleWithoutDuplicate);

            $key = implode(' -> ', $normalizedCycle);

            if (! isset($normalized[$key])) {
                $normalized[$key] = $cycle;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function rotateToSmallest(array $values): array
    {
        $minIndex = 0;
        $minValue = $values[0];

        foreach ($values as $index => $value) {
            if ($value < $minValue) {
                $minValue = $value;
                $minIndex = $index;
            }
        }

        return array_merge(array_slice($values, $minIndex), array_slice($values, 0, $minIndex));
    }

    /**
     * @throws Throwable
     */
    private function loadModulePathsFromAutoload(): void
    {
        $autoloadFile = $this->baseDir.'/vendor/composer/autoload_psr4.php';

        throw_unless(file_exists($autoloadFile), RuntimeException::class, 'Autoload file not found: '.$autoloadFile);

        /** @var array<string, array<string>> $autoload */
        $autoload = require $autoloadFile;

        $vendorUpper = ucfirst($this->vendor);

        foreach ($autoload as $namespace => $paths) {
            if (! $this->isModuleNamespace($namespace, $vendorUpper)) {
                continue;
            }

            $path = $paths[0] ?? null;

            if ($path === null) {
                continue;
            }

            $this->modulePathMap[$namespace] = $path;

            $moduleName = $this->pathToModuleName($path);

            if ($moduleName !== null) {
                $this->pathToModuleMap[dirname($path)] = $moduleName;
                $this->namespaceToModuleMap[$namespace] = $moduleName;
            }
        }
    }

    private function isModuleNamespace(string $namespace, string $vendorUpper): bool
    {
        if (! str_starts_with($namespace, $vendorUpper.'\\')) {
            return false;
        }

        foreach (['Database\\Factories\\', 'Database\\Seeders\\', 'Tests\\'] as $excluded) {
            if (str_contains($namespace, $excluded)) {
                return false;
            }
        }

        return true;
    }

    private function loadModuleDependencies(): void
    {
        foreach ($this->pathToModuleMap as $modulePath => $moduleName) {
            $composer = $this->readComposerJson($modulePath.'/composer.json');

            if ($composer === null) {
                continue;
            }

            $dependencies = $this->extractVendorDependencies($composer);
            $dependencies[$moduleName] = true;

            $this->moduleDependencies[$moduleName] = $dependencies;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readComposerJson(string $composerPath): ?array
    {
        if (! file_exists($composerPath)) {
            return null;
        }

        $content = file_get_contents($composerPath);

        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, associative: true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $composer
     * @return array<string, bool>
     */
    private function extractVendorDependencies(array $composer): array
    {
        if (! isset($composer['require']) || ! is_array($composer['require'])) {
            return [];
        }

        $dependencies = [];

        foreach (array_keys($composer['require']) as $dependency) {
            if (str_starts_with((string) $dependency, $this->vendor.'/')) {
                $dependencies[$dependency] = true;
            }
        }

        return $dependencies;
    }

    private function pathToModuleName(string $path): ?string
    {
        // Extract module name from path like "vendor/acme/crm/src"
        // or "app-modules/crm/src"

        $normalizedPath = str_replace('\\', '/', $path);

        // Try vendor path: vendor/acme/module-name/src
        if (preg_match('#/vendor/'.preg_quote($this->vendor, '#').'/([^/]+)/#', $normalizedPath, $matches)) {
            return $this->vendor.'/'.$matches[1];
        }

        // Try local app-modules path
        if (preg_match('#/app-modules/([^/]+)/#', $normalizedPath, $matches)) {
            // Convert directory name to package name
            // e.g., "amazon-selling-partner-integration" stays as is
            return $this->vendor.'/'.$matches[1];
        }

        return null;
    }
}
