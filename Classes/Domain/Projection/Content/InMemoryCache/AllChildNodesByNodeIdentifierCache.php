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

use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeTypeConstraints;

/**
 * This cache is only filled for a $parentNodeIdentifier if we have retrieved *all* childNodes, without any restriction.
 */
final class AllChildNodesByNodeIdentifierCache
{
    protected $childNodes = [];

    /**
     * @var bool
     */
    protected $isEnabled;

    public function __construct(bool $isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    public function add(NodeAggregateIdentifier $parentNodeAggregateIdentifier, array $allChildNodes): void
    {
        if ($this->isEnabled === false) {
            return;
        }

        $key = (string)$parentNodeAggregateIdentifier;
        $this->childNodes[$key] = $allChildNodes;
    }

    public function contains(NodeAggregateIdentifier $parentNodeAggregateIdentifier): bool
    {
        if ($this->isEnabled === false) {
            return false;
        }

        $key = (string)$parentNodeAggregateIdentifier;
        return isset($this->childNodes[$key]);
    }

    public function findChildNodes(NodeAggregateIdentifier $parentNodeAggregateIdentifier, NodeTypeConstraints $nodeTypeConstraints = null, int $limit = null, int $offset = null): array
    {
        if ($this->isEnabled === false) {
            return [];
        }

        $key = (string)$parentNodeAggregateIdentifier;
        $result = [];

        if (isset($this->childNodes[$key])) {
            $childNodes = $this->childNodes[$key];
            foreach ($childNodes as $childNode) {
                /* @var  NodeInterface $childNode */
                if ($nodeTypeConstraints === null || $nodeTypeConstraints->matches($childNode->getNodeTypeName())) {
                    $result[] = $childNode;
                }
            }

            if ($limit || $offset) {
                $result = array_slice($result, $offset ?? 0, $limit);
            }
        }
        return $result;
    }
}
