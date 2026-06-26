<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\ModuleBoundaryEnforcer;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\FileNode;

/**
 * Collector that triggers circular dependency detection at the end of analysis.
 * Uses FileNode to run once per file and the first file triggers the check.
 *
 * @implements Collector<FileNode, array{file: string}>
 */
final readonly class CircularDependencyCollector implements Collector
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @return array{file: string}
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Just return the file path - the rule will process all collected data
        return ['file' => $scope->getFile()];
    }
}
