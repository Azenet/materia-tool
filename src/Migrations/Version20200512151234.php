<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200512151234 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', token LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE materia_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(255) NOT NULL, image VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE materia_loadout (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, name VARCHAR(255) NOT NULL, root TINYINT(1) NOT NULL, table_order INT DEFAULT NULL, INDEX IDX_1D26F6647E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE materia (id INT AUTO_INCREMENT NOT NULL, type_id INT NOT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_6DF05284C54C8C93 (type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE materia_loadout_item (id INT AUTO_INCREMENT NOT NULL, loadout_id INT NOT NULL, materia_id INT DEFAULT NULL, row INT NOT NULL, col INT NOT NULL, char_name VARCHAR(4) NOT NULL, INDEX IDX_4E2D9BBD7DFEF3F7 (loadout_id), INDEX IDX_4E2D9BBDB54DBBCB (materia_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE materia_loadout ADD CONSTRAINT FK_1D26F6647E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE materia ADD CONSTRAINT FK_6DF05284C54C8C93 FOREIGN KEY (type_id) REFERENCES materia_type (id)');
        $this->addSql('ALTER TABLE materia_loadout_item ADD CONSTRAINT FK_4E2D9BBD7DFEF3F7 FOREIGN KEY (loadout_id) REFERENCES materia_loadout (id)');
        $this->addSql('ALTER TABLE materia_loadout_item ADD CONSTRAINT FK_4E2D9BBDB54DBBCB FOREIGN KEY (materia_id) REFERENCES materia (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE materia_loadout DROP FOREIGN KEY FK_1D26F6647E3C61F9');
        $this->addSql('ALTER TABLE materia DROP FOREIGN KEY FK_6DF05284C54C8C93');
        $this->addSql('ALTER TABLE materia_loadout_item DROP FOREIGN KEY FK_4E2D9BBD7DFEF3F7');
        $this->addSql('ALTER TABLE materia_loadout_item DROP FOREIGN KEY FK_4E2D9BBDB54DBBCB');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE materia_type');
        $this->addSql('DROP TABLE materia_loadout');
        $this->addSql('DROP TABLE materia');
        $this->addSql('DROP TABLE materia_loadout_item');
    }
}
