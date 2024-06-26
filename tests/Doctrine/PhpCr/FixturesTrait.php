<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Doctrine\PhpCr;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Proxy\AbstractProxyFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\ODM\PHPCR\Configuration;
use Doctrine\ODM\PHPCR\DocumentManager;
use Doctrine\ODM\PHPCR\DocumentManagerInterface;
use Doctrine\ODM\PHPCR\Mapping\Driver\AnnotationDriver;
use Doctrine\ODM\PHPCR\Mapping\Driver\AttributeDriver;
use Doctrine\ODM\PHPCR\NodeTypeRegistrator;
use Jackalope\Factory;
use Jackalope\Repository;
use Jackalope\Transport\DoctrineDBAL\Client;
use Jackalope\Transport\DoctrineDBAL\RepositorySchema;
use PHPCR\SimpleCredentials;
use Solido\QueryLanguage\Tests\Fixtures\Document as QueryLanguageFixtures;

use function sys_get_temp_dir;
use function uniqid;

trait FixturesTrait
{
    private static DocumentManagerInterface $documentManager;

    public static function setUpBeforeClass(): void
    {
        $factory = new Factory();
        $transport = new Client($factory, $connection = new Connection(['url' => 'sqlite:///:memory:'], new Driver()));
        $repository = new Repository($factory, $transport);
        $credentials = new SimpleCredentials('admin', 'admin');

        $schema = new RepositorySchema([], $connection);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->exec($sql);
        }

        $session = $repository->login($credentials, 'default');
        $registrator = new NodeTypeRegistrator();
        $registrator->registerNodeTypes($session);

        $configuration = new Configuration();
        if (class_exists(AnnotationDriver::class)) {
            $configuration->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader(), __DIR__.'/../../Fixtures/Document'));
            $configuration->setAutoGenerateProxyClasses(AbstractProxyFactory::AUTOGENERATE_EVAL);
        } else {
            $configuration->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/../../Fixtures/Document']));
        }

        $configuration->setProxyNamespace('__CG__\\' . QueryLanguageFixtures::class);
        $configuration->setProxyDir(sys_get_temp_dir() . '/' . uniqid('api-platform-proxy', true));
        if (method_exists($configuration, 'setDocumentNamespaces')) {
            $configuration->setDocumentNamespaces([
                QueryLanguageFixtures::class,
            ]);
        }

        self::$documentManager = DocumentManager::create($session, $configuration);

        self::$documentManager->persist(new QueryLanguageFixtures\User('foo'));
        self::$documentManager->persist(new QueryLanguageFixtures\User('bar'));
        self::$documentManager->persist(new QueryLanguageFixtures\User('foobar'));
        self::$documentManager->persist(new QueryLanguageFixtures\User('barbar'));
        self::$documentManager->persist(new QueryLanguageFixtures\User('baz'));
        self::$documentManager->persist(new QueryLanguageFixtures\User('donald duck'));
        self::$documentManager->persist(new QueryLanguageFixtures\User('goofy'));

        self::$documentManager->flush();
        self::$documentManager->clear();
    }

    protected function tearDown(): void
    {
        self::$documentManager->clear();
    }
}
