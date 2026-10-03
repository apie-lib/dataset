<?php
namespace Apie\Dataset;

use Apie\Core\Attributes\FakeCount;
use Apie\Core\Attributes\FakeMethod;
use Apie\Core\Attributes\StoreOptions;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\FileStorage\FileStorageFactory;
use Apie\Core\FileStorage\SqliteFile;
use Apie\Core\Indexing\Indexer;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Common\ActionDefinitionProvider;
use Apie\Core\Attributes\Context;
use Apie\Core\Attributes\Internal;
use Apie\Core\Attributes\Policy;
use Apie\Core\Attributes\RuntimeCheck;
use Apie\Core\Attributes\StaticCheck;
use Apie\Core\ContextConstants;
use Apie\Core\FileStorage\StoredFile;
use Apie\Dataset\DummyApp\Resources\DummyUser;
use Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory;
use Apie\DoctrineEntityConverter\OrmBuilder as DoctrineEntityConverterOrmBuilder;
use Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer;
use Apie\DoctrineEntityDatalayer\EntityReindexer;
use Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory;
use Apie\DoctrineEntityDatalayer\Factories\EntityQueryFilterFactory;
use Apie\DoctrineEntityDatalayer\IndexStrategy\DirectIndexStrategy;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Apie\RestApi\EventListeners\OpenApiOperationAddedEventSubscriber;
use Apie\RestApi\EventListeners\OpenApiTagsNormalizerSubscriber;
use Apie\RestApi\EventListeners\PruneUnusedComponentsSubscriber;
use Apie\RestApi\OpenApi\OpenApiGenerator;
use Apie\RestApi\RouteDefinitions\RestApiRouteDefinitionProvider;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\Serializer\Serializer;
use Apie\StorageMetadata\DomainToStorageConverter;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\Writer;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[FakeMethod('createRandom')]
#[FakeCount(1)]
#[StaticCheck(new Policy('staticCanViewAny', enabledOnMissingRule: true))]
#[RuntimeCheck(
    new Policy('canView', 'canViewAny')
)]
final class Dataset implements EntityInterface
{
    private DatasetIdentifier $id;

    #[StoreOptions(noStorage: true)]
    private ?DoctrineEntityDatalayer $datalayer = null;

    protected SqliteFile $sqliteFile;

    #[StaticCheck(new Policy('staticCreate', enabledOnMissingRule: true))]    
    #[RuntimeCheck(new Policy('create'))]
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

    #[StaticCheck(new Policy('staticHashmap', enabledOnMissingRule: true))]
    #[RuntimeCheck(new Policy('hashmap'))]
    public function getHashmap(): BoundedContextHashmap
    {
        return $this->hashmap;
    }

    #[StaticCheck(new Policy('staticSqliteFile', enabledOnMissingRule: true))]
    #[RuntimeCheck(new Policy('sqliteFile'))]
    public function getSqliteFile(): SqliteFile
    {
        return $this->sqliteFile;
    }

    #[Internal]
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

    #[Internal]
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

    #[StaticCheck(new Policy('staticBuildOpenApi', enabledOnMissingRule: true))]
    #[RuntimeCheck(new Policy('buildOpenApi'))]
    public function buildOpenApi(
        BoundedContextId $boundedContextId,
        #[Context(ContextConstants::BOUNDED_CONTEXT_ID)] ?BoundedContextId $datasetBoundedContextId = null,
        #[Context(ContextConstants::RESOURCE_NAME)] string $resourceName = 'Dataset',
        #[Context(ContextConstants::RESOURCE_ID)] ?string $resourceId = null
    ): StoredFile {
        $id = $resourceId ?? $this->getId()->toNative();
        $baseUrl = $datasetBoundedContextId?->toNative() ?? '';
        $baseUrl .= '/' . $resourceName . '/' . $id . '/' . $boundedContextId->toNative();
        $baseSpec = new OpenApi([
            'openapi' => '3.0.0',
            'info' => [
                'title' => $resourceName . ' ' . $id . ' - ' . $boundedContextId->toNative(),
                'version' => '1.0.0',
            ],
            'paths' => [],
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new OpenApiTagsNormalizerSubscriber($this->hashmap));
        $dispatcher->addSubscriber(new PruneUnusedComponentsSubscriber());
        $dispatcher->addSubscriber(new OpenApiOperationAddedEventSubscriber($this->toDatalayer()));

        $generator = new OpenApiGenerator(
            new ContextBuilderFactory(),
            ComponentsBuilderFactory::createComponentsBuilderFactory(),
            new RestApiRouteDefinitionProvider(
                new ActionDefinitionProvider(new ServiceLocator([])),
                new NullLogger()
            ),
            Serializer::create(),
            $dispatcher,
            $baseUrl,
            $baseSpec,
        );
        return StoredFile::createFromString(
            Writer::writeToJson($generator->create($this->hashmap[$boundedContextId->toNative()])),
            'application/json',
            'openapi-' . $resourceName . '-' . $id . '-' . $boundedContextId->toNative() . '.json'
        );
    }
}
