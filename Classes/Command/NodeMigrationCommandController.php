<?php
namespace Neos\EventSourcedContentRepository\Command;

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
use Neos\ContentRepository\Migration\Domain\Factory\MigrationFactory;
use Neos\EventSourcedContentRepository\Domain\Context\Migration\NodeMigrationService;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\Flow\Cli\CommandController;
use Neos\ContentRepository\Migration\Exception\MigrationException;
use Neos\ContentRepository\Migration\Domain\Model\MigrationConfiguration;
use Neos\Flow\Annotations as Flow;

/**
 * Command controller for tasks related to node migration.
 *
 * @Flow\Scope("singleton")
 */
class NodeMigrationCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var MigrationFactory
     */
    protected $migrationFactory;


    /**
     * @Flow\Inject
     * @var NodeMigrationService
     */
    protected $nodeMigrationService;

    /**
     * Do the configured migrations in the given migration.
     *
     * @param string $version The version of the migration configuration you want to use.
     * @param string $workspace The workspace where the migration should be applied; by default "live"
     * @param boolean $confirmation Confirm application of this migration, only needed if the given migration contains any warnings.
     * @return void
     * @throws \Neos\Flow\Cli\Exception\StopCommandException
     * @see neos.contentrepository.migration:node:migrationstatus
     */
    public function migrateCommand(string $version, $workspace = 'live', bool $confirmation = false)
    {
        try {
            $migrationConfiguration = $this->migrationFactory->getMigrationForVersion($version)->getUpConfiguration();

            $this->outputCommentsAndWarnings($migrationConfiguration);
            if ($migrationConfiguration->hasWarnings() && $confirmation === false) {
                $this->outputLine();
                $this->outputLine('Migration has warnings. You need to confirm execution by adding the "--confirmation true" option to the command.');
                $this->quit(1);
            }

            $contentStreamForWriting = ContentStreamIdentifier::create();
            $this->nodeMigrationService->execute($migrationConfiguration, new WorkspaceName($workspace), $contentStreamForWriting);
            $this->outputLine();
            $this->outputLine('Successfully applied migration.');
        } catch (MigrationException $e) {
            $this->outputLine();
            $this->outputLine('Error: ' . $e->getMessage());
            $this->quit(1);
        }
    }

    /**
     * Helper to output comments and warnings for the given configuration.
     *
     * @param MigrationConfiguration $migrationConfiguration
     * @return void
     */
    protected function outputCommentsAndWarnings(MigrationConfiguration $migrationConfiguration)
    {
        if ($migrationConfiguration->hasComments()) {
            $this->outputLine();
            $this->outputLine('<b>Comments</b>');
            $this->outputFormatted($migrationConfiguration->getComments(), [], 2);
        }

        if ($migrationConfiguration->hasWarnings()) {
            $this->outputLine();
            $this->outputLine('<b><u>Warnings</u></b>');
            $this->outputFormatted($migrationConfiguration->getWarnings(), [], 2);
        }
    }
}
