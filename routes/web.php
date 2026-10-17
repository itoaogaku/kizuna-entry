<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\Auth\IndividualAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TeamAuthController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KomojuWebhookController;
use App\Http\Controllers\PressController;
use App\Http\Controllers\SheetEntryController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// 公開サイト：大会開催
Route::get('/', [SiteController::class, 'meets'])->name('site.meets');
Route::get('/meets/{meet}/timetable', [SiteController::class, 'timetable'])->name('site.timetable');
Route::get('/meets/{meet}/startlist', [SiteController::class, 'startList'])->name('site.startlist');
Route::get('/meets/{meet}/program', [SiteController::class, 'program'])->name('site.program');
Route::get('/meets/{meet}/press', [PressController::class, 'create'])->name('press.create');
Route::post('/meets/{meet}/press', [PressController::class, 'store'])->middleware('throttle:10,1')->name('press.store');

// エントリーの入口（ログイン前：方法の選択／ログイン後：受付中の大会）
Route::get('/entry', HomeController::class)->name('home');

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::post('/komoju/webhook', KomojuWebhookController::class)->name('komoju.webhook');

// エクセル添付でのエントリー（ログインなしでも使える）
Route::get('/sheet-entry', [SheetEntryController::class, 'create'])->name('sheet.create');
Route::post('/sheet-entry', [SheetEntryController::class, 'upload'])->middleware('throttle:20,1')->name('sheet.upload');
Route::get('/sheet-entry/confirm', [SheetEntryController::class, 'preview'])->name('sheet.preview');
Route::post('/sheet-entry/confirm', [SheetEntryController::class, 'store'])->name('sheet.store');
Route::get('/sheet-entry/{entry}', [SheetEntryController::class, 'status'])->name('sheet.status');
Route::get('/sheet-entry/{entry}/pay', [SheetEntryController::class, 'pay'])->name('sheet.pay');
Route::get('/sheet-entry/{entry}/cancelled', [SheetEntryController::class, 'cancelled'])->name('sheet.cancelled');

// ログインが必要な画面からは、最初の選択画面（団体／個人）に戻す
Route::redirect('/login', '/entry')->name('login');

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
    Route::get('/athletes/advance', [AthleteController::class, 'advanceForm'])->name('athletes.advance');
    Route::post('/athletes/advance', [AthleteController::class, 'advance']);
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
    Route::get('/entries/{entry}/sheet', [Admin\EntryController::class, 'sheet'])->name('entries.sheet');
    Route::get('/meets/{meet}/entries.xlsx', [Admin\EntryController::class, 'export'])->name('entries.export');
    Route::get('/meets/{meet}/roster', [Admin\RosterController::class, 'index'])->name('roster.index');
    Route::put('/entry-items/{item}/best', [Admin\RosterController::class, 'updateBest'])->name('roster.best');
    Route::get('/meets/{meet}/program', [Admin\ProgramController::class, 'show'])->name('program.show');
    Route::put('/meets/{meet}/program/races', [Admin\ProgramController::class, 'updateRaces'])->name('program.races');
    Route::post('/meets/{meet}/program/generate', [Admin\ProgramController::class, 'generate'])->name('program.generate');
    Route::put('/meets/{meet}/program/heats', [Admin\ProgramController::class, 'moveHeats'])->name('program.heats');
    Route::put('/meets/{meet}/program/status', [Admin\ProgramController::class, 'status'])->name('program.status');
    Route::get('/meets/{meet}/startlist.xlsx', [Admin\ProgramController::class, 'export'])->name('program.export');
    Route::get('/meets/{meet}/press', [Admin\PressController::class, 'index'])->name('press.index');
    Route::get('/teams', [Admin\TeamController::class, 'index'])->name('teams.index');
});
