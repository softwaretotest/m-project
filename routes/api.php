<?php
// route/api.php
use App\Http\Controllers\Api\ProductApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\M_Controller;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/products', [ProductApiController::class, 'index']);

// Endpoint for the UI to fetch the metadata JSON
Route::get('/m-value', [M_Controller::class, 'getMetadata']);

// Endpoint for the UI to fetch the metadata JSON
Route::post('/m-value', [M_Controller::class, 'save']);

// Endpoint for get new template from frontend , if JSON in Backend not exist 404
Route::post('/m-value/init', [M_Controller::class, 'saveAll']);

// Endpoint for get config for selected target app 
Route::get('/config', [M_Controller::class, 'get_M_Config_json']);
