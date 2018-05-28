<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20180529102348 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('ALTER TABLE transaction ADD type VARCHAR(255) NOT NULL DEFAULT \'paybox\'');
        $this->addSql('ALTER TABLE transaction CHANGE type type VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema)
    {
        $this->addSql('ALTER TABLE transaction DROP type');
    }
}
