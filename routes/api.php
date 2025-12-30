<?php

use App\Http\Controllers\Api\ApprovalWorkflowController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\ItemCategoryController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemSerialNumberController;
use App\Http\Controllers\Api\PickingOrderController;
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

    // Items
    Route::prefix('items')->group(function () {
        Route::get('/', [ItemController::class, 'index']);
        Route::post('/', [ItemController::class, 'store']);
        Route::get('/{item}', [ItemController::class, 'show']);
        Route::put('/{item}', [ItemController::class, 'update']);
        Route::delete('/{item}', [ItemController::class, 'destroy']);
    });

    Route::get('/uoms', [UomController::class, 'index']);
    Route::get('/departments', [DepartmentController::class, 'index']);

    // Item Categories
    Route::prefix('item-categories')->group(function () {
        Route::get('/tree', [ItemCategoryController::class, 'tree']);
        Route::get('/', [ItemCategoryController::class, 'index']);
        Route::post('/', [ItemCategoryController::class, 'store']);
        Route::get('/{itemCategory}', [ItemCategoryController::class, 'show']);
        Route::put('/{itemCategory}', [ItemCategoryController::class, 'update']);
        Route::delete('/{itemCategory}', [ItemCategoryController::class, 'destroy']);
    });

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
        Route::get('/{purchaseOrder}/export-pdf', [PurchaseOrderController::class, 'exportPdf']);
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
        Route::put('/{goodsReceipt}', [GoodsReceiptController::class, 'update']);
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

    Route::prefix('picking-orders')->group(function () {
        Route::get('/', [PickingOrderController::class, 'index']);
        Route::post('/', [PickingOrderController::class, 'store']);
        Route::get('/{pickingOrder}', [PickingOrderController::class, 'show']);

        Route::post('/{pickingOrder}/post', [PickingOrderController::class, 'post']);
        Route::post('/{pickingOrder}/cancel', [PickingOrderController::class, 'cancel']);
    });

    Route::prefix('stock/serial-numbers')->group(function () {
        Route::get('/', [ItemSerialNumberController::class, 'available']);
        Route::get('/{serialNumber}', [ItemSerialNumberController::class, 'show']);
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
    Route::get('/warehouses/{warehouse}/locations', [WarehouseLocationController::class, 'index']);
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy']);

    Route::get('/warehouse-locations', [WarehouseLocationController::class, 'index']);

    // Stock checking endpoint for picking order form
    Route::get('/stock/location/{location}/item/{item}', function (int $location, int $item, Request $request) {
        $uomId = $request->query('uom_id') ? (int) $request->query('uom_id') : null;
        $stockQueryService = app(\App\Services\Inventory\StockQueryService::class);

        $qtyOnHand = $stockQueryService->getOnHandForLocation($location, $item, $uomId);

        return response()->json([
            'data' => [
                'location_id' => $location,
                'item_id' => $item,
                'uom_id' => $uomId,
                'qty_on_hand' => $qtyOnHand,
            ],
        ]);
    });

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
