<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Cash\CashController;
use App\Http\Controllers\Cash\PaymentSettingsController;
use App\Http\Controllers\Catalog\ServiceItemController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerLookupController;
use App\Http\Controllers\Customers\VehicleController;
use App\Http\Controllers\Customers\VehicleLookupController;
use App\Http\Controllers\Customers\VehicleOwnerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\PartController;
use App\Http\Controllers\Inventory\PartLookupController;
use App\Http\Controllers\Inventory\PartStockController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Invoicing\InvoiceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Mechanics\MechanicController;
use App\Http\Controllers\Mechanics\MechanicKtpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchasing\PurchaseController;
use App\Http\Controllers\Purchasing\SupplierDebtController;
use App\Http\Controllers\Purchasing\SupplierPaymentController;
use App\Http\Controllers\Settings\InvoiceSettingsController;
use App\Http\Controllers\WorkOrders\WorkOrderController;
use App\Http\Controllers\WorkOrders\WorkOrderMechanicController;
use App\Http\Controllers\WorkOrders\WorkOrderPartController;
use App\Http\Controllers\WorkOrders\WorkOrderServiceLineController;
use App\Http\Controllers\WorkOrders\WorkOrderStatusController;
use App\Http\Controllers\WorkOrders\WorkOrderTaskController;
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

    Route::get('/vehicles/lookup', VehicleLookupController::class)->middleware('throttle:120,1')->name('vehicles.lookup');
    Route::get('/customers/{customer}/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
    Route::post('/customers/{customer}/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::resource('vehicles', VehicleController::class)->only(['show', 'edit', 'update', 'destroy']);
    Route::put('/vehicles/{vehicle}/owner', [VehicleOwnerController::class, 'update'])->name('vehicles.owner.update');

    // Work orders ("SPK") of the active branch. Lines are bound through their work order
    // (scoped bindings), so a line of another branch's work order is a 404.
    Route::resource('work-orders', WorkOrderController::class)->only(['index', 'create', 'store', 'show', 'update']);
    Route::get('/work-orders/{work_order}/print', [WorkOrderController::class, 'print'])->name('work-orders.print');
    Route::put('/work-orders/{work_order}/status', [WorkOrderStatusController::class, 'update'])->name('work-orders.status');
    Route::post('/work-orders/{work_order}/cancel', [WorkOrderStatusController::class, 'cancel'])->name('work-orders.cancel');
    Route::put('/work-orders/{work_order}/mechanics', [WorkOrderMechanicController::class, 'update'])->name('work-orders.mechanics');
    Route::scopeBindings()->prefix('work-orders/{work_order}')->name('work-orders.')->group(function () {
        Route::post('/tasks', [WorkOrderTaskController::class, 'store'])->name('tasks.store');
        Route::put('/tasks/{task}', [WorkOrderTaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [WorkOrderTaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/services', [WorkOrderServiceLineController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [WorkOrderServiceLineController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [WorkOrderServiceLineController::class, 'destroy'])->name('services.destroy');
        Route::post('/parts', [WorkOrderPartController::class, 'store'])->name('parts.store');
        Route::put('/parts/{part}', [WorkOrderPartController::class, 'update'])->name('parts.update');
        Route::delete('/parts/{part}', [WorkOrderPartController::class, 'destroy'])->name('parts.destroy');
    });

    // Invoices of the active branch: from a finished work order or a walk-in sale
    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

    // Service catalog (tenant-wide price list of jobs)
    Route::resource('service-items', ServiceItemController::class)->only(['index', 'store', 'update', 'destroy']);

    // Mechanics of the active branch (no login)
    Route::resource('mechanics', MechanicController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('/mechanics/{mechanic}/ktp', MechanicKtpController::class)->name('mechanics.ktp');

    // Inventory: tenant-wide parts catalog and suppliers, stock per active branch
    Route::get('/parts/lookup', PartLookupController::class)->middleware('throttle:120,1')->name('parts.lookup');
    Route::resource('parts', PartController::class);
    Route::post('/parts/{part}/stock/adjust', [PartStockController::class, 'adjust'])->name('parts.stock.adjust');
    Route::put('/parts/{part}/stock', [PartStockController::class, 'configure'])->name('parts.stock.configure');
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);

    // Purchases from suppliers and supplier debt ("Hutang Supplier") of the active branch
    Route::get('/supplier-debts', SupplierDebtController::class)->name('supplier-debts.index');
    Route::resource('purchases', PurchaseController::class)->except(['destroy']);
    Route::post('/purchases/{purchase}/void', [PurchaseController::class, 'void'])->name('purchases.void');
    Route::post('/purchases/{purchase}/payments', [SupplierPaymentController::class, 'store'])->name('purchases.payments.store');
    Route::post('/supplier-payments/{supplier_payment}/void', [SupplierPaymentController::class, 'void'])->name('supplier-payments.void');

    // Cash ("Kas") of the active branch
    Route::get('/cash', [CashController::class, 'index'])->name('cash.index');
    Route::post('/cash/transfer', [CashController::class, 'transfer'])->name('cash.transfer');
    Route::get('/cash/{cash_account}', [CashController::class, 'show'])->name('cash.show');
    Route::post('/cash/{cash_account}/count', [CashController::class, 'count'])->name('cash.count');

    // Branch settings: tax on invoices and the invoice footer
    Route::get('/settings/invoice', [InvoiceSettingsController::class, 'edit'])->name('settings.invoice.edit');
    Route::put('/settings/invoice', [InvoiceSettingsController::class, 'update'])->name('settings.invoice.update');

    // Branch settings: cash accounts and payment methods
    Route::prefix('settings/payments')->name('settings.payments.')->controller(PaymentSettingsController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/accounts', 'storeAccount')->name('accounts.store');
        Route::put('/accounts/{cash_account}', 'updateAccount')->name('accounts.update');
        Route::delete('/accounts/{cash_account}', 'destroyAccount')->name('accounts.destroy');
        Route::put('/cash-purchase-limit', 'updateCashPurchaseLimit')->name('cash-purchase-limit.update');
        Route::post('/methods', 'storeMethod')->name('methods.store');
        Route::put('/methods/{payment_method}', 'updateMethod')->name('methods.update');
        Route::delete('/methods/{payment_method}', 'destroyMethod')->name('methods.destroy');
    });

    // The user's own notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/auth.php';
