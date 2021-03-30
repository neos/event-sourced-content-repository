<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Infrastructure\Projection;

use Neos\EventSourcedContentRepository\Domain\ImmutableArrayObject;

final class ProcessedEventsAwareProjectorCollection extends ImmutableArrayObject
{
    public function __construct(array $projectors)
    {
        foreach ($projectors as $projector) {
            if (!$projector instanceof ProcessedEventsAwareProjectorInterface) {
                throw new \InvalidArgumentException(get_class() . ' can only consist of ' . ProcessedEventsAwareProjectorInterface::class . ' objects.', 1616950763);
            }
        }
        parent::__construct($projectors);
    }

    /**
     * @param mixed $key
     * @return ProcessedEventsAwareProjectorInterface|false
     */
    public function offsetGet($key)
    {
        return parent::offsetGet($key);
    }

    /**
     * @return array|ProcessedEventsAwareProjectorInterface[]
     */
    public function getArrayCopy(): array
    {
        return parent::getArrayCopy();
    }

    /**
     * @return \ArrayIterator|ProcessedEventsAwareProjectorInterface[]
     */
    public function getIterator(): \ArrayIterator
    {
        return parent::getIterator();
    }
}