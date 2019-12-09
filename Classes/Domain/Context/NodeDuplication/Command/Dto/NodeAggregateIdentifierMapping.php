<?php

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\Command\Dto;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;

/**
 * An assignment of "old" to "new" NodeAggregateIdentifiers
 *
 * Usable for predefining NodeAggregateIdentifiers if multiple nodes are copied.
 *
 * @Flow\Proxy(false)
 */
final class NodeAggregateIdentifierMapping implements \JsonSerializable
{
    /**
     * new Node aggregate identifiers, indexed by old node aggregate identifier
     *
     * e.g. {main => my-main-node}
     *
     * @var array|NodeAggregateIdentifier[]
     */
    protected $nodeAggregateIdentifiers = [];

    public function __construct(array $nodeAggregateIdentifiers)
    {
        foreach ($nodeAggregateIdentifiers as $oldNodeAggregateIdentifier => $newNodeAggregateIdentifier) {
            $oldNodeAggregateIdentifier = NodeAggregateIdentifier::fromString($oldNodeAggregateIdentifier);
            if (!$newNodeAggregateIdentifier instanceof NodeAggregateIdentifier) {
                throw new \InvalidArgumentException('NodeAggregateIdentifierMapping objects can only be composed of NodeAggregateIdentifiers.', 1573042379);
            }

            $this->nodeAggregateIdentifiers[(string)$oldNodeAggregateIdentifier] = $newNodeAggregateIdentifier;
        }
    }

    /**
     * Create a new identifier mapping, *GENERATING* new identifiers.
     *
     * @param NodeSubtreeSnapshot $nodeSubtreeSnapshot
     * @return static
     */
    public static function generateForNodeSubtreeSnapshot(NodeSubtreeSnapshot $nodeSubtreeSnapshot): self
    {
        $nodeAggregateIdentifierMapping = [];
        $nodeSubtreeSnapshot->walk(function (NodeSubtreeSnapshot $nodeSubtreeSnapshot) use (&$nodeAggregateIdentifierMapping) {
            // here, we create new random NodeAggregateIdentifiers.
            $nodeAggregateIdentifierMapping[(string)$nodeSubtreeSnapshot->getNodeAggregateIdentifier()] = NodeAggregateIdentifier::create();
        });

        return new self($nodeAggregateIdentifierMapping);
    }

    public static function fromArray(array $array): self
    {
        $nodeAggregateIdentifiers = [];
        foreach ($array as $oldNodeAggregateIdentifier => $newNodeAggregateIdentifier) {
            $nodeAggregateIdentifiers[$oldNodeAggregateIdentifier] = NodeAggregateIdentifier::fromString($newNodeAggregateIdentifier);
        }

        return new self($nodeAggregateIdentifiers);
    }

    public function getNewNodeAggregateIdentifier(NodeAggregateIdentifier $oldNodeAggregateIdentifier): ?NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifiers[(string)$oldNodeAggregateIdentifier] ?? null;
    }

    public function jsonSerialize(): array
    {
        return $this->nodeAggregateIdentifiers;
    }

    /**
     * @return NodeAggregateIdentifier[]|iterable
     */
    public function getAllNewNodeAggregateIdentifiers(): iterable
    {
        return array_values($this->nodeAggregateIdentifiers);
    }
}
