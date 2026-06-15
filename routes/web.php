<?php

use Illuminate\Support\Facades\Route;

// SPA entry — Vue router owns all client-side routes.
Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|up).*$');
