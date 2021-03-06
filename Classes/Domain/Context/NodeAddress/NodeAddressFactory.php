<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAddress;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope("singleton")]
class NodeAddressFactory
{
    /**
     * @Flow\Inject
     * @var WorkspaceFinder
     */
    protected $workspaceFinder;

    /**
     * @Flow\Inject
     * @var ContentGraphInterface
     */
    protected $contentGraph;

    public function createFromNode(NodeInterface $node): NodeAddress
    {
        $workspace = $this->workspaceFinder->findOneByCurrentContentStreamIdentifier(
            $node->getContentStreamIdentifier()
        );
        if ($workspace === null) {
            throw new \RuntimeException(
                'Cannot build a NodeAddress for traversable node of aggregate ' . $node->getNodeAggregateIdentifier()
                    . ', because the content stream ' . $node->getContentStreamIdentifier()
                    . ' is not assigned to a workspace.'
            );
        }
        return new NodeAddress(
            $node->getContentStreamIdentifier(),
            $node->getDimensionSpacePoint(),
            $node->getNodeAggregateIdentifier(),
            $workspace->getWorkspaceName()
        );
    }

    public function createFromUriString(string $serializedNodeAddress): NodeAddress
    {
        // the reverse method is {@link NodeAddress::serializeForUri} - ensure to adjust it
        // when changing the serialization here

        list($workspaceNameSerialized, $dimensionSpacePointSerialized, $nodeAggregateIdentifierSerialized)
            = explode('__', $serializedNodeAddress);
        $workspaceName = WorkspaceName::fromString($workspaceNameSerialized);
        $dimensionSpacePoint = DimensionSpacePoint::fromUriRepresentation($dimensionSpacePointSerialized);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($nodeAggregateIdentifierSerialized);

        $contentStreamIdentifier = $this->workspaceFinder->findOneByName($workspaceName)
            ?->getCurrentContentStreamIdentifier();
        if (is_null($contentStreamIdentifier)) {
            throw new \InvalidArgumentException(
                'Could not resolve content stream identifier for node address ' . $serializedNodeAddress,
                1645363784
            );
        }

        return new NodeAddress(
            $contentStreamIdentifier,
            $dimensionSpacePoint,
            $nodeAggregateIdentifier,
            $workspaceName
        );
    }

    /**
     * @param string $contextPath
     * @return NodeAddress
     * @deprecated make use of createFromUriString instead
     */
    public function createFromContextPath(string $contextPath): NodeAddress
    {
        $pathValues = NodePaths::explodeContextPath($contextPath);
        $workspace = $this->workspaceFinder->findOneByName(WorkspaceName::fromString($pathValues['workspaceName']));
        if (is_null($workspace)) {
            throw new \InvalidArgumentException('No workspace exists for context path ' . $contextPath, 1645363699);
        }
        $contentStreamIdentifier = $workspace->getCurrentContentStreamIdentifier();
        $dimensionSpacePoint = DimensionSpacePoint::fromLegacyDimensionArray($pathValues['dimensions']);
        $nodePath = NodePath::fromString(\mb_strpos($pathValues['nodePath'], '/sites') === 0
            ? \mb_substr($pathValues['nodePath'], 6)
            : $pathValues['nodePath']);

        $subgraph = $this->contentGraph->getSubgraphByIdentifier(
            $contentStreamIdentifier,
            $dimensionSpacePoint,
            VisibilityConstraints::withoutRestrictions()
        );
        $node = $subgraph->findNodeByPath(
            $nodePath,
            $this->contentGraph->findRootNodeAggregateByType(
                $contentStreamIdentifier,
                NodeTypeName::fromString('Neos.Neos:Sites')
            )->getIdentifier()
        );
        if (is_null($node)) {
            throw new \InvalidArgumentException('No node exists on context path ' . $contextPath, 1645363666);
        }

        return new NodeAddress(
            $contentStreamIdentifier,
            $dimensionSpacePoint,
            $node->getNodeAggregateIdentifier(),
            $workspace->getWorkspaceName()
        );
    }

    public function adjustWithDimensionSpacePoint(
        NodeAddress $baseNodeAddress,
        DimensionSpacePoint $dimensionSpacePoint
    ): NodeAddress {
        if ($dimensionSpacePoint === $baseNodeAddress->dimensionSpacePoint) {
            // optimization if dimension space point does not need adjusting
            return $baseNodeAddress;
        }

        return new NodeAddress(
            $baseNodeAddress->contentStreamIdentifier,
            $dimensionSpacePoint,
            $baseNodeAddress->nodeAggregateIdentifier,
            $baseNodeAddress->workspaceName
        );
    }

    public function adjustWithNodeAggregateIdentifier(
        NodeAddress $baseNodeAddress,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): NodeAddress {
        if ($nodeAggregateIdentifier->equals($baseNodeAddress->nodeAggregateIdentifier)) {
            // optimization if NodeAggregateIdentifier does not need adjusting
            return $baseNodeAddress;
        }

        return new NodeAddress(
            $baseNodeAddress->contentStreamIdentifier,
            $baseNodeAddress->dimensionSpacePoint,
            $nodeAggregateIdentifier,
            $baseNodeAddress->workspaceName
        );
    }

    public function adjustWithWorkspaceName(NodeAddress $baseNodeAddress, WorkspaceName $workspaceName): NodeAddress
    {
        if ($workspaceName === $baseNodeAddress->workspaceName) {
            // optimization if WorkspaceName does not need adjusting
            return $baseNodeAddress;
        }

        $contentStreamIdentifier = $this->workspaceFinder->findOneByName($workspaceName)
            ?->getCurrentContentStreamIdentifier();
        if (is_null($contentStreamIdentifier)) {
            throw new \InvalidArgumentException('Workspace ' . $workspaceName . ' does not exist', 1645363548);
        }

        return new NodeAddress(
            $contentStreamIdentifier,
            $baseNodeAddress->dimensionSpacePoint,
            $baseNodeAddress->nodeAggregateIdentifier,
            $workspaceName
        );
    }
}
