<?php

namespace App\Modules\AccessControl\Application\Commands;

final readonly class RegisterCheckInCommand
{
    public function __construct(
        public string $memberId,
    ) {}
}
