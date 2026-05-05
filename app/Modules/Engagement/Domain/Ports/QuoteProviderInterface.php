<?php

namespace App\Modules\Engagement\Domain\Ports;

use App\Modules\Engagement\Domain\ValueObjects\Quote;

interface QuoteProviderInterface
{
    public function getRandom(): Quote;
}
