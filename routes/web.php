<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\Auth\IndividualAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TeamAuthController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

// ログインが必要な画面からは、最初の選択画面（団体／個人）に戻す
Route::redirect('/login', '/')->name('login');

Route::middleware('guest')->group(function () {
    // 団体申し込み
    Route::get('/team/login', [TeamAuthController::class, 'loginForm'])->name('team.login');
    Route::post('/team/login', [TeamAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/team/register', [TeamAuthController::class, 'registerForm'])->name('team.register');
    Route::post('/team/register', [TeamAuthController::class, 'register']);

    // 個人申し込み
    Route::get('/individual/login', [IndividualAuthController::class, 'loginForm'])->name('individual.login');
    Route::post('/individual/login', [IndividualAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/individual/register', [IndividualAuthController::class, 'registerForm'])->name('individual.register');
    Route::post('/individual/register', [IndividualAuthController::class, 'register']);

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
    Route::get('/meets/{meet}/entries.xlsx', [Admin\EntryController::class, 'export'])->name('entries.export');
    Route::get('/teams', [Admin\TeamController::class, 'index'])->name('teams.index');
});
