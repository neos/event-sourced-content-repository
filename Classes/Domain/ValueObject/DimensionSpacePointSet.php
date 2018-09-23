<?php
namespace Neos\EventSourcedContentRepository\Domain\ValueObject;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * A set of points in the dimension space.
 *
 * E.g.: {[language => es, country => ar], [language => es, country => es]}
 */
final class DimensionSpacePointSet implements \JsonSerializable
{
    /**
     * @var array<DimensionSpacePoint>
     */
    private $points;

    /**
     * @param array<DimensionSpacePoint> $points Array of dimension space points
     */
    public function __construct(array $points)
    {
        $this->points = [];
        foreach ($points as $index => $point) {
            if (!$point instanceof DimensionSpacePoint) {
                throw new \InvalidArgumentException(sprintf('Point %s was not of type DimensionSpacePoint', $index));
            }
            $this->points[$point->getHash()] = $point;
        }
    }

    /**
     * @return DimensionSpacePoint[]
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @return array
     */
    public function getPointHashes(): array
    {
        return array_keys($this->points);
    }

    /**
     * @param DimensionSpacePoint $point
     * @return bool
     */
    public function contains(DimensionSpacePoint $point): bool
    {
        return isset($this->points[$point->getHash()]);
    }

    public function jsonSerialize()
    {
        return ['points' => array_values($this->points)];
    }

    public function __toString()
    {
        return 'dimension space points:[' . implode(',', $this->points) . ']';
    }
}
