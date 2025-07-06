<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250706112700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ronde (id INT AUTO_INCREMENT NOT NULL, start DATETIME NOT NULL, end DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ronde_user (ronde_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_66D9667F3D1DD13C (ronde_id), INDEX IDX_66D9667FA76ED395 (user_id), PRIMARY KEY(ronde_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ronde_user ADD CONSTRAINT FK_66D9667F3D1DD13C FOREIGN KEY (ronde_id) REFERENCES ronde (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ronde_user ADD CONSTRAINT FK_66D9667FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ronde_user DROP FOREIGN KEY FK_66D9667F3D1DD13C');
        $this->addSql('ALTER TABLE ronde_user DROP FOREIGN KEY FK_66D9667FA76ED395');
        $this->addSql('DROP TABLE ronde');
        $this->addSql('DROP TABLE ronde_user');
    }
}
