<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261120110000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add the option to remember the next free label position on a sheet';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD options_remember_position TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP options_remember_position');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_remember_position BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_remember_position');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD options_remember_position BOOLEAN DEFAULT false NOT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP options_remember_position');
    }
}
