<?php
namespace Neos\EventSourcedContentRepository\Command;

use Neos\EventSourcedContentRepository\Service\WorkspaceMaintenanceService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 *
 */
class WorkspaceCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var WorkspaceMaintenanceService
     */
    protected $workspaceMaintenanceService;

    /**
     * Rebase all outdated content streams
     */
    public function rebaseOutdatedCommand()
    {
        $outdatedWorkspaces = $this->workspaceMaintenanceService->rebaseOutdatedWorkspaces();

        if (!count($outdatedWorkspaces)) {
            $this->outputLine('There are no outdated workspaces.');
        } else {
            foreach ($outdatedWorkspaces as $outdatedWorkspace) {
                $this->outputFormatted('Rebased workspace %s', [$outdatedWorkspace]);
            }
        }
    }
}
