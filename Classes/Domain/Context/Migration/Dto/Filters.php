<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\Migration\Dto;

use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Context\Migration\Filters\NodeAggregateBasedFilterInterface;
use Neos\EventSourcedContentRepository\Domain\Context\Migration\Filters\NodeBasedFilterInterface;

final class Filters
{

    /**
     * @var NodeBasedFilterInterface[]
     */
    protected array $nodeBasedFilters = [];
    /**
     * @var NodeAggregateBasedFilterInterface[]
     */
    protected array $nodeAggregateBasedFilters = [];

    public function __construct(array $filterObjects)
    {
        foreach ($filterObjects as $filterObject) {
            if ($filterObject instanceof NodeBasedFilterInterface) {
                $this->nodeBasedFilters[] = $filterObject;
            } elseif ($filterObject instanceof NodeAggregateBasedFilterInterface) {
                $this->nodeAggregateBasedFilters[] = $filterObject;
            } else {
                throw new \RuntimeException('TODO: Filter object must implement either NodeBasedFilterInterface or NodeAggregateBasedFilterInterface');
            }
        }
    }

    public function containsNodeAggregateBased(): bool
    {
        return count($this->nodeAggregateBasedFilters) > 0;
    }

    public function containsNodeBased(): bool
    {
        return count($this->nodeBasedFilters) > 0;
    }

    public function matchesNodeAggregate(ReadableNodeAggregateInterface $nodeAggregate): bool
    {
        foreach ($this->nodeAggregateBasedFilters as $filter) {
            if (!$filter->matches($nodeAggregate)) {
                return false;
            }
        }

        return true;
    }

    public function matchesNode(NodeInterface $node): bool
    {
        foreach ($this->nodeBasedFilters as $filter) {
            if (!$filter->matches($node)) {
                return false;
            }
        }

        return true;
    }
}
