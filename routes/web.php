<?php

use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\CondimentController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\DiscountController;
use App\Http\Controllers\Web\Admin\IngredientController;
use App\Http\Controllers\Web\Admin\MenuController;
use App\Http\Controllers\Web\Admin\OrderMonitorController;
use App\Http\Controllers\Web\Admin\ReportController;
use App\Http\Controllers\Web\Admin\StaffController;
use App\Http\Controllers\Web\Admin\StockOpnameController;
use App\Http\Controllers\Web\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'));

// ----- Auth (admin) -----
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('admin.login');
    Route::post('/login', [LoginController::class, 'login'])->name('admin.login.attempt');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('admin.logout');

// ----- Admin dashboard -----
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Menus
    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
    Route::get('/menus/create', [MenuController::class, 'create'])->name('menus.create');
    Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
    Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])->name('menus.edit');
    Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
    Route::patch('/menus/{menu}/toggle', [MenuController::class, 'toggle'])->name('menus.toggle');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    Route::get('/menus/{menu}/bom', [MenuController::class, 'bomEdit'])->name('menus.bom.edit');
    Route::put('/menus/{menu}/bom', [MenuController::class, 'bomUpdate'])->name('menus.bom.update');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Condiments
    Route::get('/condiments', [CondimentController::class, 'index'])->name('condiments.index');
    Route::post('/condiments/groups', [CondimentController::class, 'storeGroup'])->name('condiments.groups.store');
    Route::delete('/condiments/groups/{group}', [CondimentController::class, 'destroyGroup'])->name('condiments.groups.destroy');
    Route::post('/condiments/groups/{group}/options', [CondimentController::class, 'storeOption'])->name('condiments.groups.options.store');
    Route::post('/condiments/groups/{group}/options/reorder', [CondimentController::class, 'reorderOptions'])->name('condiments.groups.options.reorder');
    Route::put('/condiments/options/{option}', [CondimentController::class, 'updateOption'])->name('condiments.options.update');
    Route::delete('/condiments/options/{option}', [CondimentController::class, 'destroyOption'])->name('condiments.options.destroy');

    // Ingredients
    Route::get('/ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
    Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
    Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
    Route::patch('/ingredients/{ingredient}/restock', [IngredientController::class, 'restock'])->name('ingredients.restock');
    Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy');

    // Discounts
    Route::get('/discounts', [DiscountController::class, 'index'])->name('discounts.index');
    Route::post('/discounts/product', [DiscountController::class, 'storeProduct'])->name('discounts.product.store');
    Route::delete('/discounts/product/{discount}', [DiscountController::class, 'destroyProduct'])->name('discounts.product.destroy');
    Route::post('/discounts/promo', [DiscountController::class, 'storePromo'])->name('discounts.promo.store');
    Route::delete('/discounts/promo/{promo}', [DiscountController::class, 'destroyPromo'])->name('discounts.promo.destroy');
    Route::post('/discounts/event', [DiscountController::class, 'storeEvent'])->name('discounts.event.store');
    Route::delete('/discounts/event/{event}', [DiscountController::class, 'destroyEvent'])->name('discounts.event.destroy');

    // Orders (read-only)
    Route::get('/orders', [OrderMonitorController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderMonitorController::class, 'show'])->name('orders.show');

    // Staff
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::patch('/staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');

    // Stock Opname
    Route::get('/opnames', [StockOpnameController::class, 'index'])->name('opnames.index');
    Route::get('/opnames/create', [StockOpnameController::class, 'create'])->name('opnames.create');
    Route::post('/opnames', [StockOpnameController::class, 'store'])->name('opnames.store');
    Route::get('/opnames/{opname}', [StockOpnameController::class, 'show'])->name('opnames.show');
    Route::post('/opnames/{opname}/approve', [StockOpnameController::class, 'approve'])->name('opnames.approve');
    Route::post('/opnames/{opname}/reject', [StockOpnameController::class, 'reject'])->name('opnames.reject');

    // Report
    Route::get('/report', [ReportController::class, 'index'])->name('report.index');
});

// API root status (so visiting / when not logged-in shows admin login)
Route::get('/api-status', function () {
    return response()->json([
        'name' => 'Cofflow API',
        'status' => 'ok',
        'docs' => 'See /api/* endpoints — use POST /api/auth/login to obtain a Sanctum bearer token.',
    ]);
});
