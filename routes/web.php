<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // 選手の初期登録
    Route::get('/athletes/import', [AthleteController::class, 'importForm'])->name('athletes.import');
    Route::post('/athletes/import', [AthleteController::class, 'import']);
    Route::get('/athletes/template.csv', [AthleteController::class, 'template'])->name('athletes.template');
    Route::resource('athletes', AthleteController::class)->except('show', 'destroy');

    // エントリー
    Route::get('/meets/{meet}/entry', [EntryController::class, 'create'])->name('entries.create');
    Route::post('/meets/{meet}/entry/review', [EntryController::class, 'review'])->name('entries.review');
    Route::get('/meets/{meet}/entry/confirm', [EntryController::class, 'confirm'])->name('entries.confirm');
    Route::post('/meets/{meet}/entry', [EntryController::class, 'store'])->name('entries.store');
    Route::get('/entries', [EntryController::class, 'index'])->name('entries.index');
    Route::get('/entries/{entry}', [EntryController::class, 'show'])->name('entries.show');
    Route::get('/entries/{entry}/complete', [EntryController::class, 'complete'])->name('entries.complete');
    Route::get('/entries/{entry}/cancelled', [EntryController::class, 'checkoutCancelled'])->name('entries.checkout-cancelled');
    Route::post('/entries/{entry}/pay', [EntryController::class, 'pay'])->name('entries.pay');
    Route::post('/entries/{entry}/cancel', [EntryController::class, 'cancel'])->name('entries.cancel');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\MeetController::class, 'index'])->name('meets.index');
    Route::resource('meets', Admin\MeetController::class)->except('index', 'destroy');
    Route::post('/meets/{meet}/events', [Admin\EventController::class, 'store'])->name('events.store');
    Route::put('/events/{event}', [Admin\EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [Admin\EventController::class, 'destroy'])->name('events.destroy');
    Route::get('/meets/{meet}/entries', [Admin\EntryController::class, 'index'])->name('entries.index');
    Route::get('/meets/{meet}/entries.csv', [Admin\EntryController::class, 'export'])->name('entries.export');
    Route::get('/teams', [Admin\TeamController::class, 'index'])->name('teams.index');
});
