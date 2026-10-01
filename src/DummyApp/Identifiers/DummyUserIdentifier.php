<?php

namespace Apie\Dataset\DummyApp\Identifiers;

use Apie\Core\Identifiers\IdentifierInterface;
use Apie\Core\Identifiers\Ulid;
use Apie\Dataset\DummyApp\Resources\DummyUser;
use ReflectionClass;

class DummyUserIdentifier extends Ulid implements IdentifierInterface
{
    public static function getReferenceFor(): ReflectionClass
    {
        return new \ReflectionClass(DummyUser::class);
    }
}
