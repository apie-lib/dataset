<?php
namespace Apie\Dataset;

use Apie\Core\Attributes\FakeCount;
use Apie\Core\Attributes\FakeMethod;
use Apie\Core\Attributes\StoreOptions;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\FileStorage\FileStorageFactory;
use Apie\Core\FileStorage\SqliteFile;
use Apie\Core\Indexing\Indexer;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Dataset\DummyApp\Resources\DummyUser;
use Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory;
use Apie\DoctrineEntityConverter\OrmBuilder as DoctrineEntityConverterOrmBuilder;
use Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer;
use Apie\DoctrineEntityDatalayer\EntityReindexer;
use Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory;
use Apie\DoctrineEntityDatalayer\Factories\EntityQueryFilterFactory;
use Apie\DoctrineEntityDatalayer\IndexStrategy\DirectIndexStrategy;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Apie\StorageMetadata\DomainToStorageConverter;

#[FakeMethod('createRandom')]
#[FakeCount(1)]
final class Dataset implements EntityInterface
{
    private DatasetIdentifier $id;

    #[StoreOptions(noStorage: true)]
    private ?DoctrineEntityDatalayer $datalayer = null;

    protected SqliteFile $sqliteFile;

    public function __construct(
        #[StoreOptions(alwaysMixedData: true)]
        protected BoundedContextHashmap $hashmap
    ) {
        $this->id = DatasetIdentifier::createRandom();
        $this->sqliteFile = SqliteFile::createRandom();
    }

    public function getId(): DatasetIdentifier
    {
        return $this->id;
    }

    public static function createRandom(): static
    {
        $instance = new self(
            new BoundedContextHashmap([
                'test' => new BoundedContext(
                    new BoundedContextId('test'),
                    new ReflectionClassList([new \ReflectionClass(DummyUser::class)]),
                    new ReflectionMethodList()
                )
            ])
        );
        $datalayer = $instance->toDatalayer();
        $datalayer->all(new \ReflectionClass(DummyUser::class), new BoundedContextId('test'))
            ->getTotalCount();

        return $instance;
    }

    public function toDatalayer(): DoctrineEntityDatalayer
    {
        if ($this->datalayer) {
            return $this->datalayer;
        }
        $serverPath = $this->sqliteFile->getOrSetOnServerPath();

        $tempFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->hashmap->getUniqueHash();
        if (!@mkdir($tempFolder) && !is_dir($tempFolder)) {
            throw new \LogicException('Can not create temp folder ' . $tempFolder);
        }
        $entityPath = $tempFolder . DIRECTORY_SEPARATOR . 'entities';
        if (!@mkdir($entityPath) && !is_dir($entityPath)) {
            throw new \LogicException('Can not create entity folder ' . $entityPath);
        }
        $proxyPath = $tempFolder . DIRECTORY_SEPARATOR . 'proxies';
        if (!@mkdir($proxyPath) && !is_dir($proxyPath)) {
            throw new \LogicException('Can not create proxy folder ' . $proxyPath);
        }
        $ormBuilder = new DoctrineEntityConverterOrmBuilder(
            new PersistenceLayerFactory(),
            $this->hashmap,
            true
        );
        $ormBuilder = new OrmBuilder(
            $ormBuilder,
            buildOnce: false,
            runMigrations: true,
            devMode: true,
            proxyDir: $proxyPath,
            cache: null,
            path: $entityPath,
            connectionConfig: [
                'driver' => 'pdo_sqlite',
                'path' => $serverPath
            ]
        );
        $domainToStorageConverter = DomainToStorageConverter::create(FileStorageFactory::create());
        $doctrineListFactory = new DoctrineListFactory(
            $ormBuilder,
            new EntityQueryFilterFactory(),
            $domainToStorageConverter
        );
        return $this->datalayer = new DoctrineEntityDatalayer(
            $ormBuilder,
            $domainToStorageConverter,
            new DirectIndexStrategy(new EntityReindexer($ormBuilder, Indexer::create())),
            $doctrineListFactory
        );
    }
}
