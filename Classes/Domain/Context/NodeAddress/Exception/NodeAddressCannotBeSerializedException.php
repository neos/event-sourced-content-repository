<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\Exception;

use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class NodeAddressCannotBeSerializedException extends \Neos\EventSourcedContentRepository\Exception
{
    public static function becauseNoWorkspaceNameWasResolved(NodeAddress $nodeAddress): self
    {
        return new self(
            'The node Address ' . $nodeAddress . ' cannot be serialized because no workspace name was resolved.',
            1531637028
        );
    }
}
