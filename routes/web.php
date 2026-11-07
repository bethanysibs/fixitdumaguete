<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\WorkerProfileController;
use App\Http\Controllers\WorkerRatingController;
use App\Http\Controllers\WorkerShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/services/{serviceCategory}', [ServiceCategoryController::class, 'show'])->name('services.show');
Route::get('/workers', [WorkerShopController::class, 'index'])->name('workers.index');
Route::get('/workers/{workerProfile}', [WorkerProfileController::class, 'show'])->name('workers.show');

Route::middleware('auth')->group(function () {
    Route::put('/workers/{workerProfile}/rating', [WorkerRatingController::class, 'update'])->name('workers.rating.update');
    Route::put('/workers/{workerProfile}/ratings/{rating}', [WorkerRatingController::class, 'revise'])->name('workers.rating.revise');
    Route::delete('/workers/{workerProfile}/ratings/{rating}', [WorkerRatingController::class, 'destroy'])->name('workers.rating.destroy');
});
