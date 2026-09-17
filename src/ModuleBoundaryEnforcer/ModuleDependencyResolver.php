<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\Architecture\Module\AppModulesLocator;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Throwable;

/**
 * Adapts laravel-true-modular's own module discovery for the PHPStan boundary
 * rules — zero configuration.
 *
 * Modules, their PSR-4 namespaces and their allowed cross-module dependencies are
 * read straight from each module's composer.json, exactly the way the framework
 * resolves them at runtime: discovery is delegated to {@see ModuleRegistry} (which
 * honours {@see Application::getModuleComposerType()} and
 * {@see Application::getModulesDirectory()}), and class/path lookups reuse
 * {@see AppModulesLocator}. Nothing here depends on a hard-coded vendor prefix.
 */
final class ModuleDependencyResolver
{
    private static ?self $instance = null;

    private bool $initialized = false;

    private ?ModuleRegistry $registry = null;

    private ?AppModulesLocator $locator = null;

    /** @var array<string, string|null> Memoized class name => module name */
    private array $classModuleCache = [];

    /** @var array<string, string|null> Memoized file path => module name */
    private array $fileModuleCache = [];

    /** @var array<string, array<string, true>> Memoized module name => everything reachable from it */
    private array $reachableCache = [];

    /** @var array<string, array<string>>|null Memoized graph including `require-dev` */
    private ?array $fullGraph = null;

    public function __construct(
        private readonly string $baseDir,
    ) {}

    public static function getInstance(string $baseDir): self
    {
        if (! self::$instance instanceof self || self::$instance->baseDir !== $baseDir) {
            self::$instance = new self($baseDir);
        }

        return self::$instance;
    }

    /**
     * Get the module name for a given class name, or null when the class does not
     * belong to a module.
     */
    public function getModuleForClass(string $className): ?string
    {
        $this->initialize();

        if ($this->locator === null) {
            return null;
        }

        if (array_key_exists($className, $this->classModuleCache)) {
            return $this->classModuleCache[$className];
        }

        try {
            $module = $this->locator->byClass($className)?->name;
        } catch (Throwable) {
            $module = null;
        }

        return $this->classModuleCache[$className] = $module;
    }

    /**
     * Get the module name for a file path, or null when the file is not inside a
     * module.
     */
    public function getModuleForFile(string $filePath): ?string
    {
        $this->initialize();

        if ($this->locator === null) {
            return null;
        }

        if (array_key_exists($filePath, $this->fileModuleCache)) {
            return $this->fileModuleCache[$filePath];
        }

        try {
            $module = $this->locator->byPath($filePath)?->name;
        } catch (Throwable) {
            $module = null;
        }

        return $this->fileModuleCache[$filePath] = $module;
    }

    /**
     * Whether $sourceModule is allowed to depend on $targetModule. A module may
     * always reference itself; otherwise the target must be declared in the
     * source module's composer.json require section.
     */
    public function isDependencyAllowed(string $sourceModule, string $targetModule): bool
    {
        if ($sourceModule === $targetModule) {
            return true;
        }

        return in_array($targetModule, $this->getDependencies($sourceModule), strict: true);
    }

    /**
     * Get all module dependencies declared by a module (only those that resolve to
     * a known module).
     *
     * @return array<string>
     */
    public function getDependencies(string $moduleName): array
    {
        $this->initialize();

        if ($this->registry === null) {
            return [];
        }

        try {
            return $this->registry->getDependencies($moduleName);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Dependencies a module declares for its tests only (`require-dev`).
     *
     * @return array<string>
     */
    public function getDevDependencies(string $moduleName): array
    {
        $this->initialize();

        if ($this->registry === null) {
            return [];
        }

        try {
            return $this->registry->getDevDependencies($moduleName);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Whether $targetModule is reachable from $sourceModule ONLY through `require-dev`.
     *
     * The distinction is what makes a finding actionable: a `require` edge is the
     * shipped code's, so no test author can remove it, and a cycle running through one
     * is a boundary problem rather than a test-placement problem.
     */
    public function isDevOnlyDependency(string $sourceModule, string $targetModule): bool
    {
        return in_array($targetModule, $this->getDevDependencies($sourceModule), strict: true)
            && ! in_array($targetModule, $this->getDependencies($sourceModule), strict: true);
    }

    /**
     * Whether an edge $sourceModule → $targetModule lies on a cycle — that is, whether
     * $sourceModule is reachable again from $targetModule once dev edges are walked.
     */
    public function edgeClosesCycle(string $sourceModule, string $targetModule): bool
    {
        return isset($this->reachableFrom($targetModule)[$sourceModule]);
    }

    /**
     * Everything reachable from a module through `require` and `require-dev` alike.
     *
     * Iterative on purpose: this graph is the one that HAS cycles, so a recursive walk
     * would not terminate.
     *
     * @return array<string, true>
     */
    private function reachableFrom(string $moduleName): array
    {
        if (isset($this->reachableCache[$moduleName])) {
            return $this->reachableCache[$moduleName];
        }

        $graph = $this->fullDependencyGraph();
        $seen = [];
        $queue = $graph[$moduleName] ?? [];

        while ($queue !== []) {
            $next = array_pop($queue);

            if (isset($seen[$next])) {
                continue;
            }

            $seen[$next] = true;

            foreach ($graph[$next] ?? [] as $further) {
                if (! isset($seen[$further])) {
                    $queue[] = $further;
                }
            }
        }

        return $this->reachableCache[$moduleName] = $seen;
    }

    /** @return array<string, array<string>> */
    private function fullDependencyGraph(): array
    {
        if ($this->fullGraph !== null) {
            return $this->fullGraph;
        }

        $this->initialize();

        if ($this->registry === null) {
            return $this->fullGraph = [];
        }

        try {
            return $this->fullGraph = $this->registry->getFullDependencyGraph();
        } catch (Throwable) {
            return $this->fullGraph = [];
        }
    }

    /**
     * Detect circular dependencies between modules.
     *
     * @param  bool  $includeDev  also walk `require-dev` edges. The shipped graph is
     *                            what orders service providers and is normally acyclic;
     *                            cycles usually live in the test-only edges, and a
     *                            consumer has to ask for those explicitly.
     * @return array<array<string>> Each entry is a cycle path (closing module repeated at the end).
     */
    public function detectCircularDependencies(bool $includeDev = false): array
    {
        $this->initialize();

        if ($this->registry === null) {
            return [];
        }

        try {
            return $this->registry->detectCircularDependencies($includeDev);
        } catch (Throwable) {
            return [];
        }
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Set first so a discovery failure degrades to a no-op instead of retrying
        // (and potentially throwing) on every analysed node.
        $this->initialized = true;

        $modulesPath = $this->baseDir.DIRECTORY_SEPARATOR.Application::getModulesDirectory();

        if (! is_dir($modulesPath)) {
            return;
        }

        $registry = new ModuleRegistry($modulesPath);

        try {
            // Force discovery up front so later lookups can't throw mid-analysis.
            $registry->getAllModules();
        } catch (Throwable) {
            return;
        }

        $this->registry = $registry;
        $this->locator = new AppModulesLocator($registry, Application::getModulesVendor());
    }
}
