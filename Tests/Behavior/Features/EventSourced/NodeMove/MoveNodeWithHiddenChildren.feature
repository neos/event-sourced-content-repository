@fixtures
Feature: Move a node without content dimensions

  As a user of the CR I want to move a node that
  - is disabled by one of its ancestors
  - disables itself
  - disables any of its descendants
  - is enabled

  to a new parent that
  - is enabled
  - disables itself
  - is disabled by one of its ancestors

  These are the test cases without content dimensions being involved

  Background:
    Given I have no content dimensions
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document': []
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                            | Value                                  |
      | workspaceName                  | "live"                                 |
      | workspaceTitle                 | "Live"                                 |
      | workspaceDescription           | "The live workspace"                   |
      | initiatingUserIdentifier       | "00000000-0000-0000-0000-000000000000" |
      | currentContentStreamIdentifier | "cs-identifier"                        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                  |
      | contentStreamIdentifier       | "cs-identifier"                        |
      | nodeAggregateIdentifier       | "lady-eleonode-rootford"               |
      | nodeTypeName                  | "Neos.ContentRepository:Root"          |
      | visibleInDimensionSpacePoints | [{}]                                   |
      | initiatingUserIdentifier      | "00000000-0000-0000-0000-000000000000" |
      | nodeAggregateClassification   | "root"                                 |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "sir-david-nodenborough"                  |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {}                                        |
      | visibleInDimensionSpacePoints | [{}]                                      |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                  |
      | nodeName                      | "document"                                |
      | nodeAggregateClassification   | "regular"                                 |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                        |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {}                                        |
      | visibleInDimensionSpacePoints | [{}]                                      |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"                  |
      | nodeName                      | "child-document"                          |
      | nodeAggregateClassification   | "regular"                                 |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "sir-nodeward-nodington-iii"              |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {}                                        |
      | visibleInDimensionSpacePoints | [{}]                                      |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                  |
      | nodeName                      | "esquire"                                 |
      | nodeAggregateClassification   | "regular"                                 |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "nodimus-prime"                           |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {}                                        |
      | visibleInDimensionSpacePoints | [{}]                                      |
      | parentNodeAggregateIdentifier | "sir-nodeward-nodington-iii"              |
      | nodeName                      | "esquire-child"                           |
      | nodeAggregateClassification   | "regular"                                 |
    And the graph projection is fully up to date

  Scenario: Move a node disabled by one of its ancestors to a new parent that is enabled
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the graph projection is fully up to date

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "nody-mc-nodeface"           |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "sir-nodeward-nodington-iii" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date

    # node aggregate occupation and coverage is not relevant without dimensions and thus not tested

    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"
    And I expect node aggregate identifier "nody-mc-nodeface" and path "esquire/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {}}
    And I expect this node to be a child of node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-nodeward-nodington-iii", "originDimensionSpacePoint": {}}

  Scenario: Move a node disabled by itself to a new parent that is enabled
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value              |
      | contentStreamIdentifier      | "cs-identifier"    |
      | nodeAggregateIdentifier      | "nody-mc-nodeface" |
      | affectedDimensionSpacePoints | [{}]               |
    And the graph projection is fully up to date

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "nody-mc-nodeface"           |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "sir-nodeward-nodington-iii" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "nody-mc-nodeface" and path "esquire/child-document" to lead to no node

  Scenario: Move a node that disables one of its descendants to a new parent that is enabled
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the graph projection is fully up to date

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "sir-david-nodenborough"     |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "sir-nodeward-nodington-iii" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "sir-david-nodenborough" and path "esquire/document" to lead to no node
    Then I expect node aggregate identifier "nody-mc-nodeface" and path "esquire/document/child-document" to lead to no node

  Scenario: Move a node that is disabled by one of its ancestors to a new parent that disables itself
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |
    And the graph projection is fully up to date

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "nody-mc-nodeface"           |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "sir-nodeward-nodington-iii" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "nody-mc-nodeface" and path "esquire/child-document" to lead to no node

  Scenario: Move a node that is disabled by itself to a new parent that disables itself
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "sir-david-nodenborough"     |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "sir-nodeward-nodington-iii" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "sir-david-nodenborough" and path "esquire/document" to lead to no node

  Scenario: Move a node that is enabled to a new parent that disables itself
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "sir-david-nodenborough"     |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "sir-nodeward-nodington-iii" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "sir-david-nodenborough" and path "esquire/document" to lead to no node

  Scenario: Move a node that disables any of its descendants to a new parent that disables itself
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "sir-david-nodenborough"     |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "sir-nodeward-nodington-iii" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "sir-david-nodenborough" and path "esquire/document" to lead to no node
    Then I expect node aggregate identifier "nody-mc-nodeface" and path "esquire/document/child-document" to lead to no node

  Scenario: Move a node that is disabled by one of its ancestors to a new parent that is disabled by one of its ancestors
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |

    When the command MoveNode is executed with payload:
      | Key                                         | Value              |
      | contentStreamIdentifier                     | "cs-identifier"    |
      | nodeAggregateIdentifier                     | "nody-mc-nodeface" |
      | dimensionSpacePoint                         | {}                 |
      | newParentNodeAggregateIdentifier            | "nodimus-prime"    |
      | newSucceedingSiblingNodeAggregateIdentifier | null               |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "nody-mc-nodeface" and path "esquire/esquire-child/child-document" to lead to no node

  Scenario: Move a node that is disabled by itself to a new parent that is disabled by one of its ancestors
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |

    When the command MoveNode is executed with payload:
      | Key                                         | Value                    |
      | contentStreamIdentifier                     | "cs-identifier"          |
      | nodeAggregateIdentifier                     | "sir-david-nodenborough" |
      | dimensionSpacePoint                         | {}                       |
      | newParentNodeAggregateIdentifier            | "nodimus-prime"          |
      | newSucceedingSiblingNodeAggregateIdentifier | null                     |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "sir-david-nodenborough" and path "esquire/esquire-child/document" to lead to no node

  Scenario: Move a node that disables any of its descendants to a new parent that is disabled by one of its ancestors
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | affectedDimensionSpacePoints | [{}]                     |
    And the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |

    When the command MoveNode is executed with payload:
      | Key                                         | Value                    |
      | contentStreamIdentifier                     | "cs-identifier"          |
      | nodeAggregateIdentifier                     | "sir-david-nodenborough" |
      | dimensionSpacePoint                         | {}                       |
      | newParentNodeAggregateIdentifier            | "nodimus-prime"          |
      | newSucceedingSiblingNodeAggregateIdentifier | null                     |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "sir-david-nodenborough" and path "esquire/esquire-child/document" to lead to no node
    Then I expect node aggregate identifier "nody-mc-nodeface" and path "esquire/esquire-child/document/child-document" to lead to no node

  Scenario: Move a node that is enabled to a new parent that is disabled by one of its ancestors
    Given the event NodeAggregateWasDisabled was published with payload:
      | Key                          | Value                        |
      | contentStreamIdentifier      | "cs-identifier"              |
      | nodeAggregateIdentifier      | "sir-nodeward-nodington-iii" |
      | affectedDimensionSpacePoints | [{}]                         |

    When the command MoveNode is executed with payload:
      | Key                                         | Value                        |
      | contentStreamIdentifier                     | "cs-identifier"              |
      | nodeAggregateIdentifier                     | "sir-david-nodenborough"     |
      | dimensionSpacePoint                         | {}                           |
      | newParentNodeAggregateIdentifier            | "nodimus-prime"              |
      | newSucceedingSiblingNodeAggregateIdentifier | null                         |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {}
    And VisibilityConstraints are set to "frontend"

    Then I expect node aggregate identifier "sir-david-nodenborough" and path "esquire/esquire-child/document" to lead to no node
