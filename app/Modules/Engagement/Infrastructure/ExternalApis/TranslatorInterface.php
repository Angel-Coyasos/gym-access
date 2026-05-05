<?php

namespace App\Modules\Engagement\Infrastructure\ExternalApis;

interface TranslatorInterface
{
    public function translate(string $text, string $from, string $to): string;
}
