<?php

namespace App\Modules\AccessControl\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class MemberId
{
    public function __construct(private string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('MemberId cannot be empty');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(MemberId $other): bool
    {
        return $this->value === $other->value;
    }
}
