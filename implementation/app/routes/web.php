<?php

use App\Http\Controllers\AppWebController;
use App\Http\Controllers\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('/', AppWebController::class);

Route::get('/playground', PlaygroundController::class);

Route::get('/app/{path?}', AppWebController::class)->where('path', '.*');
