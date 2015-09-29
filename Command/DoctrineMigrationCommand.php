<?php

namespace Netgusto\BootCampBundle\Command;

use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\Bundle\MigrationsBundle\Command\MigrationsMigrateDoctrineCommand;
use Doctrine\DBAL\Migrations\Configuration\Configuration;

class DoctrineMigrationCommand extends MigrationsMigrateDoctrineCommand {
    protected function outputHeader(Configuration $configuration, OutputInterface $output) {
        # Nothing, to disable the command header
    }
}