<?php

namespace Netgusto\BootCampBundle\Composer;

use Composer\Script\CommandEvent,
    Composer\IO\IOInterface as ComposerIOInterface;

use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\ParameterBag\ParameterBag,
    Symfony\Component\Yaml\Yaml,
    Symfony\Component\Yaml\Parser as YamlParser,
    Symfony\Component\Debug\Debug;

use Symfony\Component\DependencyInjection\Loader\YamlFileLoader,
    Symfony\Component\HttpKernel\KernelInterface,
    Symfony\Component\HttpKernel\Config\FileLocator,
    Symfony\Component\Process\PhpExecutableFinder,
    Symfony\Component\Process\ProcessBuilder,
    Symfony\Component\Console\Output\BufferedOutput,
    Symfony\Bundle\FrameworkBundle\Console\Application;

use Doctrine\DBAL\Connection as DBALConnection,
    Doctrine\ORM\EntityManager;

use Symfony\Bundle\AsseticBundle\Command\DumpCommand;

use Netgusto\BootCampBundle\Kernel\BootCampKernel,
    Netgusto\BootCampBundle\Helper\DatabaseCreatorHelper,
    Netgusto\BootCampBundle\Helper\PlatformDetectorHelper,
    Netgusto\BootCampBundle\Helper\Platform,
    Netgusto\BootCampBundle\Command\DoctrineMigrationCommand,
    Netgusto\BootCampBundle\InitHandler\UserInitHandlerInterface,
    Netgusto\BootCampBundle\InitHandler\ConfigInitHandlerInterface,
    Netgusto\BootCampBundle\Entity\ConfigContainer,
    Netgusto\BootCampBundle\Entity\BootCampStatus;


final class ScriptHandler {

    const DIAG_DBNOCONNECTION = 'DIAG_DBNOCONNECTION';
    const DIAG_DBMISSING = 'DIAG_DBMISSING';
    const DIAG_UNKNOWNSTATUS = 'DIAG_UNKNOWNSTATUS';

    public static function install(CommandEvent $event) {

        $io = $event->getIO();

        # Building and booting the Kernel
        $GLOBALS['BOOTCAMP_INITIALIZING'] = true;
        $kernelrootdir = self::determineKernelRootDir($event);
        $kernel = self::getKernel($kernelrootdir);
        $kernel->boot();

        $container = $kernel->getContainer();

        # Fetching required parameters

        $appname = $container->getParameter('bootcamp.appname');
        $appversion = $container->getParameter('bootcamp.appversion');
        $database_configured = $container->getParameter('database_configured');
        $databaseurl_variablename = $container->getParameter('bootcamp.environment.databaseurl_variablename');
        $userinithandlerServiceId = $container->hasParameter('bootcamp.init.user.handler') ? $container->getParameter('bootcamp.init.user.handler') : null;
        if($userinithandlerServiceId) {
            $userinitusername = $container->getParameter('bootcamp.init.user.username');
            $userinitpassword = $container->getParameter('bootcamp.init.user.password');
        }

        $configinithandlerServiceId = $container->hasParameter('bootcamp.init.config.handler') ? $container->getParameter('bootcamp.init.config.handler') : null;

        # Detecting platform
        $platform = PlatformDetectorHelper::detectPlatform();

        # Initializing the application
        $title = sprintf('BootCamp: Initializing %s %s', $appname, $appversion);
        $io->write(self::formatHero($title));

        if($io->askConfirmation('<question>We are about to initialize the the application. Proceed ?</question> [Y/n] ', TRUE) === FALSE) {
            throw new \RuntimeException("Application is not initialized. Exiting.");
        }

        #
        # Check database connectivity
        #

        $io->write(self::formatHeader("ᐅ Check: database connectivity"));

        # Check if database is configured
        if($database_configured === FALSE) {
            throw new \RuntimeException("Database is not configured.\nPlease set the " . $databaseurl_variablename . " variable in the application environment. Exiting.");
        }

        # Check if sqlite is used on a Paas Platform

        if(
            $container->getParameter('database_driver') === 'pdo_sqlite' &&
            !$platform->isLocalFileStoragePersistent()
        ) {

            $message = 'Cannot use SQLite database backend on ' . $platform->getPlatformName() . ' (filesystem is not persistent on this platform).';

            if($platform instanceof Platform\HerokuPlatform) {
                $message .= "\n\nTroobleshoot this error:\n    1. Add the Heroku Postgres addon to your Heroku app (https://addons.heroku.com/heroku-postgresql)\n    2. Promote the newly created database\n    3. Try deploying again.\n";
            }

            throw new \RuntimeException($message);
        }

        $dbal = $container->get('doctrine.dbal.default_connection');
        $em = $container->get('doctrine.orm.entity_manager');

        try {
            $dbal->connect();
        } catch(\Exception $e) {

            switch(self::diagnosticDBException($e)) {

                case self::DIAG_DBNOCONNECTION: {
                    throw new \RuntimeException("Cannot connect to the database server.\nPlease check that the " . $databaseurl_variablename . " points to a valid database. Exiting.");
                }

                case self::DIAG_DBMISSING: {

                    if($io->askConfirmation('<question>Database connection is OK, but the database is missing. Should we try to create it for you ?</question> [Y/n] ', TRUE) === FALSE) {
                        throw new \RuntimeException("Application is not initialized. Exiting.");
                    } else {

                        if(self::createDatabase($dbal)) {
                            $io->write("<info><comment>✔</comment> Database created !</info>");
                        } else {
                            throw new \RuntimeException("Database could not be created. Application is not initialized. Exiting.");
                        }
                    }

                    break;
                }

                case self::DIAG_UNKNOWNSTATUS:
                default: {
                    throw new \RuntimeException("Something unexpected happened during the database connectivity check. Error message follows.\n" . $e->getMessage());
                }
            }
        }

        $io->write("<info><comment>✔</comment> Database connectivity is OK.</info>");

        # /Check database connectivity; database connectivity is OK

        $io->write(self::formatHeader("ᐅ Check: pristineness"));

        # Check if version already configured, and if yes, if we need an upgrade
        $bootcampStatusTableName = $em->getClassMetadata('NetgustoBootCampBundle:BootCampStatus')->getTableName();
        $bootCampStatusTableFound = false;

        foreach($dbal->getSchemaManager()->listTables() as $table) {
            if(strtolower($table->getName()) === strtolower($bootcampStatusTableName)) {    # pgsql lowercases the table names
                $bootCampStatusTableFound = TRUE;
                break;
            }
        }

        $migrate = true;
        $initialize = true;
        $alreadyconfiguredthisversion = false;

        if($bootCampStatusTableFound) {
            # The BootCampStatus table is found
            # Check if the configured version is equal to the current app version

            $versions = $em->getRepository('NetgustoBootCampBundle:BootCampStatus')->findAll();
            if(count($versions) > 0) {

                $initialize = false;

                $versionkeys = array_keys($versions);
                $lastkey = array_pop($versionkeys);
                $currentversion = $versions[$lastkey];

                foreach($versions as $version) {
                    if($version->getConfiguredVersion() === $appversion) {
                        $alreadyconfiguredthisversion = true;
                        break;
                    }
                }

                if($alreadyconfiguredthisversion) {
                    $migrate = false;
                    $io->write("<info><comment>✔</comment> The application is already initialized and configured for the packaged version (" . $currentversion->getConfiguredVersion() . "); database will not be touched.</info>");
                } else {
                    $io->write("<info><comment>✔</comment> The application is already initialized and configured (version: " . $currentversion->getConfiguredVersion() . "), but packaged version is different (version: " . $appversion . "). Application will be migrated, but not initialized.</info>");
                }
            }
        }

        if($migrate) {
            #
            # Migrate database
            #

            $io->write(self::formatHeader("▶ Migrate database"));
            self::migrateDatabase($io, $kernel, $dbal);
            $io->write("<info><comment>✔</comment> Database migrated.</info>");
        }

        if($initialize) {

            # The BootCampStatus table is not found
            # The application has to be initialized

            $io->write("<info><comment>✔</comment> This application is uninitialized; proceeding with initialization.</info>");

            if($configinithandlerServiceId) {
                #
                # Initialize config
                #

                $io->write(self::formatHeader("▶ Initialize configuration"));

                if(!$container->has($configinithandlerServiceId)) {
                    throw new \RuntimeException('Bootcamp: Service ' . $configinithandlerServiceId . ' does not exist.');
                }

                $configinithandlerService = $container->get($configinithandlerServiceId);

                if(!$configinithandlerService instanceOf ConfigInitHandlerInterface) {
                    throw new \RuntimeException('Bootcamp: Service ' . $configinithandlerServiceId . ' must implement Netgusto\BootCampBundle\InitHandler\ConfigInitHandlerInterface.');
                }

                $config = $configinithandlerService->createAndPersistConfig();

                $io->write("<info><comment>✔</comment> Configuration initialized.</info>");
            }

            if($userinithandlerServiceId) {

                #
                # Create a user
                #

                $io->write(self::formatHeader("▶ Create default user"));

                if(!$container->has($userinithandlerServiceId)) {
                    throw new \RuntimeException('Bootcamp: Service ' . $userinithandlerServiceId . ' does not exist.');
                }

                $userinithandlerService = $container->get($userinithandlerServiceId);

                if(!$userinithandlerService instanceOf UserInitHandlerInterface) {
                    throw new \RuntimeException('Bootcamp: Service ' . $userinithandlerServiceId . ' must implement Netgusto\BootCampBundle\InitHandler\UserInitHandlerInterface.');
                }

                $user = $userinithandlerService->createAndPersistUser(
                    $userinitusername,
                    $userinitpassword
                );

                $io->write("<info><comment>✔</comment> Default user created (username='" . self::specialColor2($userinitusername) . "', password='" . self::specialColor2($userinitpassword) . "')</info>");
            }
        }

        if(!$alreadyconfiguredthisversion) {
            #
            # Set configured version
            #

            $io->write(self::formatHeader("▶ Mark application as configured"));

            $bootCampStatus = new BootCampStatus();
            $bootCampStatus->setConfiguredVersion($appversion);
            $em->persist($bootCampStatus);
            $em->flush();

            $io->write("<info><comment>✔</comment> Application marked as configured width version " . $appversion . ".</info>");
        }

        /*
        #
        # Dumping assets
        #

        $io->write(self::formatHeader("▶ Compiling web assets"));

        self::compileWebAssets($io, $kernel, $dbal);

        $io->write("<info><comment>✔</comment> Web assets compiled.</info>");
        $io->write('');
        */

        $message = sprintf('BootCamp: %s %s is initialized.', $appname, $appversion);
        $io->write("<info><comment>✔</comment> " . $message . "</info>");
        $io->write('');
    }

    protected static function specialColor($text) {
        return '<bg=yellow;fg=white;options=bold>' . $text . '</bg=yellow;fg=white;options=bold>';
    }

    protected static function specialColor2($text) {
        return '<bg=cyan;fg=white;options=blink>' . $text . '</bg=cyan;fg=white;options=blink>';
    }

    protected static function formatHero($text) {
        $line = str_repeat('#', mb_strlen($text, 'UTF-8') + 4);
        return "\n" . self::specialColor($line) . "\n" . self::specialColor("# " . $text . " #") . "\n" . self::specialColor($line) . "\n";
    }

    protected static function formatHeader($text) {
        return "\n" . self::specialColor("### " . $text . " ###") . "\n";
    }

    # Builds the BootCamp Kernel
    protected static function getKernel($kernelrootdir) {

        $appBootCampKernel = $kernelrootdir . '/BootCampKernel.php';
        if(file_exists($appBootCampKernel)) {
            require_once $appBootCampKernel;
            $kernelClass = '\BootCampKernel';   # Global namespace
        } else {
            $kernelClass = '\Netgusto\BootCampBundle\Kernel\BootCampKernel';    # Current namespace
        }

        $kernel = new $kernelClass(          # Global namespace
            'bootcamp', // env
            TRUE        // debug (no cache)
        );

        if(method_exists($kernel, 'setRootDir')) {
            $kernel->setRootDir($kernelrootdir);
        }

        return $kernel;
    }

    protected static function determineKernelRootDir(CommandEvent $event) {
        $extra = $event->getComposer()->getPackage()->getExtra();
        $rootdir = rtrim(getcwd(), '/');
        return $rootdir . '/' . trim($extra['symfony-app-dir'], '/');
    }

    protected static function diagnosticDBException(\Exception $e) {

        if($e instanceOf \PDOException) {
            $message = $e->getMessage();

            if(stripos($message, 'Access denied') !== FALSE) {
                return self::DIAG_DBNOCONNECTION;
            } else if(
                stripos($message, 'Unknown database') !== FALSE ||
                preg_match('/database ".*?" does not exist/', $message) !== FALSE
            ) {
                return self::DIAG_DBMISSING;
            }
        }

        return self::DIAG_UNKNOWNSTATUS;
    }

    protected static function createDatabase(DBALConnection $connection) {
        return DatabaseCreatorHelper::createDatabase($connection);
    }

    protected static function migrateDatabase(ComposerIOInterface $io, KernelInterface $kernel, DBALConnection $connection) {

        # This command is executed in the bootcamp environment

        $command = new DoctrineMigrationCommand();
        $application = new Application($kernel);
        $command->setApplication($application);
        $command->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet(array(
            'db' => new \Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($connection),
            'dialog' => new \Symfony\Component\Console\Helper\DialogHelper(),
        )));

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            array(),
            $command->getDefinition()
        );

        $input->setInteractive(FALSE);

        /*
        $reflect = new \ReflectionClass($io);
        $outputProperty = $reflect->getProperty('output');
        $outputProperty->setAccessible(TRUE);
        $output = $outputProperty->getValue($io);
        */

        # Discard output
        $output = new BufferedOutput();

        $command->execute(
            $input,
            $output
        );
    }

    protected static function compileWebAssets(ComposerIOInterface $io, KernelInterface $bootcampkernel, DBALConnection $connection) {

        # This command is executed in the application environment (needs access to all app configurations)

        $executableFinder = new PhpExecutableFinder();
        if(false === $php = $executableFinder->find()) {
            throw new \RuntimeException('Unable to find the PHP executable.');
        }

        $pb = new ProcessBuilder();

        $process = $pb
            ->add($php)
            ->add($bootcampkernel->getRootDir() . '/console')
            ->add('assetic:dump')
            ->inheritEnvironmentVariables(true)
            ->getProcess();


        /*
        $reflect = new \ReflectionClass($io);
        $outputProperty = $reflect->getProperty('output');
        $outputProperty->setAccessible(TRUE);
        $output = $outputProperty->getValue($io);
        */

        # Discard output
        $output = new BufferedOutput();

        $process->run(function ($type, $data) use ($output) {
            $output->writeln($data);
        });

        $ret = $process->getExitCode();

        return $ret;



        /*$reflect = new \ReflectionClass($io);
        $outputProperty = $reflect->getProperty('output');
        $outputProperty->setAccessible(TRUE);
        $output = $outputProperty->getValue($io);

        require_once $bootcampkernel->getRootDir() . '/AppKernel.php';
        $kernel = new \AppKernel(
            'dev',  // environment
            TRUE    // debug
        );

        $application = new Application($kernel);
        $application->setAutoExit(false);
        #Debug::enable();
        $application->run(
            new \Symfony\Component\Console\Input\ArrayInput(array(
                'command' => 'assetic:dump',
            )),
            $output
        );*/
    }
}