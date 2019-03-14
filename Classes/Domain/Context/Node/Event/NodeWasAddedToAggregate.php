<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\Node\Event;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ValueObject\NodeIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcedContentRepository\Domain\Context\Node\CopyableAcrossContentStreamsInterface;
use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeName;
use Neos\ContentRepository\Domain\ValueObject\NodeTypeName;
use Neos\Flow\Annotations as Flow;

/**
 * Node was added to node aggregate event
 *
 * @Flow\Proxy(false)
 */
final class NodeWasAddedToAggregate implements DomainEventInterface, CopyableAcrossContentStreamsInterface
{
    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifier;

    /**
     * @var NodeTypeName
     */
    private $nodeTypeName;

    /**
     * Location of the node in the dimension space
     *
     * @var DimensionSpacePoint
     */
    private $dimensionSpacePoint;

    /**
     * Visibility of node in the dimension space
     *
     * @var DimensionSpacePointSet
     */
    private $visibleInDimensionSpacePoints;

    /**
     * @var NodeIdentifier
     */
    private $nodeIdentifier;

    /**
     * @var NodeIdentifier
     */
    private $parentNodeIdentifier;

    /**
     * @var NodeName
     */
    private $nodeName;

    /**
     * (property name => PropertyValue)
     *
     * @var array
     */
    private $propertyDefaultValuesAndTypes;

    /**
     * NodeWasAddedToAggregate constructor.
     *
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param NodeTypeName $nodeTypeName
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @param DimensionSpacePointSet $visibleInDimensionSpacePoints
     * @param NodeIdentifier $nodeIdentifier
     * @param NodeIdentifier $parentNodeIdentifier
     * @param NodeName $nodeName
     * @param PropertyValues $propertyDefaultValuesAndTypes
     */
    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeName $nodeTypeName,
        DimensionSpacePoint $dimensionSpacePoint,
        DimensionSpacePointSet $visibleInDimensionSpacePoints,
        NodeIdentifier $nodeIdentifier,
        NodeIdentifier $parentNodeIdentifier,
        NodeName $nodeName,
        PropertyValues $propertyDefaultValuesAndTypes
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->nodeTypeName = $nodeTypeName;
        $this->dimensionSpacePoint = $dimensionSpacePoint;
        $this->visibleInDimensionSpacePoints = $visibleInDimensionSpacePoints;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->parentNodeIdentifier = $parentNodeIdentifier;
        $this->nodeName = $nodeName;
        $this->propertyDefaultValuesAndTypes = $propertyDefaultValuesAndTypes;
    }

    /**
     * @return ContentStreamIdentifier
     */
    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    /**
     * @return NodeAggregateIdentifier
     */
    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    /**
     * @return NodeTypeName
     */
    public function getNodeTypeName(): NodeTypeName
    {
        return $this->nodeTypeName;
    }

    /**
     * @return DimensionSpacePoint
     */
    public function getDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->dimensionSpacePoint;
    }

    /**
     * @return DimensionSpacePointSet
     */
    public function getVisibleInDimensionSpacePoints(): DimensionSpacePointSet
    {
        return $this->visibleInDimensionSpacePoints;
    }

    /**
     * @return NodeIdentifier
     */
    public function getNodeIdentifier(): NodeIdentifier
    {
        return $this->nodeIdentifier;
    }

    /**
     * @return NodeIdentifier
     */
    public function getParentNodeIdentifier(): NodeIdentifier
    {
        return $this->parentNodeIdentifier;
    }

    /**
     * @return NodeName
     */
    public function getNodeName(): NodeName
    {
        return $this->nodeName;
    }

    /**
     * @return PropertyValues
     */
    public function getPropertyDefaultValuesAndTypes(): PropertyValues
    {
        return $this->propertyDefaultValuesAndTypes;
    }

    /**
     * @param ContentStreamIdentifier $targetContentStream
     * @return NodeWasAddedToAggregate
     */
    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStream)
    {
        return new NodeWasAddedToAggregate(
            $targetContentStream,
            $this->nodeAggregateIdentifier,
            $this->nodeTypeName,
            $this->dimensionSpacePoint,
            $this->visibleInDimensionSpacePoints,
            $this->nodeIdentifier,
            $this->parentNodeIdentifier,
            $this->nodeName,
            $this->propertyDefaultValuesAndTypes
        );
    }
}
