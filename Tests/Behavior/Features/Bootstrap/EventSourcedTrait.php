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
use GuzzleHttp\Psr7\Uri;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository\ContentGraph as DbalContentGraph;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\ContentHypergraph as PostgreSQLContentHypergraph;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\Exception\DimensionSpacePointNotFound;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Exception\NodeException;
use Neos\ContentRepository\Intermediary\Domain\Command\PropertyValuesToWrite;
use Neos\ContentRepository\Intermediary\Tests\Behavior\Fixtures\PostalAddress;
use Neos\ContentRepository\Service\AuthorizationService;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Command\ForkContentStream;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamAlreadyExists;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamDoesNotExistYet;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamRepository;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNodeAndSerializedProperties;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\DisableNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\RemoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeReferences;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\EnableNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetSerializedNodeProperties;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateCurrentlyExists;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregatesTypeIsAmbiguous;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\ChangeNodeAggregateName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeVariant;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\MoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\DimensionSpacePointIsAlreadyOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\DimensionSpacePointIsNotYetOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateIdentifierCollection;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\RelationDistributionStrategy;
use Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\Command\CopyNodesRecursively;
use Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\Command\Dto\NodeSubtreeSnapshot;
use Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\NodeDuplicationCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\DiscardIndividualNodesFromWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\DiscardWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\PublishIndividualNodesFromWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\PublishWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\RebaseWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\BaseWorkspaceDoesNotExist;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\BaseWorkspaceHasBeenModifiedInTheMeantime;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\WorkspaceDoesNotExist;
use Neos\EventSourcedContentRepository\Domain\Projection\ContentStream\ContentStreamFinder;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\Workspace;
use Neos\EventSourcedContentRepository\Domain\CommandResult;
use Neos\EventSourcedContentRepository\Domain\Context\ContentSubgraph\SubtreeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\ChangeNodeAggregateType;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcedContentRepository\Infrastructure\Projection\RuntimeBlocker;
use Neos\EventSourcedContentRepository\Service\ContentStreamPruner;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\ContentGraphs;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventStore\EventEnvelope;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\Arrays;
use Neos\Utility\ObjectAccess;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;

/**
 * Features context
 */
trait EventSourcedTrait
{
    use CurrentSubgraphTrait;
    use ProjectedNodeAggregateTrait;
    use ProjectedNodeTrait;
    use NodeCreation;
    use NodeDisabling;

    /**
     * @var EventTypeResolver
     */
    private $eventTypeResolver;

    /**
     * @var EventStore
     */
    private $eventStore;

    protected ContentGraphs $contentGraphs;

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

    protected ?\Exception $lastCommandException = null;

    protected ?ContentStreamIdentifier $contentStreamIdentifier = null;

    protected ?UserIdentifier $currentUserIdentifier = null;

    protected ?DimensionSpacePoint $dimensionSpacePoint = null;

    /**
     * @var NodeAggregateIdentifier
     */
    protected $rootNodeAggregateIdentifier;

    /**
     * @var EventNormalizer
     */
    protected $eventNormalizer;

    protected ?VisibilityConstraints $visibilityConstraints = null;

    /**
     * @var CommandResult
     */
    protected $lastCommandOrEventResult;

    /**
     * @var RuntimeBlocker
     */
    protected $runtimeBlocker;

    /**
     * @var array|\Neos\EventSourcing\Projection\ProjectorInterface[]
     */
    private array $projectorsToBeReset = [];

    /**
     * @return ObjectManagerInterface
     */
    abstract protected function getObjectManager();

    protected function getWorkspaceFinder(): WorkspaceFinder
    {
        return $this->workspaceFinder;
    }

    protected function getContentGraphs(): ContentGraphs
    {
        return $this->contentGraphs;
    }

    protected function setupEventSourcedTrait()
    {
        $this->nodeAuthorizationService = $this->getObjectManager()->get(AuthorizationService::class);
        $this->nodeTypeManager = $this->getObjectManager()->get(NodeTypeManager::class);
        $this->eventTypeResolver = $this->getObjectManager()->get(EventTypeResolver::class);
        /* @var $eventStoreFactory EventStoreFactory */
        $eventStoreFactory = $this->getObjectManager()->get(EventStoreFactory::class);
        $this->eventStore = $eventStoreFactory->create('ContentRepository');
        $this->contentGraphs = new ContentGraphs([
            'DoctrineDbal' => $this->getObjectManager()->get(DbalContentGraph::class),
            'PostgreSQL' => $this->getObjectManager()->get(PostgreSQLContentHypergraph::class)
        ]);
        $this->workspaceFinder = $this->getObjectManager()->get(WorkspaceFinder::class);
        $this->nodeTypeConstraintFactory = $this->getObjectManager()->get(NodeTypeConstraintFactory::class);
        $this->eventNormalizer = $this->getObjectManager()->get(EventNormalizer::class);
        $this->runtimeBlocker = $this->getObjectManager()->get(RuntimeBlocker::class);

        $configurationManager = $this->getObjectManager()->get(\Neos\Flow\Configuration\ConfigurationManager::class);
        foreach ($configurationManager->getConfiguration(
            \Neos\Flow\Configuration\ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.EventSourcedContentRepository.testing.projectorsToBeReset'
        ) ?: [] as $projectorClassName => $toBeReset) {
            if ($toBeReset) {
                $this->projectorsToBeReset[] = $this->getObjectManager()->get($projectorClassName);
            }
        }

        $contentStreamRepository = $this->getObjectManager()->get(ContentStreamRepository::class);
        ObjectAccess::setProperty($contentStreamRepository, 'contentStreams', [], true);
    }

    /**
     * @BeforeScenario
     * @return void
     * @throws \Exception
     */
    public function beforeEventSourcedScenarioDispatcher()
    {
        foreach ($this->getContentGraphs() as $contentGraph) {
            $contentGraph->enableCache();
        }
        $this->visibilityConstraints = VisibilityConstraints::frontend();
        $this->dimensionSpacePoint = null;
        $this->rootNodeAggregateIdentifier = null;
        $this->contentStreamIdentifier = null;
        $this->currentNodeAggregate = null;
        foreach ($this->projectorsToBeReset as $projector) {
            $projector->reset();
        }
    }

    protected function deserializeProperties(array $properties): PropertyValuesToWrite
    {
        foreach ($properties as &$propertyValue) {
            if ($propertyValue === 'PostalAddress:dummy') {
                $propertyValue = PostalAddress::dummy();
            } elseif ($propertyValue === 'PostalAddress:anotherDummy') {
                $propertyValue = PostalAddress::anotherDummy();
            }
            if (is_string($propertyValue)) {
                if (\mb_strpos($propertyValue, 'Date:') === 0) {
                    $propertyValue = \DateTimeImmutable::createFromFormat(\DateTimeInterface::W3C, \mb_substr($propertyValue, 5));
                } elseif (\mb_strpos($propertyValue, 'URI:') === 0) {
                    $propertyValue = new Uri(\mb_substr($propertyValue, 4));
                } elseif ($propertyValue === 'IMG:dummy') {
                    $propertyValue = $this->requireDummyImage();
                } elseif ($propertyValue === '[IMG:dummy]') {
                    $propertyValue = [$this->requireDummyImage()];
                }
            }
        }

        return PropertyValuesToWrite::fromArray($properties);
    }

    /**
     * @Given /^the event RootWorkspaceWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventRootWorkspaceWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $newContentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['newContentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($newContentStreamIdentifier);
        $this->publishEvent('Neos.EventSourcedContentRepository:RootWorkspaceWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodeSpecializationWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeSpecializationWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(
            $contentStreamIdentifier
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
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(
            $contentStreamIdentifier
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
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(
            $contentStreamIdentifier
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
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(
            $contentStreamIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodePeerVariantWasCreated', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodePropertiesWereSet was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodePropertiesWereSetWasPublishedWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(
            $contentStreamIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodePropertiesWereSet', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodeReferencesWereSet was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeReferencesWereSetWasPublishedWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(
            $contentStreamIdentifier
        );

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeReferencesWereSet', $streamName->getEventStreamName(), $eventPayload);
    }


    /**
     * @Given /^the event NodeAggregateWasRemoved was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeAggregateWasRemovedWasPublishedWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($contentStreamIdentifier);

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeAggregateWasRemoved', $streamName->getEventStreamName(), $eventPayload);
    }

    /**
     * @Given /^the event NodeAggregateWasMoved was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theEventNodeAggregateWasMovedWasPublishedWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (!isset($eventPayload['initiatingUserIdentifier'])) {
            $eventPayload['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($eventPayload['contentStreamIdentifier']);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($contentStreamIdentifier);

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeAggregateWasMoved', $streamName->getEventStreamName(), $eventPayload);
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
        $event = DecoratedEvent::addIdentifier($event, Uuid::uuid4()->toString());
        $events = DomainEvents::withSingleEvent($event);
        $this->getObjectManager()->get(ReadSideMemoryCacheManager::class)->disableCache();
        $this->eventStore->commit($streamName, $events);
        $this->lastCommandOrEventResult = CommandResult::fromPublishedEvents($events, $this->runtimeBlocker);
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
                $propertyOrMethodName = substr($line['Value'], strlen('$this->'));
                if (method_exists($this, $propertyOrMethodName)) {
                    // is method
                    $value = (string) $this->$propertyOrMethodName();
                } else {
                    // is property
                    $value = (string) $this->$propertyOrMethodName;
                }
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

        $userIdentifier = isset($commandArguments['initiatingUserIdentifier'])
            ? UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
            : $this->currentUserIdentifier;

        $command = new CreateRootWorkspace(
            new WorkspaceName($commandArguments['workspaceName']),
            new WorkspaceTitle($commandArguments['workspaceTitle'] ?? ucfirst($commandArguments['workspaceName'])),
            new WorkspaceDescription($commandArguments['workspaceDescription'] ?? 'The workspace "' . $commandArguments['workspaceName'] . '"'),
            $userIdentifier,
            ContentStreamIdentifier::fromString($commandArguments['newContentStreamIdentifier'])
        );

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
     * @Given /^the command CreateNodeVariant is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeAggregateCurrentlyExists
     * @throws DimensionSpacePointNotFound
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws DimensionSpacePointIsNotYetOccupied
     * @throws DimensionSpacePointIsAlreadyOccupied
     * @throws NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint
     * @throws Exception
     */
    public function theCommandCreateNodeVariantIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $contentStreamIdentifier = isset($commandArguments['contentStreamIdentifier'])
            ? ContentStreamIdentifier::fromString($commandArguments['contentStreamIdentifier'])
            : $this->contentStreamIdentifier;
        $initiatingUserIdentifier = isset($commandArguments['initiatingUserIdentifier'])
            ? UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
            : $this->currentUserIdentifier;

        $command = new CreateNodeVariant(
            $contentStreamIdentifier,
            NodeAggregateIdentifier::fromString($commandArguments['nodeAggregateIdentifier']),
            OriginDimensionSpacePoint::fromArray($commandArguments['sourceOrigin']),
            OriginDimensionSpacePoint::fromArray($commandArguments['targetOrigin']),
            $initiatingUserIdentifier
        );
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
        $contentStreamIdentifier = isset($commandArguments['contentStreamIdentifier'])
            ? ContentStreamIdentifier::fromString($commandArguments['contentStreamIdentifier'])
            : $this->contentStreamIdentifier;
        $initiatingUserIdentifier = isset($commandArguments['initiatingUserIdentifier'])
            ? UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
            : $this->currentUserIdentifier;
        $sourceOriginDimensionSpacePoint = isset($commandArguments['sourceOriginDimensionSpacePoint'])
            ? OriginDimensionSpacePoint::fromArray($commandArguments['sourceOriginDimensionSpacePoint'])
            : OriginDimensionSpacePoint::fromDimensionSpacePoint($this->dimensionSpacePoint);

        $command = new SetNodeReferences(
            $contentStreamIdentifier,
            NodeAggregateIdentifier::fromString($commandArguments['sourceNodeAggregateIdentifier']),
            $sourceOriginDimensionSpacePoint,
            NodeAggregateIdentifierCollection::fromArray($commandArguments['destinationNodeAggregateIdentifiers']),
            PropertyName::fromString($commandArguments['referenceName']),
            $initiatingUserIdentifier
        );

        $this->lastCommandOrEventResult = $this->getNodeAggregateCommandHandler()
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
     * @Given /^the command RemoveNodeAggregate is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandRemoveNodeAggregateIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $command = RemoveNodeAggregate::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getNodeAggregateCommandHandler()
            ->handleRemoveNodeAggregate($command);
    }

    /**
     * @Given /^the command RemoveNodeAggregate is executed with payload and exceptions are caught:$/
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
     * @Given /^the command ChangeNodeAggregateType was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandChangeNodeAggregateTypeIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }

        $command = ChangeNodeAggregateType::fromArray($commandArguments);

        /** @var NodeAggregateCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);

        $this->lastCommandOrEventResult = $commandHandler->handleChangeNodeAggregateType($command);
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
     * @Given /^the command MoveNodeAggregate is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandMoveNodeIsExecutedWithPayload(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['relationDistributionStrategy'])) {
            $commandArguments['relationDistributionStrategy'] = RelationDistributionStrategy::STRATEGY_GATHER_ALL;
        }
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $command = MoveNodeAggregate::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getNodeAggregateCommandHandler()
            ->handleMoveNodeAggregate($command);
    }

    /**
     * @Given /^the command MoveNodeAggregate is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
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
     * @Given /^the command DiscardWorkspace is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeException
     * @throws ContentStreamAlreadyExists
     * @throws BaseWorkspaceDoesNotExist
     * @throws BaseWorkspaceHasBeenModifiedInTheMeantime
     * @throws WorkspaceDoesNotExist
     * @throws Exception
     */
    public function theCommandDiscardWorkspaceIsExecuted(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $command = DiscardWorkspace::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getWorkspaceCommandHandler()
            ->handleDiscardWorkspace($command);
    }

    /**
     * @Given /^the command PublishIndividualNodesFromWorkspace is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeException
     * @throws ContentStreamAlreadyExists
     * @throws BaseWorkspaceDoesNotExist
     * @throws BaseWorkspaceHasBeenModifiedInTheMeantime
     * @throws WorkspaceDoesNotExist
     * @throws Exception
     */
    public function theCommandPublishIndividualNodesFromWorkspaceIsExecuted(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $command = PublishIndividualNodesFromWorkspace::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getWorkspaceCommandHandler()
            ->handlePublishIndividualNodesFromWorkspace($command);
    }

    /**
     * @Given /^the command DiscardIndividualNodesFromWorkspace is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeException
     * @throws ContentStreamAlreadyExists
     * @throws BaseWorkspaceDoesNotExist
     * @throws BaseWorkspaceHasBeenModifiedInTheMeantime
     * @throws WorkspaceDoesNotExist
     * @throws Exception
     */
    public function theCommandDiscardIndividualNodesFromWorkspaceIsExecuted(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $command = DiscardIndividualNodesFromWorkspace::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getWorkspaceCommandHandler()
            ->handleDiscardIndividualNodesFromWorkspace($command);
    }

    /**
     * @Given /^the command ForkContentStream is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws ContentStreamAlreadyExists
     * @throws ContentStreamDoesNotExistYet
     * @throws Exception
     */
    public function theCommandForkContentStreamIsExecutedWithPayload(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }
        $command = ForkContentStream::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->getContentStreamCommandHandler()
            ->handleForkContentStream($command);
    }

    /**
     * @When /^the command CopyNodesRecursively is executed, copying the current node aggregate with payload:$/
     */
    public function theCommandCopyNodesRecursivelyIsExecutedCopyingTheCurrentNodeAggregateWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());
        $commandArguments['nodeToInsert'] = json_decode(json_encode(NodeSubtreeSnapshot::fromSubgraphAndStartNode($subgraph, $this->currentNode)), true);
        $command = CopyNodesRecursively::fromArray($commandArguments);
        $this->lastCommandOrEventResult = $this->getNodeDuplicationCommandHandler()
            ->handleCopyNodesRecursively($command);
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

        if (isset($commandArguments['propertyValues.dateProperty'])) {
            // special case to test Date type conversion
            $commandArguments['propertyValues']['dateProperty'] = \DateTime::createFromFormat(\DateTime::W3C, $commandArguments['propertyValues.dateProperty']);
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
     * @Then /^the last command should have thrown an exception of type "([^"]*)"(?: with code (\d*))?$/
     * @param string $shortExceptionName
     * @param int|null $expectedCode
     * @throws ReflectionException
     */
    public function theLastCommandShouldHaveThrown(string $shortExceptionName, ?int $expectedCode = null)
    {
        Assert::assertNotNull($this->lastCommandException, 'Command did not throw exception');
        $lastCommandExceptionShortName = (new \ReflectionClass($this->lastCommandException))->getShortName();
        Assert::assertSame($shortExceptionName, $lastCommandExceptionShortName, sprintf('Actual exception: %s (%s): %s', get_class($this->lastCommandException), $this->lastCommandException->getCode(), $this->lastCommandException->getMessage()));
        if (!is_null($expectedCode)) {
            Assert::assertSame($expectedCode, $this->lastCommandException->getCode(), sprintf(
                'Expected exception code %s, got exception code %s instead',
                $expectedCode,
                $this->lastCommandException->getCode()
            ));
        }
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
                    CreateWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handleCreateWorkspace'
                ];
            case 'PublishWorkspace':
                return [
                    PublishWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handlePublishWorkspace'
                ];
            case 'PublishIndividualNodesFromWorkspace':
                return [
                    PublishIndividualNodesFromWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handlePublishIndividualNodesFromWorkspace'
                ];
            case 'RebaseWorkspace':
                return [
                    RebaseWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handleRebaseWorkspace'
                ];
            case 'CreateNodeAggregateWithNodeAndSerializedProperties':
                return [
                    CreateNodeAggregateWithNodeAndSerializedProperties::class,
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
                    ChangeNodeAggregateName::class,
                    NodeAggregateCommandHandler::class,
                    'handleChangeNodeAggregateName'
                ];
            case 'SetSerializedNodeProperties':
                return [
                    SetSerializedNodeProperties::class,
                    NodeAggregateCommandHandler::class,
                    'handleSetSerializedNodeProperties'
                ];
            case 'DisableNodeAggregate':
                return [
                    DisableNodeAggregate::class,
                    NodeAggregateCommandHandler::class,
                    'handleDisableNodeAggregate'
                ];
            case 'EnableNodeAggregate':
                return [
                    EnableNodeAggregate::class,
                    NodeAggregateCommandHandler::class,
                    'handleEnableNodeAggregate'
                ];
            case 'MoveNodeAggregate':
                return [
                    MoveNodeAggregate::class,
                    NodeAggregateCommandHandler::class,
                    'handleMoveNodeAggregate'
                ];
            case 'SetNodeReferences':
                return [
                    SetNodeReferences::class,
                    NodeAggregateCommandHandler::class,
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
        $stream = $this->eventStore->load($streamName);
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

        $stream = $this->eventStore->load($streamName);
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
     * @Given /^I am user identified by "([^"]*)"$/
     * @param string $userIdentifier
     */
    public function iAmUserIdentifiedBy(string $userIdentifier): void
    {
        $this->currentUserIdentifier = UserIdentifier::fromString($userIdentifier);
    }

    public function getCurrentUserIdentifier(): ?UserIdentifier
    {
        return $this->currentUserIdentifier;
    }

    /**
     * @Then /^workspace "([^"]*)" points to another content stream than workspace "([^"]*)"$/
     * @param string $rawWorkspaceNameA
     * @param string $rawWorkspaceNameB
     */
    public function workspacesPointToDifferentContentStreams(string $rawWorkspaceNameA, string $rawWorkspaceNameB)
    {
        $workspaceA = $this->workspaceFinder->findOneByName(new WorkspaceName($rawWorkspaceNameA));
        Assert::assertInstanceOf(Workspace::class, $workspaceA, 'Workspace "' . $rawWorkspaceNameA . '" does not exist.');
        $workspaceB = $this->workspaceFinder->findOneByName(new WorkspaceName($rawWorkspaceNameB));
        Assert::assertInstanceOf(Workspace::class, $workspaceB, 'Workspace "' . $rawWorkspaceNameB . '" does not exist.');
        if ($workspaceA && $workspaceB) {
            Assert::assertNotEquals(
                $workspaceA->getCurrentContentStreamIdentifier(),
                $workspaceB->getCurrentContentStreamIdentifier(),
                'Workspace "' . $rawWorkspaceNameA . '" points to the same content stream as "' . $rawWorkspaceNameB . '"'
            );
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
     * @Then /^I expect the graph projection to consist of exactly (\d+) node(?:s)?$/
     * @param int $expectedNumberOfNodes
     */
    public function iExpectTheGraphProjectionToConsistOfExactlyNodes(int $expectedNumberOfNodes)
    {
        foreach ($this->getContentGraphs() as $adapterName => $contentGraph) {
            $actualNumberOfNodes = $contentGraph->countNodes();
            Assert::assertSame($expectedNumberOfNodes, $actualNumberOfNodes, 'Content graph in adapter "' . $adapterName . '" consists of ' . $actualNumberOfNodes . ' nodes, expected were ' . $expectedNumberOfNodes . '.');
        }
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
        $expectedDiscriminator = NodeDiscriminator::fromArray(json_decode($serializedNodeIdentifier, true));
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $nodeByAggregateIdentifier = $subgraph->findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier::fromString($serializedNodeAggregateIdentifier));
        $this->currentNode = $nodeByAggregateIdentifier;
        Assert::assertInstanceOf(NodeInterface::class, $nodeByAggregateIdentifier, 'No node could be found by node aggregate identifier "' . $serializedNodeAggregateIdentifier . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '"');
        $actualDiscriminator = NodeDiscriminator::fromNode($nodeByAggregateIdentifier);
        Assert::assertTrue($expectedDiscriminator->equals($actualDiscriminator), 'Node discriminators do not match. Expected was ' . json_encode($expectedDiscriminator) . ' , given was ' . json_encode($actualDiscriminator));
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
        $expectedDiscriminator = NodeDiscriminator::fromArray(json_decode($serializedNodeIdentifier, true));
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $this->iExpectNodeAggregateIdentifierToLeadToNode($serializedNodeAggregateIdentifier, $serializedNodeIdentifier);

        $nodeByPath = $subgraph->findNodeByPath(NodePath::fromString($serializedNodePath), $this->getRootNodeAggregateIdentifier());
        Assert::assertInstanceOf(NodeInterface::class, $nodeByPath, 'No node could be found by path "' . $serializedNodePath . '"" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '"');
        $actualDiscriminator = NodeDiscriminator::fromNode($nodeByPath);
        Assert::assertTrue($expectedDiscriminator->equals($actualDiscriminator), 'Node discriminators do not match. Expected was ' . json_encode($expectedDiscriminator) . ', given was ' . json_encode($actualDiscriminator));
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

        $nodeByPath = $subgraph->findNodeByPath(NodePath::fromString($serializedNodePath), $this->getRootNodeAggregateIdentifier());
        Assert::assertNull($nodeByPath, 'A node was found by path "' . $serializedNodePath . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '"');
    }

    /**
     * @Then /^I expect this node to be a child of node (.*)$/
     * @param string $serializedNodeDiscriminator
     */
    public function iExpectThisNodeToBeTheChildOfNode(string $serializedNodeDiscriminator): void
    {
        $expectedParentDiscriminator = NodeDiscriminator::fromArray(json_decode($serializedNodeDiscriminator, true));
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        $parent = $subgraph->findParentNode($this->currentNode->getNodeAggregateIdentifier());
        Assert::assertInstanceOf(NodeInterface::class, $parent, 'Parent not found.');
        $actualParentDiscriminator = NodeDiscriminator::fromNode($parent);
        Assert::assertTrue($expectedParentDiscriminator->equals($actualParentDiscriminator), 'Parent discriminator does not match. Expected was ' . json_encode($expectedParentDiscriminator) . ', given was ' . json_encode($actualParentDiscriminator));

        $expectedChildDiscriminator = NodeDiscriminator::fromNode($this->currentNode);
        $child = $subgraph->findChildNodeConnectedThroughEdgeName($parent->getNodeAggregateIdentifier(), $this->currentNode->getNodeName());
        $actualChildDiscriminator = NodeDiscriminator::fromNode($child);
        Assert::assertTrue($expectedChildDiscriminator->equals($actualChildDiscriminator), 'Child discriminator does not match. Expected was ' . json_encode($expectedChildDiscriminator) . ', given was ' . json_encode($actualChildDiscriminator));
    }

    /**
     * @Then /^I expect this node to have the preceding siblings (.*)$/
     * @param string $serializedExpectedSiblingNodeAggregateIdentifiers
     */
    public function iExpectThisNodeToHaveThePrecedingSiblings(string $serializedExpectedSiblingNodeAggregateIdentifiers)
    {
        $rawExpectedSiblingNodeAggregateIdentifiers = json_decode($serializedExpectedSiblingNodeAggregateIdentifiers);
        $expectedSiblingNodeAggregateIdentifiers = array_map(function ($item) {
            return NodeAggregateIdentifier::fromString($item);
        }, $rawExpectedSiblingNodeAggregateIdentifiers);

        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);
        $actualSiblings = $subgraph->findPrecedingSiblings($this->currentNode->getNodeAggregateIdentifier());
        $actualSiblingNodeAggregateIdentifiers = array_map(function (NodeInterface $sibling) {
            return $sibling->getNodeAggregateIdentifier();
        }, iterator_to_array($actualSiblings));

        Assert::assertEquals(
            $expectedSiblingNodeAggregateIdentifiers,
            $actualSiblingNodeAggregateIdentifiers,
            'Expected preceding siblings ' . json_encode($expectedSiblingNodeAggregateIdentifiers) . ', found ' . json_encode($actualSiblingNodeAggregateIdentifiers)
        );
    }

    /**
     * @Then /^I expect this node to have the succeeding siblings (.*)$/
     * @param string $serializedExpectedSiblingNodeAggregateIdentifiers
     */
    public function iExpectThisNodeToHaveTheSucceedingSiblings(string $serializedExpectedSiblingNodeAggregateIdentifiers)
    {
        $rawExpectedSiblingNodeAggregateIdentifiers = json_decode($serializedExpectedSiblingNodeAggregateIdentifiers);
        $expectedSiblingNodeAggregateIdentifiers = array_map(function ($item) {
            return NodeAggregateIdentifier::fromString($item);
        }, $rawExpectedSiblingNodeAggregateIdentifiers);

        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);
        $actualSiblings = $subgraph->findSucceedingSiblings($this->currentNode->getNodeAggregateIdentifier());
        $actualSiblingNodeAggregateIdentifiers = array_map(function (NodeInterface $sibling) {
            return $sibling->getNodeAggregateIdentifier();
        }, iterator_to_array($actualSiblings));

        Assert::assertEquals(
            $expectedSiblingNodeAggregateIdentifiers,
            $actualSiblingNodeAggregateIdentifiers,
            'Expected succeeding siblings ' . json_encode($expectedSiblingNodeAggregateIdentifiers) . ', found ' . json_encode($actualSiblingNodeAggregateIdentifiers)
        );
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
        $nodes = iterator_to_array($nodes);
        foreach ($expectedChildNodesTable->getHash() as $index => $row) {
            $expectedNodeName = NodeName::fromString($row['Name']);
            $actualNodeName = $nodes[$index]->getNodeName();
            Assert::assertEquals($expectedNodeName, $actualNodeName, 'ContentSubgraph::findChildNodes: Node name in index ' . $index . ' does not match. Expected: "' . $expectedNodeName . '" Actual: "' . $actualNodeName . '"');
            if (isset($row['NodeDiscriminator'])) {
                $expectedNodeDiscriminator = NodeDiscriminator::fromArray(json_decode($row['NodeDiscriminator'], true));
                $actualNodeDiscriminator = NodeDiscriminator::fromNode($nodes[$index]);
                Assert::assertTrue($expectedNodeDiscriminator->equals($actualNodeDiscriminator), 'ContentSubgraph::findChildNodes: Node discriminator in index ' . $index . ' does not match. Expected: ' . $expectedNodeDiscriminator . ' Actual: ' . $actualNodeDiscriminator);
            }
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
        Assert::assertNotNull($this->currentNode, 'current node not found');
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier($this->currentNode->getNodeAggregateIdentifier());

        foreach ($expectedProperties->getHash() as $row) {
            Assert::assertTrue($this->currentNode->hasProperty($row['Key']), 'Property "' . $row['Key'] . '" not found');
            $actualProperty = $this->currentNode->getProperty($row['Key']);
            $expected = $row['Value'];
            if (isset($row['Type']) && $row['Type'] === 'DateTime') {
                $expected = DateTimeImmutable::createFromFormat(DATE_W3C, $expected);
            }
            Assert::assertEquals($expected, $actualProperty, 'Node property ' . $row['Key'] . ' does not match. Expected: ' . json_encode($row['Value']) . '; Actual: ' . json_encode($actualProperty));
        }
    }

    /**
     * @Then /^I expect this node to have no properties$/
     */
    public function iExpectThisNodeToHaveNoProperties()
    {
        Assert::assertNotNull($this->currentNode, 'current node not found');
        $properties = $this->currentNode->getProperties();
        $properties = iterator_to_array($properties);
        Assert::assertCount(0, $properties, 'I expect no properties');
    }

    /**
     * @Then /^I expect this node to be of type "([^"]*)"$/
     * @param string $nodeTypeName
     */
    public function iExpectTheNodeToBeOfType(string $nodeTypeName)
    {
        Assert::assertEquals($nodeTypeName, $this->currentNode->getNodeTypeName()->jsonSerialize());
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

        /** @var ContentSubgraphInterface $subgraph */
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
                iterator_to_array($destinationNodes)
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

        /** @var ContentSubgraphInterface $subgraph */
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
                iterator_to_array($destinationNodes)
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
     * @param string $serializedNodePath
     * @param string $serializedNodeIdentifier
     * @throws Exception
     */
    public function iExpectThePathToLeadToTheNode(string $serializedNodePath, string $serializedNodeIdentifier)
    {
        if (!$this->getRootNodeAggregateIdentifier()) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $expectedDiscriminator = NodeDiscriminator::fromArray(json_decode($serializedNodeIdentifier, true));
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath(NodePath::fromString($serializedNodePath), $this->getRootNodeAggregateIdentifier());
        Assert::assertNotNull($this->currentNode, 'Did not find node at path "' . $serializedNodePath . '"');
        $actualDiscriminator = NodeDiscriminator::fromNode($this->currentNode);
        Assert::assertTrue($expectedDiscriminator->equals($actualDiscriminator), 'Node discriminators do not match. Expected was ' . json_encode($expectedDiscriminator) . ' , given was ' . json_encode($actualDiscriminator));
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
     * @param string $serializedNodePath
     * @throws Exception
     */
    public function iExpectThePathToLeadToNoNode(string $serializedNodePath)
    {
        if (!$this->getRootNodeAggregateIdentifier()) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath(NodePath::fromString($serializedNodePath), $this->getRootNodeAggregateIdentifier());
        Assert::assertNull($node, 'Did find node at path "' . $serializedNodePath . '"');
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
            $expectedLevel = (int)$expectedRow['Level'];
            $actualLevel = $flattenedSubtree[$i]->getLevel();
            Assert::assertSame($expectedLevel, $actualLevel, 'Level does not match in index ' . $i . ', expected: ' . $expectedLevel . ', actual: ' . $actualLevel);
            $expectedNodeAggregateIdentifier = NodeAggregateIdentifier::fromString($expectedRow['NodeAggregateIdentifier']);
            $actualNodeAggregateIdentifier = $flattenedSubtree[$i]->getNode()->getNodeAggregateIdentifier();
            Assert::assertTrue($expectedNodeAggregateIdentifier->equals($actualNodeAggregateIdentifier), 'NodeAggregateIdentifier does not match in index ' . $i . ', expected: "' . $expectedNodeAggregateIdentifier . '", actual: "' . $actualNodeAggregateIdentifier . '"');
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
     * @var \Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress[]
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
     * @param string $serializedNodePath
     * @param string $alias
     * @throws Exception
     */
    public function iGetTheNodeAddressForTheNodeAtPath(string $serializedNodePath, $alias = 'DEFAULT')
    {
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);
        if (!$this->getRootNodeAggregateIdentifier()) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $node = $subgraph->findNodeByPath(NodePath::fromString($serializedNodePath), $this->getRootNodeAggregateIdentifier());
        Assert::assertNotNull($node, 'Did not find a node at path "' . $serializedNodePath . '"');

        $this->currentNodeAddresses[$alias] = new NodeAddress(
            $this->contentStreamIdentifier,
            $this->dimensionSpacePoint,
            $node->getNodeAggregateIdentifier(),
            $this->workspaceFinder->findOneByCurrentContentStreamIdentifier($this->contentStreamIdentifier)->getWorkspaceName()
        );
    }

    protected function getWorkspaceCommandHandler(): WorkspaceCommandHandler
    {
        /** @var WorkspaceCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(WorkspaceCommandHandler::class);

        return $commandHandler;
    }

    protected function getContentStreamCommandHandler(): ContentStreamCommandHandler
    {
        /** @var ContentStreamCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(ContentStreamCommandHandler::class);

        return $commandHandler;
    }

    protected function getNodeAggregateCommandHandler(): NodeAggregateCommandHandler
    {
        /** @var NodeAggregateCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);

        return $commandHandler;
    }

    protected function getNodeDuplicationCommandHandler(): NodeDuplicationCommandHandler
    {
        /** @var NodeDuplicationCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeDuplicationCommandHandler::class);

        return $commandHandler;
    }

    protected function getRootNodeAggregateIdentifier(): ?NodeAggregateIdentifier
    {
        if ($this->rootNodeAggregateIdentifier) {
            return $this->rootNodeAggregateIdentifier;
        }

        $sitesNodeAggregate = $this->contentGraph->findRootNodeAggregateByType($this->contentStreamIdentifier, \Neos\ContentRepository\Domain\NodeType\NodeTypeName::fromString('Neos.Neos:Sites'));
        if ($sitesNodeAggregate) {
            return $sitesNodeAggregate->getIdentifier();
        }

        return null;
    }


    /**
     * @Then the content stream :contentStreamIdentifier has state :expectedState
     */
    public function theContentStreamHasState(string $contentStreamIdentifier, string $expectedState)
    {
        $contentStreamIdentifier = ContentStreamIdentifier::fromString($contentStreamIdentifier);
        /** @var ContentStreamFinder $contentStreamFinder */
        $contentStreamFinder = $this->getObjectManager()->get(ContentStreamFinder::class);

        $actual = $contentStreamFinder->findStateForContentStream($contentStreamIdentifier);
        Assert::assertEquals($expectedState, $actual);
    }

    /**
     * @Then the current content stream has state :expectedState
     */
    public function theCurrentContentStreamHasState(string $expectedState)
    {
        $this->theContentStreamHasState($this->contentStreamIdentifier->jsonSerialize(), $expectedState);
    }

    /**
     * @When I prune unused content streams
     */
    public function iPruneUnusedContentStreams()
    {
        /** @var ContentStreamPruner $contentStreamPruner */
        $contentStreamPruner = $this->getObjectManager()->get(ContentStreamPruner::class);
        $contentStreamPruner->prune();
        $this->lastCommandOrEventResult = $contentStreamPruner->getLastCommandResult();
    }

    /**
     * @When I prune removed content streams from the event stream
     */
    public function iPruneRemovedContentStreamsFromTheEventStream()
    {
        /** @var ContentStreamPruner $contentStreamPruner */
        $contentStreamPruner = $this->getObjectManager()->get(ContentStreamPruner::class);
        $contentStreamPruner->pruneRemovedFromEventStream();
    }
}
