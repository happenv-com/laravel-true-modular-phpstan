<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Rule that reports circular dependencies detected across modules.
 * Runs after all files are collected.
 *
 * @implements Rule<CollectedDataNode>
 */
final class CircularDependencyRule implements Rule
{
    private readonly ModuleDependencyResolver $resolver;

    private bool $alreadyReported = false;

    public function __construct(
        string $baseDir,
        private readonly bool $includeDevDependencies = false,
    ) {
        $this->resolver = ModuleDependencyResolver::getInstance($baseDir);
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only report once
        if ($this->alreadyReported) {
            return [];
        }

        // Check if this is for our collector
        /** @var array<string, array<int, array{file: string}>> $collected */
        $collected = $node->get(CircularDependencyCollector::class);
        if ($collected === []) {
            return [];
        }

        $this->alreadyReported = true;

        $cycles = $this->resolver->detectCircularDependencies($this->includeDevDependencies);
        if ($cycles === []) {
            return [];
        }

        $errors = [];

        foreach ($cycles as $cycle) {
            $cyclePath = implode(' → ', $cycle);
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Circular dependency detected between modules: %s. '.
                'This creates a tight coupling between modules and should be resolved by introducing an abstraction or rethinking the module boundaries.%s',
                $cyclePath,
                $this->includeDevDependencies
                    ? ' A `require-dev` edge can be what closes it, in which case the import that'
                        .' creates it is reported on its own line by CircularDependencyImportRule.'
                    : '',
            ))
                ->identifier($this->includeDevDependencies
                    ? 'trueModular.circularDependencyIncludingDev'
                    : 'trueModular.circularDependency')
                ->build();
        }

        return $errors;
    }
}
