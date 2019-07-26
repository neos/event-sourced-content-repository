<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature;

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
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\Exception\DimensionSpacePointNotFound;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream;
use Neos\ContentRepository\DimensionSpace\DimensionSpace;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamDoesNotExistYet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregatesTypeIsAmbiguous;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateCurrentlyDoesNotExist;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateCurrentlyExists;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeTypeIsNotOfTypeRoot;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeTypeIsOfTypeRoot;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeTypeNotFound;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateRootNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\RootNodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateIdentifiersByNodePaths;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;

trait NodeCreation
{
    abstract protected function getReadSideMemoryCacheManager(): ReadSideMemoryCacheManager;

    abstract protected function getNodeAggregateEventPublisher(): NodeAggregateEventPublisher;

    abstract protected function getInterDimensionalVariationGraph(): DimensionSpace\InterDimensionalVariationGraph;

    abstract protected function areAncestorNodeTypeConstraintChecksEnabled(): bool;

    /**
     * @param CreateRootNodeAggregateWithNode $command
     * @return CommandResult
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeAggregateCurrentlyExists
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws NodeTypeNotFound
     * @throws NodeTypeIsNotOfTypeRoot
     */
    public function handleCreateRootNodeAggregateWithNode(CreateRootNodeAggregateWithNode $command): CommandResult
    {
        $this->getReadSideMemoryCacheManager()->disableCache();

        $this->requireContentStreamToExist($command->getContentStreamIdentifier());
        $this->requireProjectedNodeAggregateToNotExist($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        $nodeType = $this->requireNodeType($command->getNodeTypeName());
        $this->requireNodeTypeToBeOfTypeRoot($nodeType);

        $events = DomainEvents::createEmpty();
        $this->getNodeAggregateEventPublisher()->withCommand($command, function () use ($command, &$events) {
            $events = $this->createRootWithNode(
                $command,
                $this->getAllowedDimensionSubspace()
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    /**
     * @param CreateRootNodeAggregateWithNode $command
     * @param DimensionSpacePointSet $coveredDimensionSpacePoints
     * @return DomainEvents
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    private function createRootWithNode(
        CreateRootNodeAggregateWithNode $command,
        DimensionSpacePointSet $coveredDimensionSpacePoints
    ): DomainEvents {
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new RootNodeAggregateWithNodeWasCreated(
                    $command->getContentStreamIdentifier(),
                    $command->getNodeAggregateIdentifier(),
                    $command->getNodeTypeName(),
                    $coveredDimensionSpacePoints,
                    NodeAggregateClassification::root(),
                    $command->getInitiatingUserIdentifier()
                )
            )
        );

        $contentStreamEventStreamName = ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier());
        $this->getNodeAggregateEventPublisher()->publishMany(
            $contentStreamEventStreamName->getEventStreamName(),
            $events
        );

        return $events;
    }

    /**
     * @param CreateNodeAggregateWithNode $command
     * @return CommandResult
     * @throws ContentStreamDoesNotExistYet
     * @throws DimensionSpacePointNotFound
     * @throws NodeTypeNotFound
     * @throws NodeTypeIsOfTypeRoot
     * @throws NodeTypeNotFoundException
     * @throws NodeConstraintException
     * @throws NodeAggregateCurrentlyDoesNotExist
     * @throws NodeAggregateCurrentlyExists
     */
    public function handleCreateNodeAggregateWithNode(CreateNodeAggregateWithNode $command): CommandResult
    {
        $this->getReadSideMemoryCacheManager()->disableCache();

        $this->requireContentStreamToExist($command->getContentStreamIdentifier());
        $this->requireDimensionSpacePointToExist($command->getOriginDimensionSpacePoint());
        $nodeType = $this->requireNodeType($command->getNodeTypeName());
        $this->requireNodeTypeToNotBeOfTypeRoot($nodeType);
        $this->requireTetheredDescendantNodeTypesToExist($nodeType);
        $this->requireTetheredDescendantNodeTypesToNotBeOfTypeRoot($nodeType);
        if ($this->areAncestorNodeTypeConstraintChecksEnabled()) {
            $this->requireConstraintsImposedByAncestorsAreMet($command->getContentStreamIdentifier(), $nodeType, $command->getNodeName(), [$command->getParentNodeAggregateIdentifier()]);
        }
        $this->requireProjectedNodeAggregateToNotExist($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        $parentNodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getParentNodeAggregateIdentifier());
        if ($command->getSucceedingSiblingNodeAggregateIdentifier()) {
            $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getSucceedingSiblingNodeAggregateIdentifier());
        }
        $this->requireNodeAggregateToCoverDimensionSpacePoint($parentNodeAggregate, $command->getOriginDimensionSpacePoint());
        $specializations = $this->getInterDimensionalVariationGraph()->getSpecializationSet($command->getOriginDimensionSpacePoint());
        $coveredDimensionSpacePoints = $specializations->getIntersection($parentNodeAggregate->getCoveredDimensionSpacePoints());
        if ($command->getNodeName()) {
            $this->requireNodeNameToBeUnoccupied(
                $command->getContentStreamIdentifier(),
                $command->getNodeName(),
                $command->getParentNodeAggregateIdentifier(),
                $command->getOriginDimensionSpacePoint(),
                $coveredDimensionSpacePoints
            );
        }
        $descendantNodeAggregateIdentifiers = $this->populateNodeAggregateIdentifiers($nodeType, $command->getTetheredDescendantNodeAggregateIdentifiers());
        foreach ($descendantNodeAggregateIdentifiers as $rawNodePath => $descendantNodeAggregateIdentifier) {
            $this->requireProjectedNodeAggregateToNotExist($command->getContentStreamIdentifier(), $descendantNodeAggregateIdentifier);
        }

        $events = DomainEvents::createEmpty();
        $this->getNodeAggregateEventPublisher()->withCommand($command, function () use ($command, $nodeType, $parentNodeAggregate, $coveredDimensionSpacePoints, $descendantNodeAggregateIdentifiers, &$events) {
            $defaultPropertyValues = $this->getDefaultPropertyValues($nodeType);
            $initialPropertyValues = $defaultPropertyValues->merge($command->getInitialPropertyValues());

            $events = $this->createRegularWithNode(
                $command,
                $coveredDimensionSpacePoints,
                $initialPropertyValues
            );

            $events = $events->appendEvents($this->createTetheredChildNodes(
                $command->getContentStreamIdentifier(),
                $nodeType,
                $coveredDimensionSpacePoints,
                $command->getOriginDimensionSpacePoint(),
                $command->getNodeAggregateIdentifier(),
                $descendantNodeAggregateIdentifiers,
            ));

            $contentStreamEventStreamName = ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier());
            $this->getNodeAggregateEventPublisher()->publishMany(
                $contentStreamEventStreamName->getEventStreamName(),
                $events
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    /**
     * @param CreateNodeAggregateWithNode $command
     * @param DimensionSpacePointSet $coveredDimensionSpacePoints
     * @param PropertyValues $initialPropertyValues
     * @return DomainEvents
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    private function createRegularWithNode(
        CreateNodeAggregateWithNode $command,
        DimensionSpacePointSet $coveredDimensionSpacePoints,
        PropertyValues $initialPropertyValues
    ): DomainEvents {
        return DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new NodeAggregateWithNodeWasCreated(
                    $command->getContentStreamIdentifier(),
                    $command->getNodeAggregateIdentifier(),
                    $command->getNodeTypeName(),
                    $command->getOriginDimensionSpacePoint(),
                    $coveredDimensionSpacePoints,
                    $command->getParentNodeAggregateIdentifier(),
                    $command->getNodeName(),
                    $initialPropertyValues,
                    NodeAggregateClassification::regular(),
                    $command->getSucceedingSiblingNodeAggregateIdentifier()
                )
            )
        );
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeType $nodeType
     * @param DimensionSpacePointSet $coveredDimensionSpacePoints
     * @param DimensionSpacePoint $originDimensionSpacePoint
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers
     * @param NodePath|null $nodePath
     * @return DomainEvents
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeTypeNotFoundException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    private function createTetheredChildNodes(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeType $nodeType,
        DimensionSpacePointSet $coveredDimensionSpacePoints,
        DimensionSpacePoint $originDimensionSpacePoint,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers,
        NodePath $nodePath = null
    ): DomainEvents {
        $events = DomainEvents::createEmpty();
        foreach ($nodeType->getAutoCreatedChildNodes() as $rawNodeName => $childNodeType) {
            $events = $events->appendEvents(
                $this->createTetheredChildNode(
                    $contentStreamIdentifier,
                    $coveredDimensionSpacePoints,
                    $originDimensionSpacePoint,
                    $parentNodeAggregateIdentifier,
                    $nodeAggregateIdentifiers,
                    NodeName::fromString($rawNodeName),
                    $childNodeType,
                    $nodePath
                )
            );
        }
        return $events;
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param DimensionSpacePointSet $coveredDimensionSpacePoints
     * @param DimensionSpacePoint $originDimensionSpacePoint
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers
     * @param NodeName $nodeName
     * @param NodeType $childNodeType
     * @param NodePath|null $nodePath
     * @return DomainEvents
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeTypeNotFoundException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function createTetheredChildNode(
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePointSet $coveredDimensionSpacePoints,
        DimensionSpacePoint $originDimensionSpacePoint,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers,
        NodeName $nodeName,
        NodeType $childNodeType,
        NodePath $nodePath = null
    ): DomainEvents {
        $childNodePath = $nodePath ? $nodePath->appendPathSegment($nodeName) : NodePath::fromString((string)$nodeName);
        $childNodeAggregateIdentifier = $nodeAggregateIdentifiers->getNodeAggregateIdentifier($childNodePath) ?? NodeAggregateIdentifier::create();
        $initialPropertyValues = $this->getDefaultPropertyValues($childNodeType);
        $this->requireContentStreamToExist($contentStreamIdentifier);


        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new NodeAggregateWithNodeWasCreated(
                    $contentStreamIdentifier,
                    $childNodeAggregateIdentifier,
                    NodeTypeName::fromString($childNodeType->getName()),
                    $originDimensionSpacePoint,
                    $coveredDimensionSpacePoints,
                    $parentNodeAggregateIdentifier,
                    $nodeName,
                    $initialPropertyValues,
                    NodeAggregateClassification::tethered(),
                    null
                )
            )
        );

        $events = $events->appendEvents(
            $this->createTetheredChildNodes(
                $contentStreamIdentifier,
                $childNodeType,
                $coveredDimensionSpacePoints,
                $originDimensionSpacePoint,
                $childNodeAggregateIdentifier,
                $nodeAggregateIdentifiers,
                $childNodePath
            )
        );

        return $events;
    }

    private function getDefaultPropertyValues(NodeType $nodeType): PropertyValues
    {
        $rawDefaultPropertyValues = [];
        foreach ($nodeType->getDefaultValuesForProperties() as $propertyName => $defaultValue) {
            $rawDefaultPropertyValues[$propertyName] = [
                'type' => $nodeType->getPropertyType($propertyName),
                'value' => $defaultValue
            ];
        }

        return PropertyValues::fromArray($rawDefaultPropertyValues);
    }

    /**
     * @param NodeType $nodeType
     * @param NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers
     * @param NodePath|null $childPath
     * @return NodeAggregateIdentifiersByNodePaths
     */
    private function populateNodeAggregateIdentifiers(NodeType $nodeType, NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers, NodePath $childPath = null): NodeAggregateIdentifiersByNodePaths
    {
        foreach ($nodeType->getAutoCreatedChildNodes() as $rawChildName => $childNodeType) {
            $childName = NodeName::fromString($rawChildName);
            $childPath = $childPath ? $childPath->appendPathSegment($childName) : NodePath::fromString((string) $childName);
            if (!$nodeAggregateIdentifiers->getNodeAggregateIdentifier($childPath)) {
                $nodeAggregateIdentifiers = $nodeAggregateIdentifiers->add($childPath, NodeAggregateIdentifier::create());
            }
        }

        return $nodeAggregateIdentifiers;
    }
}
