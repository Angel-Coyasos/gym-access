<?php

namespace App\Providers;

use App\Modules\Engagement\Domain\Ports\QuoteProviderInterface;
use App\Modules\Engagement\Infrastructure\ExternalApis\DummyJsonQuoteAdapter;
use Illuminate\Support\ServiceProvider;

class EngagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(QuoteProviderInterface::class, function () {
            return new DummyJsonQuoteAdapter(
                url: config('services.quote_api.url'),
                timeout: (int) config('services.quote_api.timeout', 5),
            );
        });
    }
}
