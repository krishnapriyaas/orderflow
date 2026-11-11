<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Admin\ReviewQueue;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // 5 attempts per minute per IP against password guessing.
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::livewire('/admin/reviews', ReviewQueue::class)
    ->middleware(['auth', 'can:review-order-notes'])
    ->name('admin.reviews');
