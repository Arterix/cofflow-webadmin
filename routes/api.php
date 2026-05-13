<?php

use App\Http\Controllers\Api\Admin\AdminMenuController;
use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\CondimentController;
use App\Http\Controllers\Api\Admin\DiscountController;
use App\Http\Controllers\Api\Admin\IngredientController;
use App\Http\Controllers\Api\Admin\StaffManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Staff\QueueController;
use App\Http\Controllers\Api\Staff\StaffOrderController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cofflow API Routes
|--------------------------------------------------------------------------
*/

// 1) Public
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/menus', [MenuController::class, 'index']);
Route::get('/menus/{id}', [MenuController::class, 'show']);
Route::get('/categories', [MenuController::class, 'categories']);
Route::post('/webhook/midtrans', [WebhookController::class, 'midtrans']);

// 2) Authenticated (any role)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::patch('/auth/fcm-token', [AuthController::class, 'updateFcmToken']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/{id}/payment-status', [OrderController::class, 'paymentStatus']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
});

// 3) Kasir + Admin
Route::middleware(['auth:sanctum', 'role:kasir,admin'])->group(function () {
    Route::get('/staff/orders', [StaffOrderController::class, 'index']);
    Route::get('/staff/orders/{id}', [StaffOrderController::class, 'show']);
    Route::patch('/staff/orders/{id}/status', [StaffOrderController::class, 'updateStatus']);

    Route::get('/queue/active', [QueueController::class, 'active']);
    Route::get('/queue/estimate', [QueueController::class, 'estimate']);
});

// 4) Admin only
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Menus
    Route::get('/menus', [AdminMenuController::class, 'index']);
    Route::get('/menus/{id}', [AdminMenuController::class, 'show']);
    Route::post('/menus', [AdminMenuController::class, 'store']);
    Route::post('/menus/{id}', [AdminMenuController::class, 'update']); // multipart-friendly
    Route::put('/menus/{id}', [AdminMenuController::class, 'update']);
    Route::patch('/menus/{id}/toggle', [AdminMenuController::class, 'toggle']);
    Route::delete('/menus/{id}', [AdminMenuController::class, 'destroy']);
    Route::get('/menus/{id}/bom', [AdminMenuController::class, 'getBom']);
    Route::put('/menus/{id}/bom', [AdminMenuController::class, 'updateBom']);
    Route::post('/menus/{menu_id}/condiment-groups/{group_id}/attach', [AdminMenuController::class, 'attachCondimentGroup']);
    Route::delete('/menus/{menu_id}/condiment-groups/{group_id}/detach', [AdminMenuController::class, 'detachCondimentGroup']);

    // Condiments
    Route::get('/condiment-groups', [CondimentController::class, 'indexGroups']);
    Route::post('/condiment-groups', [CondimentController::class, 'storeGroup']);
    Route::put('/condiment-groups/{id}', [CondimentController::class, 'updateGroup']);
    Route::delete('/condiment-groups/{id}', [CondimentController::class, 'destroyGroup']);
    Route::post('/condiment-groups/{id}/options', [CondimentController::class, 'storeOption']);
    Route::put('/condiment-options/{id}', [CondimentController::class, 'updateOption']);
    Route::delete('/condiment-options/{id}', [CondimentController::class, 'destroyOption']);

    // Ingredients
    Route::get('/ingredients', [IngredientController::class, 'index']);
    Route::post('/ingredients', [IngredientController::class, 'store']);
    Route::put('/ingredients/{id}', [IngredientController::class, 'update']);
    Route::patch('/ingredients/{id}/restock', [IngredientController::class, 'restock']);
    Route::delete('/ingredients/{id}', [IngredientController::class, 'destroy']);

    // Discounts
    Route::get('/discounts/product', [DiscountController::class, 'indexProduct']);
    Route::post('/discounts/product', [DiscountController::class, 'storeProduct']);
    Route::put('/discounts/product/{id}', [DiscountController::class, 'updateProduct']);
    Route::delete('/discounts/product/{id}', [DiscountController::class, 'destroyProduct']);

    Route::get('/discounts/promo-codes', [DiscountController::class, 'indexPromo']);
    Route::post('/discounts/promo-codes', [DiscountController::class, 'storePromo']);
    Route::put('/discounts/promo-codes/{id}', [DiscountController::class, 'updatePromo']);
    Route::delete('/discounts/promo-codes/{id}', [DiscountController::class, 'destroyPromo']);

    Route::get('/discounts/events', [DiscountController::class, 'indexEvent']);
    Route::post('/discounts/events', [DiscountController::class, 'storeEvent']);
    Route::put('/discounts/events/{id}', [DiscountController::class, 'updateEvent']);
    Route::delete('/discounts/events/{id}', [DiscountController::class, 'destroyEvent']);

    // Analytics
    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/weekly-sales', [AnalyticsController::class, 'weeklySales']);
    Route::get('/analytics/top-menus', [AnalyticsController::class, 'topMenus']);
    Route::get('/analytics/peak-hours', [AnalyticsController::class, 'peakHours']);
    Route::get('/analytics/report', [AnalyticsController::class, 'report']);

    // Staff
    Route::get('/staff', [StaffManagementController::class, 'index']);
    Route::post('/staff', [StaffManagementController::class, 'store']);
    Route::put('/staff/{id}', [StaffManagementController::class, 'update']);
    Route::patch('/staff/{id}/toggle', [StaffManagementController::class, 'toggle']);
});
