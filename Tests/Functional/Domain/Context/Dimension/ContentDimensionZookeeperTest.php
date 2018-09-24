<?php
namespace Neos\EventSourcedContentRepository\Tests\Functional\Domain\Context\Dimension;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\Dimension;
use Neos\EventSourcedContentRepository\Tests\Functional\Domain\Context\Dimension\Fixtures\ExampleDimensionSource;
use Neos\Flow\Tests\FunctionalTestCase;

/**
 * Functional test cases for the ContentDimensionZookeeper
 */
class ContentDimensionZookeeperTest extends FunctionalTestCase
{
    /**
     * @var Dimension\ContentDimensionZookeeper
     */
    protected $subject;

    /**
     * @var ExampleDimensionSource
     */
    protected $dimensionSource;


    public function setUp()
    {
        parent::setUp();

        $this->subject = new Dimension\ContentDimensionZookeeper();
        $this->dimensionSource = new ExampleDimensionSource();
        $this->inject($this->subject, 'contentDimensionSource', $this->dimensionSource);
    }

    /**
     * @test
     */
    public function getAllowedCombinationsCorrectlyDeterminesAllowedCombinations()
    {
        $allowedCombinations = $this->subject->getAllowedCombinations();
        $marketIdentifier = new Dimension\ContentDimensionIdentifier('market');
        $languageIdentifier = new Dimension\ContentDimensionIdentifier('language');

        $this->assertSame(6, count($allowedCombinations));
        $this->assertContains([
            'market' => $this->dimensionSource->getDimension($marketIdentifier)->getValue('CH'),
            'language' => $this->dimensionSource->getDimension($languageIdentifier)->getValue('de')
        ], $allowedCombinations);
        $this->assertContains([
            'market' => $this->dimensionSource->getDimension($marketIdentifier)->getValue('CH'),
            'language' => $this->dimensionSource->getDimension($languageIdentifier)->getValue('fr')
        ], $allowedCombinations);
        $this->assertContains([
            'market' => $this->dimensionSource->getDimension($marketIdentifier)->getValue('CH'),
            'language' => $this->dimensionSource->getDimension($languageIdentifier)->getValue('it')
        ], $allowedCombinations);
        $this->assertContains([
            'market' => $this->dimensionSource->getDimension($marketIdentifier)->getValue('LU'),
            'language' => $this->dimensionSource->getDimension($languageIdentifier)->getValue('de')
        ], $allowedCombinations);
        $this->assertContains([
            'market' => $this->dimensionSource->getDimension($marketIdentifier)->getValue('LU'),
            'language' => $this->dimensionSource->getDimension($languageIdentifier)->getValue('fr')
        ], $allowedCombinations);
        $this->assertContains([
            'market' => $this->dimensionSource->getDimension($marketIdentifier)->getValue('LU'),
            'language' => $this->dimensionSource->getDimension($languageIdentifier)->getValue('lb')
        ], $allowedCombinations);
    }
}
