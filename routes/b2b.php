<?php

use Illuminate\Support\Facades\Route;

Route::prefix('b2b')->name('b2b.')->group(function (): void {
    Route::view('/foundation-check', 'foundation.check', ['portal' => 'B2B'])->name('foundation.check');
});
