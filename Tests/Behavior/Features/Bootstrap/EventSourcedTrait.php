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
use Neos\ContentRepository\DimensionSpace\DimensionSpace\Exception\DimensionSpacePointIsNoGeneralizationException;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\Exception\DimensionSpacePointIsNoSpecializationException;
use Neos\ContentRepository\Domain\Factory\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeName;
use Neos\ContentRepository\Domain\ValueObject\NodeTypeName;
use Neos\ContentRepository\Domain\ValueObject\RootNodeIdentifiers;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\ContentRepository\Exception\NodeExistsException;
use Neos\ContentRepository\Service\AuthorizationService;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Command\ForkContentStream;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamDoesNotExistYet;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamRepository;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\RemoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\RemoveNodesFromAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeAggregateNotFound;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\Node\ParentsNodeAggregateNotVisibleInDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\Node\RelationDistributionStrategy;
use Neos\EventSourcedContentRepository\Domain\Context\Node\SpecializedDimensionsMustBePartOfDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\Node\SubtreeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\ChangeNodeAggregateType;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeGeneralization;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateRootNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\DimensionSpacePointIsAlreadyOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\DimensionSpacePointIsNotYetOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCurrentlyExists;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeTypeIsNotOfTypeRoot;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeTypeNotFound;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\BaseWorkspaceHasBeenModifiedInTheMeantime;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValue;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcedContentRepository\Exception\DimensionSpacePointNotFound;
use Neos\EventSourcedContentRepository\Exception\NodeNotFoundException;
use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddressFactory;
use Neos\EventSourcing\Event\EventInterface;
use Neos\EventSourcing\Event\EventPublisher;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventStore\EventAndRawEvent;
use Neos\EventSourcing\EventStore\EventStoreManager;
use Neos\EventSourcing\EventStore\StreamNameFilter;
use Neos\EventSourcing\EventStore\StreamNamePrefixFilter;
use Neos\Flow\Property\PropertyMapper;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;

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
     * @var PropertyMapper
     */
    private $propertyMapper;

    /**
     * @var EventPublisher
     */
    private $eventPublisher;

    /**
     * @var EventStoreManager
     */
    private $eventStoreManager;

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
     * @var NodeAggregateIdentifier
     */
    protected $rootNodeAggregateIdentifier;

    /**
     * @var NodeInterface
     */
    protected $currentNode;


    /**
     * @var VisibilityConstraints
     */
    protected $visibilityConstraints;

    /**
     * @return \Neos\Flow\ObjectManagement\ObjectManagerInterface
     */
    abstract protected function getObjectManager();

    protected function setupEventSourcedTrait()
    {
        $this->nodeAuthorizationService = $this->getObjectManager()->get(AuthorizationService::class);
        $this->nodeTypeManager = $this->getObjectManager()->get(NodeTypeManager::class);
        $this->eventTypeResolver = $this->getObjectManager()->get(EventTypeResolver::class);
        $this->propertyMapper = $this->getObjectManager()->get(PropertyMapper::class);
        $this->eventPublisher = $this->getObjectManager()->get(EventPublisher::class);
        $this->eventStoreManager = $this->getObjectManager()->get(EventStoreManager::class);
        $this->contentGraph = $this->getObjectManager()->get(ContentGraphInterface::class);
        $this->workspaceFinder = $this->getObjectManager()->get(WorkspaceFinder::class);
        $this->nodeTypeConstraintFactory = $this->getObjectManager()->get(NodeTypeConstraintFactory::class);

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
        $this->contentGraph->resetCache();
        $this->workspaceFinder->resetCache();
        $this->visibilityConstraints = VisibilityConstraints::frontend();
    }

    /**
     * @Given /^the event RootWorkspaceWasCreated was published with payload:$/
     * @throws Exception
     */
    public function theEventRootWorkspaceWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($eventPayload['currentContentStreamIdentifier']);
        $this->publishEvent('Neos.EventSourcedContentRepository:RootWorkspaceWasCreated', $streamName, $eventPayload);
    }

    /**
     * @Given /^the event RootNodeAggregateWithNodeWasCreated was published with payload:$/
     * @throws Exception
     */
    public function theEventRootNodeAggregateWithNodeWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $streamName = NodeAggregateEventStreamName::fromContentStreamIdentifierAndNodeAggregateIdentifier(
            $eventPayload['contentStreamIdentifier'],
            $eventPayload['nodeAggregateIdentifier']
        );
        $this->publishEvent('Neos.EventSourcedContentRepository:RootNodeAggregateWithNodeWasCreated', $streamName, $eventPayload);
        $this->rootNodeAggregateIdentifier = $eventPayload['nodeAggregateIdentifier'];
    }

    /**
     * @Given /^the event NodeAggregateWithNodeWasCreated was published with payload:$/
     * @param TableNode $payloadTable
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function theEventNodeAggregateWithNodeWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (empty($eventPayload['initialPropertyValues'])) {
            $eventPayload['initialPropertyValues'] = new PropertyValues([]);
        }
        if (empty($eventPayload['dimensionSpacePoint'])) {
            $eventPayload['dimensionSpacePoint'] = new DimensionSpacePoint([]);
        }
        if (empty($eventPayload['visibleInDimensionSpacePoints'])) {
            $eventPayload['visibleInDimensionSpacePoints'] = new DimensionSpacePointSet([new DimensionSpacePoint([])]);
        }

        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($eventPayload['contentStreamIdentifier']);
        $streamName = $this->replaceUuidIdentifiers($streamName);
        $streamName .= ':NodeAggregate:' . $eventPayload['nodeAggregateIdentifier'];

        $this->publishEvent('Neos.EventSourcedContentRepository:NodeAggregateWithNodeWasCreated', $streamName, $eventPayload);
    }

    /**
     * @Given /^the event NodeSpecializationWasCreated was published with payload:$/
     * @throws Exception
     */
    public function theEventNodeSpecializationWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(new ContentStreamIdentifier($eventPayload['contentStreamIdentifier']));
        $streamName = $this->replaceUuidIdentifiers($streamName);
        $streamName .= ':NodeAggregate:' . $eventPayload['nodeAggregateIdentifier'];
        $this->publishEvent('Neos.EventSourcedContentRepository:NodeSpecializationWasCreated', $streamName, $eventPayload);
    }

    /**
     * @Given /^the event NodeGeneralizationWasCreated was published with payload:$/
     * @throws Exception
     */
    public function theEventNodeGeneralizationWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(new ContentStreamIdentifier($eventPayload['contentStreamIdentifier']));
        $streamName = $this->replaceUuidIdentifiers($streamName);
        $streamName .= ':NodeAggregate:' . $eventPayload['nodeAggregateIdentifier'];
        $this->publishEvent('Neos.EventSourcedContentRepository:NodeGeneralizationWasCreated', $streamName, $eventPayload);
    }

    /**
     * @Given /^the Event "([^"]*)" was published to stream "([^"]*)" with payload:$/
     * @throws Exception
     */
    public function theEventWasPublishedToStreamWithPayload($eventType, $streamName, TableNode $payloadTable)
    {
        $streamName = $this->replaceUuidIdentifiers($streamName);

        $eventPayload = $this->readPayloadTable($payloadTable);
        $this->publishEvent($eventType, $streamName, $eventPayload);
    }

    /**
     * @param $eventType
     * @param $streamName
     * @param $eventPayload
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    protected function publishEvent($eventType, $streamName, $eventPayload)
    {
        $eventClassName = $this->eventTypeResolver->getEventClassNameByType($eventType);

        /** @var EventInterface $event */
        switch ($eventClassName) {
            case \Neos\EventSourcedContentRepository\Domain\Context\Node\Event\NodesWereMoved::class:
                \Neos\Flow\var_dump($eventPayload, 'hello from publishEvent');
                exit();
            default:
                $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
                $event = $this->propertyMapper->convert($eventPayload, $eventClassName, $configuration);
        }

        $this->eventPublisher->publish((string) $streamName, $event);
    }


    /**
     * @param TableNode $payloadTable
     * @return array
     * @throws Exception
     */
    protected function readPayloadTable(TableNode $payloadTable)
    {
        $eventPayload = [];
        foreach ($payloadTable->getHash() as $line) {
            if (!empty($line['Type'])) {
                switch ($line['Type']) {
                    case 'json':
                        $eventPayload[$line['Key']] = json_decode($line['Value'], true);
                        break;
                    case 'WorkspaceName':
                        $eventPayload[$line['Key']] = new WorkspaceName($line['Value']);
                        break;
                    case 'WorkspaceTitle':
                        $eventPayload[$line['Key']] = new WorkspaceTitle($line['Value']);
                        break;
                    case 'WorkspaceDescription':
                        $eventPayload[$line['Key']] = new WorkspaceDescription($line['Value']);
                        break;
                    case 'ContentStreamIdentifier':
                        try {
                            $eventPayload[$line['Key']] = new ContentStreamIdentifier($line['Value']);
                        } catch (\Ramsey\Uuid\Exception\InvalidUuidStringException $e) {
                            $eventPayload[$line['Key']] = new ContentStreamIdentifier($this->replaceUuidIdentifiers($line['Value']));
                        }
                        break;
                    case 'DimensionSpacePoint':
                        $eventPayload[$line['Key']] = DimensionSpacePoint::fromJsonString($line['Value']);
                        break;
                    case 'DimensionSpacePointSet':
                        $tmp = json_decode($line['Value'], true);
                        $convertedPoints = [];
                        if (isset($tmp)) {
                            foreach ($tmp as $point) {
                                $convertedPoints[] = new DimensionSpacePoint($point);
                            }
                        }
                        $eventPayload[$line['Key']] = new DimensionSpacePointSet($convertedPoints);
                        break;
                    case 'NodeAggregateIdentifier':
                        $eventPayload[$line['Key']] = new NodeAggregateIdentifier($line['Value']);
                        break;
                    case 'NodeTypeName':
                        $eventPayload[$line['Key']] = new NodeTypeName($line['Value']);
                        break;
                    case 'NodeName':
                        $eventPayload[$line['Key']] = new NodeName($line['Value']);
                        break;
                    case 'UserIdentifier':
                        try {
                            $eventPayload[$line['Key']] = new UserIdentifier($line['Value']);
                        } catch (\Ramsey\Uuid\Exception\InvalidUuidStringException $e) {
                            $eventPayload[$line['Key']] = new UserIdentifier($this->replaceUuidIdentifiers($line['Value']));
                        }
                        break;
                    case 'PropertyValue':
                        $tmp = json_decode($line['Value'], true);
                        $eventPayload[$line['Key']] = new PropertyValue($tmp['value'], $tmp['type']);
                        break;
                    case 'PropertyName':
                        $eventPayload[$line['Key']] = new PropertyName($line['Value']);
                        break;
                    case 'PropertyValues':
                        $tmp = json_decode($line['Value'], true);
                        $propertyValues = [];
                        foreach ($tmp as $rawPropertyName => $rawPropertyValue) {
                            $propertyValues[$rawPropertyName] = new PropertyValue($rawPropertyValue['value'], $rawPropertyValue['type']);
                        }
                        $eventPayload[$line['Key']] = new PropertyValues($propertyValues);
                        break;
                    case 'Uuid':
                        $eventPayload[$line['Key']] = $this->replaceUuidIdentifiers('[' . $line['Value'] . ']');
                        break;
                    case 'Uuid[]':
                        if ($line['Value']) {
                            $eventPayload[$line['Key']] = array_map(
                                function ($part) {
                                    return $this->replaceUuidIdentifiers('[' . trim($part) . ']');
                                },
                                explode(',', $line['Value'])
                            );
                        } else {
                            $eventPayload[$line['Key']] = [];
                        }
                        break;
                    case 'null':
                        $eventPayload[$line['Key']] = null;
                        break;
                    default:
                        throw new \Exception("I do not understand type " . $line['Type'] . " in line: " . json_encode($line));
                }
            } else {
                $eventPayload[$line['Key']] = $line['Value'];
            }
        }

        return $eventPayload;
    }

    protected function replaceUuidIdentifiers($identifierString): string
    {
        return preg_replace_callback(
            '#\[[0-9a-zA-Z\-]+\]#',
            function ($matches) {
                if ($matches[0] === '[ROOT]') {
                    return RootNodeIdentifiers::rootNodeAggregateIdentifier();
                }
                return (string)Uuid::uuid5('00000000-0000-0000-0000-000000000000', $matches[0]);
            },
            $identifierString
        );
    }

    /**
     * @When /^the command CreateRootNodeAggregateWithNode is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function theCommandCreateRootNodeAggregateWithNodeIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
        $command = $this->propertyMapper->convert($commandArguments, CreateRootNodeAggregateWithNode::class, $configuration);

        $this->getNodeAggregateCommandHandler()->handleCreateRootNodeAggregateWithNode($command);
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

        $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
        $command = $this->propertyMapper->convert($commandArguments, CreateNodeAggregateWithNode::class, $configuration);

        $this->getNodeAggregateCommandHandler()->handleCreateNodeAggregateWithNode($command);
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
     * @Given /^the command CreateNodeSpecialization was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function theCommandCreateNodeSpecializationIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
        $command = $this->propertyMapper->convert($commandArguments, \Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeSpecialization::class, $configuration);
        /** @var NodeAggregateCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);

        $commandHandler->handleCreateNodeSpecialization($command);
    }

    /**
     * @Given /^the command CreateNodeSpecialization was published with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandCreateNodeSpecializationIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandCreateNodeSpecializationIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
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
     * @Given /^the command RemoveNodeAggregate was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function theCommandRemoveNodeAggregateIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
        $command = $this->propertyMapper->convert($commandArguments, RemoveNodeAggregate::class, $configuration);
        /** @var NodeCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeCommandHandler::class);

        $commandHandler->handleRemoveNodeAggregate($command);
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
     * @Given /^the command RemoveNodesFromAggregate was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function theCommandRemoveNodesFromAggregateIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
        $command = $this->propertyMapper->convert($commandArguments, RemoveNodesFromAggregate::class, $configuration);
        /** @var NodeCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeCommandHandler::class);

        $commandHandler->handleRemoveNodesFromAggregate($command);
    }


    /**
     * @Given /^the command CreateNodeGeneralization was published with payload:$/
     * @param TableNode $payloadTable
     * @throws Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function theCommandCreateNodeGeneralizationIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
        $command = $this->propertyMapper->convert($commandArguments, CreateNodeGeneralization::class, $configuration);
        /** @var NodeAggregateCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);

        $commandHandler->handleCreateNodeGeneralization($command);
    }

    /**
     * @Given /^the command CreateNodeGeneralization was published with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     * @throws Exception
     */
    public function theCommandCreateNodeGeneralizationIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theCommandCreateNodeGeneralizationIsExecutedWithPayload($payloadTable);
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

        $command = new ChangeNodeAggregateType(
            new ContentStreamIdentifier($commandArguments['contentStreamIdentifier']),
            new NodeAggregateIdentifier($commandArguments['nodeAggregateIdentifier']),
            new NodeTypeName($commandArguments['newNodeTypeName']),
            $commandArguments['strategy'] ? new NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy($commandArguments['strategy']) : null
        );
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
     * @When /^the command "([^"]*)" is executed with payload:$/
     * @Given /^the command "([^"]*)" was executed with payload:$/
     * @throws Exception
     */
    public function theCommandIsExecutedWithPayload($shortCommandName, TableNode $payloadTable = null, $commandArguments = null)
    {
        list($commandClassName, $commandHandlerClassName, $commandHandlerMethod) = self::resolveShortCommandName($shortCommandName);
        if ($commandArguments === null) {
            $commandArguments = $this->readPayloadTable($payloadTable);
        }

        switch ($commandClassName) {
            case \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\MoveNode::class:
                $command = new \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\MoveNode(
                    is_string($commandArguments['contentStreamIdentifier']) ? new ContentStreamIdentifier($commandArguments['contentStreamIdentifier']) : $commandArguments['contentStreamIdentifier'],
                    $commandArguments['dimensionSpacePoint'],
                    is_string($commandArguments['nodeAggregateIdentifier']) ? new NodeAggregateIdentifier($commandArguments['nodeAggregateIdentifier']) : $commandArguments['nodeAggregateIdentifier'],
                    is_string($commandArguments['newParentNodeAggregateIdentifier']) ? new NodeAggregateIdentifier($commandArguments['newParentNodeAggregateIdentifier']) : $commandArguments['newParentNodeAggregateIdentifier'],
                    is_string($commandArguments['newSucceedingSiblingNodeAggregateIdentifier']) ? new NodeAggregateIdentifier($commandArguments['newSucceedingSiblingNodeAggregateIdentifier']) : $commandArguments['newSucceedingSiblingNodeAggregateIdentifier'],
                    is_string($commandArguments['relationDistributionStrategy']) ? RelationDistributionStrategy::fromConfigurationValue($commandArguments['relationDistributionStrategy']) : $commandArguments['relationDistributionStrategy']
                );
                break;
            default:
                $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
                $command = $this->propertyMapper->convert($commandArguments, $commandClassName, $configuration);
        }

        $commandHandler = $this->getObjectManager()->get($commandHandlerClassName);

        $commandHandler->$commandHandlerMethod($command);

        // @todo check whether this is necessary at all
        if (isset($commandArguments['rootNodeAggregateIdentifier'])) {
            $this->rootNodeAggregateIdentifier = new NodeAggregateIdentifier($commandArguments['rootNodeAggregateIdentifier']);
        }
    }

    /**
     * @When /^the command CreateWorkspace is executed with payload:$/
     * @throws Exception
     */
    public function theCommandCreateWorkspaceIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        if (!isset($commandArguments['workspaceTitle'])) {
            $commandArguments['workspaceTitle'] = new WorkspaceTitle(ucfirst((string) $commandArguments['workspaceName']));
        }
        if (!isset($commandArguments['workspaceDescription'])) {
            $commandArguments['workspaceDescription'] = new WorkspaceDescription('The workspace "' . $commandArguments['workspaceName'] . '".');
        }
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = new UserIdentifier($this->replaceUuidIdentifiers('[initiatingUserIdentifier]'));
        }

        $this->theCommandIsExecutedWithPayload('CreateWorkspace', null, $commandArguments);
    }

    /**
     * @When /^the command CreateRootWorkspace is executed with payload:$/
     * @throws Exception
     */
    public function theCommandCreateRootWorkspaceIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        if (!isset($commandArguments['workspaceTitle'])) {
            $commandArguments['workspaceTitle'] = new WorkspaceTitle(ucfirst((string) $commandArguments['workspaceName']));
        }
        if (!isset($commandArguments['workspaceDescription'])) {
            $commandArguments['workspaceDescription'] = new WorkspaceDescription('The workspace "' . $commandArguments['workspaceName'] . '".');
        }
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = new UserIdentifier($this->replaceUuidIdentifiers('[initiatingUserIdentifier]'));
        }
        if (!isset($commandArguments['workspaceOwner'])) {
            $commandArguments['workspaceOwner'] = new UserIdentifier($this->replaceUuidIdentifiers('[workspaceOwner]'));
        }

        $this->theCommandIsExecutedWithPayload('CreateRootWorkspace', null, $commandArguments);
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
     * @throws Exception
     */
    public function theLastCommandShouldHaveThrown($shortExceptionName)
    {
        Assert::assertNotNull($this->lastCommandException, 'Command did not throw exception');

        switch ($shortExceptionName) {
            case 'Exception':
                return;
            case 'ContentStreamDoesNotExistYet':
                Assert::assertInstanceOf(ContentStreamDoesNotExistYet::class, $this->lastCommandException);

                return;
            case 'NodeNotFoundException':
                Assert::assertInstanceOf(NodeNotFoundException::class, $this->lastCommandException);

                return;
            case 'BaseWorkspaceHasBeenModifiedInTheMeantime':
                Assert::assertInstanceOf(BaseWorkspaceHasBeenModifiedInTheMeantime::class, $this->lastCommandException);

                return;
            case 'NodeAggregateNotFound':
                Assert::assertInstanceOf(NodeAggregateNotFound::class, $this->lastCommandException);

                return;
            case 'NodeAggregateCurrentlyExists':
                Assert::assertInstanceOf(NodeAggregateCurrentlyExists::class, $this->lastCommandException);

                return;
            case 'NodeTypeIsNotOfTypeRoot':
                Assert::assertInstanceOf(NodeTypeIsNotOfTypeRoot::class, $this->lastCommandException);

                return;
            case 'NodeExistsException':
                Assert::assertInstanceOf(NodeExistsException::class, $this->lastCommandException);

                return;
            case 'NodeConstraintException':
                Assert::assertInstanceOf(NodeConstraintException::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsNoSpecialization':
                Assert::assertInstanceOf(DimensionSpacePointIsNoSpecializationException::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsAlreadyOccupied':
                Assert::assertInstanceOf(DimensionSpacePointIsAlreadyOccupied::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsNotYetOccupied':
                Assert::assertInstanceOf(DimensionSpacePointIsNotYetOccupied::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsNoGeneralization':
                Assert::assertInstanceOf(DimensionSpacePointIsNoGeneralizationException::class, $this->lastCommandException);

                return;
            case 'NodeTypeNotFound':
                Assert::assertInstanceOf(NodeTypeNotFound::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointNotFound':
                Assert::assertInstanceOf(DimensionSpacePointNotFound::class, $this->lastCommandException);

                return;
            case 'ParentsNodeAggregateNotVisibleInDimensionSpacePoint':
                Assert::assertInstanceOf(ParentsNodeAggregateNotVisibleInDimensionSpacePoint::class, $this->lastCommandException);

                return;
            case 'SpecializedDimensionsMustBePartOfDimensionSpacePointSet':
                Assert::assertInstanceOf(SpecializedDimensionsMustBePartOfDimensionSpacePointSet::class, $this->lastCommandException);

                return;
            default:
                throw new \Exception('The short exception name "' . $shortExceptionName . '" is currently not supported by the tests.');
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
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handleCreateRootWorkspace'
                ];
            case 'CreateWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handleCreateWorkspace'
                ];
            case 'PublishWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\PublishWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handlePublishWorkspace'
                ];
            case 'RebaseWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\RebaseWorkspace::class,
                    WorkspaceCommandHandler::class,
                    'handleRebaseWorkspace'
                ];
            case 'AddNodeToAggregate':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\AddNodeToAggregate::class,
                    NodeCommandHandler::class,
                    'handleAddNodeToAggregate'
                ];
            case 'CreateNodeAggregateWithNode':
                return [
                    CreateNodeAggregateWithNode::class,
                    NodeCommandHandler::class,
                    'handleCreateNodeAggregateWithNode'
                ];
            case 'ForkContentStream':
                return [
                    ForkContentStream::class,
                    ContentStreamCommandHandler::class,
                    'handleForkContentStream'
                ];
            case 'ChangeNodeName':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\ChangeNodeName::class,
                    NodeCommandHandler::class,
                    'handleChangeNodeName'
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
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\MoveNode::class,
                    NodeCommandHandler::class,
                    'handleMoveNode'
                ];
            case 'TranslateNodeInAggregate':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\TranslateNodeInAggregate::class,
                    NodeCommandHandler::class,
                    'handleTranslateNodeInAggregate'
                ];
            case 'SetNodeReferences':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\SetNodeReferences::class,
                    NodeCommandHandler::class,
                    'handleSetNodeReferences'
                ];

            default:
                throw new \Exception('The short command name "' . $shortCommandName . '" is currently not supported by the tests.');
        }
    }

    /**
     * @Then /^I expect exactly (\d+) events? to be published on stream "([^"]*)"$/
     * @throws \Neos\EventSourcing\EventStore\Exception\EventStreamNotFoundException
     */
    public function iExpectExactlyEventToBePublishedOnStream($numberOfEvents, $streamName)
    {
        $streamName = $this->replaceUuidIdentifiers($streamName);

        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $stream = $eventStore->get(new StreamNameFilter($streamName));
        $this->currentEventStreamAsArray = iterator_to_array($stream, false);
        Assert::assertEquals($numberOfEvents, count($this->currentEventStreamAsArray), 'Number of events did not match');
    }

    /**
     * @Then /^I expect exactly (\d+) events? to be published on stream with prefix "([^"]*)"$/
     * @throws \Neos\EventSourcing\EventStore\Exception\EventStreamNotFoundException
     */
    public function iExpectExactlyEventToBePublishedOnStreamWithPrefix($numberOfEvents, $streamName)
    {
        $streamName = $this->replaceUuidIdentifiers($streamName);

        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $stream = $eventStore->get(new StreamNamePrefixFilter($streamName));
        $this->currentEventStreamAsArray = iterator_to_array($stream, false);
        Assert::assertEquals($numberOfEvents, count($this->currentEventStreamAsArray), 'Number of events did not match');
    }

    /**
     * @Then /^event at index (\d+) is of type "([^"]*)" with payload:/
     */
    public function eventNumberIs($eventNumber, $eventType, TableNode $payloadTable)
    {
        if ($this->currentEventStreamAsArray === null) {
            Assert::fail('Step \'I expect exactly ? events to be published on stream "?"\' was not executed');
        }

        Assert::assertArrayHasKey($eventNumber, $this->currentEventStreamAsArray, 'Event at index does not exist');

        /* @var $actualEvent EventAndRawEvent */
        $actualEvent = $this->currentEventStreamAsArray[$eventNumber];

        Assert::assertNotNull($actualEvent, sprintf('Event with number %d not found', $eventNumber));
        Assert::assertEquals($eventType, $actualEvent->getRawEvent()->getType(), 'Event Type does not match: "' . $actualEvent->getRawEvent()->getType() . '" !== "' . $eventType . '"');

        $actualEventPayload = $actualEvent->getRawEvent()->getPayload();

        foreach ($payloadTable->getHash() as $assertionTableRow) {
            $actualValue = \Neos\Utility\Arrays::getValueByPath($actualEventPayload, $assertionTableRow['Key']);
            $type = $assertionTableRow['Type'] ?? null;
            if ($type == 'Uuid' || $type === 'ContentStreamIdentifier' || $type == 'UserIdentifier') {
                $expectedValue = $this->replaceUuidIdentifiers($assertionTableRow['Expected']);
            } else {
                $expectedValue = $assertionTableRow['Expected'];
            }
            if (isset($assertionTableRow['AssertionType']) && $assertionTableRow['AssertionType'] === 'json') {
                $expectedValue = json_decode($expectedValue, true);
            }

            Assert::assertEquals($expectedValue, $actualValue, 'ERROR at ' . $assertionTableRow['Key'] . ': ' . json_encode($actualValue) . ' !== ' . json_encode($expectedValue));
        }
    }


    /**
     * @When /^the graph projection is fully up to date$/
     */
    public function theGraphProjectionIsFullyUpToDate()
    {
        // we do not need to do anything here yet, as the graph projection is synchronous.
    }

    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var DimensionSpacePoint
     */
    private $dimensionSpacePoint;

    /**
     * @var ContentGraphInterface
     */
    private $contentGraph;

    /**
     * @Given /^I am in the active content stream of workspace "([^"]*)" and Dimension Space Point (.*)$/
     */
    public function iAmInTheActiveContentStreamOfWorkspaceAndDimensionSpacePoint(string $workspaceName, string $dimensionSpacePoint)
    {
        $workspaceName = new WorkspaceName($workspaceName);
        $this->contentStreamIdentifier = $this->workspaceFinder->findOneByName($workspaceName)->getCurrentContentStreamIdentifier();
        $this->dimensionSpacePoint = new DimensionSpacePoint(json_decode($dimensionSpacePoint, true));
    }

    /**
     * @Given /^I am in content stream "([^"]*)" and Dimension Space Point (.*)$/
     */
    public function iAmInContentStreamAndDimensionSpacePoint(string $contentStreamIdentifier, string $dimensionSpacePoint)
    {
        $contentStreamIdentifier = $this->replaceUuidIdentifiers($contentStreamIdentifier);
        $this->contentStreamIdentifier = new ContentStreamIdentifier($contentStreamIdentifier);
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
        $rawContentStreamIdentifier = $this->replaceUuidIdentifiers($rawContentStreamIdentifier);
        $workspace = $this->workspaceFinder->findOneByName(new WorkspaceName($rawWorkspaceName));

        Assert::assertNotEquals($rawContentStreamIdentifier, (string)$workspace->getCurrentContentStreamIdentifier());
    }

    /**
     * @deprecated use "a node aggregate"
     * @Then /^I expect a node "([^"]*)" to exist in the graph projection$/
     */
    public function iExpectANodeToExistInTheGraphProjection($nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        Assert::assertNotNull($node, 'Node "' . $nodeIdentifier . '" was not found in the current Content Stream / Dimension Space Point.');
    }

    /**
     * @deprecated use "a node aggregate"
     * @Then /^I expect a node "([^"]*)" not to exist in the graph projection$/
     */
    public function iExpectANodeNotToExistInTheGraphProjection($nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        // we're not using assertNull here: If $node is not null, the
        Assert::assertTrue($node === null, 'Node "' . $nodeIdentifier . '" was found in the current Content Stream / Dimension Space Point.');
    }

    /**
     * @Then /^I expect a node with identifier (.*) to exist in the content graph$/
     * @param string $serializedNodeIdentifier
     * @throws Exception
     */
    public function iExpectANodeWithIdentifierToExistInTheContentGraph(string $serializedNodeIdentifier)
    {
        $serializedNodeIdentifier = $this->replaceUuidIdentifiers($serializedNodeIdentifier);
        $nodeIdentifier = NodeIdentifier::fromJsonString($serializedNodeIdentifier);
        $this->currentNode = $this->contentGraph->findNodeByIdentifier($nodeIdentifier);
        Assert::assertNotNull($this->currentNode, 'Node with aggregate identifier "' . $nodeIdentifier->getNodeAggregateIdentifier()
            . '" and originating in dimension space point "' . $nodeIdentifier->getOriginDimensionSpacePoint()
            . '" was not found in content stream "' . $nodeIdentifier->getContentStreamIdentifier() . '"'
        );
    }

    /**
     * @Then /^I expect a node identified by aggregate identifier "([^"]*)" to exist in the subgraph$/
     * @param string $rawNodeAggregateIdentifier
     * @throws Exception
     */
    public function iExpectANodeIdentifiedByAggregateIdentifierToExistInTheSubgraph(string $rawNodeAggregateIdentifier)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(new NodeAggregateIdentifier($rawNodeAggregateIdentifier));
        Assert::assertNotNull($this->currentNode, 'Node with aggregate identifier "' . $rawNodeAggregateIdentifier . '" was not found in the subgraph with dimension space point "' . $this->dimensionSpacePoint . '" in content stream "' . $this->contentStreamIdentifier . '".');
    }

    /**
     * @Then /^I expect a node identified by aggregate identifier "([^"]*)" not to exist in the subgraph$/
     * @param string $nodeAggregateIdentifier
     */
    public function iExpectANodeIdentifiedByAggregateIdentifierNotToExistInTheSubgraph(string $nodeAggregateIdentifier)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(new NodeAggregateIdentifier($nodeAggregateIdentifier));
        Assert::assertTrue($node === null, 'Node with aggregate identifier "' . $nodeAggregateIdentifier . '" was found in the current Content Stream / Dimension Space Point, but it SHOULD NOT BE FOUND.');
    }

    /**
     * @Then /^I expect the node aggregate "([^"]*)" to have the following child nodes:$/
     */
    public function iExpectTheNodeToHaveTheFollowingChildNodes($nodeAggregateIdentifier, TableNode $expectedChildNodesTable)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $nodes = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findChildNodes(new NodeAggregateIdentifier($nodeAggregateIdentifier));

        $numberOfChildNodes = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->countChildNodes(new NodeAggregateIdentifier($nodeAggregateIdentifier));

        Assert::assertEquals(count($expectedChildNodesTable->getHash()), $numberOfChildNodes, 'ContentSubgraph::countChildNodes returned a wrong value');
        Assert::assertCount(count($expectedChildNodesTable->getHash()), $nodes, 'ContentSubgraph::findChildNodes: Child Node Count does not match');
        foreach ($expectedChildNodesTable->getHash() as $index => $row) {
            Assert::assertEquals($row['Name'], (string)$nodes[$index]->getNodeName(), 'ContentSubgraph::findChildNodes: Node name in index ' . $index . ' does not match. Actual: ' . $nodes[$index]->getNodeName());
            Assert::assertEquals($this->replaceUuidIdentifiers($row['NodeIdentifier']), (string)$nodes[$index]->getNodeIdentifier(), 'ContentSubgraph::findChildNodes: Node identifier in index ' . $index . ' does not match. Actual: ' . $nodes[$index]->getNodeIdentifier() . ' Expected: ' . $this->replaceUuidIdentifiers($row['NodeIdentifier']));
        }
    }

    /**
     * @Then /^I expect the node aggregate "([^"]*)" to resolve to node "([^"]*)"$/
     */
    public function iExpectTheNodeAggregateToHaveTheNodes($nodeAggregateIdentifier, $nodeIdentifier)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(new NodeAggregateIdentifier($nodeAggregateIdentifier));
        Assert::assertNotNull($node, 'Node with ID "' . $nodeIdentifier . '" not found!');
        Assert::assertEquals($nodeIdentifier, (string)$node->getNodeIdentifier(), 'Node ID does not match!');
    }


    /**
     * @Then /^I expect the node "([^"]*)" to have the type "([^"]*)"$/
     */
    public function iExpectTheNodeToHaveTheType($nodeIdentifier, $nodeType)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
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
     */
    public function iExpectTheNodeToHaveTheProperties($nodeIdentifier, TableNode $expectedProperties)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        $this->iExpectTheCurrentNodeToHaveTheProperties($expectedProperties);
    }

    /**
     * @Then /^I expect the current Node to have the properties:$/
     */
    public function iExpectTheCurrentNodeToHaveTheProperties(TableNode $expectedProperties)
    {
        $properties = $this->currentNode->getProperties();
        foreach ($expectedProperties->getHash() as $row) {
            Assert::assertArrayHasKey($row['Key'], $properties, 'Property "' . $row['Key'] . '" not found');
            $actualProperty = $properties[$row['Key']];
            Assert::assertEquals($row['Value'], $actualProperty, 'Node property ' . $row['Key'] . ' does not match. Expected: ' . $row['Value'] . '; Actual: ' . $actualProperty);
        }
    }

    /**
     * @Then /^I expect the node aggregate "([^"]*)" to have the references:$/
     */
    public function iExpectTheNodeToHaveTheReferences($nodeAggregateIdentifier, TableNode $expectedReferences)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $expectedReferences = $this->readPayloadTable($expectedReferences);

        /** @var \Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface $subgraph */
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        foreach ($expectedReferences as $propertyName => $expectedDestinationNodeAggregateIdentifiers) {
            $destinationNodes = $subgraph->findReferencedNodes(new NodeAggregateIdentifier($nodeAggregateIdentifier), new PropertyName($propertyName));
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
     */
    public function iExpectTheNodeToBeReferencedBy($nodeAggregateIdentifier, TableNode $expectedReferences)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $expectedReferences = $this->readPayloadTable($expectedReferences);

        /** @var \Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface $subgraph */
        $subgraph = $this->contentGraph->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints);

        foreach ($expectedReferences as $propertyName => $expectedDestinationNodeAggregateIdentifiers) {
            $destinationNodes = $subgraph->findReferencingNodes(new NodeAggregateIdentifier($nodeAggregateIdentifier), new PropertyName($propertyName));
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
     * @Then /^I expect the path "([^"]*)" to lead to the node "([^"]*)"$/
     * @throws Exception
     */
    public function iExpectThePathToLeadToTheNode(string $nodePath, string $nodeAggregateIdentifier)
    {
        if (!$this->rootNodeAggregateIdentifier) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath($nodePath, $this->rootNodeAggregateIdentifier);
        Assert::assertNotNull($this->currentNode, 'Did not find node at path "' . $nodePath . '"');
        Assert::assertEquals($nodeAggregateIdentifier, (string)$this->currentNode->getNodeAggregateIdentifier(), 'Node aggregate identifiers do not match.');
    }

    /**
     * @When /^I go to the parent node of node aggregate "([^"]*)"$/
     */
    public function iGoToTheParentNodeOfNode($nodeAggregateIdentifier)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findParentNode(new NodeAggregateIdentifier($nodeAggregateIdentifier));
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
     */
    public function currentNodeAggregateShouldBe($nodeAggregateIdentifier)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        Assert::assertEquals($nodeAggregateIdentifier, (string)$this->currentNode->getNodeAggregateIdentifier());
    }

    /**
     * @Then /^I expect the path "([^"]*)" to lead to no node$/
     * @throws Exception
     */
    public function iExpectThePathToLeadToNoNode($nodePath)
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
     */
    public function theSubtreeForNodeAggregateWithNodeTypesAndLevelsDeepShouldBe($nodeAggregateIdentifier, $nodeTypeConstraints, $maximumLevels, TableNode $table)
    {
        $expectedRows = $table->getHash();
        $nodeAggregateIdentifier = new NodeAggregateIdentifier($this->replaceUuidIdentifiers($nodeAggregateIdentifier));
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
            if ($expectedRow['NodeAggregateIdentifier'] === 'ROOT') {
                Assert::assertNull($flattenedSubtree[$i]->getNode(), 'ROOT node was not correct at index ' . $i);
            } else {
                Assert::assertEquals($this->replaceUuidIdentifiers($expectedRow['NodeAggregateIdentifier']), (string)$flattenedSubtree[$i]->getNode()->getNodeAggregateIdentifier(), 'NodeAggregateIdentifier does not match in index ' . $i);
            }
        }
    }

    private static function flattenSubtreeForComparison(SubtreeInterface $subtree, array &$result)
    {
        $result[] = $subtree;
        foreach ($subtree->getChildren() as $childSubtree) {
            self::flattenSubtreeForComparison($childSubtree, $result);
        }
    }

    /**
     * @var \Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddress[]
     */
    private $currentNodeAddresses;

    /**
     * @return \Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddress
     */
    protected function getCurrentNodeAddress(string $alias = null): \Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddress
    {
        if ($alias === null) {
            $alias = 'DEFAULT';
        }
        return $this->currentNodeAddresses[$alias];
    }

    /**
     * @return \Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddress[]
     */
    public function getCurrentNodeAddresses(): array
    {
        return $this->currentNodeAddresses;
    }

    /**
     * @Given /^I get the node address for node aggregate "([^"]*)"(?:, remembering it as "([^"]*)")?$/
     */
    public function iGetTheNodeAddressForNodeAggregate($nodeAggregateIdentifier, $alias = 'DEFAULT')
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByNodeAggregateIdentifier(new NodeAggregateIdentifier($nodeAggregateIdentifier));
        Assert::assertNotNull($node, 'Did find node with aggregate identifier "' . $nodeAggregateIdentifier . '"');

        /* @var $nodeAddressFactory NodeAddressFactory */
        $nodeAddressFactory = $this->getObjectManager()->get(NodeAddressFactory::class);
        $this->currentNodeAddresses[$alias] = $nodeAddressFactory->createFromNode($node);
    }

    /**
     * @Then /^I get the node address for the node at path "([^"]*)"(?:, remembering it as "([^"]*)")?$/
     * @throws Exception
     */
    public function iGetTheNodeAddressForTheNodeAtPath($nodePath, $alias = 'DEFAULT')
    {
        $node = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath($nodePath, RootNodeIdentifiers::rootNodeAggregateIdentifier());
        Assert::assertNotNull($node, 'Did find node at path "' . $nodePath . '"');

        /* @var $nodeAddressFactory NodeAddressFactory */
        $nodeAddressFactory = $this->getObjectManager()->get(NodeAddressFactory::class);
        $this->currentNodeAddresses[$alias] = $nodeAddressFactory->createFromNode($node);
    }

    /**
     * @Then /^I get the node at path "([^"]*)"$/
     * @throws Exception
     */
    public function iGetTheNodeAtPath($nodePath)
    {
        $this->currentNode = $this->contentGraph
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint, $this->visibilityConstraints)
            ->findNodeByPath($nodePath, RootNodeIdentifiers::rootNodeAggregateIdentifier());
    }

    protected function getNodeAggregateCommandHandler(): NodeAggregateCommandHandler
    {
        /** @var NodeAggregateCommandHandler $commandHandler */
        $commandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);

        return $commandHandler;
    }
}
