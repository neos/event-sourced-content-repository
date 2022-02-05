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
 * The exception to be thrown if a property type is invalid
 */
#[Flow\Proxy(false)]
final class PropertyTypeIsInvalid extends \DomainException
{
    public static function becauseItIsReference(PropertyName $propertyName, NodeTypeName $nodeTypeName): self
    {
        return new self('Given property "' . $propertyName . '" is declared as "reference" in node type "' . $nodeTypeName . '" and must be treated as such.', 1630063201);
    }

    public static function becauseItIsUndefined(PropertyName $propertyName, string $declaredType, NodeTypeName $nodeTypeName): self
    {
        return new self('Given property "' . $propertyName . '" is declared as undefined type "' . $declaredType . '" in node type "' . $nodeTypeName . '"', 1630063406);
    }
}
