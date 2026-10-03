<?php
namespace Apie\Tests\Dataset;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Lists\ReflectionClassList;
use Apie\Core\Lists\ReflectionMethodList;
use Apie\Dataset\Dataset;
use Apie\Dataset\DatasetIdentifier;
use Apie\Dataset\DummyApp\Resources\DummyUser;
use Apie\Fixtures\TestHelpers\ObjectTestCase;
use cebe\openapi\Reader;
use cebe\openapi\Writer;

class DatasetTest extends ObjectTestCase
{
    public static function className(): string
    {
        return Dataset::class;
    }

    public static function getOpenApiSchemaForCreation(): array
    {
        return [
            'required' => ['hashmap'],
            'type' => 'object',
            'properties' => [
                'hashmap' => [
                    '$ref' => '#/components/schemas/BoundedContextHashmap-post'
                ],
            ],
        ];
    }

    public function testBuildOpenApiForSelectedBoundedContext(): void
    {
        $dataset = new Dataset(new BoundedContextHashmap([
            'test' => new BoundedContext(
                new BoundedContextId('test'),
                new ReflectionClassList([
                    new \ReflectionClass(DummyUser::class),
                    new \ReflectionClass(Dataset::class),
                ]),
                new ReflectionMethodList()
            )
        ]));
        $refl = (new \ReflectionClass($dataset))
            ->getProperty('id');
        $refl->setValue($dataset, DatasetIdentifier::fromNative('1CffPUemjj4fcTbm4Vpk8H'));

        $openApiFile = $dataset->buildOpenApi(
            new BoundedContextId('test'),
            new BoundedContextId('data')
        );

        $openApi = Reader::readFromJson((string) $openApiFile->getContent());

        self::assertArrayHasKey('hashmap', $openApi->components->schemas['Dataset-get']->properties);
        self::assertTrue($openApi->paths->hasPath('/Dataset/{id}/buildOpenApi'));

        $actual = Writer::writeToYaml($openApi);
        $path = __DIR__ . '/../fixtures/openapi.yaml';
        // file_put_contents($path, $actual);
        $expected = file_get_contents($path);
        self::assertSame($expected, $actual);
    }
}
