<?php

use App\Constant\TargetManager;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return 'Project M is Running!';
});

Route::get('/dashboard', function () {
    $activeTarget = TargetManager::get_activeTarget();
    $hasTarget = false === empty($activeTarget);
    if ($hasTarget)
        return Inertia::render('0_M_Dashboard');
    else
        return Inertia::render('3_M_TargetSelector');
});
