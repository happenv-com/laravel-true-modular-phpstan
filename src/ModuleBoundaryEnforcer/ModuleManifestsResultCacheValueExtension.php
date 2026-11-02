<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use PHPStan\Analyser\ResultCache\ResultCacheValueExtension;

/**
 * The module manifests as PHPStan's result cache sees them.
 *
 * Every boundary rule answers from the modules' composer.json files, which are not PHP
 * and which PHPStan therefore does not know about: edit one, and a warm result cache
 * hands back the errors computed from the old graph. Since PHPStan 2.3.4 that includes
 * the graph-level {@see CircularDependencyRule}, whose errors are reused while its
 * collected data is unchanged — and a new `require-dev` cycle changes no PHP file.
 *
 * The rules declare this value through {@see ModuleDependencyResolver::trackManifests()},
 * so PHPStan re-runs them when it differs. The key is the modules directory; the value
 * covers exactly what {@see ModuleRegistry} reads — every
 * `<modulesDir>/<module>/composer.json` — so a manifest that is edited, added or
 * removed, and a module directory that appears or goes, all change it.
 */
final class ModuleManifestsResultCacheValueExtension implements ResultCacheValueExtension
{
    private const string MISSING = 'missing';

    /** @var array<string, string> Memoized modules directory => value, asked once per analysed file */
    private array $values = [];

    public function __construct(
        private readonly string $currentWorkingDirectory,
    ) {}

    public function getValue(string $key): string
    {
        return $this->values[$key] ??= $this->computeValue($key);
    }

    public function keyToResultCache(string $key): string
    {
        $prefix = rtrim($this->currentWorkingDirectory, '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
    }

    public function keyFromResultCache(string $storedKey): string
    {
        if (str_starts_with($storedKey, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $storedKey) === 1) {
            return $storedKey;
        }

        return rtrim($this->currentWorkingDirectory, '/\\').DIRECTORY_SEPARATOR.$storedKey;
    }

    private function computeValue(string $modulesPath): string
    {
        if (! is_dir($modulesPath)) {
            return self::MISSING;
        }

        $directories = glob($modulesPath.'/*', GLOB_ONLYDIR);

        if ($directories === false) {
            return self::MISSING;
        }

        $lines = [];

        foreach ($directories as $directory) {
            $hash = is_file($directory.'/composer.json') ? hash_file('sha256', $directory.'/composer.json') : false;
            $lines[basename($directory)] = basename($directory)."\x00".($hash === false ? self::MISSING : $hash);
        }

        ksort($lines);

        return hash('sha256', implode("\n", $lines));
    }
}
