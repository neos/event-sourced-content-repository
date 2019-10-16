<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\ContentStream;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\StreamName;

/**
 * A content stream to write events into
 *
 * Content streams contain an arbitrary amount of node aggregates that can be retrieved by identifier
 */
final class ContentStream
{
    /**
     * @var ContentStreamIdentifier
     */
    private $identifier;

    /**
     * @var StreamName
     */
    private $streamName;

    /**
     * @var NodeAggregateEventPublisher
     */
    private $nodeEventPublisher;

    /**
     * @var EventStore
     */
    private $eventStore;


    public function __construct(ContentStreamIdentifier $identifier, EventStore $eventStore, NodeAggregateEventPublisher $nodeEventPublisher)
    {
        $this->identifier = $identifier;
        $this->streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($identifier)->getEventStreamName();
        $this->eventStore = $eventStore;
        $this->nodeEventPublisher = $nodeEventPublisher;
    }

    public function getVersion(): int
    {
        // TODO !!! PLEASE CHANGE THIS!!!
        // TODO hack!! The new Event Store does not have a getStreamVersion() method any longer - we should probably use the reconstitution version from an aggregate instead
        return count(iterator_to_array($this->eventStore->load($this->streamName))) - 1;
    }

    public function __toString(): string
    {
        return $this->identifier->__toString();
    }
}
