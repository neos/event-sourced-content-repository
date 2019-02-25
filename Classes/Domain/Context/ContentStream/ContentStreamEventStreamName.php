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

use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\EventSourcing\EventStore\StreamName;

/**
 * A content stream's event stream name
 */
final class ContentStreamEventStreamName
{
    /**
     * @var string
     */
    protected $eventStreamName;

    protected function __construct(string $eventStreamName)
    {
        $this->eventStreamName = $eventStreamName;
    }

    public static function fromContentStreamIdentifier(ContentStreamIdentifier $contentStreamIdentifier): self
    {
        return new ContentStreamEventStreamName('Neos.ContentRepository:ContentStream:' . $contentStreamIdentifier);
    }

    public function getEventStreamName(): StreamName
    {
        return StreamName::fromString($this->eventStreamName);
    }

    public function __toString(): string
    {
        return $this->eventStreamName;
    }
}
