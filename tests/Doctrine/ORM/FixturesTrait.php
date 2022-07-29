<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Doctrine\ORM;

use Closure;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
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
        $configuration->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader(), __DIR__ . '/../../Fixtures/Entity'));
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

        self::$entityManager = EntityManager::create(['url' => 'sqlite:///:memory:'], $configuration);
        $schemaTool = new SchemaTool(self::$entityManager);
        $schemaTool->updateSchema(self::$entityManager->getMetadataFactory()->getAllMetadata(), true);

        self::$entityManager->persist(new QueryLanguageFixtures\User('foo'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('bar'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('foobar'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('barbar'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('baz'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('donald duck'));
        self::$entityManager->persist(new QueryLanguageFixtures\User('goofy'));

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
