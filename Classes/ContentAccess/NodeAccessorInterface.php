<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\ContentAccess;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\ContentSubgraph\SubtreeInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\Nodes;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\SearchTerm;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;

/**
 * This is the main "node access" API which the upper layers of Neos, the Neos UI, Fusion should always use
 * to fetch Nodes and traverse the Node Tree.
 *
 * You can retrieve instances of NodeAccessor by injecting {@see NodeAccessorManager}
 * and calling {@see NodeAccessorManager::accessorFor()).
 *
 * ## Extensibility
 *
 * Because we want to make the fetching and traversal of Nodes independent of an actual storage implementation
 * (implemented by {@see ContentSubgraphInterface}), we instead use this interface as central access point.
 *
 * Internally, a NodeAccessor might *delegate* to another NodeAccessor; so effectively a *Chain* of NodeAccessors
 * is built up. This can be used to e.g. implement "Virtual Nodes" not tied to the Content Graph, or many kinds
 * of other features.
 *
 * To build an own NodeAccessor, the following things need to be done:
 *
 * 1) Create a custom implementation of {@see NodeAccessorInterface}.
 *    We recommend to use the {@see AbstractDelegatingNodeAccessor} for a base implementation
 *    of the full {@see NodeAccessorInterface} which delegates every call to the next accessor,
 *    and then overriding the methods selectively where you want to hook in.
 *
 * 2) Create a custom factory for your NodeAccessor, by implementing {@see NodeAccessorFactoryInterface}.
 *    Ensure that you pass on $nextAccessor to your custom factory.
 *
 * 3) Register your custom factory in `Settings.yaml` underneath `nodeAccessorFactories`.
 */
interface NodeAccessorInterface
{
    // IDENTITY of this NodeAccessor.
    public function getContentStreamIdentifier(): ContentStreamIdentifier;

    public function getDimensionSpacePoint(): DimensionSpacePoint;

    // Find by ID
    public function findByIdentifier(NodeAggregateIdentifier $nodeAggregateIdentifier): ?NodeInterface;

    // Traversal
    public function findChildNodes(
        NodeInterface $parentNode,
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): Nodes;

    // Nodes implements IteratorAggregate oÄ

    public function findReferencedNodes(NodeInterface $node, PropertyName $name = null): Nodes;

    public function findReferencingNodes(NodeInterface $node, PropertyName $name = null): Nodes;

    public function findParentNode(NodeInterface $childNode): ?NodeInterface;

    public function findNodeByPath(NodePath $path, NodeInterface $startingNode): ?NodeInterface;

    public function findChildNodeConnectedThroughEdgeName(
        NodeInterface $parentNode,
        NodeName $edgeName
    ): ?NodeInterface;

    // NO SIBLING methods - as we do not use them except in constraint checks

    public function findNodePath(NodeInterface $node): NodePath;

    /**
     * @param NodeInterface[] $entryNodes
     */
    public function findSubtrees(
        array $entryNodes,
        int $maximumLevels,
        NodeTypeConstraints $nodeTypeConstraints
    ): SubtreeInterface;

    /**
     * Recursively find all nodes underneath the $entryNodeAggregateIdentifiers,
     * which match the node type constraints specified by NodeTypeConstraints.
     *
     * If a Search Term is specified, the properties are searched for this search term.
     *
     * @param NodeInterface[] $entryNodes
     */
    public function findDescendants(
        array $entryNodes,
        NodeTypeConstraints $nodeTypeConstraints,
        ?SearchTerm $searchTerm
    ): Nodes;
}
