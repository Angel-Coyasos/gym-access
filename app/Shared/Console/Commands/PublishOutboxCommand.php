<?php

namespace App\Shared\Console\Commands;

use App\Modules\Engagement\Application\Jobs\HandleCheckInForEngagement;
use App\Shared\Infrastructure\Outbox\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishOutboxCommand extends Command
{
    protected $signature = 'outbox:publish {--interval=5 : Seconds between polling cycles}';

    protected $description = 'Polls the outbox table and dispatches pending domain events to the queue';

    public function handle(): void
    {
        $interval = (int) $this->option('interval');

        $this->info('Outbox publisher started. Polling every '.$interval.'s...');

        while (true) {
            $this->publishPendingEvents();
            sleep($interval);
        }
    }

    private function publishPendingEvents(): void
    {
        $pending = OutboxEvent::pending()->get();

        if ($pending->isEmpty()) {
            return;
        }

        foreach ($pending as $outboxEvent) {
            DB::transaction(function () use ($outboxEvent) {
                HandleCheckInForEngagement::dispatch($outboxEvent->payload)
                    ->onQueue('engagement');

                $outboxEvent->update(['published_at' => now()]);
            });
        }

        $this->line('Published '.$pending->count().' event(s).');
    }
}
