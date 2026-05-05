<?php

namespace App\Modules\AccessControl\Domain\Repositories;

use App\Modules\AccessControl\Domain\CheckIn;

interface CheckInRepositoryInterface
{
    public function save(CheckIn $checkIn): void;
}
