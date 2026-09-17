<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports the single import that puts a module inside a dependency cycle.
 *
 * {@see CircularDependencyRule} answers "does the graph have a cycle" — once, for the
 * whole run, with no file to open. That is the right shape for a fact about a graph and
 * the wrong shape for fixing one: a cycle running through six modules names none of the
 * places a person could edit. This rule answers the other half, "which line creates it",
 * so a cycle becomes a list of files instead of a diagram.
 *
 * It fires only for an edge that is BOTH
 *
 *  - declared in `require-dev` and not in `require` — the shipped code does not need it,
 *    so whoever wrote the import can take it back; and
 *  - on a cycle — the module the import reaches leads, through some path, back here.
 *
 * Both conditions matter. An undeclared import is {@see ModuleBoundaryRule}'s finding and
 * would be reported twice. A `require` edge on a cycle is a boundary decision no test
 * author can undo, and telling them to move a test would be advice they cannot take.
 *
 * WHERE TO WIRE IT: in a configuration that analyses the modules' TESTS. A `require-dev`
 * edge exists because a test uses the other module, so a run that excludes the modules'
 * test directories — as a main run reasonably does, tests being neither shipped nor typed
 * like shipped code — sees none of these imports and the rule is silently inert there.
 *
 * @implements Rule<Use_>
 */
final readonly class CircularDependencyImportRule implements Rule
{
    private ModuleDependencyResolver $resolver;

    public function __construct(
        string $baseDir,
        private bool $enabled = true,
    ) {
        $this->resolver = ModuleDependencyResolver::getInstance($baseDir);
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
        if (! $this->enabled || $node->type !== Use_::TYPE_NORMAL) {
            return [];
        }

        $sourceModule = $this->resolver->getModuleForFile($scope->getFile());

        if ($sourceModule === null) {
            return [];
        }

        $errors = [];
        $reported = [];

        foreach ($node->uses as $use) {
            $usedClass = $use->name->toCodeString();
            $targetModule = $this->resolver->getModuleForClass($usedClass);

            if ($targetModule === null || $targetModule === $sourceModule || isset($reported[$targetModule])) {
                continue;
            }

            if (! $this->resolver->isDevOnlyDependency($sourceModule, $targetModule)) {
                continue;
            }

            if (! $this->resolver->edgeClosesCycle($sourceModule, $targetModule)) {
                continue;
            }

            $reported[$targetModule] = true;

            $errors[] = RuleErrorBuilder::message(sprintf(
                'This import puts "%s" inside a dependency cycle: it uses "%s" from module "%s", '.
                'which depends on "%s" again. The edge exists only because of this `require-dev` '.
                'declaration, so it can be removed — replace "%s" with a fixture this module owns, '.
                'or move the test to "%s", whichever actually owns the subject.',
                $sourceModule,
                $usedClass,
                $targetModule,
                $sourceModule,
                $usedClass,
                $targetModule,
            ))
                ->identifier('trueModular.circularDependencyImport')
                ->line($use->getStartLine())
                ->build();
        }

        return $errors;
    }
}
