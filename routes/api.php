<?php

use App\Modules\AccessControl\Infrastructure\Http\CheckInController;
use App\Modules\Engagement\Infrastructure\Http\DashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/check-in', [CheckInController::class, 'store']);
Route::get('/dashboard/{memberId}', [DashboardController::class, 'index']);
