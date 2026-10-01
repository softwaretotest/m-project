<?php

// route/api.php
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\M_Controller;
use App\Http\Controllers\SyncManagerController;
use App\Http\Controllers\TargetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/products', [ProductApiController::class, 'index']);

// Endpoint for the UI to fetch the metadata JSON
Route::get('/m-value', [M_Controller::class, 'get_M_value']);

// Endpoint for the UI to fetch the metadata JSON
Route::post('/m-value', [M_Controller::class, 'save_M_value']);

// Endpoint for get new template from frontend , if JSON in Backend not exist 404
Route::post('/m-value/init', [M_Controller::class, 'saveAll']);

// Endpoint for get config for selected target app
Route::get('/config', [M_Controller::class, 'get_M_Config_json']);

// Endpoint for crud target app in 3_M-Config.json
Route::get('/target/scan', [TargetController::class, 'scanTargets']);
Route::post('/target/save_Target_App', [TargetController::class, 'updateTargetConfig']);

Route::post('/sync-manager/start', [SyncManagerController::class, 'start']);
Route::get('/sync-manager/status', [SyncManagerController::class, 'status']);
Route::post('/sync-manager/reset', [SyncManagerController::class, 'resetFailedRun']);
Route::post('/sync-manager/{run_ID}/continue', [SyncManagerController::class, 'continueRun']);
