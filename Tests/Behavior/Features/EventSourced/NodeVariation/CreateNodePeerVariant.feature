@fixtures
Feature: Create node peer variant

  As a user of the CR I want to create a copy of a node within an aggregate to a peer dimension space point, i.e. one that is neither a generalization nor a specialization.

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values          | Generalizations |
      | market     | DE      | DE, CH          | CH->DE          |
      | language   | en      | en, de, fr, gsw | gsw->de->en     |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:AutoCreated': []
    'Neos.ContentRepository.Testing:AutoCreatedDocument':
      childNodes:
        autocreated:
          type: 'Neos.ContentRepository.Testing:AutoCreated'
    'Neos.ContentRepository.Testing:Document':
      childNodes:
        autocreated-document:
          type: 'Neos.ContentRepository.Testing:AutoCreatedDocument'
    'Neos.ContentRepository.Testing:DocumentWithoutAutoCreatedChildren': []
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                            | Value                |
      | workspaceName                  | "live"               |
      | workspaceTitle                 | "Live"               |
      | workspaceDescription           | "The live workspace" |
      | currentContentStreamIdentifier | "cs-identifier"      |
      | initiatingUserIdentifier       | "system-user"        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                                                                                                                                                                                                                       |
      | contentStreamIdentifier       | "cs-identifier"                                                                                                                                                                                                                                                             |
      | nodeAggregateIdentifier       | "lady-eleonode-rootford"                                                                                                                                                                                                                                                    |
      | nodeTypeName                  | "Neos.ContentRepository:Root"                                                                                                                                                                                                                                               |
      | visibleInDimensionSpacePoints | [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"DE", "language":"fr"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"},{"market":"CH", "language":"fr"}] |
      | initiatingUserIdentifier      | "system-user"                                                                                                                                                                                                                                                               |
    # We have to add another node since root nodes have no origin dimension space points and thus cannot be varied.
    # We also need an autocreated child node to test that it is reachable from the freshly created peer variant of the parent
    # and we need an autocreated child node of the autocreated child node to test that this works recursively
    # and we need a non-autocreated child node to make sure it is _not_ reachable from the freshly created peer variant of the parent
    # Node /document
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                                                                                                                                                     |
      | contentStreamIdentifier       | "cs-identifier"                                                                                                                                                                                           |
      | nodeAggregateIdentifier       | "sir-david-nodenborough"                                                                                                                                                                                  |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                                                                                                                                                                 |
      | originDimensionSpacePoint     | {"market":"DE", "language":"en"}                                                                                                                                                                          |
      | visibleInDimensionSpacePoints | [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                                                                                                                                                                  |
      | nodeName                      | "document"                                                                                                                                                                                                |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                                                                                                                                                     |
      | contentStreamIdentifier       | "cs-identifier"                                                                                                                                                                                           |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                                                                                                                                                                        |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:DocumentWithoutAutoCreatedChildren"                                                                                                                                                                 |
      | originDimensionSpacePoint     | {"market":"DE", "language":"en"}                                                                                                                                                                          |
      | visibleInDimensionSpacePoints | [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"                                                                                                                                                                                  |
      | nodeName                      | "child-document"                                                                                                                                                                                          |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                                                                                                                                                     |
      | contentStreamIdentifier       | "cs-identifier"                                                                                                                                                                                           |
      | nodeAggregateIdentifier       | "nodimus-prime"                                                                                                                                                                                           |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:AutoCreatedDocument"                                                                                                                                                      |
      | originDimensionSpacePoint     | {"market":"DE", "language":"en"}                                                                                                                                                                          |
      | visibleInDimensionSpacePoints | [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"                                                                                                                                                                                  |
      | nodeName                      | "autocreated-document"                                                                                                                                                                                             |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                                                                                                                                                     |
      | contentStreamIdentifier       | "cs-identifier"                                                                                                                                                                                           |
      | nodeAggregateIdentifier       | "nodimus-mediocre"                                                                                                                                                                                        |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:AutoCreated"                                                                                                                                                              |
      | originDimensionSpacePoint     | {"market":"DE", "language":"en"}                                                                                                                                                                          |
      | visibleInDimensionSpacePoints | [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
      | parentNodeAggregateIdentifier | "nodimus-prime"                                                                                                                                                                                           |
      | nodeName                      | "autocreated"                                                                                                                                                                                             |
    # We have to add another node as a peer.
    # We also need an autocreated child node to test that it is reachable from the freshly created peer variant of the parent
    # and we need an autocreated child node of the autocreated child node to test that this works recursively
    # and we need a non-autocreated child node to make sure it is _not_ reachable from the freshly created peer variant of the parent
    # Node /document
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "madame-lanode"                           |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {"market":"CH", "language":"fr"}          |
      | visibleInDimensionSpacePoints | [{"market":"CH", "language":"fr"}]        |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                  |
      | nodeName                      | "peer-document"                           |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "nodette"                                 |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {"market":"CH", "language":"fr"}          |
      | visibleInDimensionSpacePoints | [{"market":"CH", "language":"fr"}]        |
      | parentNodeAggregateIdentifier | "madame-lanode"                           |
      | nodeName                      | "peer-child-document"                     |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "nodesis-prime"                           |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {"market":"CH", "language":"fr"}          |
      | visibleInDimensionSpacePoints | [{"market":"CH", "language":"fr"}]        |
      | parentNodeAggregateIdentifier | "madame-lanode"                           |
      | nodeName                      | "autocreated"                             |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "nodesis-mediocre"                        |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {"market":"CH", "language":"fr"}          |
      | visibleInDimensionSpacePoints | [{"market":"CH", "language":"fr"}]        |
      | parentNodeAggregateIdentifier | "nodesis-prime"                           |
      | nodeName                      | "autocreated"                             |
    And the graph projection is fully up to date

  Scenario: Create peer variant of node to dimension space point without specializations
    When the command CreateNodeVariant is executed with payload:
      | Key                       | Value                            |
      | contentStreamIdentifier   | "cs-identifier"                  |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"         |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"} |
      | targetDimensionSpacePoint | {"market":"CH", "language":"fr"} |
    Then I expect exactly 2 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier:NodeAggregate:sir-david-nodenborough"
    # The first event is NodeAggregateWithNodeWasCreated
    And event at index 1 is of type "Neos.EventSourcedContentRepository:NodePeerVariantWasCreated" with payload:
      | Key                       | Expected                           |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"           |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"}   |
      | peerLocation              | {"market":"CH", "language":"fr"}   |
      | peerVisibility            | [{"market":"CH", "language":"fr"}] |
    And I expect exactly 2 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier:NodeAggregate:nodimus-prime"
    # The first event is NodeAggregateWithNodeWasCreated
    And event at index 1 is of type "Neos.EventSourcedContentRepository:NodePeerVariantWasCreated" with payload:
      | Key                       | Expected                           |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nodimus-prime"                    |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"}   |
      | peerLocation              | {"market":"CH", "language":"fr"}   |
      | peerVisibility            | [{"market":"CH", "language":"fr"}] |
    And I expect exactly 2 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier:NodeAggregate:nodimus-mediocre"
    # The first event is NodeAggregateWithNodeWasCreated
    And event at index 1 is of type "Neos.EventSourcedContentRepository:NodePeerVariantWasCreated" with payload:
      | Key                       | Expected                           |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nodimus-mediocre"                 |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"}   |
      | peerLocation              | {"market":"CH", "language":"fr"}   |
      | peerVisibility            | [{"market":"CH", "language":"fr"}] |
    And I expect exactly 1 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier:NodeAggregate:nody-mc-nodeface"
    # No peer node creation for non-auto created child nodes

    When the graph projection is fully up to date
    Then I expect the graph projection to consist of exactly 12 nodes
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"madame-lanode", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodette", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodesis-prime", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodesis-mediocre", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph

    When I am in content stream "cs-identifier"
    Then I expect the node aggregate "lady-eleonode-rootford" to exist
    And I expect this node aggregate to occupy dimension space points [{}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"DE", "language":"fr"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"},{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "sir-david-nodenborough" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"},{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"},{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "nody-mc-nodeface" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}]

    And I expect the node aggregate "nodimus-prime" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"},{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"},{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "nodimus-mediocre" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"},{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"},{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "madame-lanode" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "nodette" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "nodesis-prime" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "nodesis-mediocre" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"CH", "language":"fr"}]

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"en"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to no node
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to no node
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to no node

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"de"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to no node
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to no node
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to no node

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"gsw"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to no node
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to no node
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to no node

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"fr"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to no node
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to no node
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to no node
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to no node
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to no node
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to no node

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"en"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to no node
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to no node
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to no node

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"de"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to no node
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to no node
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to no node

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"gsw"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to no node
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to no node
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to no node
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to no node

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"fr"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to no node
    And I expect node aggregate identifier "nodimus-prime" and path "document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}
    And I expect node aggregate identifier "nodimus-mediocre" and path "document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}
    And I expect node aggregate identifier "madame-lanode" and path "peer-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"madame-lanode", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}
    And I expect node aggregate identifier "nodette" and path "peer-document/peer-child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodette", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}
    And I expect node aggregate identifier "nodesis-prime" and path "peer-document/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodesis-prime", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}
    And I expect node aggregate identifier "nodesis-mediocre" and path "peer-document/autocreated/autocreated" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodesis-mediocre", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}

  Scenario: Create a peer node variant to a dimension space point with specializations and where the parent node aggregate is already specialized in
    Given the event NodePeerVariantWasCreated was published with payload:
      | Key                       | Value                                                               |
      | contentStreamIdentifier   | "cs-identifier"                                                     |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"                                            |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"}                                    |
      | peerLocation              | {"market":"DE", "language":"fr"}                                    |
      | peerVisibility            | [{"market":"DE", "language":"fr"},{"market":"CH", "language":"fr"}] |
    And the event NodeSpecializationVariantWasCreated was published with payload:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"           |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"fr"}   |
      | specializationLocation    | {"market":"CH", "language":"fr"}   |
      | specializationVisibility  | [{"market":"CH", "language":"fr"}] |
    And the graph projection is fully up to date

    When the command CreateNodeVariant is executed with payload:
      | Key                       | Value                            |
      | contentStreamIdentifier   | "cs-identifier"                  |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"               |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"} |
      | targetDimensionSpacePoint | {"market":"DE", "language":"fr"} |
    Then I expect exactly 2 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier:NodeAggregate:nody-mc-nodeface"
    # The first event is NodeAggregateWithNodeWasCreated
    And event at index 1 is of type "Neos.EventSourcedContentRepository:NodePeerVariantWasCreated" with payload:
      | Key                       | Expected                                                            |
      | contentStreamIdentifier   | "cs-identifier"                                                     |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                                  |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"}                                    |
      | peerLocation              | {"market":"DE", "language":"fr"}                                    |
      | peerVisibility            | [{"market":"DE", "language":"fr"},{"market":"CH", "language":"fr"}] |

    When the graph projection is fully up to date
    Then I expect the graph projection to consist of exactly 12 nodes
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-prime", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimus-mediocre", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"madame-lanode", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodette", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodesis-prime", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodesis-mediocre", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}} to exist in the content graph

    When I am in content stream "cs-identifier"

    # only nodenborough and mc-nodeface are affected

    Then I expect the node aggregate "sir-david-nodenborough" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"fr"},{"market":"CH", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"DE", "language":"fr"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"},{"market":"CH", "language":"fr"}]

    And I expect the node aggregate "nody-mc-nodeface" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"fr"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"DE", "language":"fr"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"},{"market":"CH", "language":"fr"}]


    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"en"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"de"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"gsw"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"fr"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"fr"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"fr"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"en"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"de"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"gsw"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"fr"}
    Then I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"fr"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"fr"}}

  Scenario: Create specialization of node to dimension space point with specializations that are partially occupied and covered
    When the event NodeSpecializationVariantWasCreated was published with payload:
      | Key                       | Value                                                                |
      | contentStreamIdentifier   | "cs-identifier"                                                      |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"                                             |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"}                                     |
      | specializationLocation    | {"market":"CH", "language":"de"}                                     |
      | specializationVisibility  | [{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
    And the graph projection is fully up to date

    When the command CreateNodeVariant is executed with payload:
      | Key                       | Value                            |
      | contentStreamIdentifier   | "cs-identifier"                  |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"         |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"} |
      | targetDimensionSpacePoint | {"market":"CH", "language":"en"} |
    Then I expect exactly 3 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier:NodeAggregate:sir-david-nodenborough"
    # The first event is NodeAggregateWithNodeWasCreated
    # The second event is the above
    And event at index 2 is of type "Neos.EventSourcedContentRepository:NodeSpecializationVariantWasCreated" with payload:
      | Key                       | Expected                           |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"           |
      | sourceDimensionSpacePoint | {"market":"DE", "language":"en"}   |
      | specializationLocation    | {"market":"CH", "language":"en"}   |
      | specializationVisibility  | [{"market":"CH", "language":"en"}] |

    When the graph projection is fully up to date
    Then I expect the graph projection to consist of exactly 5 nodes
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"de"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"en"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}} to exist in the content graph

    When I am in content stream "cs-identifier"
    Then I expect the node aggregate "lady-eleonode-rootford" to exist
    And I expect this node aggregate to occupy dimension space points [{}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}]

    And I expect the node aggregate "sir-david-nodenborough" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}]

    And I expect the node aggregate "nody-mc-nodeface" to exist
    And I expect this node aggregate to occupy dimension space points [{"market":"DE", "language":"en"}]
    And I expect this node aggregate to cover dimension space points [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}]

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"en"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"de"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"DE", "language":"gsw"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"en"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"en"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"de"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"de"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"market":"CH", "language":"gsw"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" and path "" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"market":"CH", "language":"de"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"market":"DE", "language":"en"}}
