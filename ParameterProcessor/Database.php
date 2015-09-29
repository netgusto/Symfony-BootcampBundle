<?php

use Netgusto\BootCampBundle\Helper\DatabaseUrlResolverHelper;

$_bootdb = function($container) {

    $databaseurl_variablename = $container->getParameter('bootcamp.environment.databaseurl_variablename');

    $env = $container->getParameter('environment');
    $databaseurl = isset($env[$databaseurl_variablename]) ? str_replace('%kernel.root_dir%', $container->getParameter('kernel.root_dir'), $env[$databaseurl_variablename]) : FALSE;

    if($databaseurl !== FALSE) {
        $dbparameters = DatabaseUrlResolverHelper::resolve($databaseurl);

        foreach($dbparameters as $parametername => $parametervalue) {
            $container->setParameter('database_' . $parametername, $parametervalue);
        }

        $container->setParameter('database_configured', TRUE);
    } else {
        $container->setParameter('database_configured', FALSE);
    }
};

$_bootdb($container);
unset($_bootdb);