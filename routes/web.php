<?php

use App\Http\Controllers\CapturedRequestController;
use App\Http\Controllers\EndpointController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [EndpointController::class, 'index'])->name('dashboard');
    Route::post('endpoints', [EndpointController::class, 'store'])->name('endpoints.store');
    Route::get('endpoints/{endpoint}', [EndpointController::class, 'show'])->name('endpoints.show');
    Route::get('endpoints/{endpoint}/requests/{capturedRequest}', [CapturedRequestController::class, 'show'])
        ->name('captured-requests.show');
});

require __DIR__.'/settings.php';
