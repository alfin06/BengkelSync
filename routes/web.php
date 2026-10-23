<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Auth & Profile
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\GoogleController;

// Admin & Dashboard
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReportController;

// Core Operations
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderItemController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\StockEntryController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\SupplierController;

/*
|--------------------------------------------------------------------------
| 1. Public & Guest Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/invoices/{invoice:uuid}', [InvoiceController::class, 'showPublic'])->name('invoices.show');

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.login');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
});

/*
|--------------------------------------------------------------------------
| 2. Strict Admin Routes (Only Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/settings', [SettingController::class, 'edit'])->name('admin.settings.edit');
    Route::post('/admin/settings', [SettingController::class, 'update'])->name('admin.settings.update');
    Route::resource('/admin/users', UserController::class)->names('admin.users');
    Route::get('/admin/reports', [ReportController::class, 'index'])->name('admin.reports.index');
});

/*
|--------------------------------------------------------------------------
| 3. Employee Routes (Admin, Finance, Staff)
|--------------------------------------------------------------------------
| Note: The 'staff' middleware handles this array of roles.
*/
Route::middleware(['auth', 'verified', 'staff'])->group(function () {
    
    // Dashboards & Monitoring
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/monitoring', [WorkOrderController::class, 'adminIndex'])->name('admin.work-orders.monitor');
    
    // Data Deletion & Modification (Protected by $user->canManageData() in Controllers)
    Route::delete('/work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');
    Route::put('/parts/{part}', [PartController::class, 'update'])->name('parts.update');
    Route::delete('/parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');
    Route::post('/invoices/{invoice:id}/reopen', [InvoiceController::class, 'reopen'])->name('invoices.reopen');

});

/*
|--------------------------------------------------------------------------
| 4. General Authenticated Routes (Core Shop Operations)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Customers & Vehicles
    Route::resource('customers', CustomerController::class)->except(['create', 'edit', 'destroy']);
    Route::resource('vehicles', VehicleController::class)->only(['show', 'update', 'destroy']);
    Route::post('/customers/{customer}/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');

    // Work Orders
    Route::prefix('work-orders')->name('work-orders.')->group(function () {
        Route::get('/quick-checkin', fn() => inertia('WorkOrders/UnifiedCreate'))->name('quick-checkin');
        Route::get('/check-in', [WorkOrderController::class, 'create'])->name('create');
        Route::post('/unified', [WorkOrderController::class, 'storeUnified'])->name('store-unified');
        Route::post('/', [WorkOrderController::class, 'store'])->name('store');
        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->name('show');
        Route::patch('/{workOrder}', [WorkOrderController::class, 'update'])->name('update');
        Route::patch('/{workOrder}/start', [WorkOrderController::class, 'startWork'])->name('start');
        
        // Items & Parts linking
        Route::post('/{workOrder}/items', [WorkOrderItemController::class, 'store'])->name('items.store');
        Route::post('/{workOrder}/parts', [WorkOrderController::class, 'addPart'])->name('parts.store');
        Route::get('/{workOrder}/invoice', [InvoiceController::class, 'create'])->name('invoices.create'); // Bill prep
    });

    // Work Order Items Management
    Route::prefix('work-order-items')->name('work-order-items.')->group(function () {
        Route::patch('/{item}', [WorkOrderItemController::class, 'update'])->name('update'); // Toggle check
        Route::patch('/{item}/update', [WorkOrderItemController::class, 'updateTask'])->name('update-task');
        Route::delete('/{item}', [WorkOrderItemController::class, 'destroy'])->name('destroy');
    });

    // Work Order Parts Management
    Route::patch('/work-order-parts/{workOrderPart}/qty', [WorkOrderController::class, 'updatePartQty'])->name('work-orders.parts.update-qty');
    Route::delete('/work-order-parts/{id}', [WorkOrderController::class, 'removePart'])->name('work-orders.remove-part');

    // Invoices & Receivables
    Route::get('/receivables', [InvoiceController::class, 'receivables'])->name('receivables.index');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::put('/invoices/{invoice}/settle', [InvoiceController::class, 'settle'])->name('invoices.settle');

    // Inventory & Parts
    Route::prefix('parts')->name('parts.')->group(function () {
        Route::get('/', [PartController::class, 'index'])->name('index');
        Route::post('/', [PartController::class, 'store'])->name('store');
        Route::post('/quick-add', [PartController::class, 'quickStore'])->name('quick-add');
        Route::get('/restock', [PartController::class, 'restockPage'])->name('restock.page');
        Route::post('/{part}/restock', [PartController::class, 'restock'])->name('restock');
        Route::get('/search', [PartController::class, 'search'])->name('search');
        Route::get('/{part}', [PartController::class, 'show'])->name('show');
    });

    // Stock Entries, Mechanics, Suppliers
    Route::resource('stock-entries', StockEntryController::class);
    Route::resource('mechanics', MechanicController::class);
    Route::resource('suppliers', SupplierController::class);

    // Expenses (Buku Pengeluaran)
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'destroy']);

    // API Search Endpoints
    Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
    Route::get('/api/vehicles/search', function (Request $request) {
        $plate = strtoupper($request->query('plate'));
        return response()->json(
            \App\Models\Vehicle::with('customer')->where('plate_number', $plate)->first()
        );
    })->name('api.vehicles.search');

});

require __DIR__.'/auth.php';