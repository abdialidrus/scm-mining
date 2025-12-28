<?php

use App\Http\Controllers\Api\ApprovalWorkflowController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\PutAwayController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseLocationController;
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
        Route::get('/{purchaseOrder}/approvals', [PurchaseOrderController::class, 'approvals']);
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'updateDraft']);
        Route::post('/{purchaseOrder}/reopen', [PurchaseOrderController::class, 'reopen']);

        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
        Route::post('/{purchaseOrder}/close', [PurchaseOrderController::class, 'close']);
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    });

    Route::prefix('goods-receipts')->group(function () {
        Route::get('/', [GoodsReceiptController::class, 'index']);
        Route::get('/eligible-for-put-away', [GoodsReceiptController::class, 'eligibleForPutAway']);
        Route::post('/', [GoodsReceiptController::class, 'store']);
        Route::get('/{goodsReceipt}', [GoodsReceiptController::class, 'show']);
        Route::get('/{goodsReceipt}/put-away-summary', [GoodsReceiptController::class, 'putAwaySummary']);

        Route::post('/{goodsReceipt}/post', [GoodsReceiptController::class, 'post']);
        Route::post('/{goodsReceipt}/cancel', [GoodsReceiptController::class, 'cancel']);
    });

    Route::prefix('put-aways')->group(function () {
        Route::get('/', [PutAwayController::class, 'index']);
        Route::post('/', [PutAwayController::class, 'store']);
        Route::get('/{putAway}', [PutAwayController::class, 'show']);

        Route::post('/{putAway}/post', [PutAwayController::class, 'post']);
        Route::post('/{putAway}/cancel', [PutAwayController::class, 'cancel']);
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

    Route::get('/warehouses', [WarehouseController::class, 'index']);
    Route::post('/warehouses', [WarehouseController::class, 'store']);
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy']);

    Route::get('/warehouse-locations', [WarehouseLocationController::class, 'index']);

    Route::prefix('approval-workflows')->group(function () {
        Route::get('/', [ApprovalWorkflowController::class, 'index']);
        Route::post('/', [ApprovalWorkflowController::class, 'store']);
        Route::get('/{approvalWorkflow}', [ApprovalWorkflowController::class, 'show']);
        Route::put('/{approvalWorkflow}', [ApprovalWorkflowController::class, 'update']);
        Route::delete('/{approvalWorkflow}', [ApprovalWorkflowController::class, 'destroy']);

        Route::post('/{approvalWorkflow}/steps', [ApprovalWorkflowController::class, 'storeStep']);
        Route::put('/{approvalWorkflow}/steps/{step}', [ApprovalWorkflowController::class, 'updateStep']);
        Route::delete('/{approvalWorkflow}/steps/{step}', [ApprovalWorkflowController::class, 'destroyStep']);
        Route::put('/{approvalWorkflow}/steps/reorder', [ApprovalWorkflowController::class, 'reorderSteps']);
    });
});
