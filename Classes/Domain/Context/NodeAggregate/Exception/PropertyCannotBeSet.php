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

use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a property was attempted to be set but cannot be
 */
#[Flow\Proxy(false)]
final class PropertyCannotBeSet extends \DomainException
{
    public static function becauseTheValueDoesNotMatchTheConfiguredType(
        PropertyName $propertyName,
        string $attemptedType,
        string $configuredType
    ): self {
        return new self(
            'Property "' . $propertyName . '" cannot be set to a value of type "' . $attemptedType
                . '", must be of type "' . $configuredType . '".',
            1615466573
        );
    }

    public static function becauseTheNodeTypeDoesNotDeclareIt(
        PropertyName $propertyName,
        NodeTypeName $nodeTypeName
    ): self {
        return new self(
            'Property "' . $propertyName . '" cannot be set because node type "'
                . $nodeTypeName . '" does not declare it.',
            1615664798
        );
    }
}
