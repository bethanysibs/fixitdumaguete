<?php

use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CommissionController as AdminCommissionController;
use App\Http\Controllers\Admin\QuotationController as AdminQuotationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapLookupController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\WorkerJobController;
use App\Http\Controllers\WorkerProfileController;
use App\Http\Controllers\WorkerProfileEditorController;
use App\Http\Controllers\WorkerQuotationController;
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

Route::middleware(['auth', 'admin'])->group(function () {
    Route::redirect('/admin', '/admin/users')->name('admin.home');
    Route::resource('admin/users', AdminUserController::class)->names('admin.users');
    Route::get('/admin/bookings', [AdminBookingController::class, 'index'])->name('admin.bookings.index');
    Route::put('/admin/bookings/{booking}', [AdminBookingController::class, 'update'])->name('admin.bookings.update');
    Route::get('/admin/bookings/{booking}/quotation', [AdminQuotationController::class, 'show'])->name('admin.quotations.show');
    Route::put('/admin/bookings/{booking}/quotation', [AdminQuotationController::class, 'update'])->name('admin.quotations.update');
    Route::get('/admin/bookings/{booking}/quotation/approve', [AdminQuotationController::class, 'confirm'])->name('admin.quotations.confirm');
    Route::post('/admin/bookings/{booking}/quotation/approve', [AdminQuotationController::class, 'approve'])->name('admin.quotations.approve');
    Route::post('/admin/bookings/{booking}/quotation/reject', [AdminQuotationController::class, 'reject'])->name('admin.quotations.reject');
    Route::get('/admin/commission', [AdminCommissionController::class, 'index'])->name('admin.commission.index');
    Route::post('/admin/commission', [AdminCommissionController::class, 'store'])->name('admin.commission.store');
    Route::get('/admin/users/{user}/profile', [WorkerProfileEditorController::class, 'create'])->name('admin.workers.create');
    Route::post('/admin/users/{user}/profile', [WorkerProfileEditorController::class, 'store'])->name('admin.workers.store');
    Route::get('/admin/workers/{workerProfile}/edit', [WorkerProfileEditorController::class, 'edit'])->name('admin.workers.edit');
    Route::put('/admin/workers/{workerProfile}', [WorkerProfileEditorController::class, 'update'])->name('admin.workers.update');
    Route::post('/admin/workers/{workerProfile}/photo', [WorkerProfileEditorController::class, 'updatePhoto'])->name('admin.workers.photo.update');
    Route::delete('/admin/workers/{workerProfile}/photo', [WorkerProfileEditorController::class, 'destroyPhoto'])->name('admin.workers.photo.destroy');
    Route::post('/admin/workers/{workerProfile}/skills', [WorkerProfileEditorController::class, 'storeSkill'])->name('admin.workers.skills.store');
    Route::delete('/admin/workers/{workerProfile}/skills/{skill}', [WorkerProfileEditorController::class, 'destroySkill'])->name('admin.workers.skills.destroy');
    Route::post('/admin/workers/{workerProfile}/projects', [WorkerProfileEditorController::class, 'storeProject'])->name('admin.workers.projects.store');
    Route::delete('/admin/workers/{workerProfile}/projects/{project}', [WorkerProfileEditorController::class, 'destroyProject'])->name('admin.workers.projects.destroy');
    Route::post('/admin/workers/{workerProfile}/portfolio', [WorkerProfileEditorController::class, 'storeImage'])->name('admin.workers.portfolio.store');
    Route::delete('/admin/workers/{workerProfile}/portfolio/{image}', [WorkerProfileEditorController::class, 'destroyImage'])->name('admin.workers.portfolio.destroy');
    Route::post('/admin/workers/{workerProfile}/services', [WorkerProfileEditorController::class, 'storeService'])->name('admin.workers.services.store');
    Route::delete('/admin/workers/{workerProfile}/services/{service}', [WorkerProfileEditorController::class, 'destroyService'])->name('admin.workers.services.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/maps/geocode', [MapLookupController::class, 'geocode'])->middleware('throttle:30,1')->name('maps.geocode');
    Route::get('/maps/reverse', [MapLookupController::class, 'reverse'])->middleware('throttle:30,1')->name('maps.reverse');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/bookings/{booking}/photos/{photo}', [BookingController::class, 'photo'])->name('bookings.photos.show');
    Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay'])->name('bookings.pay');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/workers/{workerProfile}/book', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/workers/{workerProfile}/book', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/jobs', [WorkerJobController::class, 'index'])->name('jobs.index');
    Route::post('/jobs/{booking}/quotation', [WorkerQuotationController::class, 'store'])->name('jobs.quotation.store');
    Route::put('/jobs/{booking}/quotation', [WorkerQuotationController::class, 'update'])->name('jobs.quotation.update');
    Route::get('/jobs/{booking}/quotation/submit', [WorkerQuotationController::class, 'confirm'])->name('jobs.quotation.confirm');
    Route::post('/jobs/{booking}/quotation/submit', [WorkerQuotationController::class, 'submit'])->name('jobs.quotation.submit');
    Route::post('/jobs/{booking}/quotation/revise', [WorkerQuotationController::class, 'revise'])->name('jobs.quotation.revise');
    Route::get('/my-profile', [WorkerProfileEditorController::class, 'edit'])->name('work.edit');
    Route::post('/my-profile', [WorkerProfileEditorController::class, 'store'])->name('work.store');
    Route::put('/my-profile', [WorkerProfileEditorController::class, 'update'])->name('work.update');
    Route::post('/my-profile/photo', [WorkerProfileEditorController::class, 'updatePhoto'])->name('work.photo.update');
    Route::delete('/my-profile/photo', [WorkerProfileEditorController::class, 'destroyPhoto'])->name('work.photo.destroy');
    Route::post('/my-profile/skills', [WorkerProfileEditorController::class, 'storeSkill'])->name('work.skills.store');
    Route::delete('/my-profile/skills/{skill}', [WorkerProfileEditorController::class, 'destroySkill'])->name('work.skills.destroy');
    Route::post('/my-profile/projects', [WorkerProfileEditorController::class, 'storeProject'])->name('work.projects.store');
    Route::delete('/my-profile/projects/{project}', [WorkerProfileEditorController::class, 'destroyProject'])->name('work.projects.destroy');
    Route::post('/my-profile/portfolio', [WorkerProfileEditorController::class, 'storeImage'])->name('work.portfolio.store');
    Route::delete('/my-profile/portfolio/{image}', [WorkerProfileEditorController::class, 'destroyImage'])->name('work.portfolio.destroy');
    Route::post('/my-profile/services', [WorkerProfileEditorController::class, 'storeService'])->name('work.services.store');
    Route::delete('/my-profile/services/{service}', [WorkerProfileEditorController::class, 'destroyService'])->name('work.services.destroy');
    Route::put('/workers/{workerProfile}/rating', [WorkerRatingController::class, 'update'])->name('workers.rating.update');
    Route::put('/workers/{workerProfile}/ratings/{rating}', [WorkerRatingController::class, 'revise'])->name('workers.rating.revise');
    Route::delete('/workers/{workerProfile}/ratings/{rating}', [WorkerRatingController::class, 'destroy'])->name('workers.rating.destroy');
});
