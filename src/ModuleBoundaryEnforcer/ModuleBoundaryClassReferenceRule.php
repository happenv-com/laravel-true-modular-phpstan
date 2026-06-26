<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Rule that checks class references (new, static calls, extends, implements, etc.)
 * to ensure they don't violate module boundaries.
 *
 * @implements Rule<Node>
 */
final class ModuleBoundaryClassReferenceRule implements Rule
{
    private readonly ModuleDependencyResolver $resolver;

    /** @var array<string, bool> Already reported violations (to avoid duplicates) */
    private array $reportedViolations = [];

    public function __construct(
        string $baseDir,
    ) {
        $this->resolver = ModuleDependencyResolver::getInstance($baseDir);
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $currentFile = $scope->getFile();
        $sourceModule = $this->resolver->getModuleForFile($currentFile);

        // If the current file is not in a module, skip
        if ($sourceModule === null) {
            return [];
        }

        $classNames = $this->extractClassNames($node);
        if ($classNames === []) {
            return [];
        }

        $errors = [];

        foreach ($classNames as $className) {
            $error = $this->checkClassReference($className, $sourceModule, $currentFile, $node->getStartLine());
            if ($error instanceof RuleError) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return array<string>
     */
    private function extractClassNames(Node $node): array
    {
        return match (true) {
            $node instanceof Class_ => $this->classNamesFromClassDeclaration($node),
            $node instanceof Catch_ => array_map(static fn (Name $type): string => $type->toString(), $node->types),
            $node instanceof New_,
            $node instanceof StaticCall,
            $node instanceof StaticPropertyFetch,
            $node instanceof ClassConstFetch,
            $node instanceof Instanceof_ => $node->class instanceof Name ? [$node->class->toString()] : [],
            default => [],
        };
    }

    /**
     * @return array<string>
     */
    private function classNamesFromClassDeclaration(Class_ $node): array
    {
        $names = [];

        if ($node->extends instanceof Name) {
            $names[] = $node->extends->toString();
        }

        foreach ($node->implements as $interface) {
            $names[] = $interface->toString();
        }

        return $names;
    }

    private function checkClassReference(
        string $className,
        string $sourceModule,
        string $file,
        int $line,
    ): ?RuleError {
        // Skip self, static, parent references
        if (in_array(strtolower($className), ['self', 'static', 'parent'], strict: true)) {
            return null;
        }

        $targetModule = $this->resolver->getModuleForClass($className);

        // If the used class is not from a module, skip (it's from vendor, etc.)
        if ($targetModule === null) {
            return null;
        }

        // Check if the dependency is allowed
        if ($this->resolver->isDependencyAllowed($sourceModule, $targetModule)) {
            return null;
        }

        // Create a unique key to avoid duplicate errors
        $violationKey = sprintf('%s:%s:%s:%s', $file, $sourceModule, $targetModule, $className);
        if (isset($this->reportedViolations[$violationKey])) {
            return null;
        }

        $this->reportedViolations[$violationKey] = true;

        return RuleErrorBuilder::message(sprintf(
            'Module boundary violation: "%s" is not allowed to use "%s" (from module "%s"). '.
            'Add "%s" to the require section of %s/composer.json to allow this dependency.',
            $sourceModule,
            $className,
            $targetModule,
            $targetModule,
            $sourceModule,
        ))
            ->identifier('trueModular.moduleBoundary')
            ->line($line)
            ->build();
    }
}
