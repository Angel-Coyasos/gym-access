<?php

namespace App\Modules\Engagement\Infrastructure\ExternalApis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MyMemoryTranslatorAdapter implements TranslatorInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function translate(string $text, string $from, string $to): string
    {
        try {
            $response = Http::timeout($this->timeout)->get($this->baseUrl, [
                'q'        => $text,
                'langpair' => "{$from}|{$to}",
            ]);

            if ($response->status() === 429) {
                Log::warning('MyMemory translation rate limit exceeded, falling back to original text.', [
                    'langpair' => "{$from}|{$to}",
                ]);

                return $text;
            }

            if ($response->failed()) {
                Log::warning('MyMemory translation request failed.', [
                    'status'   => $response->status(),
                    'langpair' => "{$from}|{$to}",
                ]);

                return $text;
            }

            $translated = $response->json('responseData.translatedText');

            if (! is_string($translated) || trim($translated) === '') {
                Log::warning('MyMemory returned empty or missing translatedText, falling back.', [
                    'langpair' => "{$from}|{$to}",
                ]);

                return $text;
            }

            return $translated;
        } catch (ConnectionException $e) {
            Log::warning('MyMemory translation service unreachable, falling back to original text.', [
                'error' => $e->getMessage(),
            ]);

            return $text;
        } catch (\Throwable $e) {
            Log::warning('Unexpected error during translation, falling back to original text.', [
                'error' => $e->getMessage(),
            ]);

            return $text;
        }
    }
}
