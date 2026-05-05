<?php

namespace App\Modules\Engagement\Infrastructure\ExternalApis;

use App\Modules\Engagement\Domain\Exceptions\QuoteProviderContractException;
use App\Modules\Engagement\Domain\Exceptions\QuoteProviderUnavailableException;
use App\Modules\Engagement\Domain\Ports\QuoteProviderInterface;
use App\Modules\Engagement\Domain\ValueObjects\Quote;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class DummyJsonQuoteAdapter implements QuoteProviderInterface
{
    public function __construct(
        private readonly string $url,
        private readonly int $timeout,
    ) {}

    public function getRandom(): Quote
    {
        try {
            $response = Http::timeout($this->timeout)->get($this->url);
        } catch (ConnectionException $e) {
            throw new QuoteProviderUnavailableException(
                'Quote provider is unreachable: '.$e->getMessage(),
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw new QuoteProviderUnavailableException(
                'Quote provider returned HTTP '.$response->status(),
            );
        }

        $data = $response->json();

        if (! isset($data['quote'], $data['author'])) {
            throw new QuoteProviderContractException(
                'Quote provider response is missing required fields "quote" or "author"',
            );
        }

        return new Quote(
            id: (string) ($data['id'] ?? ''),
            body: $data['quote'],
            author: $data['author'],
        );
    }
}
