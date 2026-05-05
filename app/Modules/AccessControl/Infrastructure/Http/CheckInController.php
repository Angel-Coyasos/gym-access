<?php

namespace App\Modules\AccessControl\Infrastructure\Http;

use App\Modules\AccessControl\Application\Commands\RegisterCheckInCommand;
use App\Modules\AccessControl\Application\Handlers\RegisterCheckInHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CheckInController extends Controller
{
    public function __construct(
        private readonly RegisterCheckInHandler $handler,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'member_id' => 'required|string|max:255',
        ]);

        $command = new RegisterCheckInCommand(
            memberId: $request->string('member_id')->value(),
        );

        $checkIn = $this->handler->handle($command);

        return response()->json([
            'check_in_id' => $checkIn->id()->value(),
            'member_id' => $checkIn->memberId()->value(),
            'checked_in_at' => $checkIn->checkedInAt()->format('Y-m-d H:i:s'),
        ], 201);
    }
}
