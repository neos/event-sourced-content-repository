@fixtures
Feature: Tethered Nodes Reordering Structure changes

  As a user of the CR I want to be able to detect wrongly ordered tethered nodes, and fix them.

  Background:
    Given I have no content dimensions
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document':
      childNodes:
        'tethered-node':
          type: 'Neos.ContentRepository.Testing:Tethered'
        'other-tethered-node':
          type: 'Neos.ContentRepository.Testing:Tethered'
        'third-tethered-node':
          type: 'Neos.ContentRepository.Testing:Tethered'
    'Neos.ContentRepository.Testing:Tethered': []
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                        | Value                |
      | workspaceName              | "live"               |
      | workspaceTitle             | "Live"               |
      | workspaceDescription       | "The live workspace" |
      | newContentStreamIdentifier | "cs-identifier"      |
      | initiatingUserIdentifier   | "system-user"        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                         |
      | contentStreamIdentifier     | "cs-identifier"               |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"      |
      | nodeTypeName                | "Neos.ContentRepository:Root" |
      | coveredDimensionSpacePoints | [{}]                          |
      | initiatingUserIdentifier    | "system-user"                 |
      | nodeAggregateClassification | "root"                        |
    And the graph projection is fully up to date
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                                        | Value                                                                                                                                      |
      | contentStreamIdentifier                    | "cs-identifier"                                                                                                                            |
      | nodeAggregateIdentifier                    | "sir-david-nodenborough"                                                                                                                   |
      | nodeTypeName                               | "Neos.ContentRepository.Testing:Document"                                                                                                  |
      | originDimensionSpacePoint                  | {}                                                                                                                                         |
      | parentNodeAggregateIdentifier              | "lady-eleonode-rootford"                                                                                                                   |
      | nodeName                                   | "document"                                                                                                                                 |
      | tetheredDescendantNodeAggregateIdentifiers | {"tethered-node": "tethered-node-agg", "other-tethered-node": "other-tethered-node-agg", "third-tethered-node": "third-tethered-node-agg"} |
    And the graph projection is fully up to date

    Then I expect no needed structure adjustments for type "Neos.ContentRepository.Testing:Document"

    When I am in the active content stream of workspace "live" and Dimension Space Point {}
    And I get the node at path "document/tethered-node"
    And I expect this node to have the preceding siblings []
    And I expect this node to have the succeeding siblings ["other-tethered-node-agg", "third-tethered-node-agg"]

  Scenario: re-ordering the tethered child nodes brings up wrongly sorted tethered nodes
    Given I have the following additional NodeTypes configuration:
    """
    'Neos.ContentRepository.Testing:Document':
      childNodes:
        'other-tethered-node':
          position: start
    """
    Then I expect the following structure adjustments for type "Neos.ContentRepository.Testing:Document":
      | Type                          | nodeAggregateIdentifier |
      | TETHERED_NODE_WRONGLY_ORDERED | sir-david-nodenborough  |
    When I adjust the node structure for node type "Neos.ContentRepository.Testing:Document"
    Then I expect no needed structure adjustments for type "Neos.ContentRepository.Testing:Document"

    When I am in the active content stream of workspace "live" and Dimension Space Point {}
    And I get the node at path "document/tethered-node"
    And I expect this node to have the preceding siblings ["other-tethered-node-agg"]
    And I expect this node to have the succeeding siblings ["third-tethered-node-agg"]
