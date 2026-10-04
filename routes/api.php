<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UrbanController;

Route::get('/dashboard', [UrbanController::class, 'dashboard']);
Route::get('/stations', [UrbanController::class, 'stations']);
Route::get('/stations/{id}', [UrbanController::class, 'station']);
Route::get('/forecast', [UrbanController::class, 'forecast']);
Route::get('/hotspots', [UrbanController::class, 'hotspots']);
Route::get('/alerts', [UrbanController::class, 'alerts']);
Route::get('/model/metrics', [UrbanController::class, 'metrics']);
Route::get('/pipeline/status', [UrbanController::class, 'pipeline']);
Route::get('/health', [UrbanController::class, 'health']);

Route::prefix('v1/flood')->group(function () {
    Route::get('/health', [\App\Http\Controllers\FloodController::class, 'health']);
    Route::middleware(\App\Http\Middleware\FloodApiToken::class)->group(function () {
        foreach (['cameras' => 'Camera', 'datasets' => 'Dataset', 'models' => 'Model', 'predictions' => 'Prediction'] as $resource => $method) {
            Route::get('/'.$resource, [\App\Http\Controllers\FloodController::class, $resource]);
            Route::post('/'.$resource, [\App\Http\Controllers\FloodController::class, 'store'.$method]);
        }
    });
});
