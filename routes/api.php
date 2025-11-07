<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FirebaseOrderController;
use App\Http\Controllers\Api\FirebaseLiveTrackingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppUserController;
use App\Http\Controllers\Api\FirebaseUserController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\MenuItemBannerController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\UserProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['throttle:5,1'])->group(function () {
    Route::get('/firebase/users', [FirebaseUserController::class, 'index']);
    Route::get('/firebase/orders', [FirebaseOrderController::class, 'index']);

    // Live tracking endpoints
    Route::get('/firebase/live-tracking', [FirebaseLiveTrackingController::class, 'index']);
    Route::get('/firebase/drivers/{driverId}/location', [FirebaseLiveTrackingController::class, 'getDriverLocation']);
    Route::post('/firebase/drivers/locations', [FirebaseLiveTrackingController::class, 'batchDriverLocations']);
});

// SQL users listing (replaces client-side Firebase usage on Users page)
Route::get('/app-users', [AppUserController::class, 'index']);
Route::post('/app-users', [AppUserController::class, 'store']);
Route::delete('/app-users/{id}', [AppUserController::class, 'destroy']);
Route::patch('/app-users/{id}/active', [AppUserController::class, 'setActive']);

Route::post('/send-otp', [App\Http\Controllers\OTPController::class, 'sendOtp']);
Route::post('/verify-otp', [App\Http\Controllers\OTPController::class, 'verifyOtp']);
Route::post('/resend-otp', [App\Http\Controllers\OTPController::class, 'resendOtp']);
Route::middleware('auth:sanctum')->group(function () {
Route::post('/signup', [App\Http\Controllers\OTPController::class, 'signUp']);
});

Route::post('/sms-delivery-status', [App\Http\Controllers\OTPController::class, 'smsDeliveryStatus']);

// Debug route - remove in production
Route::get('/debug-otp/{phone}', [App\Http\Controllers\OTPController::class, 'debugOtp']);




// Zone detection routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/zones/current', [ZoneController::class, 'getCurrentZone']);
    Route::get('/zones/detect-id', [ZoneController::class, 'detectZoneId']);
    Route::get('/zones/check-service-area', [ZoneController::class, 'checkServiceArea']);
    Route::get('/zones/all', [ZoneController::class, 'getAllZones']);
});

// Restaurant/Vendor API routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/restaurants/nearest', [RestaurantController::class, 'nearest']);
    Route::get('/restaurants/search', [RestaurantController::class, 'search']);
    Route::get('/restaurants/by-zone/{zone_id}', [RestaurantController::class, 'byZone']);
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);
});

// Category API routes (Public - no auth required)
Route::middleware('auth:sanctum')->group(function () {
Route::get('/categories/home', [CategoryController::class, 'home']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
});
Route::middleware('auth:sanctum')->group(function () {

// Banner API routes (Public - no auth required)
Route::get('/banners/top', [BannerController::class, 'top']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/banners/{id}', [BannerController::class, 'show']);
});
Route::middleware('auth:sanctum')->group(function () {
// Menu Item Banner API routes (Public - no auth required)
    Route::get('/menu-items/banners/top', [MenuItemBannerController::class, 'top']);
    Route::get('/menu-items/banners/middle', [MenuItemBannerController::class, 'middle']);
    Route::get('/menu-items/banners/bottom', [MenuItemBannerController::class, 'bottom']);
//Route::get('/menu-items/banners', [MenuItemBannerController::class, 'index']);
//Route::get('/menu-items/banners/{id}', [MenuItemBannerController::class, 'show']);
});
Route::middleware('auth:sanctum')->group(function () {
// Stories API routes (Public - no auth required)
Route::get('/stories', [StoryController::class, 'index']);
});
// User Profile API routes (Customers only)
Route::middleware('auth:sanctum')->group(function () {

Route::get('/users/profile/{firebase_id}', [UserProfileController::class, 'show']); // Public - get customer by firebase_id
    Route::get('/user/profile', [UserProfileController::class, 'me']); // Get current customer profile
    Route::put('/user/profile', [UserProfileController::class, 'update']); // Update current customer profile
});

