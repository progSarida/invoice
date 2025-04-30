<?php

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->to('/admin');
});
