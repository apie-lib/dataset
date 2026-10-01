<?php

namespace Apie\Dataset;

use Apie\Core\Identifiers\IdentifierInterface;
use Apie\Core\Identifiers\Ulid;
use ReflectionClass;

class DatasetIdentifier extends Ulid implements IdentifierInterface
{
    public static function getReferenceFor(): ReflectionClass
    {
        return new ReflectionClass(Dataset::class);
    }
}
