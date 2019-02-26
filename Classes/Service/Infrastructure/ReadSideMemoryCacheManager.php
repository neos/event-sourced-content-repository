<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Service\Infrastructure;

/*
 * This file is part of the Neos.ContentGraph.DoctrineDbalAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\Flow\Annotations as Flow;

/**
 * A central place to enable / disable all caches in the read side
 *
 * @Flow\Scope("singleton")
 */
class ReadSideMemoryCacheManager
{

    /**
     * @Flow\Inject
     * @var ContentGraphInterface
     */
    protected $contentGraph;

    public function enableCache(): void
    {
        $this->contentGraph->enableCache();
    }

    public function disableCache(): void
    {
        $this->contentGraph->disableCache();
    }
}
