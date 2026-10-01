<?php

namespace Apie\Dataset\DummyApp\Resources;

use Apie\Core\Entities\EntityInterface;
use Apie\Core\ValueObjects\NonEmptyString;
use Apie\Dataset\DummyApp\Identifiers\DummyUserIdentifier;

class DummyUser implements EntityInterface
{
    private DummyUserIdentifier $id;

    public function __construct(private NonEmptyString $password)
    {
        $this->id = DummyUserIdentifier::createRandom();
    }

    public function getId(): DummyUserIdentifier
    {
        return $this->id;
    }

    public function hasPasswordWithUppercase(): bool
    {
        $pw = $this->password->toNative();
        return mb_strtolower($pw) !== $pw;
    }

    public function hasPasswordWithLowercase(): bool
    {
        $pw = $this->password->toNative();
        return mb_strtoupper($pw) !== $pw;
    }
}
