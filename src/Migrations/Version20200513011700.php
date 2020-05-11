<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200513011700 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE materia_loadout CHANGE table_order parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE materia_loadout ADD CONSTRAINT FK_1D26F664727ACA70 FOREIGN KEY (parent_id) REFERENCES materia_loadout (id)');
        $this->addSql('CREATE INDEX IDX_1D26F664727ACA70 ON materia_loadout (parent_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE materia_loadout DROP FOREIGN KEY FK_1D26F664727ACA70');
        $this->addSql('DROP INDEX IDX_1D26F664727ACA70 ON materia_loadout');
        $this->addSql('ALTER TABLE materia_loadout CHANGE parent_id table_order INT DEFAULT NULL');
    }
}
