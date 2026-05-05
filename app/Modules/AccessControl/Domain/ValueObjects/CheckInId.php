<?php

namespace App\Modules\AccessControl\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CheckInId
{
    public function __construct(private string $value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException('CheckInId cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self((string) \Illuminate\Support\Str::uuid());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(CheckInId $other): bool
    {
        return $this->value === $other->value;
    }
}
