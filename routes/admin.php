<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::view('/foundation-check', 'foundation.check', ['portal' => 'Admin'])->name('foundation.check');
});
