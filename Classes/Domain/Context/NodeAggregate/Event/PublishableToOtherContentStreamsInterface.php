<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event;

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

/**
 * This interface is implemented by **events** which can be published to different content streams.
 *
 * Reminder: Event Publishing to a target content stream can not fail if the source content stream is based
 *           on the target content stream, and no events have been committed to the target content stream in
 *           the meantime. This is because event's effects have to be fully deterministic.
 */
interface PublishableToOtherContentStreamsInterface
{
    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier);
}
