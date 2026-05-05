<?php

namespace App\Modules\AccessControl\Domain\Events;

use App\Modules\AccessControl\Domain\ValueObjects\CheckInId;
use App\Modules\AccessControl\Domain\ValueObjects\MemberId;
use DateTimeImmutable;

final readonly class CheckInRegistered
{
    public function __construct(
        public CheckInId $checkInId,
        public MemberId $memberId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function toArray(): array
    {
        return [
            'check_in_id' => $this->checkInId->value(),
            'member_id' => $this->memberId->value(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
