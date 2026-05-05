<?php

namespace App\Modules\AccessControl\Domain;

use App\Modules\AccessControl\Domain\Events\CheckInRegistered;
use App\Modules\AccessControl\Domain\ValueObjects\CheckInId;
use App\Modules\AccessControl\Domain\ValueObjects\MemberId;
use DateTimeImmutable;

final class CheckIn
{
    private array $events = [];

    private function __construct(
        private readonly CheckInId $id,
        private readonly MemberId $memberId,
        private readonly DateTimeImmutable $checkedInAt,
    ) {}

    public static function register(MemberId $memberId): self
    {
        $checkIn = new self(
            id: CheckInId::generate(),
            memberId: $memberId,
            checkedInAt: new DateTimeImmutable(),
        );

        $checkIn->events[] = new CheckInRegistered(
            checkInId: $checkIn->id,
            memberId: $memberId,
            occurredAt: $checkIn->checkedInAt,
        );

        return $checkIn;
    }

    public function id(): CheckInId
    {
        return $this->id;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }

    public function checkedInAt(): DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    /** @return array<CheckInRegistered> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
