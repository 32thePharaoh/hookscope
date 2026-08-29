<?php

use App\Http\Controllers\CaptureController;
use Illuminate\Support\Facades\Route;

Route::match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '/in/{token}',
    CaptureController::class,
);
