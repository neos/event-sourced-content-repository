<?php
declare(strict_types=1);

namespace Neos\ContentRepository\Intermediary\Tests\Behavior\Features\Bootstrap;

/*
 * This file is part of the Neos.ContentGraph.DoctrineDbalAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeProperties;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateIdentifiersByNodePaths;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\PropertyValuesToWrite;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Tests\Behavior\Fixtures\PostalAddress;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Image;

/**
 * Custom context trait for executing intermediary commands
 */
trait IntermediaryCommandTrait
{
    protected NodeAggregateCommandHandler $nodeAggregateCommandHandler;

    private ?\Exception $lastCommandException = null;

    protected ?Image $dummyImage = null;

    protected ResourceManager $resourceManager;

    abstract protected function getObjectManager(): ObjectManagerInterface;

    abstract protected function readPayloadTable(TableNode $payloadTable): array;

    public function setupIntermediaryCommandTrait(): void
    {
        $this->nodeAggregateCommandHandler = $this->getObjectManager()->get(NodeAggregateCommandHandler::class);
        $this->resourceManager = $this->getObjectManager()->get(ResourceManager::class);
    }

    /**
     * @When /^the intermediary command CreateNodeAggregateWithNode is executed with payload:$/
     * @param TableNode $payloadTable
     */
    public function theIntermediaryCommandCreateNodeAggregateWithNodeIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }

        $command = new CreateNodeAggregateWithNode(
            ContentStreamIdentifier::fromString($commandArguments['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($commandArguments['nodeAggregateIdentifier']),
            NodeTypeName::fromString($commandArguments['nodeTypeName']),
            new OriginDimensionSpacePoint($commandArguments['originDimensionSpacePoint']),
            UserIdentifier::fromString($commandArguments['initiatingUserIdentifier']),
            NodeAggregateIdentifier::fromString($commandArguments['parentNodeAggregateIdentifier']),
            isset($commandArguments['succeedingSiblingNodeAggregateIdentifier'])
                ? NodeAggregateIdentifier::fromString($commandArguments['succeedingSiblingNodeAggregateIdentifier'])
                : null,
            isset($commandArguments['nodeName'])
                ? NodeName::fromString($commandArguments['nodeName'])
                : null,
            isset($commandArguments['initialPropertyValues'])
                ? $this->unserializeProperties($commandArguments['initialPropertyValues'])
                : null,
            isset($commandArguments['tetheredDescendantNodeAggregateIdentifiers'])
                ? NodeAggregateIdentifiersByNodePaths::fromArray($commandArguments['tetheredDescendantNodeAggregateIdentifiers'])
                : null
        );

        $this->lastCommandOrEventResult = $this->nodeAggregateCommandHandler
            ->handleCreateNodeAggregateWithNode($command);
    }

    /**
     * @When /^the intermediary command CreateNodeAggregateWithNode is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     */
    public function theIntermediaryCommandCreateNodeAggregateWithNodeIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theIntermediaryCommandCreateNodeAggregateWithNodeIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }


    /**
     * @When the following intermediary CreateNodeAggregateWithNode commands are executed for content stream :contentStreamIdentifier and origin :originSpacePoint:
     * @throws JsonException
     */
    public function theFollowingCreateNodeAggregateWithNodeAndSerializedPropertiesCommandsAreExecuted(string $contentStreamIdentifier, string $originSpacePoint, TableNode $table): void
    {
        foreach ($table->getHash() as $row) {
            $command = new CreateNodeAggregateWithNode(
                ContentStreamIdentifier::fromString($contentStreamIdentifier),
                NodeAggregateIdentifier::fromString($row['nodeAggregateIdentifier']),
                NodeTypeName::fromString($row['nodeTypeName']),
                OriginDimensionSpacePoint::fromJsonString($originSpacePoint),
                UserIdentifier::forSystemUser(),
                NodeAggregateIdentifier::fromString($row['parentNodeAggregateIdentifier']),
                !empty($row['succeedingSiblingNodeAggregateIdentifier'])
                    ? NodeAggregateIdentifier::fromString($row['succeedingSiblingNodeAggregateIdentifier'])
                    : null,
                !empty($row['nodeName'])
                    ? NodeName::fromString($row['nodeName'])
                    : null,
                !empty($row['initialPropertyValues'])
                    ? PropertyValuesToWrite::fromArray(json_decode($row['initialPropertyValues'], true, 512, JSON_THROW_ON_ERROR))
                    : null,
                !empty($row['tetheredDescendantNodeAggregateIdentifiers'])
                    ? NodeAggregateIdentifiersByNodePaths::fromArray(json_decode($row['tetheredDescendantNodeAggregateIdentifiers'], true, 512, JSON_THROW_ON_ERROR))
                    : null
            );
            $this->lastCommandOrEventResult = $this->nodeAggregateCommandHandler
                ->handleCreateNodeAggregateWithNode($command);
            $this->theGraphProjectionIsFullyUpToDate();
        }
    }


    /**
     * @When /^the intermediary command SetNodeProperties is executed with payload:$/
     * @param TableNode $payloadTable
     */
    public function theIntermediaryCommandSetPropertiesIsExecutedWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        if (!isset($commandArguments['initiatingUserIdentifier'])) {
            $commandArguments['initiatingUserIdentifier'] = 'initiating-user-identifier';
        }

        $command = new SetNodeProperties(
            ContentStreamIdentifier::fromString($commandArguments['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($commandArguments['nodeAggregateIdentifier']),
            OriginDimensionSpacePoint::fromArray($commandArguments['originDimensionSpacePoint']),
            $this->unserializeProperties($commandArguments['propertyValues']),
            UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
        );

        $this->lastCommandOrEventResult = $this->nodeAggregateCommandHandler
            ->handleSetNodeProperties($command);
    }

    /**
     * @When /^the intermediary command SetNodeProperties is executed with payload and exceptions are caught:$/
     * @param TableNode $payloadTable
     */
    public function theIntermediaryCommandSetPropertiesIsExecutedWithPayloadAndExceptionsAreCaught(TableNode $payloadTable)
    {
        try {
            $this->theIntermediaryCommandSetPropertiesIsExecutedWithPayload($payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    private function unserializeProperties(array $properties): PropertyValuesToWrite
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

    private function requireDummyImage(): Image
    {
        if (!$this->dummyImage) {
            $resource = $this->resourceManager->importResource(__DIR__ . '/../../Fixtures/bat.jpg');
            $this->dummyImage = new Image($resource);
        }

        return $this->dummyImage;
    }
}
