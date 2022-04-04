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

declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature;

use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeReferences;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeReferencesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\CommandResult;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\PropertyScope;
use Neos\EventSourcedContentRepository\Infrastructure\Projection\RuntimeBlocker;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Ramsey\Uuid\Uuid;

trait NodeReferencing
{
    use ConstraintChecks;

    abstract protected function getReadSideMemoryCacheManager(): ReadSideMemoryCacheManager;

    abstract protected function getNodeAggregateEventPublisher(): NodeAggregateEventPublisher;

    abstract protected function getRuntimeBlocker(): RuntimeBlocker;

    /**
     * @param SetNodeReferences $command
     * @return CommandResult
     * @throws \Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamDoesNotExistYet
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function handleSetNodeReferences(SetNodeReferences $command): CommandResult
    {
        $this->getReadSideMemoryCacheManager()->disableCache();

        $this->requireContentStreamToExist($command->contentStreamIdentifier);
        $this->requireDimensionSpacePointToExist(
            $command->sourceOriginDimensionSpacePoint->toDimensionSpacePoint()
        );
        $sourceNodeAggregate = $this->requireProjectedNodeAggregate(
            $command->contentStreamIdentifier,
            $command->sourceNodeAggregateIdentifier
        );
        $this->requireNodeAggregateToNotBeRoot($sourceNodeAggregate);
        $this->requireNodeAggregateToOccupyDimensionSpacePoint(
            $sourceNodeAggregate,
            $command->sourceOriginDimensionSpacePoint
        );
        $this->requireNodeTypeToDeclareReference($sourceNodeAggregate->getNodeTypeName(), $command->referenceName);
        foreach ($command->destinationNodeAggregateIdentifiers as $destinationNodeAggregateIdentifier) {
            $destinationNodeAggregate = $this->requireProjectedNodeAggregate(
                $command->contentStreamIdentifier,
                $destinationNodeAggregateIdentifier
            );
            $this->requireNodeAggregateToNotBeRoot($destinationNodeAggregate);
            $this->requireNodeAggregateToCoverDimensionSpacePoint(
                $destinationNodeAggregate,
                $command->sourceOriginDimensionSpacePoint->toDimensionSpacePoint()
            );
            $this->requireNodeTypeToAllowNodesOfTypeInReference(
                $sourceNodeAggregate->getNodeTypeName(),
                $command->referenceName,
                $destinationNodeAggregate->getNodeTypeName()
            );
        }

        $domainEvents = DomainEvents::createEmpty();
        $this->getNodeAggregateEventPublisher()->withCommand(
            $command,
            function () use ($command, &$domainEvents, $sourceNodeAggregate) {
                $events = [];
                $sourceNodeType = $this->requireNodeType($sourceNodeAggregate->getNodeTypeName());
                $declaration = $sourceNodeType->getProperties()[$command->referenceName->value]['scope'] ?? null;
                if (is_string($declaration)) {
                    $scope = PropertyScope::from($declaration);
                } else {
                    $scope = PropertyScope::SCOPE_NODE;
                }
                $affectedOrigins = $scope->resolveAffectedOrigins(
                    $command->sourceOriginDimensionSpacePoint,
                    $sourceNodeAggregate,
                    $this->interDimensionalVariationGraph
                );
                foreach ($affectedOrigins as $originDimensionSpacePoint) {
                    $events[] = DecoratedEvent::addIdentifier(
                        new NodeReferencesWereSet(
                            $command->contentStreamIdentifier,
                            $command->sourceNodeAggregateIdentifier,
                            $originDimensionSpacePoint,
                            $command->destinationNodeAggregateIdentifiers,
                            $command->referenceName,
                            $command->initiatingUserIdentifier
                        ),
                        Uuid::uuid4()->toString()
                    );
                }

                $domainEvents = DomainEvents::fromArray($events);
                $this->getNodeAggregateEventPublisher()->publishMany(
                    ContentStreamEventStreamName::fromContentStreamIdentifier($command->contentStreamIdentifier)
                        ->getEventStreamName(),
                    $domainEvents
                );
            }
        );

        return CommandResult::fromPublishedEvents($domainEvents, $this->getRuntimeBlocker());
    }
}
