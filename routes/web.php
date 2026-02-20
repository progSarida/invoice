<?php

use App\Http\Controllers\SsoController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::get('/sso-login', [SsoController::class, 'redirect'])->name('sso.login');
Route::post('/slo-callback', [SsoController::class, 'handleSloCallback'])->name('sso.logout')->withoutMiddleware([VerifyCsrfToken::class]);

Route::get('/admin/login', fn() => redirect()->route('sso.login'))->name('filament.admin.auth.login');

Route::get('/login', fn() => redirect()->route('sso.login'));
