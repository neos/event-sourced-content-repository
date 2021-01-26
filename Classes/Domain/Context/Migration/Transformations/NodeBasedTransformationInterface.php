<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\Migration\Transformations;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;

/**
 * A node-specific transformation, like setting node properties.
 *
 * Settings given to a transformation will be passed to accordingly named setters.
 */
interface NodeBasedTransformationInterface
{
    public function execute(NodeInterface $node, ContentStreamIdentifier $contentStreamForWriting): CommandResult;
}
