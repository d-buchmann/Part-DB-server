<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261120100000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add the built-in label sheet selection to label options';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD options_sheet VARCHAR(255) DEFAULT '' NOT NULL");
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP options_sheet');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD COLUMN options_sheet VARCHAR(255) DEFAULT '' NOT NULL");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_sheet');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql("ALTER TABLE label_profiles ADD options_sheet VARCHAR(255) DEFAULT '' NOT NULL");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP options_sheet');
    }
}
