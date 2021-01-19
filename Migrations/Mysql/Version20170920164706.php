<?php
declare(strict_types=1);
namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20170920164706 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Remove collation from workspace.workspaceOwner column';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('ALTER TABLE neos_contentrepository_projection_workspace_v1 CHANGE workspaceowner workspaceowner VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('ALTER TABLE neos_contentrepository_projection_workspace_v1 CHANGE workspaceowner workspaceowner VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
    }
}
