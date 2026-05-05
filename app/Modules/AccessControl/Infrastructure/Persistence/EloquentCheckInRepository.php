<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence;

use App\Modules\AccessControl\Domain\CheckIn;
use App\Modules\AccessControl\Domain\Repositories\CheckInRepositoryInterface;

final class EloquentCheckInRepository implements CheckInRepositoryInterface
{
    public function save(CheckIn $checkIn): void
    {
        EloquentCheckIn::create([
            'id' => $checkIn->id()->value(),
            'member_id' => $checkIn->memberId()->value(),
            'checked_in_at' => $checkIn->checkedInAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
