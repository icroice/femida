<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\SessionCaseController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StepResultController;
use App\Http\Controllers\Api\TestCaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — access for agents and CI via a project key
|--------------------------------------------------------------------------
*/

// throttle first, before the DB key lookup: a flood of 401s must not hammer SQLite
Route::middleware(['throttle:api', 'api.key'])->prefix('v1')->group(function () {
    Route::get('/me', MeController::class)->name('api.me');

    Route::apiResource('cases', TestCaseController::class)
        ->parameters(['cases' => 'case']);

    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::get('/sessions/{session}', [SessionController::class, 'show']);
    Route::patch('/sessions/{session}', [SessionController::class, 'update']);

    Route::post('/sessions/{session}/runs', [RunController::class, 'store']);
    Route::patch('/sessions/{session}/cases/{case}', [SessionCaseController::class, 'update']);

    Route::patch('/runs/{run}', [RunController::class, 'update']);
    Route::post('/runs/{run}/steps', [StepResultController::class, 'store']);
});
