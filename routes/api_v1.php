<?php

use Illuminate\Support\Facades\Route;

Route::get('/foundation-check', fn () => response()->json([
    'application' => 'Royal Travel Platform',
    'version' => 'v1',
    'locale' => app()->getLocale(),
    'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
    'message' => __('foundation.ready'),
]));
