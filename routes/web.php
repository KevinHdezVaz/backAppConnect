<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResetController;
use App\Http\Controllers\InfoUserController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SessionsController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\FieldManagementController;

// Rutas públicas/guest
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [SessionsController::class, 'create'])->name('login');
    Route::post('/login', [SessionsController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create']);
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login/forgot-password', [ResetController::class, 'create']);
    Route::post('/forgot-password', [ResetController::class, 'sendEmail']);
    Route::get('/reset-password/{token}', [ResetController::class, 'resetPass'])->name('password.reset');
    Route::post('/reset-password', [ChangePasswordController::class, 'changePassword'])->name('password.update');
});

// Rutas protegidas para admin
Route::middleware(['auth:admin'])->group(function () {
    Route::get('/', [HomeController::class, 'home']);
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    Route::get('/billing', function () {
        return view('billing');
    })->name('billing');
    
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');
    
    Route::get('/rtl', function () {
        return view('rtl');
    })->name('rtl');
    
    Route::get('/tables', function () {
        return view('tables');
    })->name('tables');
    
    Route::get('/virtual-reality', function () {
        return view('virtual-reality');
    })->name('virtual-reality');


    Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management');
Route::get('/user-management/{id}/edit', [UserManagementController::class, 'edit'])->name('user.edit');
Route::put('/user-management/{id}', [UserManagementController::class, 'update'])->name('user.update');
Route::delete('/user-management/{id}', [UserManagementController::class, 'destroy'])->name('user.destroy');


// Primero las rutas específicas
Route::get('/field-management', [FieldManagementController::class, 'index'])->name('field-management');
Route::get('/field-management/create', [FieldManagementController::class, 'create'])->name('field.create');
Route::post('/field-management', [FieldManagementController::class, 'store'])->name('field.store');

// Después las rutas con parámetros
Route::get('/field-management/{id}/edit', [FieldManagementController::class, 'edit'])->name('field.edit');
Route::put('/field-management/{id}', [FieldManagementController::class, 'update'])->name('field.update');
Route::delete('/field-management/{id}', [FieldManagementController::class, 'destroy'])->name('field.destroy');


    Route::get('/logout', [SessionsController::class, 'destroy']);
    Route::get('/user-profile', [InfoUserController::class, 'create']);
    Route::post('/user-profile', [InfoUserController::class, 'store']);
});