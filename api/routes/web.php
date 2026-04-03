<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'kind' => 'api',
        'hint' => 'JSON unter /api/* · SPA separat (frontend/)',
    ]);
});
