<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Tests\Behavior\Features\Bootstrap;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\ContentGraphs;
use Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\ContentSubgraphs;
use Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\NodeDiscriminator;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\NodeDiscriminators;
use Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\NodesByAdapter;
use Neos\EventSourcedContentRepository\Tests\Behavior\Fixtures\PostalAddress;
use PHPUnit\Framework\Assert;

/**
 * The feature trait to test projected nodes
 */
trait ProjectedNodeTrait
{
    use CurrentSubgraphTrait;

    protected ?NodesByAdapter $currentNodes = null;

    abstract protected function getContentGraphs(): ContentGraphs;

    abstract protected function getCurrentSubgraphs(): ContentSubgraphs;

    abstract protected function getRootNodeAggregateIdentifier(): NodeAggregateIdentifier;

    abstract protected function readPayloadTable(TableNode $payloadTable): array;

    protected function getCurrentNodes(): ?NodesByAdapter
    {
        return $this->currentNodes;
    }

    /**
     * @When /^I go to the parent node of node aggregate "([^"]*)"$/
     * @param string $serializedNodeAggregateIdentifier
     */
    public function iGoToTheParentNodeOfNode(string $serializedNodeAggregateIdentifier): void
    {
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($serializedNodeAggregateIdentifier);
        $this->initializeCurrentNodesFromContentSubgraphs(function (ContentSubgraphInterface $subgraph) use($nodeAggregateIdentifier) {
            return $subgraph->findParentNode($nodeAggregateIdentifier);
        });
    }

    /**
     * @Then /^I get the node at path "([^"]*)"$/
     * @param string $serializedNodePath
     * @throws \Exception
     */
    public function iGetTheNodeAtPath(string $serializedNodePath): void
    {
        $nodePath = NodePath::fromString($serializedNodePath);
        $this->initializeCurrentNodesFromContentSubgraphs(function (ContentSubgraphInterface $subgraph) use($nodePath) {
            return $subgraph->findNodeByPath($nodePath, $this->getRootNodeAggregateIdentifier());
        });
    }

    /**
     * @Then /^I expect a node identified by (.*) to exist in the content graph$/
     * @param string $serializedNodeDiscriminator
     * @throws \Exception
     */
    public function iExpectANodeIdentifiedByXToExistInTheContentGraph(string $serializedNodeDiscriminator): void
    {
        $nodeDiscriminator = NodeDiscriminator::fromShorthand($serializedNodeDiscriminator);
        $this->initializeCurrentNodesFromContentGraphs(function (ContentGraphInterface $contentGraph, string $adapterName) use($nodeDiscriminator) {
            $currentNode = $contentGraph->findNodeByIdentifiers(
                $nodeDiscriminator->getContentStreamIdentifier(),
                $nodeDiscriminator->getNodeAggregateIdentifier(),
                $nodeDiscriminator->getOriginDimensionSpacePoint()
            );
            Assert::assertNotNull(
                $currentNode,
                'Node with aggregate identifier "' . $nodeDiscriminator->getNodeAggregateIdentifier()
                . '" and originating in dimension space point "' . $nodeDiscriminator->getOriginDimensionSpacePoint()
                . '" was not found in content stream "' . $nodeDiscriminator->getContentStreamIdentifier() . '"'
                . '" in adapter "' . $adapterName . '"'
            );

            return $currentNode;
        });
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" to lead to node (.*)$/
     * @param string $serializedNodeAggregateIdentifier
     * @param string $serializedNodeDiscriminator
     */
    public function iExpectNodeAggregateIdentifierToLeadToNode(
        string $serializedNodeAggregateIdentifier,
        string $serializedNodeDiscriminator
    ): void {
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($serializedNodeAggregateIdentifier);
        $expectedDiscriminator = NodeDiscriminator::fromShorthand($serializedNodeDiscriminator);
        $this->initializeCurrentNodesFromContentSubgraphs(function (ContentSubgraphInterface $subgraph, string $adapterName) use($nodeAggregateIdentifier, $expectedDiscriminator) {
            $currentNode = $subgraph->findNodeByNodeAggregateIdentifier($nodeAggregateIdentifier);
            Assert::assertNotNull($currentNode, 'No node could be found by node aggregate identifier "' . $nodeAggregateIdentifier . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '" and adapter "' . $adapterName . '"');
            $actualDiscriminator = NodeDiscriminator::fromNode($currentNode);
            Assert::assertTrue($expectedDiscriminator->equals($actualDiscriminator), 'Node discriminators do not match. Expected was ' . json_encode($expectedDiscriminator) . ' , given was ' . json_encode($actualDiscriminator) . ' in adapter "' . $adapterName . '"');
            return $currentNode;
        });
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" to lead to no node$/
     * @param string $serializedNodeAggregateIdentifier
     */
    public function iExpectNodeAggregateIdentifierToLeadToNoNode(string $serializedNodeAggregateIdentifier): void
    {
        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($serializedNodeAggregateIdentifier);
        foreach ($this->getCurrentSubgraphs() as $adapterName => $subgraph) {
            $nodeByAggregateIdentifier = $subgraph->findNodeByNodeAggregateIdentifier($nodeAggregateIdentifier);
            Assert::assertNull($nodeByAggregateIdentifier, 'A node was found by node aggregate identifier "' . $nodeAggregateIdentifier . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '" and adapter "' . $adapterName . '"');
        }
    }

    /**
     * @Then /^I expect path "([^"]*)" to lead to node (.*)$/
     * @param string $serializedNodePath
     * @param string $serializedNodeDiscriminator
     * @throws \Exception
     */
    public function iExpectPathToLeadToNode(string $serializedNodePath, string $serializedNodeDiscriminator): void
    {
        if (!$this->getRootNodeAggregateIdentifier()) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $nodePath = NodePath::fromString($serializedNodePath);
        $expectedDiscriminator = NodeDiscriminator::fromShorthand($serializedNodeDiscriminator);
        $this->initializeCurrentNodesFromContentSubgraphs(function (ContentSubgraphInterface $subgraph, string $adapterName) use($nodePath, $expectedDiscriminator) {
            $currentNode = $subgraph->findNodeByPath($nodePath, $this->getRootNodeAggregateIdentifier());
            Assert::assertNotNull($currentNode, 'No node could be found by node path "' . $nodePath . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '" and adapter "' . $adapterName . '"');
            $actualDiscriminator = NodeDiscriminator::fromNode($currentNode);
            Assert::assertTrue($expectedDiscriminator->equals($actualDiscriminator), 'Node discriminators do not match. Expected was ' . json_encode($expectedDiscriminator) . ' , given was ' . json_encode($actualDiscriminator) . ' in adapter "' . $adapterName . '"');
            return $currentNode;
        });
    }

    /**
     * @Then /^I expect path "([^"]*)" to lead to no node$/
     * @param string $serializedNodePath
     * @throws \Exception
     */
    public function iExpectPathToLeadToNoNode(string $serializedNodePath): void
    {
        if (!$this->getRootNodeAggregateIdentifier()) {
            throw new \Exception('ERROR: rootNodeAggregateIdentifier needed for running this step. You need to use "the event RootNodeAggregateWithNodeWasCreated was published with payload" to create a root node..');
        }
        $nodePath = NodePath::fromString($serializedNodePath);
        foreach ($this->getCurrentSubgraphs() as $adapterName => $subgraph) {
            $nodeByPath = $subgraph->findNodeByPath($nodePath, $this->getRootNodeAggregateIdentifier());
            Assert::assertNull($nodeByPath, 'A node was found by node path "' . $nodePath . '" in content subgraph "' . $this->dimensionSpacePoint . '@' . $this->contentStreamIdentifier . '" and adapter "' . $adapterName . '"');
        }
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" and node path "([^"]*)" to lead to node (.*)$/
     * @param string $serializedNodeAggregateIdentifier
     * @param string $serializedNodePath
     * @param string $serializedNodeDiscriminator
     * @throws \Exception
     */
    public function iExpectNodeAggregateIdentifierAndNodePathToLeadToNode(string $serializedNodeAggregateIdentifier, string $serializedNodePath, string $serializedNodeDiscriminator): void
    {
        $this->iExpectNodeAggregateIdentifierToLeadToNode($serializedNodeAggregateIdentifier, $serializedNodeDiscriminator);
        $this->iExpectPathToLeadToNode($serializedNodePath, $serializedNodeDiscriminator);
    }

    /**
     * @Then /^I expect node aggregate identifier "([^"]*)" and node path "([^"]*)" to lead to no node$/
     * @param string $serializedNodeAggregateIdentifier
     * @param string $serializedNodePath
     * @throws \Exception
     */
    public function iExpectNodeAggregateIdentifierAndNodePathToLeadToNoNode(string $serializedNodeAggregateIdentifier, string $serializedNodePath): void
    {
        $this->iExpectNodeAggregateIdentifierToLeadToNoNode($serializedNodeAggregateIdentifier);
        $this->iExpectPathToLeadToNoNode($serializedNodePath);
    }

    protected function initializeCurrentNodesFromContentGraphs(callable $query): void
    {
        $currentNodes = [];
        foreach ($this->getContentGraphs() as $adapterName => $graph) {
            $currentNodes[$adapterName] = $query($graph, $adapterName);
        }

        $this->currentNodes = new NodesByAdapter($currentNodes);
    }

    protected function initializeCurrentNodesFromContentSubgraphs(callable $query): void
    {
        $currentNodes = [];
        foreach ($this->getCurrentSubgraphs() as $adapterName => $subgraph) {
            $currentNodes[$adapterName] = $query($subgraph, $adapterName);
        }

        $this->currentNodes = new NodesByAdapter($currentNodes);
    }

    /**
     * @Then /^I expect this node to be classified as "([^"]*)"$/
     */
    public function iExpectThisNodeToBeClassifiedAs(string $serializedExpectedClassification): void
    {
        $expectedClassification = NodeAggregateClassification::fromString($serializedExpectedClassification);
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedClassification) {
            Assert::assertTrue(
                $expectedClassification->equals($currentNode->getClassification()),
                'Node was expected to be classified as "' . $expectedClassification . '" but is as "' . $currentNode->getClassification() . '" in adapter "' . $adapterName . '"'
            );
        });
    }

    /**
     * @Then /^I expect this node to be of type "([^"]*)"$/
     * @param string $serializedExpectedNodeTypeName
     */
    public function iExpectThisNodeToBeOfType(string $serializedExpectedNodeTypeName): void
    {
        $expectedNodeTypeName = NodeTypeName::fromString($serializedExpectedNodeTypeName);
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedNodeTypeName) {
            $actualNodeTypeName = $currentNode->getNodeTypeName();
            Assert::assertTrue($expectedNodeTypeName->equals($actualNodeTypeName), 'Actual node type name "' . $actualNodeTypeName .'" does not match expected "' . $expectedNodeTypeName . '" in adapter "' . $adapterName . '"');
        });
    }

    /**
     * @Then /^I expect this node to be named "([^"]*)"$/
     * @param string $serializedExpectedNodeName
     */
    public function iExpectThisNodeToBeNamed(string $serializedExpectedNodeName): void
    {
        $expectedNodeName = NodeName::fromString($serializedExpectedNodeName);
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedNodeName) {
            $actualNodeName = $currentNode->getNodeName();
            Assert::assertSame((string)$expectedNodeName, (string)$actualNodeName, 'Actual node name "' . $actualNodeName .'" does not match expected "' . $expectedNodeName . '" in adapter "' . $adapterName . '"');
        });
    }

    /**
     * @Then /^I expect this node to be unnamed$/
     */
    public function iExpectThisNodeToBeUnnamed(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            Assert::assertNull($currentNode->getNodeName(), 'Node was not expected to be named in adapter "' . $adapterName . '"');
        });
    }

    /**
     * @Then /^I expect this node to have the following properties:$/
     * @param TableNode $expectedProperties
     */
    public function iExpectThisNodeToHaveTheFollowingProperties(TableNode $expectedProperties): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedProperties) {
            $properties = $currentNode->getProperties();
            foreach ($expectedProperties->getHash() as $row) {
                Assert::assertTrue($properties->offsetExists($row['Key']), 'Property "' . $row['Key'] . '" not found');
                $expectedPropertyValue = $this->resolvePropertyValue($row['Value']);
                $actualPropertyValue = $properties->offsetGet($row['Key']);
                if ($row['Value'] === 'Date:now') {
                    // we accept 10s offset for the projector to be fine
                    Assert::assertLessThan($actualPropertyValue, $expectedPropertyValue->sub(new \DateInterval('PT10S')), 'Node property ' . $row['Key'] . ' does not match. Expected: ' . json_encode($expectedPropertyValue) . '; Actual: ' . json_encode($actualPropertyValue));
                } else {
                    Assert::assertEquals($expectedPropertyValue, $actualPropertyValue, 'Node property ' . $row['Key'] . ' does not match. Expected: ' . json_encode($row['Value']) . '; Actual: ' . json_encode($actualPropertyValue)) . ' in adapter "' . $adapterName . '"';
                }
            }
        });
    }

    private function resolvePropertyValue(string $serializedPropertyValue)
    {
        switch ($serializedPropertyValue) {
            case 'PostalAddress:dummy':
                return PostalAddress::dummy();
            case 'PostalAddress:anotherDummy':
                return PostalAddress::anotherDummy();
            case 'Date:now':
                return new \DateTimeImmutable();
            default:
                if (\mb_strpos($serializedPropertyValue, 'Date:') === 0) {
                    return \DateTimeImmutable::createFromFormat(\DateTimeInterface::W3C, \mb_substr($serializedPropertyValue, 5));
                } elseif (\mb_strpos($serializedPropertyValue, 'URI:') === 0) {
                    return new Uri(\mb_substr($serializedPropertyValue, 4));
                }
        }

        return \json_decode($serializedPropertyValue, true);
    }

    /**
     * @Then /^I expect this node to have no properties$/
     */
    public function iExpectThisNodeToHaveNoProperties(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $properties = $currentNode->getProperties();
            $properties = iterator_to_array($properties);
            Assert::assertCount(0, $properties, 'No properties were expected in adapter "' . $adapterName . '"');
        });
    }

    /**
     * @Then /^I expect this node to have the following references:$/
     * @param TableNode $expectedReferences
     * @throws \Exception
     */
    public function iExpectThisNodeToHaveTheFollowingReferences(TableNode $expectedReferences): void
    {
        $expectedReferences = $this->readPayloadTable($expectedReferences);
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedReferences) {
            foreach ($expectedReferences as $propertyName => $serializedDiscriminators) {
                $expectedDiscriminators = NodeDiscriminators::fromArray($serializedDiscriminators);
                $destinationNodes = $this->getCurrentSubgraphs()[$adapterName]
                    ->findReferencedNodes($currentNode->getNodeAggregateIdentifier(), PropertyName::fromString($propertyName));
                $actualDiscriminators = NodeDiscriminators::fromNodes($destinationNodes);
                Assert::assertTrue($expectedDiscriminators->equal($actualDiscriminators), 'Node references ' . $propertyName . ' do not match in adapter "' . $adapterName . '". Expected: ' . json_encode($expectedDiscriminators) . '; Actual: ' . json_encode($actualDiscriminators));
            }
        });
    }

    /**
     * @Then /^I expect this node to have no references$/
     * @throws \Exception
     */
    public function iExpectThisNodeToHaveNoReferences(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $destinationNodes = $this->getCurrentSubgraphs()[$adapterName]
                ->findReferencedNodes($currentNode->getNodeAggregateIdentifier());
            Assert::assertCount(0, $destinationNodes, 'No references were expected in adapter "' . $adapterName . '".');
        });
    }

    /**
     * @Then /^I expect this node to be referenced by:$/
     * @param TableNode $expectedReferences
     * @throws \Exception
     */
    public function iExpectThisNodeToBeReferencedBy(TableNode $expectedReferences): void
    {
        $expectedReferences = $this->readPayloadTable($expectedReferences);
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedReferences) {
            foreach ($expectedReferences as $propertyName => $serializedDiscriminators) {
                $expectedDiscriminators = NodeDiscriminators::fromArray($serializedDiscriminators);
                $originNodes = $this->getCurrentSubgraphs()[$adapterName]
                    ->findReferencingNodes($currentNode->getNodeAggregateIdentifier(), PropertyName::fromString($propertyName));
                $actualDiscriminators = NodeDiscriminators::fromNodes($originNodes);

                // since the order on the target side is not defined we sort expectation and result before comparison
                Assert::assertTrue($expectedDiscriminators->areSimilarTo($actualDiscriminators), 'Node references ' . $propertyName . ' do not match. Expected: ' . json_encode($expectedDiscriminators) . '; Actual: ' . json_encode($actualDiscriminators));
            }
        });
    }

    /**
     * @Then /^I expect this node to not be referenced$/
     * @throws \Exception
     */
    public function iExpectThisNodeToNotBeReferenced(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $originNodes = $this->getCurrentSubgraphs()[$adapterName]
                ->findReferencingNodes($currentNode->getNodeAggregateIdentifier());
            Assert::assertCount(0, $originNodes, 'No referencing nodes were expected in adapter "' . $adapterName . '".');
        });
    }

    /**
     * @Then /^I expect this node to be a child of node (.*)$/
     * @param string $serializedParentNodeDiscriminator
     */
    public function iExpectThisNodeToBeTheChildOfNode(string $serializedParentNodeDiscriminator): void
    {
        $expectedParentDiscriminator = NodeDiscriminator::fromShorthand($serializedParentNodeDiscriminator);
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use ($expectedParentDiscriminator) {
            $subgraph = $this->getCurrentSubgraphs()[$adapterName];

            $parent = $subgraph->findParentNode($currentNode->getNodeAggregateIdentifier());
            Assert::assertInstanceOf(NodeInterface::class, $parent, 'Parent not found.');
            $actualParentDiscriminator = NodeDiscriminator::fromNode($parent);
            Assert::assertTrue($expectedParentDiscriminator->equals($actualParentDiscriminator), 'Parent discriminator does not match in adapter "' . $adapterName . '". Expected was ' . json_encode($expectedParentDiscriminator) . ', given was ' . json_encode($actualParentDiscriminator));

            $expectedChildDiscriminator = NodeDiscriminator::fromNode($currentNode);
            $child = $subgraph->findChildNodeConnectedThroughEdgeName($parent->getNodeAggregateIdentifier(), $currentNode->getNodeName());
            $actualChildDiscriminator = NodeDiscriminator::fromNode($child);
            Assert::assertTrue($expectedChildDiscriminator->equals($actualChildDiscriminator), 'Child discriminator does not match in adapter "' . $adapterName . '". Expected was ' . json_encode($expectedChildDiscriminator) . ', given was ' . json_encode($actualChildDiscriminator));
        });
    }

    /**
     * @Then /^I expect this node to have no parent node$/
     */
    public function iExpectThisNodeToHaveNoParentNode(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $parentNode = $this->getCurrentSubgraphs()[$adapterName]->findParentNode($currentNode->getNodeAggregateIdentifier());
            $unexpectedNodeAggregateIdentifier = $parentNode ? $parentNode->getNodeAggregateIdentifier() : '';
            Assert::assertNull($parentNode, 'Parent node ' . $unexpectedNodeAggregateIdentifier . ' was found in adapter "' . $adapterName . '", but none was expected.');
        });
    }

    /**
     * @Then /^I expect this node to have the following child nodes:$/
     * @param TableNode $expectedChildNodesTable
     */
    public function iExpectThisNodeToHaveTheFollowingChildNodes(TableNode $expectedChildNodesTable): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use ($expectedChildNodesTable) {
            $subgraph = $this->getCurrentSubgraphs()[$adapterName];
            $actualChildNodes = [];
            foreach ($subgraph->findChildNodes($currentNode->getNodeAggregateIdentifier()) as $actualChildNode) {
                $actualChildNodes[] = $actualChildNode;
            }

            Assert::assertEquals(count($expectedChildNodesTable->getHash()), $subgraph->countChildNodes($currentNode->getNodeAggregateIdentifier()), 'ContentSubgraph::countChildNodes returned a wrong value in adapter "' . $adapterName . '"');
            Assert::assertCount(count($expectedChildNodesTable->getHash()), $actualChildNodes, 'ContentSubgraph::findChildNodes: Child node count does not match in adapter "' . $adapterName . '"');

            foreach ($expectedChildNodesTable->getHash() as $index => $row) {
                $expectedNodeName = NodeName::fromString($row['Name']);
                $actualNodeName = $actualChildNodes[$index]->getNodeName();
                Assert::assertEquals($expectedNodeName, $actualNodeName, 'ContentSubgraph::findChildNodes: Node name in index ' . $index . ' does not match in adapter "' . $adapterName . '". Expected: "' . $expectedNodeName . '" Actual: "' . $actualNodeName . '"');
                if (isset($row['NodeDiscriminator'])) {
                    $expectedNodeDiscriminator = NodeDiscriminator::fromShorthand($row['NodeDiscriminator']);
                    $actualNodeDiscriminator = NodeDiscriminator::fromNode($actualChildNodes[$index]);
                    Assert::assertTrue($expectedNodeDiscriminator->equals($actualNodeDiscriminator), 'ContentSubgraph::findChildNodes: Node discriminator in index ' . $index . ' does not match in adapter "' . $adapterName . '". Expected: ' . $expectedNodeDiscriminator . ' Actual: ' . $actualNodeDiscriminator);
                }
            }
        });
    }

    /**
     * @Then /^I expect this node to have no child nodes$/
     */
    public function iExpectThisNodeToHaveNoChildNodes(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $subgraph = $this->getCurrentSubgraphs()[$adapterName];
            $actualChildNodes = $subgraph->findChildNodes($currentNode->getNodeAggregateIdentifier());

            Assert::assertEquals(0, $subgraph->countChildNodes($currentNode->getNodeAggregateIdentifier()), 'ContentSubgraph::countChildNodes indicated present child nodes in adapter "' . $adapterName . '"');
            Assert::assertEquals(0, count($actualChildNodes), 'ContentSubgraph::findChildNodes returned present child nodes in adapter "' . $adapterName . '"');
        });
    }

    /**
     * @Then /^I expect this node to have the following siblings:$/
     * @param TableNode $expectedChildNodesTable
     */
    public function iExpectThisNodeToHaveTheFollowingSiblings(TableNode $expectedSiblingsTable): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedSiblingsTable) {
            $actualSiblings = [];
            foreach ($this->getCurrentSubgraphs()[$adapterName]->findSiblings($currentNode->getNodeAggregateIdentifier()) as $actualSibling) {
                $actualSiblings[] = $actualSibling;
            }
            Assert::assertCount(count($expectedSiblingsTable->getHash()), $actualSiblings, 'ContentSubgraph::findSiblings: Sibling count does not match in adapter "' . $adapterName . '"');
            foreach ($expectedSiblingsTable->getHash() as $index => $row) {
                $expectedNodeDiscriminator = NodeDiscriminator::fromShorthand($row['NodeDiscriminator']);
                $actualNodeDiscriminator = NodeDiscriminator::fromNode($actualSiblings[$index]);
                Assert::assertTrue($expectedNodeDiscriminator->equals($actualNodeDiscriminator), 'ContentSubgraph::findSiblings: Node discriminator in index ' . $index . ' does not match in adapter "' . $adapterName . '". Expected: ' . $expectedNodeDiscriminator . ' Actual: ' . $actualNodeDiscriminator);
            }
        });
    }

    /**
     * @Then /^I expect this node to have no siblings$/
     */
    public function iExpectThisNodeToHaveNoSiblings(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $actualSiblings = $this->getCurrentSubgraphs()[$adapterName]->findSiblings($currentNode->getNodeAggregateIdentifier());
            Assert::assertCount(0, $actualSiblings, 'ContentSubgraph::findSiblings: No siblings were expected in adapter "' . $adapterName . '"');
        });
    }

    /**
     * @Then /^I expect this node to have the following preceding siblings:$/
     * @param TableNode $expectedPrecedingSiblingsTable
     */
    public function iExpectThisNodeToHaveTheFollowingPrecedingSiblings(TableNode $expectedPrecedingSiblingsTable): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedPrecedingSiblingsTable) {
            $actualSiblings = [];
            foreach ($this->getCurrentSubgraphs()[$adapterName]->findPrecedingSiblings($currentNode->getNodeAggregateIdentifier()) as $actualSibling) {
                $actualSiblings[] = $actualSibling;
            }
            Assert::assertCount(count($expectedPrecedingSiblingsTable->getHash()), $actualSiblings, 'ContentSubgraph::findPrecedingSiblings: Sibling count does not match in adapter "' . $adapterName . '"');
            foreach ($expectedPrecedingSiblingsTable->getHash() as $index => $row) {
                $expectedNodeDiscriminator = NodeDiscriminator::fromShorthand($row['NodeDiscriminator']);
                $actualNodeDiscriminator = NodeDiscriminator::fromNode($actualSiblings[$index]);
                Assert::assertTrue($expectedNodeDiscriminator->equals($actualNodeDiscriminator), 'ContentSubgraph::findPrecedingSiblings: Node discriminator in index ' . $index . ' does not match in adapter "' . $adapterName . '". Expected: ' . $expectedNodeDiscriminator . ' Actual: ' . $actualNodeDiscriminator);
            }
        });
    }

    /**
     * @Then /^I expect this node to have no preceding siblings$/
     */
    public function iExpectThisNodeToHaveNoPrecedingSiblings(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $actualSiblings = $this->getCurrentSubgraphs()[$adapterName]->findPrecedingSiblings($currentNode->getNodeAggregateIdentifier());
            Assert::assertCount(0, $actualSiblings, 'ContentSubgraph::findPrecedingSiblings: No siblings were expected in adapter "' . $adapterName . '"');
        });
    }

    /**
     * @Then /^I expect this node to have the following succeeding siblings:$/
     * @param TableNode $expectedSucceedingSiblingsTable
     */
    public function iExpectThisNodeToHaveTheFollowingSucceedingSiblings(TableNode $expectedSucceedingSiblingsTable): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) use($expectedSucceedingSiblingsTable) {
            $actualSiblings = [];
            foreach ($this->getCurrentSubgraphs()[$adapterName]->findSucceedingSiblings($currentNode->getNodeAggregateIdentifier()) as $actualSibling) {
                $actualSiblings[] = $actualSibling;
            }
            Assert::assertCount(count($expectedSucceedingSiblingsTable->getHash()), $actualSiblings, 'ContentSubgraph::findSucceedingSiblings: Sibling count does not match in adapter "' . $adapterName . '"');
            foreach ($expectedSucceedingSiblingsTable->getHash() as $index => $row) {
                $expectedNodeDiscriminator = NodeDiscriminator::fromShorthand($row['NodeDiscriminator']);
                $actualNodeDiscriminator = NodeDiscriminator::fromNode($actualSiblings[$index]);
                Assert::assertTrue($expectedNodeDiscriminator->equals($actualNodeDiscriminator), 'ContentSubgraph::findSucceedingSiblings: Node discriminator in index ' . $index . ' does not match in adapter "' . $adapterName . '". Expected: ' . $expectedNodeDiscriminator . ' Actual: ' . $actualNodeDiscriminator);
            }
        });
    }

    /**
     * @Then /^I expect this node to have no succeeding siblings$/
     */
    public function iExpectThisNodeToHaveNoSucceedingSiblings(): void
    {
        $this->assertOnCurrentNodes(function (NodeInterface $currentNode, string $adapterName) {
            $actualSiblings = $this->getCurrentSubgraphs()[$adapterName]->findSucceedingSiblings($currentNode->getNodeAggregateIdentifier());
            Assert::assertCount(0, $actualSiblings, 'ContentSubgraph::findSucceedingSiblings: No siblings were expected');
        });
    }

    protected function assertOnCurrentNodes(callable $assertions): void
    {
        $this->expectCurrentNodes();
        foreach ($this->currentNodes as $adapterName => $currentNode) {
            $assertions($currentNode, $adapterName);
        }
    }

    protected function expectCurrentNodes(): void
    {
        foreach ($this->currentNodes as $adapterName => $currentNode) {
            Assert::assertNotNull($currentNode, 'No current node present for adapter "' . $adapterName . '"');
        }
    }
}
