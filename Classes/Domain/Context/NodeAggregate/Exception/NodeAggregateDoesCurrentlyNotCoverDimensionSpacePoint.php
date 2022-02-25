<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a node aggregate does currently not cover a given dimension space point
 * but is supposed to be
 */
#[Flow\Proxy(false)]
final class NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint extends \DomainException
{
    public static function butWasSupposedTo(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        DimensionSpacePoint $coveredDimensionSpacePoint
    ): self {
        return new self(
            'Node aggregate "' . $nodeAggregateIdentifier
                . '" does currently not cover dimension space point ' . $coveredDimensionSpacePoint,
            1554902892
        );
    }
}
