@fixtures
Feature: Remove NodeAggregate

  As a user of the CR I want to be able to remove a NodeAggregate completely.

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values  | Generalizations |
      | language   | de      | de, gsw | gsw->de         |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository:Document': []
    """
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     | Type                    |
      | contentStreamIdentifier       | live-cs-identifier                        | ContentStreamIdentifier |
      | nodeAggregateIdentifier       | sir-david-nodenborough                    | NodeAggregateIdentifier |
      | nodeTypeName                  | Neos.ContentRepository:Root               | NodeTypeName            |
      | visibleInDimensionSpacePoints | [{"language": "de"}, {"language": "gsw"}] | DimensionSpacePointSet  |
      | initiatingUserIdentifier      | 00000000-0000-0000-0000-000000000000      | UserIdentifier          |
    # We have to add another node since root nodes are in all dimension space points and thus cannot be varied
    # Node /document
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                  | Type                    |
      | contentStreamIdentifier       | live-cs-identifier                     | Uuid                    |
      | nodeAggregateIdentifier       | doc-agg-identifier                     | NodeAggregateIdentifier |
      | nodeTypeName                  | Neos.ContentRepository:Document        |                         |
      | dimensionSpacePoint           | {"language":"de"}                      | DimensionSpacePoint     |
      | visibleInDimensionSpacePoints | [{"language":"de"},{"language":"gsw"}] | DimensionSpacePointSet  |
      | nodeIdentifier                | doc-identifier-de                      | Uuid                    |
      | parentNodeIdentifier          | rn-identifier                          | Uuid                    |
      | nodeName                      | document                               |                         |
      | propertyDefaultValuesAndTypes | {}                                     | json                    |
    # We also want to add a child node to make sure it is correctly removed when the parent is removed
    # Node /document/child-document
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                  | Type                    |
      | contentStreamIdentifier       | live-cs-identifier                     | Uuid                    |
      | nodeAggregateIdentifier       | cdoc-agg-identifier                    | NodeAggregateIdentifier |
      | nodeTypeName                  | Neos.ContentRepository:Document        |                         |
      | dimensionSpacePoint           | {"language":"de"}                      | DimensionSpacePoint     |
      | visibleInDimensionSpacePoints | [{"language":"de"},{"language":"gsw"}] | DimensionSpacePointSet  |
      | nodeIdentifier                | cdoc-identifier-de                     | Uuid                    |
      | parentNodeIdentifier          | doc-identifier-de                      | Uuid                    |
      | nodeName                      | child-document                         |                         |
      | propertyDefaultValuesAndTypes | {}                                     | json                    |

    And the command CreateNodeSpecialization was published with payload:
      | Key                       | Value              | Type                    |
      | contentStreamIdentifier   | live-cs-identifier | Uuid                    |
      | nodeAggregateIdentifier   | doc-agg-identifier | NodeAggregateIdentifier |
      | sourceDimensionSpacePoint | {"language":"de"}  | DimensionSpacePoint     |
      | targetDimensionSpacePoint | {"language":"gsw"} | DimensionSpacePoint     |
      | specializationIdentifier  | doc-identifier-gsw | Uuid                    |

  ########################
  # Section: EXTRA testcases
  ########################
  Scenario: (Exception) Trying to remove a non existing nodeAggregate should fail with an exception
    When the command RemoveNodeAggregate was published with payload and exceptions are caught:
      | Key                     | Value                       | Type                    |
      | contentStreamIdentifier | live-cs-identifier          | Uuid                    |
      | nodeAggregateIdentifier | non-existing-agg-identifier | NodeAggregateIdentifier |
    Then the last command should have thrown an exception of type "NodeAggregateNotFound"

  Scenario: In LIVE workspace, removing a NodeAggregate removes all nodes completely

    When the command RemoveNodeAggregate was published with payload:
      | Key                     | Value              | Type                    |
      | contentStreamIdentifier | live-cs-identifier | Uuid                    |
      | nodeAggregateIdentifier | doc-agg-identifier | NodeAggregateIdentifier |
    And the graph projection is fully up to date

    When I am in content stream "[live-cs-identifier]" and Dimension Space Point {"language":"de"}
    Then I expect a node "[doc-identifier-de]" not to exist in the graph projection
    Then I expect a node "[cdoc-identifier-de]" not to exist in the graph projection

    When I am in content stream "[live-cs-identifier]" and Dimension Space Point {"language":"gsw"}
    Then I expect a node "[doc-identifier-gsw]" not to exist in the graph projection
    Then I expect a node "[cdoc-identifier-de]" not to exist in the graph projection

  Scenario: In USER workspace, removing a NodeAggregate removes all nodes completely; leaving the live workspace untouched

    When the command "ForkContentStream" is executed with payload:
      | Key                           | Value              | Type |
      | contentStreamIdentifier       | user-cs-identifier | Uuid |
      | sourceContentStreamIdentifier | live-cs-identifier | Uuid |

    When the command RemoveNodeAggregate was published with payload:
      | Key                     | Value              | Type                    |
      | contentStreamIdentifier | user-cs-identifier | Uuid                    |
      | nodeAggregateIdentifier | doc-agg-identifier | NodeAggregateIdentifier |
    And the graph projection is fully up to date

    When I am in content stream "[user-cs-identifier]" and Dimension Space Point {"language":"de"}
    Then I expect a node "[doc-identifier-gsw]" not to exist in the graph projection
    Then I expect a node "[doc-identifier-de]" not to exist in the graph projection
    Then I expect a node "[cdoc-identifier-de]" not to exist in the graph projection

    When I am in content stream "[user-cs-identifier]" and Dimension Space Point {"language":"gsw"}
    Then I expect a node "[doc-identifier-gsw]" not to exist in the graph projection
    Then I expect a node "[cdoc-identifier-de]" not to exist in the graph projection
    Then I expect a node "[gcdoc-identifier-de]" not to exist in the graph projection

    # ensure LIVE ContentStream is untouched
    When I am in content stream "[live-cs-identifier]" and Dimension Space Point {"language":"de"}
    Then I expect the path "document" to lead to the node "[doc-identifier-de]"
    Then I expect a node "[cdoc-identifier-de]" to exist in the graph projection
    Then I expect the path "document/child-document" to lead to the node "[cdoc-identifier-de]"

    When I am in content stream "[live-cs-identifier]" and Dimension Space Point {"language":"gsw"}
    Then I expect the path "document" to lead to the node "[doc-identifier-gsw]"
    Then I expect a node "[cdoc-identifier-de]" to exist in the graph projection
    Then I expect the path "document/child-document" to lead to the node "[cdoc-identifier-de]"
