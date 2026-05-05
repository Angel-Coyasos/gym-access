<?php

namespace Tests\Unit\Engagement;

use App\Modules\Engagement\Domain\Ports\QuoteProviderInterface;
use App\Modules\Engagement\Domain\ValueObjects\Quote;
use App\Modules\Engagement\Infrastructure\ExternalApis\TranslatingQuoteProviderDecorator;
use App\Modules\Engagement\Infrastructure\ExternalApis\TranslatorInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TranslatingQuoteProviderDecoratorTest extends TestCase
{
    private QuoteProviderInterface&MockInterface $innerProvider;
    private TranslatorInterface&MockInterface    $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->innerProvider = Mockery::mock(QuoteProviderInterface::class);
        $this->translator    = Mockery::mock(TranslatorInterface::class);
    }

    public function test_translates_body_field(): void
    {
        $original = new Quote(id: '1', body: 'The only limit is your mind.', author: 'Unknown');

        $this->innerProvider
            ->shouldReceive('getRandom')
            ->once()
            ->andReturn($original);

        $this->translator
            ->shouldReceive('translate')
            ->once()
            ->with('The only limit is your mind.', 'en', 'es')
            ->andReturn('El único límite es tu mente.');

        $quote = $this->makeDecorator()->getRandom();

        $this->assertSame('El único límite es tu mente.', $quote->body);
    }

    public function test_does_not_translate_author(): void
    {
        $original = new Quote(id: '2', body: 'Keep going.', author: 'Winston Churchill');

        $this->innerProvider
            ->shouldReceive('getRandom')
            ->once()
            ->andReturn($original);

        $this->translator
            ->shouldReceive('translate')
            ->once()
            ->andReturn('Sigue adelante.');

        $quote = $this->makeDecorator()->getRandom();

        $this->assertSame('Winston Churchill', $quote->author);
    }

    public function test_falls_back_to_english_when_translator_fails(): void
    {
        $original = new Quote(id: '3', body: 'Believe in yourself.', author: 'Someone');

        $this->innerProvider
            ->shouldReceive('getRandom')
            ->once()
            ->andReturn($original);

        $this->translator
            ->shouldReceive('translate')
            ->once()
            ->andThrow(new \RuntimeException('Simulated translator failure'));

        $quote = $this->makeDecorator()->getRandom();

        $this->assertSame('Believe in yourself.', $quote->body);
    }

    public function test_passes_quote_id_unchanged(): void
    {
        $original = new Quote(id: 'abc-123', body: 'Push harder.', author: 'Coach');

        $this->innerProvider
            ->shouldReceive('getRandom')
            ->once()
            ->andReturn($original);

        $this->translator
            ->shouldReceive('translate')
            ->once()
            ->andReturn('Esfuérzate más.');

        $quote = $this->makeDecorator()->getRandom();

        $this->assertSame('abc-123', $quote->id);
    }

    private function makeDecorator(): TranslatingQuoteProviderDecorator
    {
        return new TranslatingQuoteProviderDecorator(
            inner:      $this->innerProvider,
            translator: $this->translator,
            from:       'en',
            to:         'es',
        );
    }
}
