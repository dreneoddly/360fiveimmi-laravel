<?php

use App\Http\Controllers\Api\ContactInquiryController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\UpdateController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('auth')->group(function () {
    Route::get('/csrf', [SessionController::class, 'csrf']);
    Route::post('/login', [SessionController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/register', [SessionController::class, 'register'])->middleware('throttle:6,1');
    Route::middleware(['auth:web', 'admin'])->group(function () {
        Route::get('/me', [SessionController::class, 'currentUser']);
        Route::post('/logout', [SessionController::class, 'logout']);
    });
});

Route::get('/updates', [UpdateController::class, 'index']);
Route::get('/updates/{update}', [UpdateController::class, 'show'])
    ->whereNumber('update')
    ->name('api.updates.show');
Route::post('/contact-inquiries', [ContactInquiryController::class, 'store'])
    ->middleware('throttle:5,1');

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service:slug}', [ServiceController::class, 'show'])
    ->name('api.services.show');

Route::get('/countries', [CountryController::class, 'index']);
Route::get('/countries/{country}', [CountryController::class, 'show'])
    ->whereNumber('country')
    ->name('api.countries.show');

Route::middleware(['web', 'auth:web', 'admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/admins', [UserController::class, 'admins']);
    Route::post('/admins', [UserController::class, 'store']);
    Route::put('/admins/{user}', [UserController::class, 'update'])->whereNumber('user');
    Route::delete('/admins/{user}', [UserController::class, 'destroy'])->whereNumber('user');
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service:slug}', [ServiceController::class, 'update']);
    Route::delete('/services/{service:slug}', [ServiceController::class, 'destroy']);
    Route::post('/updates', [UpdateController::class, 'store']);
    Route::put('/updates/{update}', [UpdateController::class, 'update'])->whereNumber('update');
    Route::delete('/updates/{update}', [UpdateController::class, 'destroy'])->whereNumber('update');
    Route::get('/contact-inquiries', [ContactInquiryController::class, 'index']);
    Route::get('/contact-inquiries/{contactInquiry}', [ContactInquiryController::class, 'show'])
        ->whereNumber('contactInquiry');
    Route::patch('/contact-inquiries/{contactInquiry}', [ContactInquiryController::class, 'updateStatus'])
        ->whereNumber('contactInquiry');
    Route::post('/countries', [CountryController::class, 'store']);
    Route::put('/countries/{country}', [CountryController::class, 'update'])->whereNumber('country');
    Route::patch('/countries/{country}', [CountryController::class, 'update'])->whereNumber('country');
    Route::delete('/countries/{country}', [CountryController::class, 'destroy'])->whereNumber('country');
});
