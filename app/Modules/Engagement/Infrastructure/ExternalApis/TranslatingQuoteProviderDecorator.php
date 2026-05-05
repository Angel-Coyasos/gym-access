<?php

namespace App\Modules\Engagement\Infrastructure\ExternalApis;

use App\Modules\Engagement\Domain\Ports\QuoteProviderInterface;
use App\Modules\Engagement\Domain\ValueObjects\Quote;

final class TranslatingQuoteProviderDecorator implements QuoteProviderInterface
{
    public function __construct(
        private readonly QuoteProviderInterface $inner,
        private readonly TranslatorInterface $translator,
        private readonly string $from,
        private readonly string $to,
    ) {}

    public function getRandom(): Quote
    {
        $quote = $this->inner->getRandom();

        try {
            $body = $this->translator->translate(
                text: $quote->body,
                from: $this->from,
                to:   $this->to,
            );
        } catch (\Throwable) {
            $body = $quote->body;
        }

        return new Quote(
            id:     $quote->id,
            body:   $body,
            author: $quote->author,
        );
    }
}
