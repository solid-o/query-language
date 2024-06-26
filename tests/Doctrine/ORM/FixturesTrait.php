<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Doctrine\ORM;

use Closure;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\AbstractLogger;
use Solido\QueryLanguage\Tests\Fixtures\Entity as QueryLanguageFixtures;

use function sys_get_temp_dir;
use function uniqid;

trait FixturesTrait
{
    private static EntityManagerInterface $entityManager;
    /** @var array<string, mixed> */
    private static array $queryLogs = [];

    public static function setUpBeforeClass(): void
    {
        $configuration = new Configuration();
        if (class_exists(AnnotationDriver::class)) {
            $configuration->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader(), __DIR__.'/../../Fixtures/Entity'));
        } else {
            $configuration->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/../../Fixtures/Entity']));
        }
        $configuration->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_EVAL);
        $configuration->setProxyNamespace('__CG__\\' . QueryLanguageFixtures::class);
        $configuration->setProxyDir(sys_get_temp_dir() . '/' . uniqid('query-language-proxy', true));
        $configuration->setEntityNamespaces([QueryLanguageFixtures::class]);

        $addToLog = static fn (array $query) => self::$queryLogs[] = $query;
        $configuration->setMiddlewares([
            new LoggingMiddleware(new class($addToLog) extends AbstractLogger {
                private Closure $addToLog;

                public function __construct(Closure $addToLog)
                {
                    $this->addToLog = $addToLog;
                }

                public function log($level, $message, array $context = []): void
                {
                    if (isset($context['sql'])) {
                        ($this->addToLog)($context);
                    }
                }
            }),
        ]);

        if (class_exists(DsnParser::class)) {
            $params = (new DsnParser())->parse('sqlite3:///:memory:');
        } else {
            $params = ['url' => 'sqlite:///:memory:'];
        }

        $connection = DriverManager::getConnection($params, $configuration);
        self::$entityManager = new EntityManager($connection, $configuration);
        $schemaTool = new SchemaTool(self::$entityManager);
        $schemaTool->updateSchema(self::$entityManager->getMetadataFactory()->getAllMetadata(), true);

        self::$entityManager->persist($foo = new QueryLanguageFixtures\User('foo'));
        self::$entityManager->persist($bar = new QueryLanguageFixtures\User('bar'));
        self::$entityManager->persist($foobar = new QueryLanguageFixtures\User('foobar'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('barbar'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('baz'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('donald duck'));
        self::$entityManager->persist($goofy = new QueryLanguageFixtures\User('goofy'));

        self::$entityManager->persist($group1 = new QueryLanguageFixtures\Group(1, 'group1'));
        $foo->groups[] = $group1;
        $bar->groups[] = $group1;
        $foobar->groups[] = $group1;
        $goofy->groups[] = $group1;

        self::$entityManager->flush();
        self::$entityManager->clear();
    }

    protected function tearDown(): void
    {
        self::$entityManager->clear();
    }

    /**
     * @before
     */
    public function beforeEachClearLogs(): void
    {
        self::$queryLogs = [];
    }
}
