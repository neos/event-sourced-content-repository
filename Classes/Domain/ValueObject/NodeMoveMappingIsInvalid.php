<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\ValueObject;

/**
 * The exception to be thrown if a node move mapping was was tried to be created without proper parameters
 */
class NodeMoveMappingIsInvalid extends \DomainException
{
}
