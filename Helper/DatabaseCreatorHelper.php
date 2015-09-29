<?php

namespace Netgusto\BootCampBundle\Helper;

use Doctrine\DBAL\DriverManager,
    Doctrine\DBAL\Connection;

class DatabaseCreatorHelper {

    public static function createDatabase(Connection $connection) {

        $params = $connection->getParams();
        $name = isset($params['path']) ? $params['path'] : $params['dbname'];

        unset($params['dbname']);

        $tmpConnection = DriverManager::getConnection($params);

        // Only quote if we don't have a path
        if (!isset($params['path'])) {
            $name = $tmpConnection->getDatabasePlatform()->quoteSingleIdentifier($name);
        }

        $error = FALSE;

        try {
            $tmpConnection->getSchemaManager()->createDatabase($name);
        } catch (\Exception $e) {
            $error = TRUE;
        }

        $tmpConnection->close();

        return !$error;
    }
}