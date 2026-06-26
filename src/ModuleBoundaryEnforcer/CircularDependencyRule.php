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
        string $vendor,
        string $baseDir,
    ) {
        $this->resolver = ModuleDependencyResolver::getInstance($vendor, $baseDir);
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
        if (! $node instanceof CollectedDataNode) {
            return [];
        }

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

        $cycles = $this->resolver->detectCircularDependencies();
        if ($cycles === []) {
            return [];
        }

        $errors = [];

        foreach ($cycles as $cycle) {
            $cyclePath = implode(' → ', $cycle);
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Circular dependency detected between modules: %s. '.
                'This creates a tight coupling between modules and should be resolved by introducing an abstraction or rethinking the module boundaries.',
                $cyclePath,
            ))
                ->identifier('trueModular.circularDependency')
                ->build();
        }

        return $errors;
    }
}
