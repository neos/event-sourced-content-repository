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

use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;

/**
 * NOTE: we do NOT directly cache the Parent Node; but only the Parent Node Identifier; as then, the NodeByNodeIdentifierCache can be used properly - thus
 * it might increase the cache hit rate to split this apart.
 */
final class ParentNodeIdentifierByChildNodeIdentifierCache
{
    protected $parentNodeAggregateIdentifiers = [];
    protected $nodesWithoutParentNode = [];

    /**
     * @var bool
     */
    protected $isEnabled;

    public function __construct(bool $isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    public function add(NodeAggregateIdentifier $childNodeAggregateIdentifier, NodeAggregateIdentifier $parentNodeAggregateIdentifier): void
    {
        if ($this->isEnabled === false) {
            return;
        }

        $key = (string)$childNodeAggregateIdentifier;
        $this->parentNodeAggregateIdentifiers[$key] = $parentNodeAggregateIdentifier;
    }

    public function knowsAbout(NodeAggregateIdentifier $childNodeAggregateIdentifier): bool
    {
        if ($this->isEnabled === false) {
            return false;
        }

        $key = (string)$childNodeAggregateIdentifier;
        return isset($this->parentNodeAggregateIdentifiers[$key])  || isset($this->nodesWithoutParentNode[$key]);
    }

    public function rememberNonExistingParentNode(NodeAggregateIdentifier $nodeAggregateIdentifier): void
    {
        if ($this->isEnabled === false) {
            return;
        }

        $key = (string)$nodeAggregateIdentifier;
        $this->nodesWithoutParentNode[$key] = true;
    }


    public function get(NodeAggregateIdentifier $childNodeAggregateIdentifier): ?NodeAggregateIdentifier
    {
        if ($this->isEnabled === false) {
            return null;
        }

        $key = (string)$childNodeAggregateIdentifier;
        return $this->parentNodeAggregateIdentifiers[$key] ?? null;
    }
}
