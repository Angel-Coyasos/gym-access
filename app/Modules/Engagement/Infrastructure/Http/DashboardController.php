<?php

namespace App\Modules\Engagement\Infrastructure\Http;

use App\Modules\Engagement\Infrastructure\Persistence\EloquentCheckInSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index(string $memberId): JsonResponse
    {
        $summaries = EloquentCheckInSummary::where('member_id', $memberId)
            ->orderBy('checked_in_at', 'desc')
            ->get(['id', 'member_id', 'checked_in_at', 'quote_body', 'quote_author']);

        return response()->json([
            'member_id' => $memberId,
            'total' => $summaries->count(),
            'check_ins' => $summaries->map(fn ($row) => [
                'check_in_id' => $row->id,
                'checked_in_at' => $row->checked_in_at?->toISOString(),
                'quote' => $row->quote_body ? [
                    'body' => $row->quote_body,
                    'author' => $row->quote_author,
                ] : null,
            ]),
        ]);
    }
}
