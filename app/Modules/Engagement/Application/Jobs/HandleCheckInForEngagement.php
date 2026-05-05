<?php

namespace App\Modules\Engagement\Application\Jobs;

use App\Modules\Engagement\Domain\Ports\QuoteProviderInterface;
use App\Modules\Engagement\Infrastructure\Persistence\EloquentCheckInSummary;
use App\Modules\Engagement\Infrastructure\Persistence\EloquentDailyMotivation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class HandleCheckInForEngagement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 90;

    public function __construct(
        private readonly array $payload,
    ) {}

    public function handle(QuoteProviderInterface $quoteProvider): void
    {
        $checkInId = $this->payload['check_in_id'];
        $memberId = $this->payload['member_id'];
        $checkedInAt = $this->payload['occurred_at'];

        // Idempotency: skip if already projected
        if (EloquentCheckInSummary::where('id', $checkInId)->exists()) {
            return;
        }

        $quote = $quoteProvider->getRandom();

        EloquentDailyMotivation::create([
            'id' => (string) Str::uuid(),
            'check_in_id' => $checkInId,
            'member_id' => $memberId,
            'quote_id' => $quote->id,
            'quote_body' => $quote->body,
            'quote_author' => $quote->author,
            'assigned_at' => now(),
        ]);

        EloquentCheckInSummary::create([
            'id' => $checkInId,
            'member_id' => $memberId,
            'checked_in_at' => $checkedInAt,
            'quote_body' => $quote->body,
            'quote_author' => $quote->author,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleCheckInForEngagement failed after all retries', [
            'check_in_id' => $this->payload['check_in_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
