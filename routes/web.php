<?php

use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;


Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::get('/sso-login', [SsoController::class, 'redirect'])->name('sso.login');

Route::get('/admin/login', fn() => redirect()->route('sso.login'))->name('filament.admin.auth.login');

Route::get('/', fn() => redirect()->route('sso.login'));
Route::get('/login', fn() => redirect()->route('sso.login'));
Route::get('/admin/login', fn() => redirect()->route('sso.login'));
