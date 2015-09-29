> composer.json:

    "require": {
        "netgusto/bootcamp-bundle": "1.0.1",
        "brianium/habitat": "v1.0.0",
        "doctrine/migrations": "dev-master",
        "doctrine/doctrine-migrations-bundle": "dev-master",
        "netgusto/parametertouch-bundle": "1.0.1"
    },
    "extra": {
        "touch-parameters": [
            {
                "src": "app/config/defaults/data.parameters.dist.yml",
                "dest": "data/parameters.yml"
            },
            {
                "src": "app/config/defaults/data.environment.dist.yml",
                "dest": "data/environment.yml"
            }
        ]
    }

.gitignore:
    
    remove lines:

        /app/config/parameters.yml

    add lines:

        /data/*
        !data/.gitkeep

> app/AppKernel.php:

    new Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle(),
    new Netgusto\BootCampBundle\NetgustoBootCampBundle(),

> app/config/config_bootcamp.yml:

    imports:
        - { resource: environment.yml }
        - { resource: parameters.yml }
        - { resource: ../../data/environment.yml }
        - { resource: ../../data/parameters.yml }

    parameters:
        
        bootcamp.environment.databaseurl_variablename: DATABASE_URL
        bootcamp.environment.authorizedkeys:
            - DATABASE_URL

        bootcamp.appname: "Application name"
        bootcamp.appversion: "1.0.0"

        bootcamp.init.config.handler: appname.bootcamp.configinithandler
        bootcamp.init.user.handler: appname.bootcamp.userinithandler
        
    services:

> app/config/environment.yml:
    
    parameters:

        environment.application.defaults:

            # Packaged App environment defaults here
            # To be modified by the app developer only
            # To override these parameters for your running application, see /data/environment.yml
            
            DATABASE_URL: sqlite://%kernel.root_dir%/../data/database.db?absolute

> app/config/defaults/data.environment.dist.yml:
    
    parameters:
        environment.user:
            
            # ####
            # Uncomment and define properly the line below to point your database
            # Valid drivers are: mysql://, mssql://, postgres:// and sqlite://
            # ####

            # DATABASE_URL: mysql://user:password@hostname/dbname

> app/config/defaults/data.parameters.dist.yml:
    
    parameters:

        # ####
        # Override application parameters here
        # ####

        # hello: world

> app/DoctrineMigrations/Version20140731100000.php
    
    <?php

    namespace Application\Migrations;

    use Doctrine\DBAL\Migrations\AbstractMigration;
    use Doctrine\DBAL\Schema\Schema;

    class Version20140731100000 extends AbstractMigration {
        
        public function up(Schema $schema) {

            #######################################################################
            # Netgusto\BootCampBundle\Entity\BootCampStatus
            #######################################################################

            $bootcampstatus = $schema->createTable('BootCampStatus');
            
            $bootcampstatus->addColumn('id', 'integer')->setAutoincrement(true);
            
            $bootcampstatus->addColumn('configuredversion', 'string', array(
                'length' => 32,
            ));

            $bootcampstatus->setPrimaryKey(array('id'));

            #######################################################################
            # Netgusto\BootCampBundle\Entity\ConfigContainer
            #######################################################################

            $configcontainer = $schema->createTable('ConfigContainer');
            
            $configcontainer->addColumn('id', 'integer')->setAutoincrement(true);

            $configcontainer->addColumn('name', 'string', array(
                'length' => 255,
            ));

            $configcontainer->addColumn('config', 'json_array');

            $configcontainer->setPrimaryKey(array('id'));
        }

        public function down(Schema $schema) {

        }
    }

> app/DoctrineMigrations/

> Add an entity ConfigContainer in a bundle of your project (ex: AppName/ModelBundle)

> AppName/ModelBundle/Entity/ConfigContainer.php
    
    <?php

    namespace AppName\ModelBundle\Entity;

    class ConfigContainer {
        /**
         * @var integer
         */
        private $id;

        /**
         * @var string
         */
        private $name;

        /**
         * @var array
         */
        private $config;

        /**
         * Get id
         *
         * @return integer 
         */
        public function getId()
        {
            return $this->id;
        }

        /**
         * Set name
         *
         * @param string $name
         * @return HierarchicalConfig
         */
        public function setName($name)
        {
            $this->name = $name;

            return $this;
        }

        /**
         * Get name
         *
         * @return string 
         */
        public function getName()
        {
            return $this->name;
        }

        /**
         * Set config
         *
         * @param array $config
         * @return HierarchicalConfig
         */
        public function setConfig(array $config)
        {
            $this->config = $config;

            return $this;
        }

        /**
         * Get config
         *
         * @return array 
         */
        public function getConfig()
        {
            return $this->config;
        }

        public function has($prop) {
            return array_key_exists($prop, $this->config);
        }

        public function get($prop) {
            
            if(!$this->has($prop)) {
                throw new \RuntimeException('ConfigContainer: attempt to access undefined config property "' . $prop . '"');
            }

            return $this->config[$prop];
        }

        public function set($prop, $value) {
            $this->config[$prop] = $value;

            return $this;
        }
    }


> Create a bundle dedicated to initialization (ex: AppName/BootCampBundle)
> This bundle is not loaded in AppKernel.php, and is used only during initialization

> AppName/BootCampBundle/Resources/config/services.yml

    # These services will be available only during composer install

    imports:
        - { resource: ../../../../../app/config/parameters.yml }

    services:

        appname.bootcamp.configinithandler:
            class: AppName\BootCampBundle\InitHandler\ConfigInitHandler
            arguments:
                - @doctrine.orm.entity_manager

        appname.bootcamp.userinithandler:
            class: AppName\BootCampBundle\InitHandler\UserInitHandler
            arguments:
                - @doctrine.orm.entity_manager
                - @security.encoder_factory

        security.encoder_factory:
            class: Symfony\Component\Security\Core\Encoder\EncoderFactory
            public: false
            arguments:
                - []

> AppName/BootCampBundle/InitHandler/ConfigInitHandler.php
    
    <?php

    namespace AppName\BootCampBundle\InitHandler;

    use Doctrine\ORM\EntityManager;

    use Netgusto\BootCampBundle\InitHandler\ConfigInitHandlerInterface;

    use AppName\ModelBundle\Entity\ConfigContainer;

    class ConfigInitHandler implements ConfigInitHandlerInterface {

        protected $entityManager;

        public function __construct(EntityManager $entityManager) {
            $this->entityManager = $entityManager;
        }

        public function createAndPersistConfig() {
            
            $siteconfig = new ConfigContainer();
            $siteconfig->setName('main');
            $siteconfig->setConfig(array(
                'somevar' => 'Initialization value for somevar',
                'someothervar' => true,
            ));

            $this->entityManager->persist($siteconfig);
            $this->entityManager->flush();
        }
    }

> AppName/BootCampBundle/InitHandler/UserInitHandler.php

    <?php

    namespace AppName\BootCampBundle\InitHandler;

    use Doctrine\ORM\EntityManager;

    use Netgusto\BootCampBundle\InitHandler\UserInitHandlerInterface;

    use AppName\ModelBundle\Entity\User;

    class UserInitHandler implements UserInitHandlerInterface {

        protected $entityManager;
        protected $passwordencoder_factory;

        public function __construct(
            EntityManager $entityManager,
            $passwordencoder_factory
        ) {
            $this->entityManager = $entityManager;
            $this->passwordencoder_factory = $passwordencoder_factory;
        }

        public function createAndPersistUser($username, $password) {

            # Persisting user
            $user = new User();
            $user->setUsername($username);
            $user->setSalt(md5(rand()));
            $user->setPassword(
                $this->passwordencoder_factory->getEncoder($user)->encodePassword(
                    $password,
                    $user->getSalt()
                )
            );

            $user->addRole('ROLE_ADMIN');

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        }
    }

> app/BootCampKernel.php
    
    <?php

    use Netgusto\BootCampBundle\Kernel\BootCampKernel as BaseBootCampKernel;

    class BootCampKernel extends BaseBootCampKernel {
        
        public function registerBundles() {

            $bundles = parent::registerBundles();
            $bundles[] = new AppName\BootCampBundle\AppNameBootCampBundle();
            $bundles[] = new AppName\ModelBundle\AppNameModelBundle();

            return $bundles;
        }
    }

> app/config.yml

    imports:
        # BootCamp
        - { resource: config_bootcamp.yml }
        - { resource: @NetgustoBootCampBundle/ParameterProcessor/Environment.php }
        - { resource: @NetgustoBootCampBundle/ParameterProcessor/Database.php }
        # /BootCamp

        - { resource: security.yml }

    doctrine:
        dbal:
            # [...]
            path:     "%database_path%"
