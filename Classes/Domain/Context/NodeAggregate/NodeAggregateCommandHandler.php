<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream;
use Neos\ContentRepository\DimensionSpace\DimensionSpace;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeAggregatesTypeIsAmbiguous;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Context\Node\ParentsNodeAggregateNotVisibleInDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeVariant;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateRootNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateNameWasChanged;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeGeneralizationVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodePeerVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeSpecializationVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\RootNodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeAggregate;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\EventSourcedContentRepository\Exception\DimensionSpacePointNotFound;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;

final class NodeAggregateCommandHandler
{
    /**
     * @var ContentStream\ContentStreamRepository
     */
    protected $contentStreamRepository;

    /**
     * Used for constraint checks against the current outside configuration state of node types
     *
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * Used for constraint checks against the current outside configuration state of content dimensions
     *
     * @var DimensionSpacePointSet
     */
    protected $allowedDimensionSubspace;

    /**
     * The graph projection used for soft constraint checks
     *
     * @var ContentGraphInterface
     */
    protected $contentGraph;

    /**
     * Used for variation resolution from the current outside state of content dimensions
     *
     * @var DimensionSpace\InterDimensionalVariationGraph
     */
    protected $interDimensionalVariationGraph;

    /**
     * Used for publishing events
     *
     * @var NodeEventPublisher
     */
    protected $nodeEventPublisher;

    /**
     * @var ReadSideMemoryCacheManager
     */
    protected $readSideMemoryCacheManager;

    /**
     * can be disabled in {@see NodeAggregateCommandHandler::withoutAnchestorNodeTypeConstraintChecks()}
     * @var bool
     */
    protected $ancestorNodeTypeConstraintChecksEnabled = true;

    public function __construct(
        ContentStream\ContentStreamRepository $contentStreamRepository,
        NodeTypeManager $nodeTypeManager,
        DimensionSpace\ContentDimensionZookeeper $contentDimensionZookeeper,
        ContentGraphInterface $contentGraph,
        DimensionSpace\InterDimensionalVariationGraph $interDimensionalVariationGraph,
        NodeEventPublisher $nodeEventPublisher,
        ReadSideMemoryCacheManager $readSideMemoryCacheManager
    ) {
        $this->contentStreamRepository = $contentStreamRepository;
        $this->nodeTypeManager = $nodeTypeManager;
        $this->allowedDimensionSubspace = $contentDimensionZookeeper->getAllowedDimensionSubspace();
        $this->contentGraph = $contentGraph;
        $this->interDimensionalVariationGraph = $interDimensionalVariationGraph;
        $this->nodeEventPublisher = $nodeEventPublisher;
        $this->readSideMemoryCacheManager = $readSideMemoryCacheManager;
    }

    /**
     * Use this closure to run code with the Anchestor Node Type Checks disabled; e.g.
     * during imports.
     *
     * You can disable this because many old sites have this constraint violated more or less;
     * and it's easy to fix lateron; as it does not touch the fundamental integrity of the CR.
     *
     * @param \Closure $callback
     */
    public function withoutAncestorNodeTypeConstraintChecks(\Closure $callback): void
    {
        $previousAncestorNodeTypeConstraintChecksEnabled = $this->ancestorNodeTypeConstraintChecksEnabled;
        $this->ancestorNodeTypeConstraintChecksEnabled = false;

        $callback();

        $this->ancestorNodeTypeConstraintChecksEnabled = $previousAncestorNodeTypeConstraintChecksEnabled;
    }

    /**
     * @param CreateRootNodeAggregateWithNode $command
     * @return CommandResult
     * @throws ContentStream\ContentStreamDoesNotExistYet
     * @throws NodeAggregatesTypeIsAmbiguous
     */
    public function handleCreateRootNodeAggregateWithNode(CreateRootNodeAggregateWithNode $command): CommandResult
    {
        $this->readSideMemoryCacheManager->disableCache();

        $this->requireProjectedNodeAggregateToNotExist($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());


        $events = DomainEvents::createEmpty();
        $this->nodeEventPublisher->withCommand($command, function () use ($command, &$events) {
            $this->requireContentStreamToExist($command->getContentStreamIdentifier());
            $nodeType = $this->requireNodeType($command->getNodeTypeName());
            $this->requireNodeTypeToBeOfTypeRoot($nodeType);

            $events = $this->createRootWithNode(
                $command,
                $this->allowedDimensionSubspace
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    private function createRootWithNode(
        CreateRootNodeAggregateWithNode $command,
        DimensionSpacePointSet $visibleDimensionSpacePoints
    ): DomainEvents {
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new RootNodeAggregateWithNodeWasCreated(
                    $command->getContentStreamIdentifier(),
                    $command->getNodeAggregateIdentifier(),
                    $command->getNodeTypeName(),
                    $visibleDimensionSpacePoints,
                    NodeAggregateClassification::root(),
                    $command->getInitiatingUserIdentifier()
                )
            )
        );

        $contentStreamEventStreamName = ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier());
        $this->nodeEventPublisher->publishMany(
            $contentStreamEventStreamName->getEventStreamName(),
            $events
        );

        return $events;
    }

    /**
     * @param CreateNodeAggregateWithNode $command
     * @return CommandResult
     * @throws ContentStream\ContentStreamDoesNotExistYet If the given content stream does not exist yet
     * @throws DimensionSpacePointNotFound If the given dimension space point is not in the allowed dimension space
     * @throws NodeConstraintException If a node aggregate of that type is not allowed to be created as a descendant of its parents
     * @throws NodeNameIsAlreadyOccupied If the given node name is already taken in any of the dimension space points the node will be visible in
     * @throws NodeTypeNotFoundException If the given type does not exist
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     * @throws NodeAggregatesTypeIsAmbiguous
     */
    public function handleCreateNodeAggregateWithNode(CreateNodeAggregateWithNode $command): CommandResult
    {
        $this->readSideMemoryCacheManager->disableCache();

        $events = DomainEvents::createEmpty();
        $this->nodeEventPublisher->withCommand($command, function () use ($command, &$events) {
            $this->requireContentStreamToExist($command->getContentStreamIdentifier());
            $this->requireDimensionSpacePointToExist($command->getOriginDimensionSpacePoint());
            $nodeType = $this->requireNodeType($command->getNodeTypeName());
            $this->requireNodeTypeToNotBeOfTypeRoot($nodeType);
            $this->requireTetheredDescendantNodeTypesToExist($nodeType);
            $this->requireTetheredDescendantNodeTypesToNotBeOfTypeRoot($nodeType);
            if ($this->ancestorNodeTypeConstraintChecksEnabled) {
                $this->requireConstraintsImposedByAncestorsAreMet($command->getContentStreamIdentifier(), $nodeType, $command->getNodeName(), [$command->getParentNodeAggregateIdentifier()]);
            }
            $this->requireProjectedNodeAggregateToNotExist($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
            $parentNodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getParentNodeAggregateIdentifier());

            if ($command->getSucceedingSiblingNodeAggregateIdentifier()) {
                $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getSucceedingSiblingNodeAggregateIdentifier());
            }
            $this->requireNodeAggregateToCoverDimensionSpacePoint($parentNodeAggregate, $command->getOriginDimensionSpacePoint());

            $specializations = $this->interDimensionalVariationGraph->getSpecializationSet($command->getOriginDimensionSpacePoint());
            $visibleDimensionSpacePoints = $specializations->getIntersection($parentNodeAggregate->getCoveredDimensionSpacePoints());

            if ($command->getNodeName()) {
                $this->requireNodeNameToBeUnoccupied(
                    $command->getContentStreamIdentifier(),
                    $command->getNodeName(),
                    $command->getParentNodeAggregateIdentifier(),
                    $command->getOriginDimensionSpacePoint(),
                    $visibleDimensionSpacePoints
                );
            }

            $descendantNodeAggregateIdentifiers = $this->populateNodeAggregateIdentifiers($nodeType, $command->getTetheredDescendantNodeAggregateIdentifiers());

            foreach ($descendantNodeAggregateIdentifiers as $rawNodePath => $descendantNodeAggregateIdentifier) {
                $this->requireProjectedNodeAggregateToNotExist($command->getContentStreamIdentifier(), $descendantNodeAggregateIdentifier);
            }

            $defaultPropertyValues = $this->getDefaultPropertyValues($nodeType);
            $initialPropertyValues = $defaultPropertyValues->merge($command->getInitialPropertyValues());

            $events = $this->createRegularWithNode(
                $command,
                $visibleDimensionSpacePoints,
                $initialPropertyValues
            );

            $events = $this->handleTetheredChildNodes(
                $command,
                $nodeType,
                $visibleDimensionSpacePoints,
                $command->getNodeAggregateIdentifier(),
                $descendantNodeAggregateIdentifiers,
                $events
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    private function createRegularWithNode(
        CreateNodeAggregateWithNode $command,
        DimensionSpacePointSet $visibleDimensionSpacePoints,
        PropertyValues $initialPropertyValues
    ): DomainEvents {
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new NodeAggregateWithNodeWasCreated(
                    $command->getContentStreamIdentifier(),
                    $command->getNodeAggregateIdentifier(),
                    $command->getNodeTypeName(),
                    $command->getOriginDimensionSpacePoint(),
                    $visibleDimensionSpacePoints,
                    $command->getParentNodeAggregateIdentifier(),
                    $command->getNodeName(),
                    $initialPropertyValues,
                    NodeAggregateClassification::regular(),
                    $command->getSucceedingSiblingNodeAggregateIdentifier()
                )
            )
        );

        $contentStreamEventStreamName = ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier());
        $this->nodeEventPublisher->publishMany(
            $contentStreamEventStreamName->getEventStreamName(),
            $events
        );

        return $events;
    }

    /**
     * @param CreateNodeAggregateWithNode $command
     * @param NodeType $nodeType
     * @param DimensionSpacePointSet $visibleDimensionSpacePoints
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers
     * @param DomainEvents $events
     * @param NodePath|null $nodePath
     * @return DomainEvents
     * @throws ContentStream\ContentStreamDoesNotExistYet
     * @throws NodeTypeNotFoundException
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    protected function handleTetheredChildNodes(
        CreateNodeAggregateWithNode $command,
        NodeType $nodeType,
        DimensionSpacePointSet $visibleDimensionSpacePoints,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers,
        DomainEvents $events,
        NodePath $nodePath = null
    ): DomainEvents {
        foreach ($nodeType->getAutoCreatedChildNodes() as $rawNodeName => $childNodeType) {
            $nodeName = NodeName::fromString($rawNodeName);
            $childNodePath = $nodePath ? $nodePath->appendPathSegment($nodeName) : NodePath::fromString((string) $nodeName);
            $childNodeAggregateIdentifier = $nodeAggregateIdentifiers->getNodeAggregateIdentifier($childNodePath) ?? NodeAggregateIdentifier::create();
            $initialPropertyValues = $this->getDefaultPropertyValues($childNodeType);

            $this->requireContentStreamToExist($command->getContentStreamIdentifier());
            $events = $events->appendEvents($this->createTetheredWithNode(
                $command,
                $childNodeAggregateIdentifier,
                NodeTypeName::fromString($childNodeType->getName()),
                $visibleDimensionSpacePoints,
                $parentNodeAggregateIdentifier,
                $nodeName,
                $initialPropertyValues
            ));

            $events = $this->handleTetheredChildNodes(
                $command,
                $childNodeType,
                $visibleDimensionSpacePoints,
                $childNodeAggregateIdentifier,
                $nodeAggregateIdentifiers,
                $events,
                $childNodePath
            );
        }

        return $events;
    }

    private function createTetheredWithNode(
        CreateNodeAggregateWithNode $command,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeName $nodeTypeName,
        DimensionSpacePointSet $visibleDimensionSpacePoints,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeName $nodeName,
        PropertyValues $initialPropertyValues,
        NodeAggregateIdentifier $precedingNodeAggregateIdentifier = null
    ): DomainEvents {
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new NodeAggregateWithNodeWasCreated(
                    $command->getContentStreamIdentifier(),
                    $nodeAggregateIdentifier,
                    $nodeTypeName,
                    $command->getOriginDimensionSpacePoint(),
                    $visibleDimensionSpacePoints,
                    $parentNodeAggregateIdentifier,
                    $nodeName,
                    $initialPropertyValues,
                    NodeAggregateClassification::tethered(),
                    $precedingNodeAggregateIdentifier
                )
            )
        );

        $contentStreamEventStreamName = ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier());
        $this->nodeEventPublisher->publishMany(
            $contentStreamEventStreamName->getEventStreamName(),
            $events
        );

        return $events;
    }


    protected function getDefaultPropertyValues(NodeType $nodeType): PropertyValues
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
    protected function populateNodeAggregateIdentifiers(NodeType $nodeType, NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers, NodePath $childPath = null): NodeAggregateIdentifiersByNodePaths
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

    protected function requireNodeTypeToBeOfTypeRoot(NodeType $nodeType): void
    {
        if (!$nodeType->isOfType(NodeTypeName::ROOT_NODE_TYPE_NAME)) {
            throw new NodeTypeIsNotOfTypeRoot('Node type "' . $nodeType . '" is not of type root.', 1541765701);
        }
    }

    /**
     * @param NodeType $nodeType
     * @throws NodeTypeIsOfTypeRoot
     */
    protected function requireNodeTypeToNotBeOfTypeRoot(NodeType $nodeType): void
    {
        if ($nodeType->isOfType(NodeTypeName::ROOT_NODE_TYPE_NAME)) {
            throw new NodeTypeIsOfTypeRoot('Node type "' . $nodeType->getName() . '" is of type root.', 1541765806);
        }
    }

    /**
     * @param NodeType $nodeType
     * @throws NodeTypeNotFoundException
     */
    protected function requireTetheredDescendantNodeTypesToExist(NodeType $nodeType): void
    {
        foreach ($nodeType->getAutoCreatedChildNodes() as $childNodeType) {
            $this->requireTetheredDescendantNodeTypesToExist($childNodeType);
        }
    }

    /**
     * @param NodeType $nodeType
     * @throws NodeTypeIsOfTypeRoot
     */
    protected function requireTetheredDescendantNodeTypesToNotBeOfTypeRoot(NodeType $nodeType): void
    {
        foreach ($nodeType->getAutoCreatedChildNodes() as $tetheredChildNodeType) {
            if ($tetheredChildNodeType->isOfType(NodeTypeName::ROOT_NODE_TYPE_NAME)) {
                throw new NodeTypeIsOfTypeRoot('Node type "' . $nodeType->getName() . '" for tethered descendant is of type root.', 1541767062);
            }
            $this->requireTetheredDescendantNodeTypesToNotBeOfTypeRoot($tetheredChildNodeType);
        }
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @return NodeAggregate
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws NodeAggregateCurrentlyDoesNotExist
     */
    protected function requireProjectedNodeAggregate(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): NodeAggregate {
        $nodeAggregate = $this->contentGraph->findNodeAggregateByIdentifier($contentStreamIdentifier, $nodeAggregateIdentifier);

        if (!$nodeAggregate) {
            throw new NodeAggregateCurrentlyDoesNotExist('Node aggregate "' . $nodeAggregateIdentifier . '" does currently not exist.', 1541678486);
        }

        return $nodeAggregate;
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws NodeAggregateCurrentlyExists
     */
    protected function requireProjectedNodeAggregateToNotExist(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): void {
        $nodeAggregate = $this->contentGraph->findNodeAggregateByIdentifier($contentStreamIdentifier, $nodeAggregateIdentifier);

        if ($nodeAggregate) {
            throw new NodeAggregateCurrentlyExists('Node aggregate "' . $nodeAggregateIdentifier . '" does currently not exist.', 1541687645);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @throws NodeAggregateIsRoot
     */
    protected function requireNodeAggregateToNotBeRoot(ReadableNodeAggregateInterface $nodeAggregate): void
    {
        if ($nodeAggregate->isRoot()) {
            throw new NodeAggregateIsRoot('Node aggregate "' . $nodeAggregate->getIdentifier() . '" is classified as root.', 1554586860);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @throws NodeAggregateIsTethered
     */
    protected function requireNodeAggregateToBeUntethered(ReadableNodeAggregateInterface $nodeAggregate): void
    {
        if ($nodeAggregate->isTethered()) {
            throw new NodeAggregateIsTethered('Node aggregate "' . $nodeAggregate->getIdentifier() . '" is classified as tethered.', 1554587288);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint
     */
    protected function requireNodeAggregateToCoverDimensionSpacePoint(
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePoint $dimensionSpacePoint
    ): void {
        if (!$nodeAggregate->coversDimensionSpacePoint($dimensionSpacePoint)) {
            throw new NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint('Node aggregate "' . $nodeAggregate->getIdentifier() . '" is currently not visible in dimension space point ' . json_encode($dimensionSpacePoint) . '.', 1541678877);
        }
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeName $nodeName
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param DimensionSpacePoint $parentOriginDimensionSpacePoint
     * @param DimensionSpacePointSet $dimensionSpacePoints
     * @throws NodeNameIsAlreadyOccupied
     */
    protected function requireNodeNameToBeUnoccupied(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeName $nodeName,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        DimensionSpacePoint $parentOriginDimensionSpacePoint,
        DimensionSpacePointSet $dimensionSpacePoints
    ): void {
        $dimensionSpacePointsOccupiedByChildNodeName = $this->contentGraph->getDimensionSpacePointsOccupiedByChildNodeName(
            $contentStreamIdentifier,
            $nodeName,
            $parentNodeAggregateIdentifier,
            $parentOriginDimensionSpacePoint,
            $dimensionSpacePoints
        );
        if (count($dimensionSpacePointsOccupiedByChildNodeName) > 0) {
            throw new NodeNameIsAlreadyOccupied('Child node name "' . $nodeName . '" is already occupied for parent "' . $parentNodeAggregateIdentifier . '" in dimension space points ' . $dimensionSpacePointsOccupiedByChildNodeName);
        }
    }

    /**
     * @param Command\ChangeNodeAggregateType $command
     * @throws NodeTypeNotFound
     * @throws NodeConstraintException
     * @throws NodeTypeNotFoundException
     * @throws NodeAggregatesTypeIsAmbiguous
     */
    public function handleChangeNodeAggregateType(Command\ChangeNodeAggregateType $command)
    {
        throw new \DomainException('Changing the type of a node aggregate is not yet supported', 1554555107);
        $this->readSideMemoryCacheManager->disableCache();

        /*
        if (!$this->nodeTypeManager->hasNodeType((string)$command->getNewNodeTypeName())) {
            throw new NodeTypeNotFound('The given node type "' . $command->getNewNodeTypeName() . '" is unknown to the node type manager', 1520009174);
        }

        $this->checkConstraintsImposedByAncestors($command);
        $this->checkConstraintsImposedOnAlreadyPresentDescendants($command);
*/
        // TODO: continue implementing!
    }

    /**
     * @param Command\ChangeNodeAggregateName $command
     * @return CommandResult
     */
    public function handleChangeNodeAggregateName(Command\ChangeNodeAggregateName $command): CommandResult
    {
        // TODO: check if CS exists
        // TODO: check if aggregate exists and delegate to it
        // TODO: check if aggregate is root
        $events = DomainEvents::fromArray([]);
        $this->nodeEventPublisher->withCommand($command, function () use ($command, &$events) {
            $events = DomainEvents::withSingleEvent(
                EventWithIdentifier::create(
                    new NodeAggregateNameWasChanged(
                        $command->getContentStreamIdentifier(),
                        $command->getNodeAggregateIdentifier(),
                        $command->getNewNodeName()
                    )
                )
            );

            $this->nodeEventPublisher->publishMany(
                ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier(
                    $command->getContentStreamIdentifier()
                )->getEventStreamName(),
                $events
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeType $nodeType
     * @param NodeName $nodeName
     * @param array|NodeAggregateIdentifier[] $parentNodeAggregateIdentifiers
     * @throws NodeConstraintException
     * @throws NodeTypeNotFoundException
     * @throws NodeAggregatesTypeIsAmbiguous
     */
    protected function requireConstraintsImposedByAncestorsAreMet(ContentStreamIdentifier $contentStreamIdentifier, NodeType $nodeType, ?NodeName $nodeName, array $parentNodeAggregateIdentifiers): void
    {
        foreach ($parentNodeAggregateIdentifiers as $parentNodeAggregateIdentifier) {
            $parentAggregate = $this->requireProjectedNodeAggregate($contentStreamIdentifier, $parentNodeAggregateIdentifier);
            try {
                $parentsNodeType = $this->requireNodeType($parentAggregate->getNodeTypeName());
                if (!$parentsNodeType->allowsChildNodeType($nodeType)) {
                    throw new NodeConstraintException('Node type "' . $nodeType . '" is not allowed for child nodes of type ' . $parentsNodeType->getName());
                }
                if ($nodeName
                    &&$parentsNodeType->hasAutoCreatedChildNode($nodeName)
                    && $parentsNodeType->getTypeOfAutoCreatedChildNode($nodeName)->getName() !== $nodeType->getName()) {
                    throw new NodeConstraintException('Node type "' . $nodeType . '" does not match configured "' . $parentsNodeType->getTypeOfAutoCreatedChildNode($nodeName)->getName()
                        . '" for auto created child nodes for parent type "' . $parentsNodeType . '" with name "' . $nodeName . '"');
                }
            } catch (NodeTypeNotFound $e) {
                // skip constraint check; Once the parent is changed to be of an available type,
                // the constraint checks are executed again. See handleChangeNodeAggregateType
            }

            foreach ($this->contentGraph->findParentNodeAggregates($contentStreamIdentifier, $parentNodeAggregateIdentifier) as $grandParentNodeAggregate) {
                try {
                    $grandParentsNodeType = $this->requireNodeType($grandParentNodeAggregate->getNodeTypeName());
                    if ($grandParentsNodeType->hasAutoCreatedChildNode($parentAggregate->getNodeName())
                        && !$grandParentsNodeType->allowsGrandchildNodeType((string)$parentAggregate->getNodeName(), $nodeType)) {
                        throw new NodeConstraintException('Node type "' . $nodeType . '" is not allowed below tethered child nodes "' . $parentAggregate->getNodeName()
                            . '" of nodes of type "' . $grandParentNodeAggregate->getNodeTypeName() . '"', 1520011791);
                    }
                } catch (NodeTypeNotFound $e) {
                    // skip constraint check; Once the grand parent is changed to be of an available type,
                    // the constraint checks are executed again. See handleChangeNodeAggregateType
                }
            }
        }
    }

    /**
     * @param Command\ChangeNodeAggregateType $command
     * @throws NodeConstraintException
     * @throws NodeTypeNotFoundException
     * @throws NodeAggregatesTypeIsAmbiguous
     * @return void
     */
    protected function checkConstraintsImposedByAncestors(Command\ChangeNodeAggregateType $command): void
    {
        $nodeAggregate = $this->contentGraph->findNodeAggregateByIdentifier($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        $newNodeType = $this->nodeTypeManager->getNodeType((string)$command->getNewNodeTypeName());
        foreach ($this->contentGraph->findParentNodeAggregates($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier()) as $parentAggregate) {
            $parentsNodeType = $this->nodeTypeManager->getNodeType((string)$parentAggregate->getNodeTypeName());
            if (!$parentsNodeType->allowsChildNodeType($newNodeType)) {
                throw new NodeConstraintException('Node type ' . $command->getNewNodeTypeName() . ' is not allowed below nodes of type ' . $parentAggregate->getNodeTypeName());
            }
            if ($nodeAggregate->getNodeName()
                && $parentsNodeType->hasAutoCreatedChildNode($nodeAggregate->getNodeName())
                && $parentsNodeType->getTypeOfAutoCreatedChildNode($nodeAggregate->getNodeName())->getName() !== (string)$command->getNewNodeTypeName()) {
                throw new NodeConstraintException('Cannot change type of auto created child node' . $nodeAggregate->getNodeName() . ' to ' . $command->getNewNodeTypeName());
            }
            foreach ($this->contentGraph->findParentNodeAggregates($command->getContentStreamIdentifier(), $parentAggregate->getIdentifier()) as $grandParentAggregate) {
                $grandParentsNodeType = $this->nodeTypeManager->getNodeType((string)$grandParentAggregate->getNodeTypeName());
                if ($parentAggregate->getNodeName()
                    && $grandParentsNodeType->hasAutoCreatedChildNode($parentAggregate->getNodeName())
                    && !$grandParentsNodeType->allowsGrandchildNodeType((string) $parentAggregate->getNodeName(), $newNodeType)) {
                    throw new NodeConstraintException('Node type "' . $command->getNewNodeTypeName() . '" is not allowed below auto created child nodes "' . $parentAggregate->getNodeName()
                        . '" of nodes of type "' . $grandParentAggregate->getNodeTypeName() . '"', 1520011791);
                }
            }
        }
    }

    /**
     * @param Command\ChangeNodeAggregateType $command
     * @throws NodeConstraintException
     * @throws NodeTypeNotFoundException
     * @return \void
     */
    protected function checkConstraintsImposedOnAlreadyPresentDescendants(Command\ChangeNodeAggregateType $command): void
    {
        $newNodeType = $this->nodeTypeManager->getNodeType((string)$command->getNewNodeTypeName());

        foreach ($this->contentGraph->findChildNodeAggregates($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier()) as $childAggregate) {
            $childsNodeType = $this->nodeTypeManager->getNodeType((string)$childAggregate->getNodeTypeName());
            if (!$newNodeType->allowsChildNodeType($childsNodeType)) {
                if (!$command->getStrategy()) {
                    throw new NodeConstraintException('Node type ' . $command->getNewNodeTypeName() . ' does not allow children of type  ' . $childAggregate->getNodeTypeName()
                        . ', which already exist. Please choose a resolution strategy.', 1520014467);
                }
            }

            if ($childAggregate->getNodeName() && $newNodeType->hasAutoCreatedChildNode($childAggregate->getNodeName())) {
                foreach ($this->contentGraph->findChildNodeAggregates($command->getContentStreamIdentifier(), $childAggregate->getIdentifier()) as $grandChildAggregate) {
                    $grandChildsNodeType = $this->nodeTypeManager->getNodeType((string)$grandChildAggregate->getNodeTypeName());
                    if ($childAggregate->getNodeName() && !$newNodeType->allowsGrandchildNodeType((string)$childAggregate->getNodeName(), $grandChildsNodeType)) {
                        if (!$command->getStrategy()) {
                            throw new NodeConstraintException('Node type ' . $command->getNewNodeTypeName() . ' does not allow auto created child nodes "' . $childAggregate->getNodeName()
                                . '" to have children of type  ' . $grandChildAggregate->getNodeTypeName() . ', which already exist. Please choose a resolution strategy.', 1520151998);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param CreateNodeVariant $command
     * @return CommandResult
     * @throws ContentStream\ContentStreamDoesNotExistYet
     * @throws NodeAggregateCurrentlyExists
     * @throws DimensionSpacePointNotFound
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws DimensionSpacePointIsNotYetOccupied
     * @throws DimensionSpacePointIsAlreadyOccupied
     * @throws NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint
     */
    public function handleCreateNodeVariant(CreateNodeVariant $command): CommandResult
    {
        $this->readSideMemoryCacheManager->disableCache();

        $this->requireContentStreamToExist($command->getContentStreamIdentifier());
        $nodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        $this->requireDimensionSpacePointToExist($command->getSourceOrigin());
        $this->requireDimensionSpacePointToExist($command->getTargetOrigin());
        $this->requireNodeAggregateToNotBeRoot($nodeAggregate);
        $this->requireNodeAggregateToBeUntethered($nodeAggregate);
        $this->requireNodeAggregateToOccupyDimensionSpacePoint($nodeAggregate, $command->getSourceOrigin());
        $this->requireNodeAggregateToNotOccupyDimensionSpacePoint($nodeAggregate, $command->getTargetOrigin());

        $parentNodeAggregate = $this->contentGraph->findParentNodeAggregateByChildOriginDimensionSpacePoint(
            $command->getContentStreamIdentifier(),
            $command->getNodeAggregateIdentifier(),
            $command->getSourceOrigin()
        );
        $this->requireNodeAggregateToCoverDimensionSpacePoint($parentNodeAggregate, $command->getTargetOrigin());

        switch ($this->interDimensionalVariationGraph->getVariantType($command->getTargetOrigin(), $command->getSourceOrigin())->getType()) {
            case DimensionSpace\VariantType::TYPE_SPECIALIZATION:
                $events = $this->handleCreateNodeSpecializationVariant($command, $nodeAggregate);
                break;
            case DimensionSpace\VariantType::TYPE_GENERALIZATION:
                $events = $this->handleCreateNodeGeneralizationVariant($command, $nodeAggregate);
                break;
            case DimensionSpace\VariantType::TYPE_PEER:
            default:
                $events = $this->handleCreateNodePeerVariant($command, $nodeAggregate);
        }

        $publishedEvents = DomainEvents::createEmpty();
        $this->nodeEventPublisher->withCommand($command, function () use ($command, $events, &$publishedEvents) {
            foreach ($events as $event) {
                $domainEvents = DomainEvents::withSingleEvent(
                    EventWithIdentifier::create($event)
                );

                $streamName = ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier(
                    $event->getContentStreamIdentifier()
                );

                $this->nodeEventPublisher->publishMany($streamName->getEventStreamName(), $domainEvents);

                $publishedEvents = $publishedEvents->appendEvents($domainEvents);
            }
        });

        return CommandResult::fromPublishedEvents($publishedEvents);
    }

    /**
     * @param CreateNodeVariant $command
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @return array|NodeSpecializationVariantWasCreated[]
     */
    protected function handleCreateNodeSpecializationVariant(CreateNodeVariant $command, ReadableNodeAggregateInterface $nodeAggregate): array
    {
        $specializations = $this->interDimensionalVariationGraph->getIndexedSpecializations($command->getSourceOrigin());
        $excludedSet = new DimensionSpacePointSet([]);
        foreach ($specializations->getIntersection($nodeAggregate->getOccupiedDimensionSpacePoints()) as $occupiedSpecialization) {
            $excludedSet = $excludedSet->getUnion($this->interDimensionalVariationGraph->getSpecializationSet($occupiedSpecialization));
        }
        $specializationVisibility = $this->interDimensionalVariationGraph->getSpecializationSet(
            $command->getTargetOrigin(),
            true,
            $excludedSet
        );

        $events = [];

        return $this->collectNodeSpecializationVariantsThatWillHaveBeenCreated($command, $nodeAggregate, $specializationVisibility, $events);
    }

    /**
     * @param CreateNodeVariant $command
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePointSet $specializationVisibility
     * @param array $events
     * @return array|NodeSpecializationVariantWasCreated[]
     */
    protected function collectNodeSpecializationVariantsThatWillHaveBeenCreated(
        CreateNodeVariant $command,
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePointSet $specializationVisibility,
        array $events
    ): array {
        $events[] = new NodeSpecializationVariantWasCreated(
            $command->getContentStreamIdentifier(),
            $nodeAggregate->getIdentifier(),
            $command->getSourceOrigin(),
            $command->getTargetOrigin(),
            $specializationVisibility
        );

        foreach ($this->contentGraph->findTetheredChildNodeAggregates($command->getContentStreamIdentifier(), $nodeAggregate->getIdentifier()) as $tetheredChildNodeAggregate) {
            $events = $this->collectNodeSpecializationVariantsThatWillHaveBeenCreated($command, $tetheredChildNodeAggregate, $specializationVisibility, $events);
        }

        return $events;
    }

    /**
     * @param CreateNodeVariant $command
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @return array|NodeGeneralizationVariantWasCreated[]
     */
    protected function handleCreateNodeGeneralizationVariant(CreateNodeVariant $command, ReadableNodeAggregateInterface $nodeAggregate): array
    {
        $specializations = $this->interDimensionalVariationGraph->getIndexedSpecializations($command->getTargetOrigin());
        $excludedSet = new DimensionSpacePointSet([]);
        foreach ($specializations->getIntersection($nodeAggregate->getOccupiedDimensionSpacePoints()) as $occupiedSpecialization) {
            $excludedSet = $excludedSet->getUnion($this->interDimensionalVariationGraph->getSpecializationSet($occupiedSpecialization));
        }
        $generalizationVisibility = $this->interDimensionalVariationGraph->getSpecializationSet(
            $command->getTargetOrigin(),
            true,
            $excludedSet
        );
        $events = [];

        return $this->collectNodeGeneralizationVariantsThatWillHaveBeenCreated($command, $nodeAggregate, $generalizationVisibility, $events);
    }

    /**
     * @param CreateNodeVariant $command
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePointSet $generalizationVisibility
     * @param array $events
     * @return array|NodeSpecializationVariantWasCreated[]
     */
    protected function collectNodeGeneralizationVariantsThatWillHaveBeenCreated(
        CreateNodeVariant $command,
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePointSet $generalizationVisibility,
        array $events
    ): array {
        $events[] = new NodeGeneralizationVariantWasCreated(
            $command->getContentStreamIdentifier(),
            $nodeAggregate->getIdentifier(),
            $command->getSourceOrigin(),
            $command->getTargetOrigin(),
            $generalizationVisibility
        );

        foreach ($this->contentGraph->findTetheredChildNodeAggregates($command->getContentStreamIdentifier(), $nodeAggregate->getIdentifier()) as $tetheredChildNodeAggregate) {
            $events = $this->collectNodeGeneralizationVariantsThatWillHaveBeenCreated($command, $tetheredChildNodeAggregate, $generalizationVisibility, $events);
        }

        return $events;
    }

    /**
     * @param CreateNodeVariant $command
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @return array|NodePeerVariantWasCreated[]
     */
    protected function handleCreateNodePeerVariant(CreateNodeVariant $command, ReadableNodeAggregateInterface $nodeAggregate): array
    {
        $specializations = $this->interDimensionalVariationGraph->getIndexedSpecializations($command->getTargetOrigin());
        $excludedSet = new DimensionSpacePointSet([]);
        foreach ($specializations->getIntersection($nodeAggregate->getOccupiedDimensionSpacePoints()) as $occupiedSpecialization) {
            $excludedSet = $excludedSet->getUnion($this->interDimensionalVariationGraph->getSpecializationSet($occupiedSpecialization));
        }
        $peerVisibility = $this->interDimensionalVariationGraph->getSpecializationSet(
            $command->getTargetOrigin(),
            true,
            $excludedSet
        );
        $events = [];

        return $this->collectNodePeerVariantsThatWillHaveBeenCreated($command, $nodeAggregate, $peerVisibility, $events);
    }

    /**
     * @param CreateNodeVariant $command
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePointSet $peerVisibility
     * @param array $events
     * @return array|NodePeerVariantWasCreated[]
     */
    protected function collectNodePeerVariantsThatWillHaveBeenCreated(
        CreateNodeVariant $command,
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePointSet $peerVisibility,
        array $events
    ): array {
        $events[] = new NodePeerVariantWasCreated(
            $command->getContentStreamIdentifier(),
            $nodeAggregate->getIdentifier(),
            $command->getSourceOrigin(),
            $command->getTargetOrigin(),
            $peerVisibility
        );

        foreach ($this->contentGraph->findTetheredChildNodeAggregates($command->getContentStreamIdentifier(), $nodeAggregate->getIdentifier()) as $tetheredChildNodeAggregate) {
            $events = $this->collectNodePeerVariantsThatWillHaveBeenCreated($command, $tetheredChildNodeAggregate, $peerVisibility, $events);
        }

        return $events;
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws DimensionSpacePointIsNotYetOccupied
     */
    protected function requireNodeAggregateToOccupyDimensionSpacePoint(ReadableNodeAggregateInterface $nodeAggregate, DimensionSpacePoint $dimensionSpacePoint)
    {
        if (!$nodeAggregate->occupiesDimensionSpacePoint($dimensionSpacePoint)) {
            throw new DimensionSpacePointIsNotYetOccupied('Dimension space point ' . json_encode($dimensionSpacePoint) . ' is not yet occupied by node aggregate "' . $nodeAggregate->getIdentifier() . '"', 1552595396);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws DimensionSpacePointIsAlreadyOccupied
     */
    protected function requireNodeAggregateToNotOccupyDimensionSpacePoint(ReadableNodeAggregateInterface $nodeAggregate, DimensionSpacePoint $dimensionSpacePoint)
    {
        if ($nodeAggregate->occupiesDimensionSpacePoint($dimensionSpacePoint)) {
            throw new DimensionSpacePointIsAlreadyOccupied('Dimension space point ' . json_encode($dimensionSpacePoint) . ' is already occupied by node aggregate "' . $nodeAggregate->getIdentifier() . '"', 1552595441);
        }
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @throws ContentStream\ContentStreamDoesNotExistYet
     */
    protected function requireContentStreamToExist(ContentStreamIdentifier $contentStreamIdentifier): void
    {
        $contentStream = $this->contentStreamRepository->findContentStream($contentStreamIdentifier);
        if (!$contentStream) {
            throw new ContentStream\ContentStreamDoesNotExistYet('Content stream "' . $contentStreamIdentifier . " does not exist yet.", 1521386692);
        }
    }

    /**
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws DimensionSpacePointNotFound
     */
    protected function requireDimensionSpacePointToExist(DimensionSpacePoint $dimensionSpacePoint): void
    {
        if (!$this->allowedDimensionSubspace->contains($dimensionSpacePoint)) {
            throw new DimensionSpacePointNotFound(sprintf('%s was not found in the allowed dimension subspace', $dimensionSpacePoint), 1520260137);
        }
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param DimensionSpacePoint $sourceDimensionSpacePoint
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws ParentsNodeAggregateNotVisibleInDimensionSpacePoint
     */
    protected function requireParentNodesAggregateToBeVisibleInDimensionSpacePoint(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        DimensionSpacePoint $sourceDimensionSpacePoint,
        DimensionSpacePoint $dimensionSpacePoint
    ): void {
        $sourceParentNodeAggregate = $this->contentGraph->findParentNodeAggregateByChildOriginDimensionSpacePoint(
            $contentStreamIdentifier,
            $nodeAggregateIdentifier,
            $sourceDimensionSpacePoint
        );

        // Root node aggregates cover the complete allowed dimension subspace
        if ($sourceParentNodeAggregate) {
            try {
                $this->requireNodeAggregateToCoverDimensionSpacePoint($sourceParentNodeAggregate, $dimensionSpacePoint);
            } catch (NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint $exception) {
                throw new ParentsNodeAggregateNotVisibleInDimensionSpacePoint(
                    'No suitable parent could be found for node "' . $nodeAggregateIdentifier . '" in target dimension space point ' . $dimensionSpacePoint,
                    1521322565
                );
            }
        }
    }

    /**
     * @param NodeTypeName $nodeTypeName
     * @return NodeType
     * @throws NodeTypeNotFound
     */
    protected function requireNodeType(NodeTypeName $nodeTypeName): NodeType
    {
        try {
            return $this->nodeTypeManager->getNodeType((string)$nodeTypeName);
        } catch (NodeTypeNotFoundException $e) {
            throw new NodeTypeNotFound('Node type "' . $nodeTypeName . '" is unknown to the node type manager.', 1541671070);
        }
    }
}
