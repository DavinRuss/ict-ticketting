<?php

use App\Http\Controllers\Admin\ActivityReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Technician\TicketController as TechnicianTicketController;
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
    Route::put('tickets/{ticket}/assignment', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::put('tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    Route::post('tickets/{ticket}/comments', [TicketController::class, 'storeComment'])->name('tickets.comments.store');
    Route::post('tickets/{ticket}/review', [ReviewController::class, 'store'])->name('tickets.review.store');
    Route::put('tickets/{ticket}/review', [ReviewController::class, 'update'])->name('tickets.review.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show']);
    Route::put('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::put('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');

    Route::get('tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::put('tickets/{ticket}/assignment', [AdminTicketController::class, 'assign'])->name('tickets.assign');
    Route::put('tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('tickets.status');
    Route::delete('tickets/{ticket}', [AdminTicketController::class, 'destroy'])->name('tickets.destroy');

    Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::get('reports', [ActivityReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ActivityReportController::class, 'export'])->name('reports.export');
    Route::post('reports/export-selected', [ActivityReportController::class, 'exportSelected'])->name('reports.export-selected');
});

Route::middleware(['auth', 'role:technician'])->prefix('technician')->name('technician.')->group(function () {
    Route::get('/', [TechnicianTicketController::class, 'dashboard'])->name('dashboard');
    Route::get('tickets', [TechnicianTicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [TechnicianTicketController::class, 'show'])->name('tickets.show');
    Route::put('tickets/{ticket}/status', [TechnicianTicketController::class, 'updateStatus'])->name('tickets.status');
    Route::post('tickets/{ticket}/notes', [TechnicianTicketController::class, 'storeNote'])->name('tickets.notes.store');
});

Route::middleware(['auth', 'role:technician,admin'])->prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::post('messages', [ChatController::class, 'store'])->name('messages.store');
    Route::post('messages/{message}/react', [ChatController::class, 'react'])->name('messages.react');
});

require __DIR__.'/auth.php';
