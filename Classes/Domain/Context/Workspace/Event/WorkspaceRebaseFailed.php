<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class WorkspaceRebaseFailed implements DomainEventInterface
{
    /**
     * @var WorkspaceName
     */
    private $workspaceName;

    /**
     * The content stream on which we could not apply the source content stream's commands -- i.e. the "failed" state.
     *
     * @var ContentStreamIdentifier
     */
    private $candidateContentStreamIdentifier;

    /**
     * The content stream which we tried to rebase
     *
     * @var ContentStreamIdentifier
     */
    private $sourceContentStreamIdentifier;

    /**
     * @var array
     */
    private $errors;

    /**
     * WorkspaceRebaseFailed constructor.
     * @param WorkspaceName $workspaceName
     * @param ContentStreamIdentifier $candidateContentStreamIdentifier
     * @param ContentStreamIdentifier $sourceContentStreamIdentifier
     * @param array $errors
     */
    public function __construct(WorkspaceName $workspaceName, ContentStreamIdentifier $candidateContentStreamIdentifier, ContentStreamIdentifier $sourceContentStreamIdentifier, array $errors)
    {
        $this->workspaceName = $workspaceName;
        $this->candidateContentStreamIdentifier = $candidateContentStreamIdentifier;
        $this->sourceContentStreamIdentifier = $sourceContentStreamIdentifier;
        $this->errors = $errors;
    }

    /**
     * @return WorkspaceName
     */
    public function getWorkspaceName(): WorkspaceName
    {
        return $this->workspaceName;
    }

    /**
     * @return ContentStreamIdentifier
     */
    public function getCandidateContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->candidateContentStreamIdentifier;
    }

    /**
     * @return ContentStreamIdentifier
     */
    public function getSourceContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->sourceContentStreamIdentifier;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
