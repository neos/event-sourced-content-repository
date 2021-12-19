<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Tests\Behavior\Features\Bootstrap\Features;

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
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\DiscardIndividualNodesFromWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\DiscardWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;

/**
 * The workspace discarding feature trait for behavioral tests
 */
trait WorkspaceDiscarding
{
    abstract protected function getCurrentUserIdentifier(): ?UserIdentifier;

    abstract protected function getWorkspaceCommandHandler(): WorkspaceCommandHandler;

    abstract protected function readPayloadTable(TableNode $payloadTable): array;

    /**
     * @Given /^the command DiscardWorkspace is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws \Exception
     */
    public function theCommandDiscardWorkspaceIsExecuted(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $initiatingUserIdentifier = isset($commandArguments['initiatingUserIdentifier'])
            ? UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
            : $this->getCurrentUserIdentifier();
        $newContentStreamIdentifier = isset($commandArguments['newContentStreamIdentifier'])
            ? ContentStreamIdentifier::fromString($commandArguments['newContentStreamIdentifier'])
            : ContentStreamIdentifier::create();

        $command = DiscardWorkspace::createFullyDeterministic(
            new WorkspaceName($commandArguments['workspaceName']),
            $initiatingUserIdentifier,
            $newContentStreamIdentifier
        );

        $this->lastCommandOrEventResult = $this->getWorkspaceCommandHandler()
            ->handleDiscardWorkspace($command);
    }


    /**
     * @Given /^the command DiscardIndividualNodesFromWorkspace is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws \Exception
     */
    public function theCommandDiscardIndividualNodesFromWorkspaceIsExecuted(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $nodeAddresses = array_map(function (array $serializedNodeAddress) {
            return NodeAddress::fromArray($serializedNodeAddress);
        }, $commandArguments['nodeAddresses']);
        $initiatingUserIdentifier = isset($commandArguments['initiatingUserIdentifier'])
            ? UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
            : $this->getCurrentUserIdentifier();
        $newContentStreamIdentifier = isset($commandArguments['newContentStreamIdentifier'])
            ? ContentStreamIdentifier::fromString($commandArguments['newContentStreamIdentifier'])
            : ContentStreamIdentifier::create();

        $command = DiscardIndividualNodesFromWorkspace::createFullyDeterministic(
            new WorkspaceName($commandArguments['workspaceName']),
            $nodeAddresses,
            $initiatingUserIdentifier,
            $newContentStreamIdentifier
        );

        $this->lastCommandOrEventResult = $this->getWorkspaceCommandHandler()
            ->handleDiscardIndividualNodesFromWorkspace($command);
    }
}
