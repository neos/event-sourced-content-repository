<?php

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * An immutable collection of NodeAggregateIdentifiers
 * @implements \IteratorAggregate<string,NodeAggregateIdentifier>
 */
#[Flow\Proxy(false)]
final class NodeAggregateIdentifierCollection implements \IteratorAggregate, \JsonSerializable
{
    /**
     * @var array<string,NodeAggregateIdentifier>
     */
    private array $nodeAggregateIdentifiers;

    /**
     * @var \ArrayIterator<string,NodeAggregateIdentifier>
     */
    private \ArrayIterator $iterator;

    /**
     * @param array<string,NodeAggregateIdentifier> $nodeAggregateIdentifiers
     */
    private function __construct(array $nodeAggregateIdentifiers)
    {
        $this->nodeAggregateIdentifiers = $nodeAggregateIdentifiers;
        $this->iterator = new \ArrayIterator($nodeAggregateIdentifiers);
    }

    public static function createEmpty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string|int,string> $array
     */
    public static function fromArray(array $array): self
    {
        $nodeAggregateIdentifiers = [];
        foreach ($array as $serializedNodeAggregateIdentifier) {
            $nodeAggregateIdentifiers[$serializedNodeAggregateIdentifier]
                = NodeAggregateIdentifier::fromString($serializedNodeAggregateIdentifier);
        }

        return new self($nodeAggregateIdentifiers);
    }

    public static function fromJsonString(string $jsonString): self
    {
        return self::fromArray(\json_decode($jsonString, true));
    }

    /**
     * @return array<string,NodeAggregateIdentifier>
     */
    public function jsonSerialize(): array
    {
        return $this->nodeAggregateIdentifiers;
    }

    public function __toString(): string
    {
        return \json_encode($this, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int,string>
     */
    public function toStringArray(): array
    {
        return array_keys($this->nodeAggregateIdentifiers);
    }

    /**
     * @return \ArrayIterator<string,NodeAggregateIdentifier>
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->iterator;
    }
}
