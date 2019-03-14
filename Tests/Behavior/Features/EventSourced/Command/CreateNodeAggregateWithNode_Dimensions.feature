@fixtures
Feature: Create node aggregate with node

  As a user of the CR I want to create a new externally referenceable node aggregate of a specific type with a node
  in a specific dimension space point.

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values          | Generalizations      |
      | language   | mul     | mul, de, en, ch | ch->de->mul, en->mul |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:NodeWithoutAutoCreatedChildNodes':
      properties:
        text:
          defaultValue: 'my default'
          type: string
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                            | Value                                  |
      | workspaceName                  | "live"                                 |
      | workspaceTitle                 | "Live"                                 |
      | workspaceDescription           | "The live workspace"                   |
      | initiatingUserIdentifier       | "00000000-0000-0000-0000-000000000000" |
      | currentContentStreamIdentifier | "cs-identifier"                        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                      |
      | contentStreamIdentifier       | "cs-identifier"                                                            |
      | nodeAggregateIdentifier       | "sir-david-nodenborough"                                                   |
      | nodeTypeName                  | "Neos.ContentRepository:Root"                                              |
      | visibleInDimensionSpacePoints | [{"language":"mul"},{"language":"de"},{"language":"en"},{"language":"ch"}] |
      | initiatingUserIdentifier      | "00000000-0000-0000-0000-000000000000"                                     |
    And the graph projection is fully up to date

  Scenario:  Create node aggregate with node with content dimensions
    When the command CreateNodeAggregateWithNode is executed with payload:
      | Key                           | Value                                                             |
      | contentStreamIdentifier       | "cs-identifier"                                                   |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                                |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:NodeWithoutAutoCreatedChildNodes" |
      | originDimensionSpacePoint     | {"language": "de"}                                                |
      | initiatingUserIdentifier      | "00000000-0000-0000-0000-000000000000"                            |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"                                          |
    And the graph projection is fully up to date
    And I am in content stream "cs-identifier" and Dimension Space Point {"language": "de"}

    Then I expect exactly 1 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier:NodeAggregate:nody-mc-nodeface"
    And event at index 0 is of type "Neos.EventSourcedContentRepository:NodeAggregateWithNodeWasCreated" with payload:
      | Key                           | Expected                                                          |
      | contentStreamIdentifier       | "cs-identifier"                                                   |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                                |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:NodeWithoutAutoCreatedChildNodes" |
      | originDimensionSpacePoint     | {"language": "de"}                                                |
      | visibleInDimensionSpacePoints | [{"language":"de"},{"language":"ch"}]                             |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"                                          |
      | initialPropertyValues         | {"text": {"value": "my default", "type": "string"}}               |

    And I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
