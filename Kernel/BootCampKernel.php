<?php

namespace Netgusto\BootCampBundle\Kernel;

use Symfony\Component\HttpKernel\Kernel as SymfonyKernel,
    Symfony\Component\Config\Loader\LoaderInterface;

class BootCampKernel extends SymfonyKernel {

    protected $kernelrootdir;
    
    public function setRootDir($kernelrootdir) {
        $this->rootDir = $kernelrootdir;
    }

    public function registerBundles() {

        $bundles = array(
            new \Netgusto\BootCampBundle\NetgustoBootCampBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle(),
        );
        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader) {
        $loader->load($this->getRootDir() .'/config/config_bootcamp.yml');
    }
}
