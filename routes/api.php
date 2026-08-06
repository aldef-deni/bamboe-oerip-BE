<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\VillaController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\VillaController as AdminVilla;
use App\Http\Controllers\Admin\BookingController as AdminBooking;
use App\Http\Controllers\Admin\PaymentController as AdminPayment;
use App\Http\Controllers\Admin\AmenityController as AdminAmenity;
use App\Http\Controllers\Admin\PromoController as AdminPromo;
use App\Http\Controllers\Admin\SettingController as AdminSetting;
use App\Http\Controllers\Admin\UserController as AdminUser;
use App\Http\Controllers\Admin\ReportController as AdminReport;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\LoyaltyController as AdminLoyalty;

/*
|--------------------------------------------------------------------------
| API Routes — Villa Box OTA
|--------------------------------------------------------------------------
*/

// ---- Public ----
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/admin/login', [AuthController::class, 'adminLogin']);

Route::get('/settings', [SettingController::class, 'index']);
Route::get('/payment/bank-info', [PaymentController::class, 'bankInfo']);

Route::get('/promos', [PromoController::class, 'index']);
Route::get('/promos/popup', [PromoController::class, 'popup']);

Route::get('/loyalty/tiers', [LoyaltyController::class, 'tiers']);

Route::get('/villas', [VillaController::class, 'index']);
Route::get('/villas/cities', [VillaController::class, 'cities']);
Route::get('/villas/map', [VillaController::class, 'mapPoints']);
Route::get('/amenities', [VillaController::class, 'amenities']);
Route::get('/villas/{villa:slug}', [VillaController::class, 'show']);
Route::post('/villas/{villa}/availability', [VillaController::class, 'availability']);
Route::get('/villas/{villa}/reviews', [ReviewController::class, 'index']);

// ---- Authenticated (user) ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/payment', [PaymentController::class, 'submitProof']);

    Route::post('/villas/{villa}/reviews', [ReviewController::class, 'store']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/villas/{villa}/wishlist', [WishlistController::class, 'toggle']);

    Route::post('/promos/validate', [PromoController::class, 'validateCode']);

    Route::get('/loyalty/balance', [LoyaltyController::class, 'balance']);
    Route::get('/loyalty/history', [LoyaltyController::class, 'history']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});

// ---- Admin ----
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'adminIndex']);
    Route::post('/notifications/read-all', [NotificationController::class, 'adminMarkAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'adminMarkRead']);

    Route::get('/villas', [AdminVilla::class, 'index']);
    Route::post('/villas', [AdminVilla::class, 'store']);
    Route::get('/villas/{villa}', [AdminVilla::class, 'show']);
    Route::post('/villas/{villa}', [AdminVilla::class, 'update']); // POST + _method=PUT for multipart
    Route::put('/villas/{villa}', [AdminVilla::class, 'update']);
    Route::delete('/villas/{villa}', [AdminVilla::class, 'destroy']);
    Route::delete('/villas/{villa}/images/{image}', [AdminVilla::class, 'deleteImage']);
    Route::post('/villas/{villa}/images/{image}/primary', [AdminVilla::class, 'setPrimaryImage']);

    Route::get('/bookings', [AdminBooking::class, 'index']);
    Route::post('/bookings/bulk-delete', [AdminBooking::class, 'bulkDestroy']);
    Route::get('/bookings/{booking}', [AdminBooking::class, 'show']);
    Route::put('/bookings/{booking}/status', [AdminBooking::class, 'updateStatus']);
    Route::delete('/bookings/{booking}', [AdminBooking::class, 'destroy']);

    Route::get('/payments', [AdminPayment::class, 'index']);
    Route::post('/payments/bulk-delete', [AdminPayment::class, 'bulkDestroy']);
    Route::post('/payments/{payment}/verify', [AdminPayment::class, 'verify']);
    Route::post('/payments/{payment}/reject', [AdminPayment::class, 'reject']);

    Route::apiResource('amenities', AdminAmenity::class)->except(['show']);
    Route::post('/promos/reorder', [AdminPromo::class, 'reorder']);
    Route::apiResource('promos', AdminPromo::class)->except(['show']);

    Route::get('/users', [AdminUser::class, 'index']);
    Route::post('/users/bulk-delete', [AdminUser::class, 'bulkDestroy']);
    Route::get('/reports', [AdminReport::class, 'index']);

    Route::get('/settings', [AdminSetting::class, 'index']);
    Route::put('/settings', [AdminSetting::class, 'update']);

    Route::get('/loyalty', [AdminLoyalty::class, 'index']);
    Route::get('/loyalty/stats', [AdminLoyalty::class, 'stats']);
    Route::get('/loyalty/tiers', [AdminLoyalty::class, 'getTiers']);
    Route::put('/loyalty/tiers', [AdminLoyalty::class, 'saveTiers']);
    Route::get('/loyalty/user/{user}', [AdminLoyalty::class, 'userInfo']);
    Route::post('/loyalty/adjust/{user}', [AdminLoyalty::class, 'adjust']);
    Route::post('/loyalty/tier/{user}', [AdminLoyalty::class, 'setTier']);
});
