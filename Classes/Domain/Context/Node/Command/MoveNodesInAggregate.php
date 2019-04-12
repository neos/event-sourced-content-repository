<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\Node\Command;

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
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\Node\CopyableAcrossContentStreamsInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\ReferencePosition;

/**
 * Move nodes in aggregate command
 *
 * Moves all nodes in the given `nodeAggregateIdentifier` to the `referencePosition` of the
 * `referenceNodeAggregateIdentifier`. Each node in the node aggregate needs a suitable
 * (visible in dimension space point) reference node in the referenced node aggregate.
 */
final class MoveNodesInAggregate implements \JsonSerializable, CopyableAcrossContentStreamsInterface
{

    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifier;

    /**
     * @var ReferencePosition
     */
    private $referencePosition;

    /**
     * @var NodeAggregateIdentifier
     */
    private $referenceNodeAggregateIdentifier;

    /**
     * MoveNodesInAggregate constructor.
     *
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param ReferencePosition $referencePosition
     * @param NodeAggregateIdentifier $referenceNodeAggregateIdentifier
     */
    public function __construct(ContentStreamIdentifier $contentStreamIdentifier, NodeAggregateIdentifier $nodeAggregateIdentifier, ReferencePosition $referencePosition, NodeAggregateIdentifier $referenceNodeAggregateIdentifier)
    {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->referencePosition = $referencePosition;
        $this->referenceNodeAggregateIdentifier = $referenceNodeAggregateIdentifier;
    }

    public static function fromArray(array $array): self
    {
        return new static(
            ContentStreamIdentifier::fromString($array['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($array['nodeAggregateIdentifier']),
            new ReferencePosition($array['referencePosition']),
            NodeAggregateIdentifier::fromString($array['referenceNodeAggregateIdentifier'])
        );
    }

    /**
     * @return ContentStreamIdentifier
     */
    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    /**
     * @return NodeAggregateIdentifier
     */
    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    /**
     * @return ReferencePosition
     */
    public function getReferencePosition(): ReferencePosition
    {
        return $this->referencePosition;
    }

    /**
     * @return NodeAggregateIdentifier
     */
    public function getReferenceNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->referenceNodeAggregateIdentifier;
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'referencePosition' => $this->referencePosition,
            'referenceNodeAggregateIdentifier' => $this->referenceNodeAggregateIdentifier
        ];
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): self
    {
        return new MoveNodesInAggregate(
            $targetContentStreamIdentifier,
            $this->nodeAggregateIdentifier,
            $this->referencePosition,
            $this->referenceNodeAggregateIdentifier
        );
    }
}
