<?php

namespace App\Providers;

use App\Modules\AccessControl\Domain\Repositories\CheckInRepositoryInterface;
use App\Modules\AccessControl\Infrastructure\Persistence\EloquentCheckInRepository;
use Illuminate\Support\ServiceProvider;

class AccessControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CheckInRepositoryInterface::class,
            EloquentCheckInRepository::class,
        );
    }
}
