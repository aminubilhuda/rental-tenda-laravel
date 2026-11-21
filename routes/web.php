<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampRentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::put('/password', [AuthController::class, 'password'])->name('password.update');
    Route::get('/dashboard', [CampRentController::class, 'page'])->defaults('section', 'dashboard')->name('dashboard');
    Route::get('/pos', [CampRentController::class, 'page'])->defaults('section', 'pos')->name('pos');
    Route::post('/pos/checkout', [CampRentController::class, 'checkout'])->name('pos.checkout');
    Route::get('/api/customers/search', [CampRentController::class, 'searchCustomers'])->name('customers.search');
    Route::get('/{section}', [CampRentController::class, 'page'])->whereIn('section', ['items', 'categories', 'customers', 'rentals', 'damages', 'reports', 'activity', 'settings', 'users'])->name('page');
    Route::post('/{section}', [CampRentController::class, 'save'])->whereIn('section', ['items', 'categories', 'customers', 'settings', 'users', 'stock', 'damages'])->name('save');
    Route::put('/{section}/{id}', [CampRentController::class, 'update'])->whereIn('section', ['items', 'categories', 'customers', 'users'])->whereNumber('id')->name('update');
    Route::post('/rentals/{rental}/return', [CampRentController::class, 'returnRental'])->name('rentals.return');
    Route::get('/rentals/{rental}', [CampRentController::class, 'showRental'])->name('rentals.show');
    Route::get('/rentals/{rental}/customer-photo', [CampRentController::class, 'customerPhoto'])->name('rentals.customer-photo');
    Route::post('/rentals/{rental}/extend', [CampRentController::class, 'extendRental'])->name('rentals.extend');
    Route::post('/rentals/{rental}/cancel', [CampRentController::class, 'cancelRental'])->name('rentals.cancel');
    Route::post('/damages/{damage}/payments', [CampRentController::class, 'damagePayment'])->name('damages.payments');
    Route::post('/damages/{damage}/resolution', [CampRentController::class, 'resolveDamage'])->name('damages.resolution');
    Route::get('/damages/{damage}/receipt.pdf', [CampRentController::class, 'damageReceiptPdf'])->name('damages.receipt.pdf');
    Route::get('/rentals/{rental}/receipt', [CampRentController::class, 'receipt'])->name('rentals.receipt');
    Route::get('/rentals/{rental}/receipt.pdf', [CampRentController::class, 'receiptPdf'])->name('rentals.receipt.pdf');
    Route::get('/customers/{customer}', [CampRentController::class, 'showCustomer'])->name('customers.show');
    Route::get('/export/{section}', [CampRentController::class, 'export'])->name('export');
    Route::delete('/{section}/{id}', [CampRentController::class, 'destroy'])->whereIn('section', ['items', 'categories', 'customers', 'rentals', 'damages'])->whereNumber('id')->name('destroy');
});
