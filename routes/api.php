<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider using a specific middleware
| group. Enjoy building your API!
|
*/

Route::post('login', [App\Http\Controllers\AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('logout', [App\Http\Controllers\AuthController::class, 'logout']);
    Route::post('refresh', [App\Http\Controllers\AuthController::class, 'refresh']);
    Route::get('me', [App\Http\Controllers\AuthController::class, 'me']);

    Route::apiResource('branches', App\Http\Controllers\BranchController::class);
    Route::apiResource('employees', App\Http\Controllers\EmployeeController::class);
    Route::get('roles', [App\Http\Controllers\RoleController::class, 'index']);

    Route::apiResource('suppliers', App\Http\Controllers\SupplierController::class);
    Route::apiResource('ingredients', App\Http\Controllers\IngredientController::class);

    Route::post('purchase-orders/{id}/receive', [App\Http\Controllers\PurchaseOrderController::class, 'receive']);
    Route::apiResource('purchase-orders', App\Http\Controllers\PurchaseOrderController::class);

    Route::post('stock-adjustments/{id}/finalize', [App\Http\Controllers\StockAdjustmentController::class, 'finalize']);
    Route::apiResource('stock-adjustments', App\Http\Controllers\StockAdjustmentController::class);

    Route::post('stock-transfers/{id}/ship', [App\Http\Controllers\StockTransferController::class, 'ship']);
    Route::post('stock-transfers/{id}/receive', [App\Http\Controllers\StockTransferController::class, 'receive']);
    Route::apiResource('stock-transfers', App\Http\Controllers\StockTransferController::class);

    Route::apiResource('stock-wastes', App\Http\Controllers\StockWasteController::class);

    Route::apiResource('product-categories', App\Http\Controllers\ProductCategoryController::class);
    Route::apiResource('products', App\Http\Controllers\ProductController::class);
    Route::apiResource('promotions', App\Http\Controllers\PromotionController::class);

    Route::apiResource('tables', App\Http\Controllers\TableController::class);

    Route::post('shifts/open', [App\Http\Controllers\ShiftController::class, 'open']);
    Route::post('shifts/{id}/close', [App\Http\Controllers\ShiftController::class, 'close']);
    Route::apiResource('shifts', App\Http\Controllers\ShiftController::class);

    Route::post('orders/{id}/pay', [App\Http\Controllers\OrderController::class, 'pay']);
    Route::apiResource('orders', App\Http\Controllers\OrderController::class);

    Route::apiResource('expenses', App\Http\Controllers\ExpenseController::class);

    Route::get('reports/sales', [App\Http\Controllers\ReportController::class, 'sales']);
    Route::get('reports/profit', [App\Http\Controllers\ReportController::class, 'profit']);
    Route::get('reports/inventory', [App\Http\Controllers\ReportController::class, 'inventory']);
    Route::get('reports/cash-flow', [App\Http\Controllers\ReportController::class, 'cashFlow']);
});
