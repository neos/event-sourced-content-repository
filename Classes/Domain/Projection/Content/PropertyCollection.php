<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Projection\Content;

/*
 * This file is part of the Neos.ContentRepository.Intermediary package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Intermediary\Domain\Property\PropertyConverter;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;
use Neos\Flow\Annotations as Flow;

/**
 * The property collection implementation
 * @Flow\Proxy(false)
 */
final class PropertyCollection implements PropertyCollectionInterface
{
    /**
     * Properties from Nodes
     */
    private SerializedPropertyValues $serializedPropertyValues;

    private array $deserializedPropertyValues;

    private \ArrayIterator $iterator;

    private PropertyConverter $propertyConverter;

    /**
     * @internal do not create from userspace
     */
    public function __construct(SerializedPropertyValues $serializedPropertyValues, PropertyConverter $propertyConverter)
    {
        $this->serializedPropertyValues = $serializedPropertyValues;
        $this->iterator = new \ArrayIterator($serializedPropertyValues->getPlainValues());
        $this->propertyConverter = $propertyConverter;
    }

    public function offsetExists($offset): bool
    {
        return $this->serializedPropertyValues->propertyExists($offset);
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }
        if (!isset($this->deserializedPropertyValues[$offset])) {
            $this->deserializedPropertyValues[$offset] = $this->propertyConverter->deserializePropertyValue(
                $this->serializedPropertyValues->getProperty($offset)
            );
        }

        return $this->deserializedPropertyValues[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException("Do not use!");
    }

    public function offsetUnset($offset)
    {
        throw new \RuntimeException("Do not use!");
    }

    public function getIterator()
    {
        return $this->iterator;
    }

    public function getSerializedPropertyValues(): SerializedPropertyValues
    {
        return $this->serializedPropertyValues;
    }
}
