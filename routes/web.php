<?php

use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);

    Route::get('tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::put('tickets/{ticket}/assignment', [AdminTicketController::class, 'assign'])->name('tickets.assign');
    Route::put('tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('tickets.status');
});

Route::middleware(['auth', 'role:technician'])->prefix('technician')->name('technician.')->group(function () {
    Route::view('/', 'technician.dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
