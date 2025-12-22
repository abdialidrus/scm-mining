<?php

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SupplierController;
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
    });

    Route::prefix('purchase-orders')->group(function () {
        Route::get('/report/summary', [PurchaseOrderController::class, 'report']);

        Route::get('/', [PurchaseOrderController::class, 'index']);
        Route::post('/', [PurchaseOrderController::class, 'store']);
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'updateDraft']);
        Route::post('/{purchaseOrder}/reopen', [PurchaseOrderController::class, 'reopen']);

        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
        Route::post('/{purchaseOrder}/close', [PurchaseOrderController::class, 'close']);
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    });

    Route::get('/roles', [RoleController::class, 'index']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);
});
