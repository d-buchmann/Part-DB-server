<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261120090000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add sheet geometry (page size, margins, pitch) to label options for exact N-up label placement';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD options_page_width DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_page_height DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_margin_top DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_margin_left DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_horizontal_pitch DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD options_vertical_pitch DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP options_page_width, DROP options_page_height, DROP options_margin_top, DROP options_margin_left, DROP options_horizontal_pitch, DROP options_vertical_pitch');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_page_width DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_page_height DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_margin_top DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_margin_left DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_horizontal_pitch DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_vertical_pitch DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_page_width');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_page_height');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_margin_top');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_margin_left');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_horizontal_pitch');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_vertical_pitch');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD options_page_width DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD options_page_height DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD options_margin_top DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD options_margin_left DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD options_horizontal_pitch DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD options_vertical_pitch DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP options_page_width, DROP options_page_height, DROP options_margin_top, DROP options_margin_left, DROP options_horizontal_pitch, DROP options_vertical_pitch');
    }
}
