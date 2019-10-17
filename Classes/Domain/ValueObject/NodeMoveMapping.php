<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\ValueObject;

use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeVariantAssignments;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\Flow\Annotations as Flow;

/**
 * A move mapping for a single node
 *
 * It declares:
 * * The moved node's origin dimension space point. With this the node can be uniquely identified
 * * The new parent assignments if given - the node might be assigned to different parents, depending on covered dimension space point
 * * The new succeeding siblings' assignments if given - the node might be assigned to different succeeding siblings, depending on covered dimension space point
 * @Flow\Proxy(false)
 */
final class NodeMoveMapping
{
    /**
     * @var OriginDimensionSpacePoint
     */
    private $movedNodeOrigin;

    /**
     * @var NodeVariantAssignments
     */
    private $newParentAssignments;

    /**
     * @var NodeVariantAssignments
     */
    private $newSucceedingSiblingAssignments;

    public function __construct(
        OriginDimensionSpacePoint $movedNodeOrigin,
        NodeVariantAssignments $newParentAssignments,
        NodeVariantAssignments $newSucceedingSiblingAssignments
    ) {
        $this->movedNodeOrigin = $movedNodeOrigin;
        $this->newParentAssignments = $newParentAssignments;
        $this->newSucceedingSiblingAssignments = $newSucceedingSiblingAssignments;
    }

    public static function fromArray(array $array): NodeMoveMapping
    {
        return new static(
            new OriginDimensionSpacePoint($array['movedNodeOrigin']),
            NodeVariantAssignments::createFromArray($array['newParentAssignments']),
            NodeVariantAssignments::createFromArray($array['newSucceedingSiblingAssignments'])
        );
    }

    public function getMovedNodeOrigin(): OriginDimensionSpacePoint
    {
        return $this->movedNodeOrigin;
    }

    public function getNewParentAssignments(): NodeVariantAssignments
    {
        return $this->newParentAssignments;
    }

    public function getNewSucceedingSiblingAssignments(): NodeVariantAssignments
    {
        return $this->newSucceedingSiblingAssignments;
    }
}
