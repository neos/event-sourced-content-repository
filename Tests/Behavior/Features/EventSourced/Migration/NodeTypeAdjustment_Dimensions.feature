@fixtures
Feature: Adjust node types with a node migration

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values          | Generalizations      |
      | language   | mul     | mul, de, en, ch | ch->de->mul, en->mul |

  Scenario: Success Case
    ########################
    # SETUP
    ########################
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root':
      constraints:
        nodeTypes:
          'Neos.ContentRepository.Testing:Document': true
          'Neos.ContentRepository.Testing:OtherDocument': true

    'Neos.ContentRepository.Testing:Document': []
    'Neos.ContentRepository.Testing:OtherDocument': []
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                        | Value                |
      | workspaceName              | "live"               |
      | workspaceTitle             | "Live"               |
      | workspaceDescription       | "The live workspace" |
      | newContentStreamIdentifier | "cs-identifier"      |
      | initiatingUserIdentifier   | "system-user"        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                                      |
      | contentStreamIdentifier     | "cs-identifier"                                                            |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                                   |
      | nodeTypeName                | "Neos.ContentRepository:Root"                                              |
      | coveredDimensionSpacePoints | [{"language":"mul"},{"language":"de"},{"language":"en"},{"language":"ch"}] |
      | initiatingUserIdentifier    | "system-user"                                                              |
      | nodeAggregateClassification | "root"                                                                     |
    And the graph projection is fully up to date
    # Node /document
    When the command CreateNodeAggregateWithNode is executed with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "sir-david-nodenborough"                  |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {"language": "de"}                        |
      | initiatingUserIdentifier      | "00000000-0000-0000-0000-000000000000"    |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                  |
    And the graph projection is fully up to date

    ########################
    # Actual Test
    ########################
    # we remove the Document node type (which still exists in the CR)
    And I have the following NodeTypes configuration:
    """
    # !!fallback node is needed!! - TODO DISCUSS
    'Neos.Neos:FallbackNode': []

    'Neos.ContentRepository:Root':
      constraints:
        nodeTypes:
          'Neos.ContentRepository.Testing:OtherDocument': true

    'Neos.ContentRepository.Testing:OtherDocument': []
    """
    # we should be able to rename the node type
    When I run the following node migration for workspace "live", creating content streams "migration-cs":
    """
    migration:
      -
        filters:
          -
            type: 'NodeType'
            settings:
              nodeType: 'Neos.ContentRepository.Testing:Document'
        transformations:
          -
            type: 'ChangeNodeType'
            settings:
              newType: 'Neos.ContentRepository.Testing:OtherDocument'
    """
    # the original content stream has not been touched
    When I am in content stream "cs-identifier" and Dimension Space Point {"language": "de"}
    Then I expect a node identified by aggregate identifier "sir-david-nodenborough" to exist in the subgraph
    And I expect this node to be of type "Neos.ContentRepository.Testing:Document"
    # ... also in the fallback dimension
    When I am in content stream "cs-identifier" and Dimension Space Point {"language": "ch"}
    Then I expect a node identified by aggregate identifier "sir-david-nodenborough" to exist in the subgraph
    And I expect this node to be of type "Neos.ContentRepository.Testing:Document"

    # the node type was changed inside the new content stream
    When I am in content stream "migration-cs" and Dimension Space Point {"language": "de"}
    Then I expect a node identified by aggregate identifier "sir-david-nodenborough" to exist in the subgraph
    And I expect this node to be of type "Neos.ContentRepository.Testing:OtherDocument"
    # ... also in the fallback dimension
    When I am in content stream "migration-cs" and Dimension Space Point {"language": "ch"}
    Then I expect a node identified by aggregate identifier "sir-david-nodenborough" to exist in the subgraph
    And I expect this node to be of type "Neos.ContentRepository.Testing:OtherDocument"
