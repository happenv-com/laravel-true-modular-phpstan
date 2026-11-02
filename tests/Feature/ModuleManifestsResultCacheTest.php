<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\CircularDependencyCollector;
use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\CircularDependencyImportRule;
use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\CircularDependencyRule;
use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\ModuleBoundaryClassReferenceRule;
use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\ModuleBoundaryRule;
use Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer\ModuleManifestsResultCacheValueExtension;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\FixturePaths;
use Happenv\LaravelTrueModular\Phpstan\Tests\Support\ModuleManifestsResultCacheTestCase;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PHPStan\Analyser\ValueDependencyCollector;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;

uses(ModuleManifestsResultCacheTestCase::class);

/**
 * A throwaway copy of the dev-circular pair, so a test can edit its manifests.
 */
function copyOfDevCircularModules(): string
{
    $baseDir = sys_get_temp_dir().'/true-modular-phpstan-'.bin2hex(random_bytes(6));

    foreach (['a', 'b'] as $module) {
        mkdir("{$baseDir}/app-modules/{$module}", recursive: true);
        copy(FixturePaths::devCircularBaseDir()."/app-modules/{$module}/composer.json", "{$baseDir}/app-modules/{$module}/composer.json");
    }

    return $baseDir;
}

function manifestsValue(string $modulesPath): string
{
    // A fresh instance, as the next PHPStan run has.
    return (new ModuleManifestsResultCacheValueExtension(dirname($modulesPath)))->getValue($modulesPath);
}

/**
 * Runs a rule on CollectedDataNode the way PHPStan 2.3.4+ does when it may reuse the
 * rule's errors later, and returns the values the rule declared they depend on.
 *
 * @param  Rule<CollectedDataNode>  $rule
 * @return array{list<mixed>, array<string, array{string, string, string}>}
 */
function runCollectedDataRule(ModuleManifestsResultCacheTestCase $test, Rule $rule, string $analysedFile): array
{
    $collector = (fn (): ValueDependencyCollector => $this->valueDependencyCollector())->call($test);
    $collector->startCollectedDataRule();

    try {
        $errors = $rule->processNode(
            new CollectedDataNode([$analysedFile => [CircularDependencyCollector::class => [['file' => $analysedFile]]]], false),
            (fn () => $this->scopeFor('', insideWalk: false))->call($test),
        );

        return [$errors, $collector->getCollectedDataRuleValues()];
    } finally {
        $collector->finishCollectedDataRule();
    }
}

/**
 * @return array<string, array{string, string, string}>
 */
function manifestsDependency(string $modulesPath): array
{
    return [
        ValueDependencyCollector::getId(ModuleManifestsResultCacheValueExtension::class, $modulesPath) => [
            ModuleManifestsResultCacheValueExtension::class,
            $modulesPath,
            manifestsValue($modulesPath),
        ],
    ];
}

it('registers the module manifests with the result cache', function (): void {
    expect($this->valueDependencyCollector()->getExtension(ModuleManifestsResultCacheValueExtension::class))
        ->toBe($this->manifests());
});

it('makes the reported cycles depend on the module manifests', function (): void {
    // PHPStan 2.3.4+ reuses this rule's errors while its collected data — the list of
    // analysed files — is unchanged, unless the rule declared what else they depend on.
    [$errors, $values] = runCollectedDataRule(
        $this,
        new CircularDependencyRule(FixturePaths::devCircularBaseDir(), includeDevDependencies: true, manifests: $this->manifests()),
        FixturePaths::devCircularFile('a/src/Thing.php'),
    );

    expect($errors)->toHaveCount(1)
        ->and($values)->toBe(manifestsDependency(FixturePaths::devCircularBaseDir().'/app-modules'));
});

it('wires the cycle report to the module manifests in the shipped configuration', function (): void {
    $baseDir = self::getContainer()->getParameter('currentWorkingDirectory');

    [, $values] = runCollectedDataRule($this, $this->configuredRule(CircularDependencyRule::class), "{$baseDir}/src/Anything.php");

    expect($values)->toBe(manifestsDependency("{$baseDir}/app-modules"));
});

it('makes the per-file boundary rules depend on the module manifests', function (Rule $rule, string $file, Node $node): void {
    $collector = $this->valueDependencyCollector();
    $collector->startFile($file);
    $rule->processNode($node, $this->scopeFor($file, insideWalk: true));
    $dependencies = $collector->finishFile();

    $modulesPath = dirname($file, 3);
    $id = ValueDependencyCollector::getId(ModuleManifestsResultCacheValueExtension::class, $modulesPath);

    expect($dependencies['values'])->toHaveKey($id)
        ->and($dependencies['values'][$id][2])->toBe(manifestsValue($modulesPath))
        ->and($dependencies['dependents'][$file]['analysis'])->toContain($id);
})->with([
    'module boundary' => fn (): array => [
        new ModuleBoundaryRule(FixturePaths::moduleBaseDir(), $this->manifests()),
        FixturePaths::saleFile('DisallowedUse.php'),
        new Use_([new UseItem(new Name('Acme\Catalog\Product'))]),
    ],
    'module boundary class reference' => fn (): array => [
        new ModuleBoundaryClassReferenceRule(FixturePaths::moduleBaseDir(), $this->manifests()),
        FixturePaths::saleFile('DisallowedInline.php'),
        new New_(new FullyQualified('Acme\Catalog\Product')),
    ],
    'circular dependency import' => fn (): array => [
        new CircularDependencyImportRule(FixturePaths::devCircularBaseDir(), manifests: $this->manifests()),
        FixturePaths::devCircularFile('a/src/Thing.php'),
        new Use_([new UseItem(new Name('Acme\B\Widget'))]),
    ],
]);

it('declares nothing when the rule is wired without the extension', function (): void {
    // PHPStan throws on a value whose extension it does not know, so a rule registered
    // by hand, without the package's configuration, must stay silent instead.
    [, $values] = runCollectedDataRule(
        $this,
        new CircularDependencyRule(FixturePaths::devCircularBaseDir(), includeDevDependencies: true),
        FixturePaths::devCircularFile('a/src/Thing.php'),
    );

    expect($values)->toBe([]);
});

it('changes the manifests value when a require-dev edge closes a cycle', function (): void {
    $modulesPath = copyOfDevCircularModules().'/app-modules';
    $manifest = "{$modulesPath}/a/composer.json";
    $closesTheCycle = file_get_contents($manifest);
    file_put_contents($manifest, str_replace('"require-dev": { "acme/b": "*" },', '', $closesTheCycle));

    $acyclic = manifestsValue($modulesPath);
    expect(manifestsValue($modulesPath))->toBe($acyclic);

    file_put_contents($manifest, $closesTheCycle);
    expect(manifestsValue($modulesPath))->not->toBe($acyclic);
});

it('changes the manifests value when a module appears or goes', function (): void {
    $modulesPath = copyOfDevCircularModules().'/app-modules';
    $before = manifestsValue($modulesPath);

    mkdir("{$modulesPath}/c");
    expect(manifestsValue($modulesPath))->not->toBe($before);

    $withEmptyDirectory = manifestsValue($modulesPath);
    file_put_contents("{$modulesPath}/c/composer.json", '{"name": "acme/c", "type": "true-module"}');
    expect(manifestsValue($modulesPath))->not->toBe($withEmptyDirectory);

    unlink("{$modulesPath}/a/composer.json");
    expect(manifestsValue($modulesPath))->not->toBe($withEmptyDirectory);
});

it('stores the modules directory relative to the project', function (): void {
    $extension = new ModuleManifestsResultCacheValueExtension('/project');

    expect($extension->keyToResultCache('/project/app-modules'))->toBe('app-modules')
        ->and($extension->keyFromResultCache('app-modules'))->toBe('/project'.DIRECTORY_SEPARATOR.'app-modules')
        ->and($extension->keyToResultCache('/elsewhere/app-modules'))->toBe('/elsewhere/app-modules')
        ->and($extension->keyFromResultCache('/elsewhere/app-modules'))->toBe('/elsewhere/app-modules');
});
