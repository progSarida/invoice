<?php

use App\Http\Controllers\SsoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::get('/sso-login', [SsoController::class, 'redirect'])->name('sso.login');

Route::get('/admin/login', fn() => redirect()->route('sso.login'))->name('filament.admin.auth.login');

Route::get('/login', fn() => redirect()->route('sso.login'));



