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

use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeVariantAssignment;
use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if an invalid node variant assignment is to be used
 */
#[Flow\Proxy(false)]
final class NodeVariantAssignmentCollectionIsInvalid extends \DomainException
{
    public static function becauseItContainsSomethingOther(): NodeVariantAssignmentCollectionIsInvalid
    {
        return new self(
            'Given node variant assignment collection is invalid because it contains an item of another type than '
                . NodeVariantAssignment::class,
            1571045106
        );
    }
}
