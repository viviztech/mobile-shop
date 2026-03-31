<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public welcome page (redirect to login if not authenticated)
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('welcome');

// Protected Routes - require authentication
Route::middleware(['auth', 'verified'])->group(function () {
    // Main Dashboard (Analytics)
    Volt::route('/dashboard', 'dashboard')
        ->name('dashboard');

    // Sales Entry (POS)
    Volt::route('/sales', 'sales-manager')
        ->name('sales');

    // Alias routes for navigation
    Route::redirect('/home', '/dashboard')->name('home');

    // Reports Page
    Volt::route('/reports', 'reports')
        ->name('reports');

    // Settings Page
    Volt::route('/settings', 'settings')
        ->name('settings');

    // Product Catalog
    Volt::route('/products', 'products')
        ->name('products');
});

// Profile (from Breeze)
Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__ . '/auth.php';
