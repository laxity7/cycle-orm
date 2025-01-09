<?php

namespace Cycle\ORM\Tests\Functional\Driver\Common\Integration\Case345\Entity;

use Stringable;

class UserId implements Stringable
{
    public function __construct(public int|string $id)
    {
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public static function typeCast(mixed $value): self
    {
        return new self($value);
    }
}
