<?php
declare(strict_types=1);

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Service\AuthorizationService;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Command\ForkContentStream;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamDoesNotExistYet;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamRepository;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\RemoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\RemoveNodesFromAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\SetNodeReferences;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeAggregatesTypeIsAmbiguous;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Node\SpecializedDimensionsMustBePartOfDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeVariant;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\MoveNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\RelationDistributionStrategyIsInvalid;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\Context\Node\SubtreeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\ChangeNodeAggregateType;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateRootNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\NodeDiscriminator;
use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddress;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventBus\EventBus;
use Neos\EventSourcing\EventStore\EventEnvelope;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStoreManager;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Utility\Arrays;
use PHPUnit\Framework\Assert;

/**
 * Features context
 */
trait EventSourcedTrait
{
    /**
     * @var EventTypeResolver
     */
    private $eventTypeResolver;

    /**
     * @var EventStoreManager
     */
    private $eventStoreManager;

    /**
     * @var ContentGraphInterface
     */
    private $contentGraph;

    /**
     * @var WorkspaceFinder
     */
    private $workspaceFinder;

    /**
     * @var NodeTypeConstraintFactory
     */
    private $nodeTypeConstraintFactory;

    /**
     * @var array
     */
    private $currentEventStreamAsArray = null;

    /**
     * @var \Exception
     */
    private $lastCommandException = null;

    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var DimensionSpacePoint
     */
    private $dimensionSpacePoint;

    /**
     * @var NodeAggregateIdentifier
     */
    protected $rootNodeAggregateIdentifier;

    /**
     * @var NodeInterface
     */
    protected $currentNode;

    /**
     * @var ReadableNodeAggregateInterface
     */
    protected $currentNodeAggregate;

    /**
     * @var EventNormalizer
     */
    protected $eventNormalizer;

    /**
     * @var VisibilityConstraints
     */
    protected $visibilityConstraints;

    /**
     * @var EventBus
     */
    private $eventBus;

    /**
     * @var CommandResult
     */
    protected $lastCommandOrEventResult;

    /**
     * @return \Neos\Flow\ObjectManagement\ObjectManagerInterface
     */
    abstract protected function getObjectManager();

    protected function setupEventSourcedTrait()
    {
        $this->nodeAuthorizationService = $this->getObjectManager()->get(AuthorizationService::class);
        $this->nodeTypeManager = $this->getObjectManager()->get(NodeTypeManager::class);
        $this->eventTypeResolver = $this->getObjectManager()->get(EventTypeResolver::class);
        $this->eventStoreManager = $this->getObjectManager()->get(EventStoreManager::class);
        $this->contentGraph = $this->getObjectManager()->get(ContentGraphInterface::class);
        $this->workspaceFinder = $this->getObjectManager()->get(WorkspaceFinder::class);
        $this->nodeTypeConstraintFactory = $this->getObjectManager()->get(NodeTypeConstraintFactory::class);
        $this->eventNormalizer = $this->getObjectManager()->get(EventNormalizer::class);
        $this->eventBus = $this->getObjectManager()->get(EventBus::class);

        $contentStreamRepository = $this->getObjectManager()->get(ContentStreamRepository::class);
        \Neos\Utility\ObjectAccess::setProperty($contentStreamRepository, 'contentStreams', [], true);
    }

    /**
     * @BeforeScenario
     * @return void
     * @throws \Exception
     */
    public function beforeEventSourcedScenarioDispatcher()
    {
        $this->contentGraph->enableCache();
        $this->visibilityConstraints = VisibilityConstraints::frontend();
    }

    /**
     * @Given /^the event RootWorkspaceWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventRootWorkspaceWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['currentContentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($contentStreamIdentifier);
        $this->publishEvent('Neos.EventSourcedContentRepository:RootWorkspaceWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event RootNodeAggregateWithNodeWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventRootNodeAggregateWithNodeWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['nodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier($contentStreamIdentifier, $nodeAggregateIdentifier);

        $this->publishEvent('Neos.EventSourcedContentRepository:RootNodeAggregateWithNodeWasCreated', $streamName->getEventStreamName(), $eventPayload);
        $this->rootNodeAggregateIdentifier = $nodeAggregateIdentifier;
    }

    /**
     * @Given /^the event NodeAggregateWithNodeWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeAggregateWithNodeWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (!isset($eventPayload['initialPropertyValues'])) {
            $eventPayload['initialPropertyValues'] = [];
        }
        if (!isset($eventPayload['originDimensionSpacePoint'])) {
            $eventPayload['originDimensionSpacePoint'] = [];
        }
        if (!isset($eventPayload['visibleInDimensionSpacePoints'])) {
            $eventPayload['visibleInDimensionSpacePoints'] = [[]];
        }
        if (!isset($eventPayload['nodeName'])) {
            $eventPayload['nodeName'] = null;
        }

        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['nodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier($contentStreamIdentifier, $nodeAggregateIdentifier);

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeAggregateWithNodeWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodeSpecializationWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeSpecializationWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['nodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier(
            $contentStreamIdentifier,
            $nodeAggregateIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeSpecializationWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodeGeneralizationVariantWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeGeneralizationVariantWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['nodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier(
            $contentStreamIdentifier,
            $nodeAggregateIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeGeneralizationVariantWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodeSpecializationVariantWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeSpecializationVariantWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['nodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier(
            $contentStreamIdentifier,
            $nodeAggregateIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeSpecializationVariantWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodePeerVariantWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodePeerVariantWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['nodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier(
            $contentStreamIdentifier,
            $nodeAggregateIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodePeerVariantWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodePropertyWasSet was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodePropertyWasSetWasPublishedWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['nodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier(
            $contentStreamIdentifier,
            $nodeAggregateIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodePropertyWasSet', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodeReferencesWereSet was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeReferencesWereSetWasPublishedWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($eventPayload['sourceNodeAggregateIdentifier']);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier(
            $contentStreamIdentifier,
            $nodeAggregateIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeReferencesWereSet', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the Event "([^"]*)" was published to stream "([^"]*)" with payload:$/
     * @param $eventType
     * @param $streamName
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventWasPublishedToStreamWithPayload(string $eventType, string $streamName, TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $this->publishEvent($eventType, StreamName::fromString($streamName), $eventPayload);
    }

    /**
     * @param $eventType
     * @param StreamName $streamName
     * @param $eventPayload
     */
    protected function publishEvent($eventType, StreamName $streamName, $eventPayload)
    {
        $event = $this->eventNormalizer->denormalize($eventPayload, $eventType);
        $event = EventWithIdentifier::create($event);
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $events = DomainEvents::withSingleEvent($event);
        $eventStore->commit($streamName, $events);
        $this->lastCommandOrEventResult = CommandResult::fromPublishedEvents($events);
    }

    /**
     * @param TableNode $payloadTable
     * @return array
     * @throws Exception
     */
    protected function readPayloadTable(TableNode $payloadTable): array
    {
        $eventPayload = [];
        foreach ($payloadTable->getHash() as $line) {
            if (strpos($line['Value'], '$this->') === 0) {
                // Special case: Referencing stuff from the context here
                $propertyName = substr($line['Value'], strlen('$this->'));
                $value = (string) $this->$propertyName;
            } else {
                // default case
                $value = json_decode($line['Value'], true);
                if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception(sprintf('The value "%s" is no valid JSON string', $line['Value']), 1546522626);
                }
            }
            $eventPayload[$line['Key']] = $value;
        }

        return $eventPayload;
    }

    /**
     * @When /^the command CreateRootWorkspace is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandCreateRootWorkspaceIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['workspaceTitle'])) {
            $commandArguments['workspaceTitle'] = ucfirst($commandArguments['workspaceName']);
        }
        if (!isset($commandArguments['workspaceDescription'])) {
            $commandArguments['workspaceDescription'] = 'The workspace "' . $commandArguments['workspaceName'] . '"';
        }
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        if (!isset($commandArguments['workspaceOwner'])) {
            $commandArguments['workspaceOwner'] = 'workspace-owner';
        }
        $command = CreateRootWorkspace::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getWorkspaceCommandHandler()
            ->handleCreateRootWorkspace($command);
    }

    /**
     * @When /^the command CreateWorkspace is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandCreateWorkspaceIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        if (!isset($commandArguments['workspaceTitle'])) {
            $commandArguments['workspaceTitle'] = ucfirst($commandArguments['workspaceName']);
        }
        if (!isset($commandArguments['workspaceDescription'])) {
            $commandArguments['workspaceDescription'] = 'The workspace "' . $commandArguments['workspaceName'] . '"';
        }
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        if (!isset($commandArguments['workspaceOwner'])) {
            $commandArguments['workspaceOwner'] = 'owner-identifier';
        }

        $this->theCommandIsExecutedWithPayload('CreateWorkspace', null, $commandArguments);
    }

    /**
     * @When /^the command CreateRootNodeAggregateWithNode is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws ContentStreamDoesNotExistYet
     * @throws Exception
     */
    public function theCommandCreateRootNodeAggregateWithNodeIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $command = CreateRootNodeAggregateWithNode::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getNodeAggregateCommandHandler()
            ->handleCreateRootNodeAggregateWithNode($command);
        $this->rootNodeAggregateIdentifier = $command->getNodeAggregateIdentifier();
    }

    /**
     * @When /^the command CreateRootNodeAggregateWithNode is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     */
    public function theCommandCreateRootNodeAggregateWithNodeIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandCreateRootNodeAggregateWithNodeIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @When /^the command CreateNodeAggregateWithNode is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function theCommandCreateNodeAggregateWithNodeIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        if (!isset($commandArguments['originDimensionSpacePoint'])) {
            $commandArguments['originDimensionSpacePoint'] = [];
        }
        $command = CreateNodeAggregateWithNode::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getNodeAggregateCommandHandler()
            ->handleCreateNodeAggregateWithNode($command);
    }

    /**
     * @When /^the command CreateNodeAggregateWithNode is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     */
    public function theCommandCreateNodeAggregateWithNodeIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandCreateNodeAggregateWithNodeIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @Given /^the command CreateNodeVariant is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws \Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\DimensionSpacePointIsAlreadyOccupied
     * @throws \Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\DimensionSpacePointIsNotYetOccupied
     * @throws \Neos\EventSourcedContentRepository\Exception\DimensionSpacePointNotFound
     * @throws Exception
     */
    public function theCommandCreateNodeVariantIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $command = CreateNodeVariant::fromArray($commandArguments);
        $this->lastCommandOrEventResult = $this->getNodeAggregateCommandHandler()
            ->handleCreateNodeVariant($command);
    }

    /**
     * @Given /^the command CreateNodeVariant is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandCreateNodeVariantIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandCreateNodeVariantIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @Given /^the command SetNodeReferences is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandSetNodeReferencesIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['sourceOriginDimensionSpacePoint'])) {
            $commandArguments['sourceOriginDimensionSpacePoint'] = [];
        }
        $command = SetNodeReferences::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getNodeCommandHandler()
            ->handleSetNodeReferences($command);
    }

    /**
     * @Given /^the command SetNodeReferences is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandSetNodeReferencesIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandSetNodeReferencesIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }
    /**
     * @Given /^the command RemoveNodeAggregate was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandRemoveNodeAggregateIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $command = RemoveNodeAggregate::fromArray($commandArguments);
        /** @var NodeCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeCommandHandler::class);

        $this->lastCommandOrEventResult = $commandHandler->handleRemoveNodeAggregate($command);
    }

    /**
     * @Given /^the command RemoveNodeAggregate was published with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandRemoveNodeAggregateIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandRemoveNodeAggregateIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @Given /^the command RemoveNodesFromAggregate was published with payload:$/
     * @param TableNode $payloadTable
     * @throws SpecializedDimensionsMustBePartOfDimensionSpacePointSet
     * @throws Exception
     */
    public function theCommandRemoveNodesFromAggregateIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $command = RemoveNodesFromAggregate::fromArray($commandArguments);
        /** @var NodeCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeCommandHandler::class);

        $this->lastCommandOrEventResult = $commandHandler->handleRemoveNodesFromAggregate($command);
    }

    /**
     * @Given /^the command RemoveNodesFromAggregate was published with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandRemoveNodesFromAggregateIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandRemoveNodesFromAggregateIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @Given /^the command ChangeNodeAggregateType was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandChangeNodeAggregateTypeIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $command = ChangeNodeAggregateType::fromArray($commandArguments);

        /** @var NodeAggregateCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);

        $commandHandler->handleChangeNodeAggregateType($command);
    }

    /**
     * @Given /^the command ChangeNodeAggregateType was published with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandChangeNodeAggregateTypeIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandChangeNodeAggregateTypeIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @Given /^the command MoveNode is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandMoveNodeIsExecutedWithPayload(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $command = MoveNode::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getNodeAggregateCommandHandler()
            ->handleMoveNode($command);
    }

    /**
     * @Given /^the command MoveNode is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandMoveNodeIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable): void
    {
        try {
            $this->theCommandMoveNodeIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @When /^the command "([^"]*)" is executed with payload:$/
     * @Given /^the command "([^"]*)" was executed with payload:$/
     * @param string $shortCommandName
     * @param TableNode|null $payloadTable
     * @param null $commandArguments
     * @throws Exception
     */
    public function theCommandIsExecutedWithPayload(string $shortCommandName, TableNode $payloadTable = null, $commandArguments = null)
    {
        list($commandClassName, $commandHandlerClassName, $commandHandlerMethod) = self::resolveShortCommandName($shortCommandName);
        if ($commandArguments === null && $payloadTable !== null) {
            $commandArguments = $this->readPayloadTable($payloadTable);
        }

        if (!method_exists($commandClassName, 'fromArray')) {
            throw new \InvalidArgumentException(sprintf('Command "%s" does not implement a static "fromArray" constructor', $commandClassName), 1545564621);
        }
        $command = $commandClassName::fromArray($commandArguments);

        $commandHandler = $this->getObjectManager()->get($commandHandlerClassName);

        $this->lastCommandOrEventResult = $commandHandler->$commandHandlerMethod($command);

        // @todo check whether this is necessary at all
        if (isset($commandArguments['rootNodeAggregateIdentifier'])) {
            $this->rootNodeAggregateIdentifier = NodeAggregateIdentifier::fromString($commandArguments['rootNodeAggregateIdentifier']);
        }
    }

    /**
     * @When /^the command "([^"]*)" is executed with payload and exceptions are caught:$/
     */
    public function theCommandIsExecutedWithPayloadAndExceptionsAreCaught($shortCommandName, TableNode $payloadTable)
    {
        try {
            $this->theCommandIsExecutedWithPayload($shortCommandName, $payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @Then /^the last command should have thrown an exception of type "([^"]*)"$/
     * @param string $shortExceptionName
     * @throws ReflectionException
     */
    public function theLastCommandShouldHaveThrown(string $shortExceptionName)
    {
        Assert::assertNotNull($this->lastCommandException, 'Command did not throw exception');
        $lastCommandExceptionShortName = (new \ReflectionClass($this->lastCommandException))->getShortName();
        Assert::assertSame($shortExceptionName, $lastCommandExceptionShortName, sprintf('Actual exception: %s (%s): %s', get_class($this->lastCommandException), $this->lastCommandException->getCode(), $this->lastCommandException->getMessage()));
    }

    /**
     * @param $shortCommandName
     * @return array
     * @throws Exception
     */
    protected static function resolveShortCommandName($shortCommandName)
    {
        switch ($shortCommandName) {
            case 'CreateRootWorkspace':
                return [
                    CreateRootWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handleCreateRootWorkspace'
                ];
            case 'CreateWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateWorkspace::class,
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler::class,
                    'handleCreateWorkspace'
                ];
            case 'PublishWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\PublishWorkspace::class,
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler::class,
                    'handlePublishWorkspace'
                ];
            case 'PublishIndividualNodesFromWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\PublishIndividualNodesFromWorkspace::class,
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler::class,
                    'handlePublishIndividualNodesFromWorkspace'
                ];
            case 'RebaseWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\RebaseWorkspace::class,
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler::class,
                    'handleRebaseWorkspace'
                ];
            case 'CreateNodeAggregateWithNode':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode::class,
                    NodeAggregateCommandHandler::class,
                    'handleCreateNodeAggregateWithNode'
                ];
            case 'ForkContentStream':
                return [
                    ForkContentStream::class,
                    ContentStreamCommandHandler::class,
                    'handleForkContentStream'
                ];
            case 'ChangeNodeAggregateName':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\ChangeNodeAggregateName::class,
                    NodeAggregateCommandHandler::class,
                    'handleChangeNodeAggregateName'
                ];
            case 'SetNodeProperty':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\SetNodeProperty::class,
                    NodeCommandHandler::class,
                    'handleSetNodeProperty'
                ];
            case 'HideNode':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\HideNode::class,
                    NodeCommandHandler::class,
                    'handleHideNode'
                ];
            case 'ShowNode':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\ShowNode::class,
                    NodeCommandHandler::class,
                    'handleShowNode'
                ];
            case 'MoveNode':
                return [
                    MoveNode::class,
                    NodeCommandHandler::class,
                    'handleMoveNode'
                ];
            case 'SetNodeReferences':
                return [
                    SetNodeReferences::class,
                    NodeCommandHandler::class,
                    'handleSetNodeReferences'
                ];

            default:
                throw new \Exception('The short command name "' . $shortCommandName . '" is currently not supported by the tests.');
        }
    }

    /**
     * @Then /^I expect exactly (\d+) events? to be published on stream "([^"]*)"$/
     * @param int $numberOfEvents
     * @param string $streamName
     */
    public function iExpectExactlyEventToBePublishedOnStream(int $numberOfEvents, string $streamName)
    {
        $streamName = StreamName::fromString($streamName);
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $stream = $eventStore->load($streamName);
        $this->currentEventStreamAsArray = iterator_to_array($stream, false);
        Assert::assertEquals($numberOfEvents, count($this->currentEventStreamAsArray), 'Number of events did not match');
    }

    /**
     * @Then /^I expect exactly (\d+) events? to be published on stream with prefix "([^"]*)"$/
     * @param int $numberOfEvents
     * @param string $streamName
     */
    public function iExpectExactlyEventToBePublishedOnStreamWithPrefix(int $numberOfEvents, string $streamName)
    {
        $streamName = StreamName::forCategory($streamName);

        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $stream = $eventStore->load($streamName);
        $this->currentEventStreamAsArray = iterator_to_array($stream, false);
        Assert::assertEquals($numberOfEvents, count($this->currentEventStreamAsArray), 'Number of events did not match');
    }

    /**
     * @Then /^event at index (\d+) is of type "([^"]*)" with payload:/
     * @param int $eventNumber
     * @param string $eventType
     * @param TableNode $payloadTable
     */
    public function eventNumberIs(int $eventNumber, string $eventType, TableNode $payloadTable)
    {
        if ($this->currentEventStreamAsArray === null) {
            Assert::fail('Step \'I expect exactly ? events to be published on stream "?"\' was not executed');
        }

        Assert::assertArrayHasKey($eventNumber, $this->currentEventStreamAsArray, 'Event at index does not exist');

        /* @var $actualEvent EventEnvelope */
        $actualEvent = $this->currentEventStreamAsArray[$eventNumber];

        Assert::assertNotNull($actualEvent, sprintf('Event with number %d not found', $eventNumber));
        Assert::assertEquals($eventType, $actualEvent->getRawEvent()->getType(), 'Event Type does not match: "' . $actualEvent->getRawEvent()->getType() . '" !== "' . $eventType . '"');

        $actualEventPayload = $actualEvent->getRawEvent()->getPayload();

        foreach ($payloadTable->getHash() as $assertionTableRow) {
            $actualValue = Arrays::getValueByPath($actualEventPayload, $assertionTableRow['Key']);
            Assert::assertJsonStringEqualsJsonString($assertionTableRow['Expected'], json_encode($actualValue));
        }
    }


    /**
     * @When /^the graph projection is fully up to date$/
     */
    public function theGraphProjectionIsFullyUpToDate()
    {
        if ($this->lastCommandOrEventResult === null) {
            throw new \RuntimeException('lastCommandOrEventResult not filled; so I cannot block!');
        }
        $this->lastCommandOrEventResult->blockUntilProjectionsAreUpToDate();
        $this->lastCommandOrEventResult = null;
    }

    /**
     * @Given /^I am in content stream "([^"]*)"$/
     * @param string $contentStreamIdentifier
     */
    public function iAmInContentStream(string $contentStreamIdentifier): void
    {
        $this->contentStreamIdentifier = ContentStreamIdentifier::fromString($contentStreamIdentifier);
    }

    /**
     * @Given /^I am in the active content stream of workspace "([^"]*)" and Dimension Space Point (.*)$/
     * @param string $workspaceName
     * @param string $dimensionSpacePoint
     * @throws Exception
     */
    public function iAmInTheActiveContentStreamOfWorkspaceAndDimensionSpacePoint(string $workspaceName, string $dimensionSpacePoint)
    {
        $workspaceName = new WorkspaceName($workspaceName);
        $workspace = $this->workspaceFinder->findOneByName($workspaceName);
        if ($workspace === null) {
            throw new \Exception(sprintf('Workspace "%s" does not exist, projection not yet up to date?', $workspaceName), 1548149355);
        }
        $this->contentStreamIdentifier = $workspace->getCurrentContentStreamIdentifier();
        $this->dimensionSpacePoint = DimensionSpacePoint::fromJsonString($dimensionSpacePoint);
    }

    /**
     * @Given /^I am in content stream "([^"]*)" and Dimension Space Point (.*)$/
     * @param string $contentStreamIdentifier
     * @param string $dimensionSpacePoint
     */
    public function iAmInContentStreamAndDimensionSpacePoint(string $contentStreamIdentifier, string $dimensionSpacePoint)
    {
        $this->contentStreamIdentifier = ContentStreamIdentifier::fromString($contentStreamIdentifier);
        $this->dimensionSpacePoint = new DimensionSpacePoint(json_decode($dimensionSpacePoint, true));
    }

    /**
     * @Then /^workspace "([^"]*)" points to another content stream than workspace "([^"]*)"$/
     * @param string $rawWorkspaceNameA
     * @param string $rawWorkspaceNameB
     */
    public function workspacesPointToDifferentContentStreams(string $rawWorkspaceNameA, string $rawWorkspaceNameB)
    {
        $workspaceA = $this->workspaceFinder->findOneByName(new WorkspaceName($rawWorkspaceNameA));
        Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Domain\Projection\Workspace\Workspace::class, $workspaceA, 'Workspace "' . $rawWorkspaceNameA . '" does not exist.');
        $workspaceB = $this->workspaceFinder->findOneByName(new WorkspaceName($rawWorkspaceNameB));
        Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Domain\Projection\Workspace\Workspace::class, $workspaceB, 'Workspace "' . $rawWorkspaceNameB . '" does not exist.');
        if ($workspaceA && $workspaceB) {
            Assert::assertNotEquals(
                $workspaceA->getCurrentContentStreamIdentifier(),
                $workspaceB->getCurrentContentStreamIdentifier(),
                'Workspace "' . $rawWorkspaceNameA . '" points to the same content stream as "' . $rawWorkspaceNameB . '"');
        }
    }

    /**
     * @Then /^workspace "([^"]*)" does not point to content stream "([^"]*)"$/
     * @param string $rawWorkspaceName
     * @param string $rawContentStreamIdentifier
     */
    public function workspaceDoesNotPointToContentStream(string $rawWorkspaceName, string $rawContentStreamIdentifier)
    {
        $workspace = $this->workspaceFinder->findOneByName(new WorkspaceName($rawWorkspaceName));

        Assert::assertNotEquals($rawContentStreamIdentifier, (string)$workspace->getCurrentContentStreamIdentifier());
    }

    /**
     * @Then /^I expect the node aggregate "([^"]*)" to exist$/
     * @param string $nodeAggregateIdentifier
     * @throws NodeAggregatesTypeIsAmbiguous
     */
    public function iExpectTheNodeAggregateToExist(string $nodeAggregateIdentifier): void
    {
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($nodeAggregateIdentifier);
        $this->currentNodeAggregate = $this->contentGraph->findNodeAggregateByIdentifier($this->contentStreamIdentifier, $nodeAggregateIdentifier);

        Assert::assertNotNull($this->currentNodeAggregate, sprintf('Node aggregate "%s" was not found in the current content stream "%s".', $nodeAggregateIdentifier, $this->contentStreamIdentifier));
    }

    /**
     * @Then /^I expect this node aggregate to occupy dimension space points (.*)$/
     * @param string $rawDimensionSpacePoints
     */
    public function iExpectThisNodeAggregateToOccupyDimensionSpacePoints(string $rawDimensionSpacePoints): void
    {
        $dimensionSpacePoints = [];
        foreach (json_decode($rawDimensionSpacePoints, true) as $coordinates) {
            $dimensionSpacePoints[] = new DimensionSpacePoint($coordinates);
        }
        $expectedDimensionSpacePoints = new DimensionSpacePointSet($dimensionSpacePoints);

        Assert::assertEquals($this->currentNodeAggregate->getOccupiedDimensionSpacePoints(), $expectedDimensionSpacePoints);
    }

    /**
     * @Then /^I expect this node aggregate to cover dimension space points (.*)$/
     * @param string $rawDimensionSpacePoints
     */
    public function iExpectThisNodeAggregateToCoverDimensionSpacePoints(string $rawDimensionSpacePoints): void
    {
        $dimensionSpacePoints = [];
        foreach (json_decode($rawDimensionSpacePoints, true) as $coordinates) {
            $dimensionSpacePoints[] = new DimensionSpacePoint($coordinates);
        }
        $expectedDimensionSpacePoints = new DimensionSpacePointSet($dimensionSpacePoints);

        Assert::assertEquals(
            $expectedDimensionSpacePoints,
            $this->currentNodeAggregate->getCoveredDimensionSpacePoints(),
            'Expected covered dimension space point set ' . json_encode($expectedDimensionSpacePoints)
            . ', got ' . json_encode($this->currentNodeAggregate->getCoveredDimensionSpacePoints())
        );
    }
    /**
     * @Then /^I expect this node aggregate to be classified as "([^"]*)"$/
     * @param string $expectedClassification
     */
    public function iExpectThisNodeAggregateToBeClassifiedAs(string $expectedClassification): void
    {
        Assert::assertEquals($expectedClassification, $this->currentNodeAggregate->getClassification());
    }

    /**
     * @Then /^I expect a node with identifier (.*) to exist in the content graph$/
     * @param string $serializedNodeIdentifier
     * @throws Exception
     */
    public function iExpectANodeWithIdentifierToExistInTheContentGraph(string $serializedNodeIdentifier)
    {
        $nodeIdentifier = NodeDiscriminator::fromArray(json_decode($serializedNodeIdentifier, true));
        $this->currentNode = $this->contentGraph->findNodeByIdentifiers(
            $nodeIdentifier->getContentStreamIdentifier(),
            $nodeIdentifier->getNodeAggregateIdentifier(),
            $nodeIdentifier->getOriginDimensionSpacePoint()
        );
        Assert::assertNotNull($this->currentNode, 'Node with aggregate identifier "' . $nodeIdentifier->getNodeAggregateIdentifier()
            . '" and originating in dimension space point "' . $nodeIdentifier->getOriginDimensionSpacePoint()
            . '" was not found in content stream "' . $nodeIdentifier->getContentStreamIdentifier() . '"'
        );
    }

    /**
     * @Then /^I expect the graph projection to consist of exactly (\d+) nodes$/
     * @param int $expectedNumberOfNodes
     */
    public function iExpectTheGraphProjectionToConsistOfExactlyNodes(int $expectedNumberOfNodes)
    {
        $actualNumberOfNodes = $this->contentGraph->countNodes();
        Assert::assertSame($expectedNumberOfNodes, $actualNumberOfNodes, 'Content graph consists of ' . $actualNumberOfNodes . ' nodes, expected were ' . $expectedNumberOfNodes . '.');
    }

    /**
     * @Then /^I expect the subgraph projection to consist of exactly (\d+) nodes$/
     * @param int $expectedNumberOfNodes
     */
    public function iExpectTheSubgraphProjectionToConsistOfExactlyNodes(int $expectedNumberOfNodes)
    {
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $actualNumberOfNodes = $subgraph->countNodes();
        Assert::assertSame($expectedNumberOfNodes, $actualNumberOfNodes, 'Content subgraph consists of ' . $actualNumberOfNodes . ' nodes, expected were ' . $expectedNumberOfNodes . '.');
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" to lead to node (.*)$/
     * @param string $serializedNodeAggregateIdentifier
     * @param string $serializedNodeIdentifier
     */
    public function iExpectNodeAggregateIdentifierToLeadToNode(string $serializedNodeAggregateIdentifier, string $serializedNodeIdentifier): void
    {
        $expectedNodeIdentifier = NodeDiscriminator::fromArray(json_decode($serializedNodeIdentifier, true));
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $nodeByAggregateIdentifier = $subgraph->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($serializedNodeAggregateIdentifier));
        $this->currentNode = $nodeByAggregateIdentifier;
        Assert::assertInstanceOf(NodeInterface::class, $nodeByAggregateIdentifier, 'No node could be found by node aggregate identifier "' . $serializedNodeAggregateIdentifier . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '"');
        Assert::assertEquals($expectedNodeIdentifier, NodeDiscriminator::fromNode($nodeByAggregateIdentifier), 'Node discriminators did not match');
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" to lead to no node$/
     * @param string $serializedNodeAggregateIdentifier
     */
    public function iExpectNodeAggregateIdentifierToLeadToNoNode(string $serializedNodeAggregateIdentifier): void
    {
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $nodeByAggregateIdentifier = $subgraph->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($serializedNodeAggregateIdentifier));
        Assert::assertNull($nodeByAggregateIdentifier, 'A node was found by node aggregate identifier "' . $serializedNodeAggregateIdentifier . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '"');
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" and path "([^"]*)" to lead to node (.*)$/
     * @param string $serializedNodeAggregateIdentifier
     * @param string $serializedNodePath
     * @param string $serializedNodeIdentifier
     */
    public function iExpectNodeAggregateIdentifierAndPathToLeadToNode(string $serializedNodeAggregateIdentifier, string $serializedNodePath, string $serializedNodeIdentifier): void
    {
        $expectedNodeIdentifier = NodeDiscriminator::fromArray(json_decode($serializedNodeIdentifier, true));
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $this->iExpectNodeAggregateIdentifierToLeadToNode($serializedNodeAggregateIdentifier, $serializedNodeIdentifier);

        $nodeByPath = $subgraph->findNodeByPath($serializedNodePath, $this->rootNodeAggregateIdentifier);
        Assert::assertInstanceOf(NodeInterface::class, $nodeByPath, 'No node could be found by path "' . $serializedNodePath . '"" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '"');
        Assert::assertEquals($expectedNodeIdentifier, NodeDiscriminator::fromNode($nodeByPath), 'Node discriminators did not match');
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" and path "([^"]*)" to lead to no node$/
     * @param string $serializedNodeAggregateIdentifier
     * @param string $serializedNodePath
     */
    public function iExpectNodeAggregateIdentifierAndPathToLeadToNoNode(string $serializedNodeAggregateIdentifier, string $serializedNodePath): void
    {
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $this->iExpectNodeAggregateIdentifierToLeadToNoNode($serializedNodeAggregateIdentifier);

        $nodeByPath = $subgraph->findNodeByPath($serializedNodePath, $this->rootNodeAggregateIdentifier);
        Assert::assertNull($nodeByPath, 'A node was found by path "' . $serializedNodePath . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '"');
    }

    /**
     * @Then /^I expect this node to be a child of node (.*)$/
     * @param string $serializedNodeDiscriminator
     */
    public function iExpectThisNodeToBeTheChildOfNode(string $serializedNodeDiscriminator): void
    {
        $expectedNodeDiscriminator = NodeDiscriminator::fromArray(json_decode($serializedNodeDiscriminator, true));
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $parent = $subgraph->findParentNode($this->currentNode->getNodeAggregateIdentifier());
        Assert::assertInstanceOf(NodeInterface::class, $parent, 'Parent not found.');
        Assert::assertEquals($expectedNodeDiscriminator, NodeDiscriminator::fromNode($parent), 'Parent discriminator does not match.');

        $child = $subgraph->findChildNodeConnectedThroughEdgeName($parent->getNodeAggregateIdentifier(), $this->currentNode->getNodeName());
        Assert::assertEquals($this->currentNode, $child, 'Child discriminator does not match.');
    }

    /**
     * @Then /^I expect a node identified by aggregate identifier "([^"]*)" to exist in the subgraph$/
     * @param string $rawNodeAggregateIdentifier
     * @throws Exception
     * @deprecated use iExpectNodeAggregateIdentifierAndPathToLeadToNode
     */
    public function iExpectANodeIdentifiedByAggregateIdentifierToExistInTheSubgraph(string $rawNodeAggregateIdentifier)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($rawNodeAggregateIdentifier));
        Assert::assertNotNull($this->currentNode, 'Node with aggregate identifier "' . $rawNodeAggregateIdentifier . '" was not found in the subgraph with dimension space point "' . $this->dimensionSpacePoint . '" in content stream "' . $this->contentStreamIdentifier . '".');
    }

    /**
     * @Then /^I expect a node identified by aggregate identifier "([^"]*)" not to exist in the subgraph$/
     * @param string $nodeAggregateIdentifier
     * @deprecated use iExpectNodeAggregateIdentifierAndPathToLeadToNoNode
     */
    public function iExpectANodeIdentifiedByAggregateIdentifierNotToExistInTheSubgraph(string $nodeAggregateIdentifier)
    {
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier));
        Assert::assertTrue($node === null, 'Node with aggregate identifier "' . $nodeAggregateIdentifier . '" was found in the current Content Stream / Dimension Space Point, but it SHOULD NOT BE FOUND.');
    }

    /**
     * @Then /^I expect the node aggregate "([^"]*)" to have the following child nodes:$/
     * @param string $rawNodeAggregateIdentifier
     * @param TableNode $expectedChildNodesTable
     */
    public function iExpectTheNodeToHaveTheFollowingChildNodes(string $rawNodeAggregateIdentifier, TableNode $expectedChildNodesTable)
    {
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($rawNodeAggregateIdentifier);
        $subgraph = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);
        $nodes = $subgraph
            ->findChildNodes($nodeAggregateIdentifier);

        $numberOfChildNodes = $subgraph
            ->countChildNodes($nodeAggregateIdentifier);

        Assert::assertEquals(count($expectedChildNodesTable->getHash()), $numberOfChildNodes, 'ContentSubgraph::countChildNodes returned a wrong value');
        Assert::assertCount(count($expectedChildNodesTable->getHash()), $nodes, 'ContentSubgraph::findChildNodes: Child Node Count does not match');
        foreach ($expectedChildNodesTable->getHash() as $index => $row) {
            Assert::assertEquals($row['Name'], (string)$nodes[$index]->getNodeName(), 'ContentSubgraph::findChildNodes: Node name in index ' . $index . ' does not match. Actual: ' . $nodes[$index]->getNodeName());
            Assert::assertEquals($row['NodeAggregateIdentifier'], (string)$nodes[$index]->getNodeAggregateIdentifier(), 'ContentSubgraph::findChildNodes: Node identifier in index ' . $index . ' does not match. Actual: ' . $nodes[$index]->getNodeAggregateIdentifier() . ' Expected: ' . $row['NodeAggregateIdentifier']);
        }
    }

    /**
     * @Then /^I expect the node "([^"]*)" to have the type "([^"]*)"$/
     * @param string $nodeAggregateIdentifier
     * @param string $nodeType
     */
    public function iExpectTheNodeToHaveTheType(string $nodeAggregateIdentifier, string $nodeType)
    {
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier));
        Assert::assertEquals($nodeType, (string)$node->getNodeTypeName(), 'Node Type names do not match');
    }

    /**
     * @Then /^I expect this node to have the properties:$/
     * @param TableNode $expectedProperties
     */
    public function iExpectThisNodeToHaveTheProperties(TableNode $expectedProperties)
    {
        $this->iExpectTheCurrentNodeToHaveTheProperties($expectedProperties);
    }

    /**
     * @Then /^I expect the node "([^"]*)" to have the properties:$/
     * @param string $nodeAggregateIdentifier
     * @param TableNode $expectedProperties
     */
    public function iExpectTheNodeToHaveTheProperties(string $nodeAggregateIdentifier, TableNode $expectedProperties)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier));
        $this->iExpectTheCurrentNodeToHaveTheProperties($expectedProperties);
    }

    /**
     * @Then /^I expect the Node Aggregate "([^"]*)" to have the properties:$/
     * @param $nodeAggregateIdentifier
     * @param TableNode $expectedProperties
     */
    public function iExpectTheNodeAggregateToHaveTheProperties($nodeAggregateIdentifier, TableNode $expectedProperties)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier));
        $this->iExpectTheCurrentNodeToHaveTheProperties($expectedProperties);
    }

    /**
     * @Then /^I expect the current Node to have the properties:$/
     * @param TableNode $expectedProperties
     */
    public function iExpectTheCurrentNodeToHaveTheProperties(TableNode $expectedProperties)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier($this->currentNode->getNodeAggregateIdentifier());

        $properties = $this->currentNode->getProperties();
        foreach ($expectedProperties->getHash() as $row) {
            Assert::assertArrayHasKey($row['Key'], $properties, 'Property "' . $row['Key'] . '" not found');
            $actualProperty = $properties[$row['Key']];
            Assert::assertEquals($row['Value'], $actualProperty, 'Node property ' . $row['Key'] . ' does not match. Expected: ' . $row['Value'] . '; Actual: ' . $actualProperty);
        }
    }

    /**
     * @Then /^I expect the node aggregate "([^"]*)" to have the references:$/
     * @param string $nodeAggregateIdentifier
     * @param TableNode $expectedReferences
     * @throws Exception
     */
    public function iExpectTheNodeToHaveTheReferences(string $nodeAggregateIdentifier, TableNode $expectedReferences)
    {
        $expectedReferences = $this->readPayloadTable($expectedReferences);

        /** @var \Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface $subgraph */
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        foreach ($expectedReferences as $propertyName => $expectedDestinationNodeAggregateIdentifiers) {
            $destinationNodes = $subgraph->findReferencedNodes(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier), PropertyName::fromString($propertyName));
            $destinationNodeAggregateIdentifiers = array_map(
                function ($item) {
                    if ($item instanceof NodeInterface) {
                        return (string)$item->getNodeAggregateIdentifier();
                    } else {
                        return $item;
                    }
                },
                $destinationNodes
            );
            Assert::assertEquals($expectedDestinationNodeAggregateIdentifiers, $destinationNodeAggregateIdentifiers, 'Node references ' . $propertyName . ' does not match. Expected: ' . json_encode($expectedDestinationNodeAggregateIdentifiers) . '; Actual: ' . json_encode($destinationNodeAggregateIdentifiers));
        }
    }

    /**
     * @Then /^I expect the node aggregate "([^"]*)" to be referenced by:$/
     * @param string $nodeAggregateIdentifier
     * @param TableNode $expectedReferences
     * @throws Exception
     */
    public function iExpectTheNodeToBeReferencedBy(string $nodeAggregateIdentifier, TableNode $expectedReferences)
    {
        $expectedReferences = $this->readPayloadTable($expectedReferences);

        /** @var \Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface $subgraph */
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        foreach ($expectedReferences as $propertyName => $expectedDestinationNodeAggregateIdentifiers) {
            $destinationNodes = $subgraph->findReferencingNodes(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier), PropertyName::fromString($propertyName));
            $destinationNodeAggregateIdentifiers = array_map(
                function ($item) {
                    if ($item instanceof NodeInterface) {
                        return (string)$item->getNodeAggregateIdentifier();
                    } else {
                        return $item;
                    }
                },
                $destinationNodes
            );

            // since the order on the target side is not defined we sort
            // expectation and result before comparison
            sort($expectedDestinationNodeAggregateIdentifiers);
            sort($destinationNodeAggregateIdentifiers);
            Assert::assertEquals($expectedDestinationNodeAggregateIdentifiers, $destinationNodeAggregateIdentifiers, 'Node references ' . $propertyName . ' does not match. Expected: ' . json_encode($expectedDestinationNodeAggregateIdentifiers) . '; Actual: ' . json_encode($destinationNodeAggregateIdentifiers));
        }
    }

    /**
     * @Then /^I expect the path "([^"]*)" to lead to the node ([^"]*)$/
     * @param string $nodePath
     * @param string $serializedNodeIdentifier
     * @throws Exception
     */
    public function iExpectThePathToLeadToTheNode(string $nodePath, string $serializedNodeIdentifier)
    {
        if (!$this->rootNodeAggregateIdentifier) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $expectedIdentifier = NodeDiscriminator::fromArray(json_decode($serializedNodeIdentifier, true));
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath($nodePath, $this->rootNodeAggregateIdentifier);
        Assert::assertNotNull($this->currentNode, 'Did not find node at path "' . $nodePath . '"');
        Assert::assertEquals($expectedIdentifier, NodeDiscriminator::fromNode($this->currentNode), 'Node discriminators do not match.');
    }

    /**
     * @When /^I go to the parent node of node aggregate "([^"]*)"$/
     * @param string $nodeAggregateIdentifier
     */
    public function iGoToTheParentNodeOfNode(string $nodeAggregateIdentifier)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findParentNode(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier));
    }

    /**
     * @Then /^I do not find any node$/
     */
    public function currentNodeIsNull()
    {
        if ($this->currentNode) {
            Assert::fail('Current node was not NULL, but node aggregate: ' . $this->currentNode->getNodeAggregateIdentifier());
        } else {
            Assert::assertTrue(true);
        }
    }

    /**
     * @Then /^I find a node with node aggregate "([^"]*)"$/
     * @param string $nodeAggregateIdentifier
     */
    public function currentNodeAggregateShouldBe(string $nodeAggregateIdentifier)
    {
        Assert::assertEquals($nodeAggregateIdentifier, (string)$this->currentNode->getNodeAggregateIdentifier());
    }

    /**
     * @Then /^I expect the path "([^"]*)" to lead to no node$/
     * @param string $nodePath
     * @throws Exception
     */
    public function iExpectThePathToLeadToNoNode(string $nodePath)
    {
        if (!$this->rootNodeAggregateIdentifier) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath($nodePath, $this->rootNodeAggregateIdentifier);
        Assert::assertNull($node, 'Did find node at path "' . $nodePath . '"');
    }

    /**
     * @When /^VisibilityConstraints are set to "(withoutRestrictions|frontend)"$/
     * @param string $restrictionType
     */
    public function visibilityConstraintsAreSetTo(string $restrictionType)
    {
        switch ($restrictionType) {
            case 'withoutRestrictions':
                $this->visibilityConstraints = VisibilityConstraints::withoutRestrictions();
                break;
            case 'frontend':
                $this->visibilityConstraints = VisibilityConstraints::frontend();
                break;
            default:
                throw new \InvalidArgumentException('Visibility constraint "' . $restrictionType . '" not supported.');
        }
    }


    /**
     * @Then /^the subtree for node aggregate "([^"]*)" with node types "([^"]*)" and (\d+) levels deep should be:$/
     * @param string $nodeAggregateIdentifier
     * @param string $nodeTypeConstraints
     * @param int $maximumLevels
     * @param TableNode $table
     */
    public function theSubtreeForNodeAggregateWithNodeTypesAndLevelsDeepShouldBe(string $nodeAggregateIdentifier, string $nodeTypeConstraints, int $maximumLevels, TableNode $table)
    {
        $expectedRows = $table->getHash();
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($nodeAggregateIdentifier);
        $nodeTypeConstraints = $this->nodeTypeConstraintFactory->parseFilterString($nodeTypeConstraints);

        $subtree = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findSubtrees([$nodeAggregateIdentifier], (int)$maximumLevels, $nodeTypeConstraints);

        /** @var SubtreeInterface[] $flattenedSubtree */
        $flattenedSubtree = [];
        self::flattenSubtreeForComparison($subtree, $flattenedSubtree);

        Assert::assertEquals(count($expectedRows), count($flattenedSubtree), 'number of expected subtrees do not match');

        foreach ($expectedRows as $i => $expectedRow) {
            Assert::assertEquals($expectedRow['Level'], $flattenedSubtree[$i]->getLevel(), 'Level does not match in index ' . $i);
            Assert::assertEquals($expectedRow['NodeAggregateIdentifier'], (string)$flattenedSubtree[$i]->getNode()->getNodeAggregateIdentifier(), 'NodeAggregateIdentifier does not match in index ' . $i . ', expected: ' . $expectedRow['NodeAggregateIdentifier'] . ', actual: ' . $flattenedSubtree[$i]->getNode()->getNodeAggregateIdentifier());
        }
    }

    private static function flattenSubtreeForComparison(SubtreeInterface $subtree, array &$result)
    {
        if ($subtree->getNode()) {
            $result[] = $subtree;
        }
        foreach ($subtree->getChildren() as $childSubtree) {
            self::flattenSubtreeForComparison($childSubtree, $result);
        }
    }

    /**
     * @var NodeAddress[]
     */
    private $currentNodeAddresses;

    /**
     * @param string|null $alias
     * @return NodeAddress
     */
    protected function getCurrentNodeAddress(string $alias = null): NodeAddress
    {
        if ($alias === null) {
            $alias = 'DEFAULT';
        }
        return $this->currentNodeAddresses[$alias];
    }

    /**
     * @return NodeAddress[]
     */
    public function getCurrentNodeAddresses(): array
    {
        return $this->currentNodeAddresses;
    }

    /**
     * @Given /^I get the node address for node aggregate "([^"]*)"(?:, remembering it as "([^"]*)")?$/
     * @param string $rawNodeAggregateIdentifier
     * @param string $alias
     */
    public function iGetTheNodeAddressForNodeAggregate(string $rawNodeAggregateIdentifier, $alias = 'DEFAULT')
    {
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($rawNodeAggregateIdentifier);
        $node = $subgraph->findNodeByNodeAggregateIdentifier($nodeAggregateIdentifier);
        Assert::assertNotNull($node, 'Did not find a node with aggregate identifier "' . $nodeAggregateIdentifier . '"');

        $this->currentNodeAddresses[$alias] = new NodeAddress(
            $this->contentStreamIdentifier,
            $this->dimensionSpacePoint,
            $nodeAggregateIdentifier,
            $this->workspaceFinder->findOneByCurrentContentStreamIdentifier($this->contentStreamIdentifier)->getWorkspaceName()
        );
    }

    /**
     * @Then /^I get the node address for the node at path "([^"]*)"(?:, remembering it as "([^"]*)")?$/
     * @param string $nodePath
     * @param string $alias
     * @throws Exception
     */
    public function iGetTheNodeAddressForTheNodeAtPath(string $nodePath, $alias = 'DEFAULT')
    {
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);
        $node = $subgraph->findNodeByPath($nodePath, $this->rootNodeAggregateIdentifier);
        Assert::assertNotNull($node, 'Did not find a node at path "' . $nodePath . '"');

        $this->currentNodeAddresses[$alias] = new NodeAddress(
            $this->contentStreamIdentifier,
            $this->dimensionSpacePoint,
            $node->getNodeAggregateIdentifier(),
            $this->workspaceFinder->findOneByCurrentContentStreamIdentifier($this->contentStreamIdentifier)->getWorkspaceName()
        );
    }

    /**
     * @Then /^I get the node at path "([^"]*)"$/
     * @param string $nodePath
     * @throws Exception
     */
    public function iGetTheNodeAtPath(string $nodePath)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath($nodePath, $this->rootNodeAggregateIdentifier);
    }

    protected function getWorkspaceCommandHandler(): WorkspaceCommandHandler
    {
        /** @var WorkspaceCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(WorkspaceCommandHandler::class);

        return $commandHandler;
    }

    protected function getNodeAggregateCommandHandler(): NodeAggregateCommandHandler
    {
        /** @var NodeAggregateCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);

        return $commandHandler;
    }

    protected function getNodeCommandHandler(): NodeCommandHandler
    {
        /** @var NodeCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeCommandHandler::class);

        return $commandHandler;
    }
}
