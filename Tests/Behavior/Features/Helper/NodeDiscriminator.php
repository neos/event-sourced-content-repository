<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Cache\CacheAwareInterface;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;

/**
 * The node discriminator value object
 *
 * Represents the identity of a specific node in the content graph and is thus composed of
 * * the content stream the node exists in
 * * the node's aggregate's external identifier
 * * the dimension space point the node originates in within its aggregate
 *
 * @package Neos\EventSourcedContentRepository
 */
final class NodeDiscriminator implements CacheAwareInterface, \JsonSerializable
{
    /**
     * @var ContentStreamIdentifier
     */
    protected $contentStreamIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    protected $nodeAggregateIdentifier;

    /**
     * @var OriginDimensionSpacePoint
     */
    protected $originDimensionSpacePoint;

    private function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        OriginDimensionSpacePoint $originDimensionSpacePoint
    ) {
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->originDimensionSpacePoint = $originDimensionSpacePoint;
    }

    public static function fromArray(array $array): self
    {
        return new NodeDiscriminator(
            ContentStreamIdentifier::fromString($array['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($array['nodeAggregateIdentifier']),
            new OriginDimensionSpacePoint($array['originDimensionSpacePoint'])
        );
    }

    public static function fromNode(NodeInterface $node): self
    {
        return new NodeDiscriminator(
            $node->getContentStreamIdentifier(),
            $node->getNodeAggregateIdentifier(),
            $node->getOriginDimensionSpacePoint()
        );
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    public function getOriginDimensionSpacePoint(): OriginDimensionSpacePoint
    {
        return $this->originDimensionSpacePoint;
    }

    public function getCacheEntryIdentifier(): string
    {
        return sha1(json_encode($this));
    }

    public function equals(NodeDiscriminator $other): bool
    {
        return $this->contentStreamIdentifier->equals($other->getContentStreamIdentifier())
            && $this->getNodeAggregateIdentifier()->equals($other->getNodeAggregateIdentifier())
            && $this->getOriginDimensionSpacePoint()->equals($other->getOriginDimensionSpacePoint());
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'originDimensionSpacePoint' => $this->originDimensionSpacePoint
        ];
    }

    public function __toString(): string
    {
        return $this->getCacheEntryIdentifier();
    }
}
