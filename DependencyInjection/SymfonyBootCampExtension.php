<?php

namespace Symfony\BootCampBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\Config\FileLocator,
    Symfony\Component\HttpKernel\DependencyInjection\Extension,
    Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface,
    Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SymfonyBootCampExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container) {

        # In prepend, to configure DBAL and ORM only if database DSN could be determined from the environment
        if(isset($GLOBALS['BOOTCAMP_INITIALIZING']) && $GLOBALS['BOOTCAMP_INITIALIZING']) {
            $kernelrootdir = $container->getParameter('kernel.root_dir');

            $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/..'));
            $yamlLoader->load('Resources/config/parameters.yml');
            $yamlLoader->load('Resources/config/services.yml');

            $phpLoader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__.'/..'));
            $phpLoader->load('ParameterProcessor/Environment.php');
            $phpLoader->load('ParameterProcessor/Database.php');

            # Check if database has been loaded from environment

            if($container->getParameter('database_configured') === TRUE) {
                # Database has been configured
                # We add the parameter binding to configure Doctrine DBAL and Doctrine ORM

                $yamlLoader->load('Resources/config/doctrine.yml');
            }
        }
    }

    public function load(array $configs, ContainerBuilder $container) {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
    }
}
