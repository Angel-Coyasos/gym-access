<?php

namespace App\Modules\AccessControl\Application\Handlers;

use App\Modules\AccessControl\Application\Commands\RegisterCheckInCommand;
use App\Modules\AccessControl\Domain\CheckIn;
use App\Modules\AccessControl\Domain\Repositories\CheckInRepositoryInterface;
use App\Modules\AccessControl\Domain\ValueObjects\MemberId;
use App\Shared\Infrastructure\Outbox\OutboxEvent;
use Illuminate\Support\Facades\DB;

final class RegisterCheckInHandler
{
    public function __construct(
        private readonly CheckInRepositoryInterface $checkInRepository,
    ) {}

    public function handle(RegisterCheckInCommand $command): CheckIn
    {
        return DB::transaction(function () use ($command) {
            $checkIn = CheckIn::register(new MemberId($command->memberId));

            $this->checkInRepository->save($checkIn);

            foreach ($checkIn->releaseEvents() as $event) {
                OutboxEvent::create([
                    'aggregate_type' => 'CheckIn',
                    'aggregate_id' => $checkIn->id()->value(),
                    'event_type' => $event::class,
                    'payload' => $event->toArray(),
                ]);
            }

            return $checkIn;
        });
    }
}
