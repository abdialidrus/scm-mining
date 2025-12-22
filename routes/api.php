<?php

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UomController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/uoms', [UomController::class, 'index']);
    Route::get('/departments', [DepartmentController::class, 'index']);

    Route::prefix('purchase-requests')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index']);
        Route::post('/', [PurchaseRequestController::class, 'store']);
        Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show']);
        Route::put('/{purchaseRequest}', [PurchaseRequestController::class, 'update']);

        Route::post('/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit']);
        Route::post('/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve']);
        Route::post('/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject']);
        Route::post('/{purchaseRequest}/convert-to-po', [PurchaseRequestController::class, 'convertToPo']);
    });

    Route::get('/roles', [RoleController::class, 'index']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
