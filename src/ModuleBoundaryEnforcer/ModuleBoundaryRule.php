<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Rule that checks if a module only uses classes from its declared dependencies.
 *
 * @implements Rule<Use_>
 */
final readonly class ModuleBoundaryRule implements Rule
{
    private ModuleDependencyResolver $resolver;

    public function __construct(
        string $vendor,
        string $baseDir,
    ) {
        $this->resolver = ModuleDependencyResolver::getInstance($vendor, $baseDir);
    }

    public function getNodeType(): string
    {
        return Use_::class;
    }

    /**
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Use_) {
            return [];
        }

        // Only check regular use statements (not function or const)
        if ($node->type !== Use_::TYPE_NORMAL) {
            return [];
        }

        $errors = [];
        $currentFile = $scope->getFile();
        $sourceModule = $this->resolver->getModuleForFile($currentFile);

        // If the current file is not in a module, skip
        if ($sourceModule === null) {
            return [];
        }

        foreach ($node->uses as $use) {
            if (! $use instanceof UseUse) {
                continue;
            }

            $usedClass = $this->getFullyQualifiedName($use->name);
            $targetModule = $this->resolver->getModuleForClass($usedClass);

            // If the used class is not from a module, skip (it's from vendor, etc.)
            if ($targetModule === null) {
                continue;
            }

            // Check if the dependency is allowed
            if (! $this->resolver->isDependencyAllowed($sourceModule, $targetModule)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Module boundary violation: "%s" is not allowed to use "%s" (from module "%s"). '.
                    'Add "%s" to the require section of %s/composer.json to allow this dependency.',
                    $sourceModule,
                    $usedClass,
                    $targetModule,
                    $targetModule,
                    $sourceModule,
                ))
                    ->identifier('trueModular.moduleBoundary')
                    ->build();
            }
        }

        return $errors;
    }

    private function getFullyQualifiedName(Name $name): string
    {
        return $name->toCodeString();
    }
}
