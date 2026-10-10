<?php

// route/api.php
use App\Http\Controllers\M_Controller;
use App\Http\Controllers\M_Sync_Controller;
use App\Http\Controllers\TargetManager_Config_Controller;
use App\Http\Controllers\UF_JS_Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::get('/products', [ProductApiController::class, 'index']);

// Endpoint for the UI to fetch the metadata JSON
Route::get('/m-value', [M_Controller::class, 'get_M_value']);

// Endpoint for the UI to fetch the metadata JSON
Route::post('/m-value', [M_Controller::class, 'save_M_value']);

// Endpoint for get new template from frontend , if JSON in Backend not exist 404
Route::post('/m-value/init', [M_Controller::class, 'saveAll']);

// Endpoint for get config for selected target app
Route::get('/config', [M_Controller::class, 'get_M_Config_json']);

// Endpoint for crud target app in 3_TargetManager_Config.json
Route::get('/target/scan', [TargetManager_Config_Controller::class, 'scanTargets']);
Route::post('/target/save_Target_App', [TargetManager_Config_Controller::class, 'updateTargetConfig']);

Route::prefix('m-sync')->group(function () {
    Route::post('/start', [M_Sync_Controller::class, 'start']);
    Route::get('/status', [M_Sync_Controller::class, 'status']);
    Route::post('/continue', [M_Sync_Controller::class, 'continueRun']);
});

Route::prefix('uf-js')->group(function () {
    Route::post('/save', [UF_JS_Controller::class, 'save']);
    Route::get('/{uf_name}', [UF_JS_Controller::class, 'load']);
});

Route::post('/m-copy-json', [M_Controller::class, 'copyJSON']);
Route::get('/m-get-example-json', [M_Controller::class, 'get_Example_JSON']);
Route::post('/m-merge-json', [M_Controller::class, 'mergeAndSaveJSON']);
