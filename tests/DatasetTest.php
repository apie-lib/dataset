<?php
namespace Apie\Tests\Dataset;

use Apie\Dataset\Dataset;
use Apie\Fixtures\TestHelpers\ObjectTestCase;

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
}
