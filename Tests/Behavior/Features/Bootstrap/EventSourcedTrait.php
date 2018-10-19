<?php

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
use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\RemoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\RemoveNodesFromAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Node\RelationDistributionStrategy;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\ChangeNodeAggregateType;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeGeneralization;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ValueObject\NodeIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValue;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
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
     * @var \Neos\Flow\ObjectManagement\ObjectManager
     */
    private $objectManager;

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
     * @var array
     */
    private $currentEventStreamAsArray = null;

    /**
     * @var \Exception
     */
    private $lastCommandException = null;

    /**
     * @var NodeIdentifier
     */
    protected $rootNodeIdentifier;

    /**
     * @var \Neos\EventSourcedContentRepository\Domain\Model\NodeInterface
     */
    protected $currentNode;

    /**
     * @BeforeScenario @fixtures
     * @return void
     * @throws \Exception
     */
    public function beforeEventSourcedScenarioDispatcher()
    {
        $this->contentGraphInterface->resetCache();
        $this->workspaceFinder->resetCache();
    }

    /**
     * @Given /^the Event RootNodeWasCreated was published with payload:$/
     * @throws Exception
     */
    public function theEventRootNodeWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(new ContentStreamIdentifier($eventPayload['contentStreamIdentifier']));
        $this->publishEvent('Neos.ContentRepository:RootNodeWasCreated', $streamName, $eventPayload);
        $this->rootNodeIdentifier = new NodeIdentifier($eventPayload['nodeIdentifier']);
    }

    /**
     * @Given /^the Event NodeAggregateWithNodeWasCreated was published with payload:$/
     * @throws Exception
     */
    public function theEventNodeAggregateWithNodeWasCreatedWasPublishedToStreamWithPayload(TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        if (empty($eventPayload['dimensionSpacePoint'])) {
            $eventPayload['dimensionSpacePoint'] = ['coordinates' => []];
        }
        if (empty($eventPayload['visibleDimensionSpacePoints'])) {
            $eventPayload['visibleDimensionSpacePoints'] =  new DimensionSpacePointSet(
                [
                    new DimensionSpacePoint([])
                ]
            );
        }
        if (empty($eventPayload['propertyDefaultValuesAndTypes'])) {
            $eventPayload['propertyDefaultValuesAndTypes'] = [];
        }

        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier(new ContentStreamIdentifier($eventPayload['contentStreamIdentifier']));
        $streamName = $this->replaceUuidIdentifiers($streamName);
        $streamName .= ':NodeAggregate:' . $eventPayload['nodeAggregateIdentifier'];
        $this->publishEvent('Neos.ContentRepository:NodeAggregateWithNodeWasCreated', $streamName, $eventPayload);
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
        $this->publishEvent('Neos.ContentRepository:NodeSpecializationWasCreated', $streamName, $eventPayload);
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
        $this->publishEvent('Neos.ContentRepository:NodeGeneralizationWasCreated', $streamName, $eventPayload);
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

        $this->eventPublisher->publish($streamName, $event);
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
                    case 'DimensionSpacePoint':
                        $eventPayload[$line['Key']] = new DimensionSpacePoint(json_decode($line['Value'], true));
                        break;
                    case 'DimensionSpacePointSet':
                        $tmp = json_decode($line['Value'], true);
                        $convertedPoints = [];
                        if (isset($tmp['points'])) {
                            foreach ($tmp['points'] as $point) {
                                $convertedPoints[] = new DimensionSpacePoint($point['coordinates']);
                            }
                        }
                        $eventPayload[$line['Key']] = new DimensionSpacePointSet($convertedPoints);
                        break;
                    case 'NodeAggregateIdentifier':
                        $eventPayload[$line['Key']] = new NodeAggregateIdentifier($line['Value']);
                        break;
                    case 'PropertyValue':
                        $tmp = json_decode($line['Value'], true);
                        $eventPayload[$line['Key']] = new PropertyValue($tmp['value'], $tmp['type']);
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

    protected function replaceUuidIdentifiers($identifierString)
    {
        return preg_replace_callback(
            '#\[[0-9a-zA-Z\-]+\]#',
            function ($matches) {
                return (string)Uuid::uuid5('00000000-0000-0000-0000-000000000000', $matches[0]);
            },
            $identifierString
        );
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
        $commandHandler = $this->objectManager->get(NodeAggregateCommandHandler::class);

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
        $commandHandler = $this->objectManager->get(NodeCommandHandler::class);

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
        $commandHandler = $this->objectManager->get(NodeCommandHandler::class);

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
        $commandHandler = $this->objectManager->get(NodeAggregateCommandHandler::class);

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
        $commandHandler = $this->objectManager->get(NodeAggregateCommandHandler::class);

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
                    is_string($commandArguments['nodeAggregateIdentifier']) ? new NodeAggregate\NodeAggregateIdentifier($commandArguments['nodeAggregateIdentifier']) : $commandArguments['nodeAggregateIdentifier'],
                    is_string($commandArguments['newParentNodeAggregateIdentifier']) ? new NodeAggregate\NodeAggregateIdentifier($commandArguments['newParentNodeAggregateIdentifier']) : $commandArguments['newParentNodeAggregateIdentifier'],
                    is_string($commandArguments['newSucceedingSiblingNodeAggregateIdentifier']) ? new NodeAggregate\NodeAggregateIdentifier($commandArguments['newSucceedingSiblingNodeAggregateIdentifier']) : $commandArguments['newSucceedingSiblingNodeAggregateIdentifier'],
                    is_string($commandArguments['relationDistributionStrategy']) ? RelationDistributionStrategy::fromConfigurationValue($commandArguments['relationDistributionStrategy']) : $commandArguments['relationDistributionStrategy']
                );
                break;
            default:
                $configuration = new \Neos\EventSourcing\Property\AllowAllPropertiesPropertyMappingConfiguration();
                $command = $this->propertyMapper->convert($commandArguments, $commandClassName, $configuration);
        }

        $commandHandler = $this->objectManager->get($commandHandlerClassName);

        $commandHandler->$commandHandlerMethod($command);

        if (isset($commandArguments['rootNodeIdentifier'])) {
            $this->rootNodeIdentifier = new NodeIdentifier($commandArguments['rootNodeIdentifier']);
        }
    }

    /**
     * @When /^the command CreateWorkspace is executed with payload:$/
     * @throws Exception
     */
    public function theCommandCreateWorkspaceIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);

        $commandArguments['workspaceTitle'] = ucfirst($commandArguments['workspaceName']);
        $commandArguments['workspaceDescription'] = 'The workspace "' . $commandArguments['workspaceName'] . '".';
        $commandArguments['initiatingUserIdentifier'] = $this->replaceUuidIdentifiers('[initiatingUserIdentifier]');
        $commandArguments['rootNodeTypeName'] = !empty($commandArguments['rootNodeTypeName']) ? $commandArguments['rootNodeTypeName'] : 'Neos.ContentRepository:Root';

        $commandName = 'CreateRootWorkspace';
        if (!empty($commandArguments['baseWorkspaceName'])) {
            $commandArguments['workspaceOwner'] = $this->replaceUuidIdentifiers('[workspaceOwner]');
            $commandName = 'CreateWorkspace';
        }

        $this->theCommandIsExecutedWithPayload($commandName, null, $commandArguments);
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
            case 'NodeNotFoundException':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Exception\NodeNotFoundException::class, $this->lastCommandException);

                return;
            case 'BaseWorkspaceHasBeenModifiedInTheMeantime':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\BaseWorkspaceHasBeenModifiedInTheMeantime::class, $this->lastCommandException);

                return;
            case 'NodeAggregateNotFound':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Domain\Context\Node\NodeAggregateNotFound::class, $this->lastCommandException);

                return;
            case 'NodeExistsException':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Exception\NodeExistsException::class, $this->lastCommandException);

                return;
            case 'NodeConstraintException':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Exception\NodeConstraintException::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsNoSpecialization':
                Assert::assertInstanceOf(\Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointIsNoSpecialization::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsAlreadyOccupied':
                Assert::assertInstanceOf(NodeAggregate\DimensionSpacePointIsAlreadyOccupied::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsNotYetOccupied':
                Assert::assertInstanceOf(NodeAggregate\DimensionSpacePointIsNotYetOccupied::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointIsNoGeneralization':
                Assert::assertInstanceOf(\Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointIsNoGeneralization::class, $this->lastCommandException);

                return;
            case 'NodeTypeNotFound':
                Assert::assertInstanceOf(NodeAggregate\NodeTypeNotFound::class, $this->lastCommandException);

                return;
            case 'DimensionSpacePointNotFound':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Exception\DimensionSpacePointNotFound::class, $this->lastCommandException);

                return;
            case 'ParentsNodeAggregateNotVisibleInDimensionSpacePoint':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Domain\Context\Node\ParentsNodeAggregateNotVisibleInDimensionSpacePoint::class, $this->lastCommandException);

                return;
            case 'SpecializedDimensionsMustBePartOfDimensionSpacePointSet':
                Assert::assertInstanceOf(\Neos\EventSourcedContentRepository\Domain\Context\Node\SpecializedDimensionsMustBePartOfDimensionSpacePointSet::class, $this->lastCommandException);

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
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler::class,
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
            case 'RebaseWorkspace':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\RebaseWorkspace::class,
                    \Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler::class,
                    'handleRebaseWorkspace'
                ];
            case 'CreateRootNode':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\CreateRootNode::class,
                    NodeCommandHandler::class,
                    'handleCreateRootNode'
                ];
            case 'CreateNodeAggregateWithNode':
                return [
                    \Neos\EventSourcedContentRepository\Domain\Context\Node\Command\CreateNodeAggregateWithNode::class,
                    NodeCommandHandler::class,
                    'handleCreateNodeAggregateWithNode'
                ];
            case 'ForkContentStream':
                return [
                    ContentStream\Command\ForkContentStream::class,
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
            if (isset($assertionTableRow['Type']) && $assertionTableRow['Type'] == 'Uuid') {
                $expectedValue = $this->replaceUuidIdentifiers('[' . $assertionTableRow['Expected'] . ']');
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
    private $contentGraphInterface;

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
     * @Then /^I expect a node "([^"]*)" to exist in the graph projection$/
     */
    public function iExpectANodeToExistInTheGraphProjection($nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        Assert::assertNotNull($node, 'Node "' . $nodeIdentifier . '" was not found in the current Content Stream / Dimension Space Point.');
    }

    /**
     * @Then /^I expect a node "([^"]*)" not to exist in the graph projection$/
     */
    public function iExpectANodeNotToExistInTheGraphProjection($nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        // we're not using assertNull here: If $node is not null, the
        Assert::assertTrue($node === null, 'Node "' . $nodeIdentifier . '" was found in the current Content Stream / Dimension Space Point.');
    }

    /**
     * @Then /^I expect a node identified by aggregate identifier "([^"]*)" to exist in the subgraph$/
     * @param string $nodeAggregateIdentifier
     */
    public function iExpectANodeIdentifiedByAggregateIdentifierToExistInTheSubgraph(string $nodeAggregateIdentifier)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByNodeAggregateIdentifier(new NodeAggregate\NodeAggregateIdentifier($nodeAggregateIdentifier));
        Assert::assertNotNull($node, 'Node with aggregate identifier "' . $nodeAggregateIdentifier . '" was not found in the current Content Stream / Dimension Space Point.');
    }

    /**
     * @Then /^I expect a node identified by aggregate identifier "([^"]*)" not to exist in the subgraph$/
     * @param string $nodeAggregateIdentifier
     */
    public function iExpectANodeIdentifiedByAggregateIdentifierNotToExistInTheSubgraph(string $nodeAggregateIdentifier)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByNodeAggregateIdentifier(new NodeAggregate\NodeAggregateIdentifier($nodeAggregateIdentifier));
        Assert::assertNotNull($node, 'Node with aggregate identifier "' . $nodeAggregateIdentifier . '" was not found in the current Content Stream / Dimension Space Point.');
    }

    /**
     * @Then /^I expect the node "([^"]*)" to have the following child nodes:$/
     */
    public function iExpectTheNodeToHaveTheFollowingChildNodes($nodeIdentifier, TableNode $expectedChildNodesTable)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $nodes = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findChildNodes(new NodeIdentifier($nodeIdentifier));
        Assert::assertCount(count($expectedChildNodesTable->getHash()), $nodes, 'Child Node Count does not match');
        foreach ($expectedChildNodesTable->getHash() as $index => $row) {
            Assert::assertEquals($row['Name'], (string)$nodes[$index]->getNodeName(), 'Node name in index ' . $index . ' does not match. Actual: ' . $nodes[$index]->getNodeTypeName());
            Assert::assertEquals($row['NodeIdentifier'], (string)$nodes[$index]->getNodeIdentifier(), 'Node identifier in index ' . $index . ' does not match.');
        }
    }

    /**
     * @Then /^I expect the Node Aggregate "([^"]*)" to resolve to node "([^"]*)"$/
     */
    public function iExpectTheNodeAggregateToHaveTheNodes($nodeAggregateIdentifier, $nodeIdentifier)
    {
        $nodeAggregateIdentifier = $this->replaceUuidIdentifiers($nodeAggregateIdentifier);
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByNodeAggregateIdentifier(new NodeAggregate\NodeAggregateIdentifier($nodeAggregateIdentifier));
        Assert::assertNotNull($node, 'Node with ID "' . $nodeIdentifier . '" not found!');
        Assert::assertEquals($nodeIdentifier, (string)$node->getNodeIdentifier(), 'Node ID does not match!');
    }


    /**
     * @Then /^I expect the Node "([^"]*)" to have the type "([^"]*)"$/
     */
    public function iExpectTheNodeToHaveTheType($nodeIdentifier, $nodeType)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        Assert::assertEquals($nodeType, (string)$node->getNodeTypeName(), 'Node Type names do not match');
    }

    /**
     * @Then /^I expect the Node "([^"]*)" to have the properties:$/
     */
    public function iExpectTheNodeToHaveTheProperties($nodeIdentifier, TableNode $expectedProperties)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        /** @var \Neos\EventSourcedContentRepository\Domain\Model\Node $node */
        $this->currentNode = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
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
     * @Then /^I expect the Node "([^"]*)" to have the references:$/
     */
    public function iExpectTheNodeToHaveTheReferences($nodeIdentifier, TableNode $expectedReferences)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $expectedReferences = $this->readPayloadTable($expectedReferences);

        /** @var \Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface $subgraph */
        $subgraph = $this->contentGraphInterface->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint);

        foreach ($expectedReferences as $propertyName => $expectedDestinationNodeAggregateIdentifiers) {
            $destinationNodes = $subgraph->findReferencedNodes(new NodeIdentifier($nodeIdentifier), new PropertyName($propertyName));
            $destinationNodeAggregateIdentifiers = array_map(
                function ($item) {
                    if ($item instanceof \Neos\ContentRepository\Domain\Projection\Content\NodeInterface) {
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
     * @Then /^I expect the Node "([^"]*)" to be referenced by:$/
     */
    public function iExpectTheNodeToBeReferencedBy($nodeIdentifier, TableNode $expectedReferences)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $expectedReferences = $this->readPayloadTable($expectedReferences);

        /** @var \Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface $subgraph */
        $subgraph = $this->contentGraphInterface->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint);

        foreach ($expectedReferences as $propertyName => $expectedDestinationNodeAggregateIdentifiers) {
            $destinationNodes = $subgraph->findReferencingNodes(new NodeIdentifier($nodeIdentifier), new PropertyName($propertyName));
            $destinationNodeAggregateIdentifiers = array_map(
                function ($item) {
                    if ($item instanceof \Neos\ContentRepository\Domain\Projection\Content\NodeInterface) {
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
     * @Then /^I expect the Node "([^"]*)" is hidden$/
     */
    public function iExpectTheNodeIsHidden($nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        /** @var \Neos\EventSourcedContentRepository\Domain\Model\Node $node */
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        Assert::assertEquals(true, $node->isHidden(), 'Node is visible. Expected: hidden;');
    }

    /**
     * @Then /^I expect the Node "([^"]*)" is shown/
     */
    public function iExpectTheNodeIsShown($nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        /** @var \Neos\EventSourcedContentRepository\Domain\Model\Node $node */
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByIdentifier(new NodeIdentifier($nodeIdentifier));
        Assert::assertEquals(false, $node->isHidden(), 'Node is hidden. Expected: shown;');
    }

    /**
     * @Then /^I expect the path "([^"]*)" to lead to the node "([^"]*)"$/
     * @throws Exception
     */
    public function iExpectThePathToLeadToTheNode($nodePath, $nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        if (!$this->rootNodeIdentifier) {
            throw new \Exception('ERROR: RootNodeIdentifier needed for running this step. You need to use "the Event RootNodeWasCreated was published with payload" to create a root node..');
        }
        $this->currentNode = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByPath($nodePath, $this->rootNodeIdentifier);
        Assert::assertNotNull($this->currentNode, 'Did not find node at path "' . $nodePath . '"');
        Assert::assertEquals($nodeIdentifier, (string)$this->currentNode->getNodeIdentifier(), 'Node identifier does not match.');
    }

    /**
     * @When /^I go to the parent node of node "([^"]*)"$/
     */
    public function iGoToTheParentNodeOfNode($nodeIdentifier)
    {
        $nodeIdentifier = $this->replaceUuidIdentifiers($nodeIdentifier);
        $this->currentNode = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findParentNode(new NodeIdentifier($nodeIdentifier));
    }

    /**
     * @Then /^I expect the path "([^"]*)" to lead to no node$/
     * @throws Exception
     */
    public function iExpectThePathToLeadToNoNode($nodePath)
    {
        if (!$this->rootNodeIdentifier) {
            throw new \Exception('ERROR: RootNodeIdentifier needed for running this step. You need to use "the Event RootNodeWasCreated was published with payload" to create a root node..');
        }
        $node = $this->contentGraphInterface
            ->getSubgraphByIdentifier($this->contentStreamIdentifier, $this->dimensionSpacePoint)
            ->findNodeByPath($nodePath, $this->rootNodeIdentifier);
        Assert::assertNull($node, 'Did find node at path "' . $nodePath . '"');
    }
}
