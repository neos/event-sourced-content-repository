@fixtures
Feature: Reading of our Graph Projection

  Background:
    Given I have no content dimensions

  Scenario: Single node connected to root
    Given the command "CreateRootNode" is executed with payload:
      | Key                      | Value                                  |
      | contentStreamIdentifier  | "c75ae6a2-7254-4d42-a31b-a629e264069d" |
      | nodeIdentifier           | "00000000-0000-0000-0000-000000000000" |
      | initiatingUserIdentifier | "00000000-0000-0000-0000-000000000000" |
      | nodeTypeName             | "Neos.ContentRepository:Root"          |

    And the Event "Neos.EventSourcedContentRepository:NodeAggregateWithNodeWasCreated" was published to stream "Neos.ContentRepository:ContentStream:c75ae6a2-7254-4d42-a31b-a629e264069d:NodeAggregate:35411439-94d1-4bd4-8fac-0646856c6a1f" with payload:
      | Key                           | Value                                                             |
      | contentStreamIdentifier       | "c75ae6a2-7254-4d42-a31b-a629e264069d"                            |
      | nodeAggregateIdentifier       | "35411439-94d1-4bd4-8fac-0646856c6a1f"                            |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:NodeWithoutAutoCreatedChildNodes" |
      | dimensionSpacePoint           | {}                                                                |
      | visibleInDimensionSpacePoints | [{}]                                                              |
      | nodeIdentifier                | "75106e9a-7dfb-4b48-8b7a-3c4ab2546b81"                            |
      | parentNodeIdentifier          | "00000000-0000-0000-0000-000000000000"                            |
      | nodeName                      | "foo"                                                             |
      | propertyDefaultValuesAndTypes | {}                                                                |

    When the graph projection is fully up to date
    And I am in content stream "c75ae6a2-7254-4d42-a31b-a629e264069d" and Dimension Space Point {}

    Then I expect a node "75106e9a-7dfb-4b48-8b7a-3c4ab2546b81" to exist in the graph projection
    And I expect the node aggregate "00000000-0000-0000-0000-000000000000" to have the following child nodes:
      | Name | NodeIdentifier                       |
      | foo  | 75106e9a-7dfb-4b48-8b7a-3c4ab2546b81 |
    And I expect the Node Aggregate "35411439-94d1-4bd4-8fac-0646856c6a1f" to resolve to node "75106e9a-7dfb-4b48-8b7a-3c4ab2546b81"
    And I expect the Node "75106e9a-7dfb-4b48-8b7a-3c4ab2546b81" to have the type "Neos.ContentRepository.Testing:NodeWithoutAutoCreatedChildNodes"
    # TODO This doesn't perform any assertion
    And I expect the Node "75106e9a-7dfb-4b48-8b7a-3c4ab2546b81" to have the properties:
      | Key | Value |
    And I expect the path "/foo" to lead to the node "75106e9a-7dfb-4b48-8b7a-3c4ab2546b81"
