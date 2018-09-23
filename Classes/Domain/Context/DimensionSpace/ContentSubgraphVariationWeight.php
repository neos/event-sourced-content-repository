<?php
namespace Neos\EventSourcedContentRepository\Domain\Context\DimensionSpace;

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

/**
 * The value object describing the weight of a variation edge between subgraphs A and B
 *
 * The weight defines a set of specialization depths by dimension identifier like
 * [
 *      'dimension1' => ContentDimensionValueSpecializationDepth(0),
 *      'dimension2' => ContentDimensionValueSpecializationDepth(3)
 * ],
 * which means that the value in dimension1 is the same in both subgraphs,
 * while subgraph B's value in dimension2 is a specialization of grade 3 of subgraph A's corresponding value.
 *
 * @see Dimension\ContentDimensionValueSpecializationDepth
 */
final class ContentSubgraphVariationWeight implements \JsonSerializable
{
    /**
     * @var array
     */
    protected $weight;

    /**
     * ContentSubgraphVariationWeight constructor.
     * @param array $weight
     */
    public function __construct(array $weight)
    {
        // @todo: validate
        $this->weight = $weight;
    }

    /**
     * @return array|Dimension\ContentDimensionValueSpecializationDepth[]
     */
    public function getWeight(): array
    {
        return $this->weight;
    }

    /**
     * @param Dimension\ContentDimensionIdentifier $dimensionIdentifier
     * @return Dimension\ContentDimensionValueSpecializationDepth|null
     */
    public function getWeightInDimension(Dimension\ContentDimensionIdentifier $dimensionIdentifier): ?Dimension\ContentDimensionValueSpecializationDepth
    {
        return $this->weight[(string)$dimensionIdentifier] ?? null;
    }

    /**
     * @param ContentSubgraphVariationWeight $otherWeight
     * @return bool
     */
    public function canBeComparedTo(ContentSubgraphVariationWeight $otherWeight): bool
    {
        return array_keys($otherWeight->getWeight()) === array_keys($this->getWeight());
    }

    /**
     * @param ContentSubgraphVariationWeight $otherWeight
     * @return ContentSubgraphVariationWeight
     * @throws Exception\IncomparableContentSubgraphVariationWeightsException
     */
    public function decreaseBy(ContentSubgraphVariationWeight $otherWeight): ContentSubgraphVariationWeight
    {
        if (!$this->canBeComparedTo($otherWeight)) {
            throw new Exception\IncomparableContentSubgraphVariationWeightsException('Weights ' . $this . ' and ' . $otherWeight . ' cannot be compared.', 1517474233);
        }
        $decreasedWeight = [];
        foreach ($this->getWeight() as $rawDimensionIdentifier => $weight) {
            $dimensionIdentifier = new Dimension\ContentDimensionIdentifier($rawDimensionIdentifier);
            $decreasedWeight[$rawDimensionIdentifier] = $weight->decreaseBy($otherWeight->getWeightInDimension($dimensionIdentifier));
        }

        return new ContentSubgraphVariationWeight($decreasedWeight);
    }

    /**
     * @param int $normalizationBase
     * @return int
     */
    public function normalize(int $normalizationBase): int
    {
        $normalizedWeight = 0;
        $exponent = count($this->getWeight()) - 1;
        foreach ($this->getWeight() as $dimensionIdentifier => $specializationDepth) {
            $normalizedWeight += pow($normalizationBase, $exponent) * $specializationDepth->getDepth();
            $exponent--;
        }

        return $normalizedWeight;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->weight;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this);
    }
}
