<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;

/**
 * Node Identifier -> Node Path cache
 */
final class NodePathCache
{
    /**
     * @var array<string,NodePath>
     */
    protected array $nodePaths = [];

    protected bool $isEnabled;

    public function __construct(bool $isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    public function contains(NodeAggregateIdentifier $nodeAggregateIdentifier): bool
    {
        if ($this->isEnabled === false) {
            return false;
        }
        $key = (string)$nodeAggregateIdentifier;
        return isset($this->nodePaths[$key]);
    }

    public function add(NodeAggregateIdentifier $nodeAggregateIdentifier, NodePath $nodePath): void
    {
        if ($this->isEnabled === false) {
            return;
        }
        $key = (string)$nodeAggregateIdentifier;
        $this->nodePaths[$key] = $nodePath;
    }

    public function get(NodeAggregateIdentifier $nodeAggregateIdentifier): ?NodePath
    {
        if ($this->isEnabled === false) {
            return null;
        }
        $key = (string)$nodeAggregateIdentifier;

        return $this->nodePaths[$key] ?? null;
    }
}
