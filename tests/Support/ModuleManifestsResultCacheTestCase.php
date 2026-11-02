<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\ModuleManifestsResultCacheValueExtension;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ValueDependencyCollector;
use PHPStan\Testing\PHPStanTestCase;

/**
 * Boots PHPStan with the package's own rule configuration, so a test sees the value
 * extension registered exactly the way a consumer's run does, and can watch what the
 * rules declare to the result cache.
 */
class ModuleManifestsResultCacheTestCase extends PHPStanTestCase
{
    /**
     * @return array<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__.'/../../rules/module-boundary.neon',
            __DIR__.'/../../rules/module-cycle-imports.neon',
        ];
    }

    protected function manifests(): ModuleManifestsResultCacheValueExtension
    {
        return self::getContainer()->getByType(ModuleManifestsResultCacheValueExtension::class);
    }

    /**
     * The rule of class $class exactly as the package's configuration builds it.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function configuredRule(string $class): object
    {
        foreach (self::getContainer()->getServicesByTag('phpstan.rules.rule') as $rule) {
            if ($rule instanceof $class) {
                return $rule;
            }
        }

        throw new \RuntimeException("{$class} is not registered.");
    }

    protected function valueDependencyCollector(): ValueDependencyCollector
    {
        return self::getContainer()->getByType(ValueDependencyCollector::class);
    }

    /**
     * A scope for $file. $insideWalk mirrors how PHPStan calls a rule: with a node
     * callback while it walks a file, without one for a rule on CollectedDataNode.
     */
    protected function scopeFor(string $file, bool $insideWalk): MutatingScope
    {
        return self::createScopeFactory(self::createReflectionProvider(), self::getContainer()->getService('typeSpecifier'))
            ->create(ScopeContext::create($file), $insideWalk ? static function (): void {} : null);
    }
}
