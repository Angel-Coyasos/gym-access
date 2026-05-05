<?php

namespace Tests\Unit\Engagement;

use App\Modules\Engagement\Domain\Exceptions\QuoteProviderContractException;
use App\Modules\Engagement\Domain\Exceptions\QuoteProviderUnavailableException;
use App\Modules\Engagement\Domain\Ports\QuoteProviderInterface;
use App\Modules\Engagement\Infrastructure\ExternalApis\DummyJsonQuoteAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies the ACL adapter in isolation.
 *
 * Demonstrates DIP: the container resolves QuoteProviderInterface to the
 * concrete adapter, and the domain never knows about HTTP details.
 */
class DummyJsonQuoteAdapterTest extends TestCase
{
    private const API_URL = 'https://dummyjson.com/quotes/random';

    public function test_container_resolves_interface_to_adapter(): void
    {
        $provider = $this->app->make(QuoteProviderInterface::class);

        $this->assertInstanceOf(DummyJsonQuoteAdapter::class, $provider);
    }

    public function test_returns_quote_when_api_succeeds(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'id' => 42,
                'quote' => 'The only way to do great work is to love what you do.',
                'author' => 'Steve Jobs',
            ], 200),
        ]);

        $adapter = $this->makeAdapter();
        $quote = $adapter->getRandom();

        $this->assertSame('42', $quote->id);
        $this->assertSame('The only way to do great work is to love what you do.', $quote->body);
        $this->assertSame('Steve Jobs', $quote->author);
    }

    public function test_throws_unavailable_when_api_returns_500(): void
    {
        Http::fake([
            self::API_URL => Http::response([], 500),
        ]);

        $this->expectException(QuoteProviderUnavailableException::class);
        $this->expectExceptionMessageMatches('/HTTP 500/');

        $this->makeAdapter()->getRandom();
    }

    public function test_throws_unavailable_when_api_returns_503(): void
    {
        Http::fake([
            self::API_URL => Http::response([], 503),
        ]);

        $this->expectException(QuoteProviderUnavailableException::class);

        $this->makeAdapter()->getRandom();
    }

    public function test_throws_contract_exception_when_body_field_is_missing(): void
    {
        Http::fake([
            self::API_URL => Http::response(['id' => 1, 'author' => 'Someone'], 200),
        ]);

        $this->expectException(QuoteProviderContractException::class);
        $this->expectExceptionMessageMatches('/"quote"/');

        $this->makeAdapter()->getRandom();
    }

    public function test_throws_contract_exception_when_author_field_is_missing(): void
    {
        Http::fake([
            self::API_URL => Http::response(['id' => 1, 'quote' => 'Something'], 200),
        ]);

        $this->expectException(QuoteProviderContractException::class);

        $this->makeAdapter()->getRandom();
    }

    public function test_throws_unavailable_on_connection_timeout(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(QuoteProviderUnavailableException::class);
        $this->expectExceptionMessageMatches('/unreachable/');

        $this->makeAdapter()->getRandom();
    }

    public function test_domain_quote_is_agnostic_of_http(): void
    {
        Http::fake([
            self::API_URL => Http::response([
                'id' => 7,
                'quote' => 'Keep going.',
                'author' => 'Unknown',
            ], 200),
        ]);

        // Domain only sees Quote value object — no HTTP primitives
        $quote = $this->makeAdapter()->getRandom();

        $this->assertIsString($quote->id);
        $this->assertIsString($quote->body);
        $this->assertIsString($quote->author);
    }

    private function makeAdapter(): DummyJsonQuoteAdapter
    {
        return new DummyJsonQuoteAdapter(
            url: self::API_URL,
            timeout: 5,
        );
    }
}
