<?php
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\Command\Dto;

use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\PropertyCollectionInterface;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\ValueObject\NodeReferences;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;

#[Flow\Proxy(false)]
final class NodeSubtreeSnapshot implements \JsonSerializable
{
    private NodeAggregateIdentifier $nodeAggregateIdentifier;

    private NodeTypeName $nodeTypeName;

    private ?NodeName $nodeName;

    private NodeAggregateClassification $nodeAggregateClassification;

    private SerializedPropertyValues $propertyValues;

    private NodeReferences $nodeReferences;

    /**
     * @var array<int,self>
     */
    private array $childNodes;

    /**
     * @param array<int,self> $childNodes
     */
    private function __construct(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeName $nodeTypeName,
        ?NodeName $nodeName,
        NodeAggregateClassification $nodeAggregateClassification,
        SerializedPropertyValues $propertyValues,
        NodeReferences $nodeReferences,
        array $childNodes
    ) {
        foreach ($childNodes as $childNode) {
            if (!$childNode instanceof NodeSubtreeSnapshot) {
                throw new \InvalidArgumentException(
                    'an element in $childNodes was not of type NodeSubtreeSnapshot, but ' . get_class($childNode)
                );
            }
        }
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->nodeTypeName = $nodeTypeName;
        $this->nodeName = $nodeName;
        $this->nodeAggregateClassification = $nodeAggregateClassification;
        $this->propertyValues = $propertyValues;
        $this->nodeReferences = $nodeReferences;
        $this->childNodes = $childNodes;
    }

    // TODO: use accessor here??
    public static function fromSubgraphAndStartNode(ContentSubgraphInterface $subgraph, NodeInterface $sourceNode): self
    {
        $childNodes = [];
        foreach ($subgraph->findChildNodes($sourceNode->getNodeAggregateIdentifier()) as $sourceChildNode) {
            $childNodes[] = self::fromSubgraphAndStartNode($subgraph, $sourceChildNode);
        }
        /** @var PropertyCollectionInterface $properties */
        $properties = $sourceNode->getProperties();

        return new self(
            $sourceNode->getNodeAggregateIdentifier(),
            $sourceNode->getNodeTypeName(),
            $sourceNode->getNodeName(),
            $sourceNode->getClassification(),
            $properties->serialized(),
            NodeReferences::fromNodes($subgraph->findReferencedNodes($sourceNode->getNodeAggregateIdentifier())),
            $childNodes
        );
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    public function getNodeTypeName(): NodeTypeName
    {
        return $this->nodeTypeName;
    }

    public function getNodeName(): ?NodeName
    {
        return $this->nodeName;
    }

    public function getNodeAggregateClassification(): NodeAggregateClassification
    {
        return $this->nodeAggregateClassification;
    }

    public function getPropertyValues(): SerializedPropertyValues
    {
        return $this->propertyValues;
    }

    public function getNodeReferences(): NodeReferences
    {
        return $this->nodeReferences;
    }

    /**
     * @return array<int,self>
     */
    public function getChildNodesToInsert(): array
    {
        return $this->childNodes;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'nodeTypeName' => $this->nodeTypeName,
            'nodeName' => $this->nodeName,
            'nodeAggregateClassification' => $this->nodeAggregateClassification,
            'propertyValues' => $this->propertyValues,
            'nodeReferences' => $this->nodeReferences,
            'childNodes' => $this->childNodes,
        ];
    }

    public function walk(\Closure $forEachElementFn): void
    {
        $forEachElementFn($this);
        foreach ($this->childNodes as $childNode) {
            $childNode->walk($forEachElementFn);
        }
    }

    /**
     * @param array<string,mixed> $array
     */
    public static function fromArray(array $array): self
    {
        $childNodes = [];
        foreach ($array['childNodes'] as $childNode) {
            $childNodes[] = self::fromArray($childNode);
        }

        return new self(
            NodeAggregateIdentifier::fromString($array['nodeAggregateIdentifier']),
            NodeTypeName::fromString($array['nodeTypeName']),
            isset($array['nodeName']) ? NodeName::fromString($array['nodeName']) : null,
            NodeAggregateClassification::from($array['nodeAggregateClassification']),
            SerializedPropertyValues::fromArray($array['propertyValues']),
            NodeReferences::fromArray($array['nodeReferences']),
            $childNodes
        );
    }
}
