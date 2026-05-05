<?php

namespace App\Modules\Engagement\Domain\ValueObjects;

final readonly class Quote
{
    public function __construct(
        public string $id,
        public string $body,
        public string $author,
    ) {}
}
