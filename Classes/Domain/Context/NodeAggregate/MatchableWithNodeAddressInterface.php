<?php
declare(strict_types=1);

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

use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;

/**
 * This interface must be implemented by all commands, such that they are filterable whether
 * they are applying their action to a NodeAddress.
 *
 * This is needed to publish individual nodes.
 */
interface MatchableWithNodeAddressInterface
{
    public function matchesNodeAddress(NodeAddress $nodeAddress): bool;
}
