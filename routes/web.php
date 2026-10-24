<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Catalog\ServiceItemController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerLookupController;
use App\Http\Controllers\Customers\VehicleController;
use App\Http\Controllers\Customers\VehicleOwnerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\PartController;
use App\Http\Controllers\Inventory\PartStockController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Mechanics\MechanicController;
use App\Http\Controllers\Mechanics\MechanicKtpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::post('/locale', [LocaleController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('locale.update');

// Everything below runs inside a tenant and an active branch (CLAUDE.md rules 1 and 2).
Route::middleware(['auth', 'tenancy'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/branch', [ActiveBranchController::class, 'update'])->name('branch.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Customers and vehicles (shared by all branches of the tenant)
    Route::get('/customers/lookup/phone', [CustomerLookupController::class, 'phone'])->name('customers.lookup.phone');
    Route::get('/customers/lookup/search', [CustomerLookupController::class, 'search'])->name('customers.lookup.search');
    Route::resource('customers', CustomerController::class);

    Route::get('/customers/{customer}/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
    Route::post('/customers/{customer}/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::resource('vehicles', VehicleController::class)->only(['show', 'edit', 'update', 'destroy']);
    Route::put('/vehicles/{vehicle}/owner', [VehicleOwnerController::class, 'update'])->name('vehicles.owner.update');

    // Service catalog (tenant-wide price list of jobs)
    Route::resource('service-items', ServiceItemController::class)->only(['index', 'store', 'update', 'destroy']);

    // Mechanics of the active branch (no login)
    Route::resource('mechanics', MechanicController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('/mechanics/{mechanic}/ktp', MechanicKtpController::class)->name('mechanics.ktp');

    // Inventory: tenant-wide parts catalog and suppliers, stock per active branch
    Route::resource('parts', PartController::class);
    Route::post('/parts/{part}/stock/adjust', [PartStockController::class, 'adjust'])->name('parts.stock.adjust');
    Route::put('/parts/{part}/stock', [PartStockController::class, 'configure'])->name('parts.stock.configure');
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);

    // The user's own notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/auth.php';
