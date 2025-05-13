<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage; // Correct import for Storage

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', [HomeController::class, 'home']);

Route::get('/stock-opname/image/{path}', function ($path) {
    $disk = Storage::disk('stock_opname');
    
    if (!$disk->exists($path)) {
        abort(404);
    }

    return response($disk->get($path))
        ->header('Content-Type', $disk->mimeType($path)); // Remove Storage::
})->where('path', '.*');
