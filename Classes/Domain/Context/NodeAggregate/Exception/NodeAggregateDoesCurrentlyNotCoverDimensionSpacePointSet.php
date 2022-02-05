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

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a node aggregate does currently not cover the given dimension space point set but is supposed to
 */
#[Flow\Proxy(false)]
final class NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet extends \DomainException
{
    public static function butWasSupposedTo(
        NodeAggregateIdentifier $identifier,
        DimensionSpacePointSet $expectedCoveredDimensionSpacePointSet,
        DimensionSpacePointSet $actualDimensionSpacePointSet
    ): NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet {
        return new self('Node aggregate "' . $identifier . '" does not cover expected dimension space point set ' . $expectedCoveredDimensionSpacePointSet . ' but ' . $actualDimensionSpacePointSet . '.', 1571134743);
    }
}
