<?php

namespace Neos\EventSourcedContentRepository\Tests\Unit\Domain\Context\DimensionSpace;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\EventSourcedContentRepository\Domain\Context\Dimension;
use Neos\EventSourcedContentRepository\Domain\Context\DimensionSpace;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Utility\ObjectAccess;

/**
 * Unit test cases for the inter dimensional variation graph
 */
class InterDimensionalVariationGraphTest extends UnitTestCase
{
    /**
     * @test
     */
    public function determineWeightNormalizationBaseEvaluatesToMaximumDimensionDepthPlusOne()
    {
        $firstDummy = new Dimension\ContentDimensionValue('firstDummy');
        $firstDimension = new Dimension\ContentDimension(new Dimension\ContentDimensionIdentifier('first'), [$firstDummy], $firstDummy);
        $firstDepth = new Dimension\ContentDimensionValueSpecializationDepth(random_int(0, 100));
        ObjectAccess::setProperty($firstDimension, 'maximumDepth', $firstDepth, true);

        $secondDummy = new Dimension\ContentDimensionValue('secondDummy');
        $secondDimension = new Dimension\ContentDimension(new Dimension\ContentDimensionIdentifier('second'), [$secondDummy], $secondDummy);
        $secondDepth = new Dimension\ContentDimensionValueSpecializationDepth(random_int(0, 100));
        ObjectAccess::setProperty($secondDimension, 'maximumDepth', $secondDepth, true);

        /** @var DimensionSpace\InterDimensionalVariationGraph $graph */
        $graph = $this->getAccessibleMock(DimensionSpace\InterDimensionalVariationGraph::class, ['dummy']);
        $this->inject($graph, 'contentDimensionSource', $this->createDimensionSourceMock(['first' => $firstDimension, 'second' => $secondDimension]));

        $this->assertSame(max($firstDepth->getDepth(), $secondDepth->getDepth()) + 1, $graph->_call('determineWeightNormalizationBase'));
    }

    /**
     * @param array|Dimension\ContentDimension[] $contentDimensions
     * @return Dimension\ContentDimensionSourceInterface
     */
    protected function createDimensionSourceMock(array $contentDimensions): Dimension\ContentDimensionSourceInterface
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Dimension\ContentDimensionSourceInterface $mockDimensionSource */
        $mockDimensionSource = $this->createMock(Dimension\ContentDimensionSourceInterface::class);
        $mockDimensionSource->method('getContentDimensionsOrderedByPriority')
            ->willReturn($contentDimensions);

        return $mockDimensionSource;
    }
}
