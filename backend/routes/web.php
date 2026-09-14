<?php

use Illuminate\Support\Facades\Route;

// this is an api-only backend, the actual app lives in the separate
// frontend/ vue spa - this route just exists so hitting / doesn't 404
Route::get('/', fn () => response()->json(['service' => 'yandex-reviews-api']));
