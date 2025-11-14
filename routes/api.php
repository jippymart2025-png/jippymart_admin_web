<?php


use App\Http\Controllers\Api\productcontroller;
use App\Http\Controllers\Api\FirestoreBridgeController;
use App\Http\Controllers\Api\SettingsApiController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\Vendor_Reviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\MenuItemBannerController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\CouponApiController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\ShippingAddressController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\MartItemController;


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

//Route::post('/login', [AuthController::class, 'login']);
//Route::middleware('auth:sanctum')->group(function () {
//    Route::get('/profile', [AuthController::class, 'profile']);
//    Route::post('/logout', [AuthController::class, 'logout']);
//});
//
//Route::middleware(['throttle:5,1'])->group(function () {
//    Route::get('/firebase/users', [FirebaseUserController::class, 'index']);
//    Route::get('/firebase/orders', [FirebaseOrderController::class, 'index']);
//
//    // Live tracking endpoints
//    Route::get('/firebase/live-tracking', [FirebaseLiveTrackingController::class, 'index']);
//    Route::get('/firebase/drivers/{driverId}/location', [FirebaseLiveTrackingController::class, 'getDriverLocation']);
//    Route::post('/firebase/drivers/locations', [FirebaseLiveTrackingController::class, 'batchDriverLocations']);
//});
//
//// SQL users listing (replaces client-side Firebase usage on Users page)
//Route::get('/app-users', [AppUserController::class, 'index']);
//Route::post('/app-users', [AppUserController::class, 'store']);
//Route::delete('/app-users/{id}', [AppUserController::class, 'destroy']);
//Route::patch('/app-users/{id}/active', [AppUserController::class, 'setActive']);


Route::get('/settings/mobile', [SettingsApiController::class, 'mobileSettings']);
Route::get('/settings/delivery-charge', [App\Http\Controllers\Api\SettingsApiController::class, 'getDeliveryChargeSettings']);
Route::get('/settings/tax', [App\Http\Controllers\Api\TaxApiController::class, 'gettaxSettings']);


Route::post('/send-otp', [App\Http\Controllers\Api\OTPController::class, 'sendOtp']);
Route::post('/verify-otp', [App\Http\Controllers\Api\OTPController::class, 'verifyOtp']);
Route::post('/resend-otp', [App\Http\Controllers\Api\OTPController::class, 'resendOtp']);
Route::post('/signup', [App\Http\Controllers\Api\OTPController::class, 'signUp']);

Route::post('/sms-delivery-status', [App\Http\Controllers\Api\OTPController::class, 'smsDeliveryStatus']);

// Debug route - remove in production
Route::get('/debug-otp/{phone}', [App\Http\Controllers\Api\OTPController::class, 'debugOtp']);




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
Route::get('/menu-items/banners/{id}', [MenuItemBannerController::class, 'show']);
});
Route::middleware('auth:sanctum')->group(function () {
// Stories API routes (Public - no auth required)
Route::get('/stories', [StoryController::class, 'index']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{userId}/shipping-address', [ShippingAddressController::class, 'show']);
    Route::get('/users/shipping-address', [ShippingAddressController::class, 'show']);
    Route::match(['put', 'post'], '/users/{userId}/shipping-address', [ShippingAddressController::class, 'update']);
    Route::post('/users/shipping-address', [ShippingAddressController::class, 'update']);
    Route::delete('users/{userId}/shipping-address/{addressId}', [ShippingAddressController::class, 'delete']);

});

Route::middleware('auth:sanctum')->group(function () {
// Coupons API routes (Public - no auth required)
    Route::prefix('coupons')->group(function () {
        Route::get('/{type}', [CouponApiController::class, 'byType']);
    });
});
// User Profile API routes (Customers only)
Route::middleware('auth:sanctum')->group(function () {

Route::get('/users/profile/{firebase_id}', [UserProfileController::class, 'show']); // Public - get customer by firebase_id
    Route::get('/user/profile', [UserProfileController::class, 'me']); // Get current customer profile
    Route::post('/user/profile', [UserProfileController::class, 'update']); // Update current customer profile
    Route::delete('/users/profile/{firebase_id}', [UserProfileController::class, 'destroy']); // Delete user and related data from database
    // Route::delete('/user/profile/{firebase_id}', [UserProfileController::class, 'destroy']); // Backward compatibility

});

//restaurants
Route::middleware('auth:sanctum')->group(function () {

Route::prefix('favorites')->group(function () {
    // Restaurants
    Route::get('restaurants/{firebase_id}', [FavoriteController::class, 'getFavoriteRestaurants']);
    Route::post('restaurants', [FavoriteController::class, 'addFavoriteRestaurant']);
    Route::delete('restaurants', [FavoriteController::class, 'removeFavoriteRestaurant']);

    // Items
    Route::get('items/{firebase_id}', [FavoriteController::class, 'getFavoriteItems']);
    Route::post('items', [FavoriteController::class, 'addFavoriteItem']);
    Route::delete('items', [FavoriteController::class, 'removeFavoriteItem']);
});
});


// vendor
Route::middleware('auth:sanctum')->group(function () {

Route::prefix('vendors')->group(function () {
    Route::get('{vendorId}/products', [\App\Http\Controllers\Api\ProductController::class, 'getProductsByVendorId']);
    Route::get('{vendorId}/offers', [VendorController::class, 'getOffersByVendorId']);
    Route::get('{categoryId}/category', [VendorController::class, 'getNearestRestaurantByCategory']);
});

Route::get('vendor-categories/{id}', [VendorController::class, 'getVendorCategoryById']);
Route::get('products/{id}', [VendorController::class, 'getProductById']);
});

//wallet
Route::middleware('auth:sanctum')->group(function () {

Route::post('/update-wallet', [WalletController::class, 'updateWallet']);

});


Route::middleware('auth:sanctum')->group(function () {
Route::get(
    '/restaurants/{vendorId}/product-feed{extra?}',
    [\App\Http\Controllers\Api\ProductController::class, 'getRestaurantProductFeed']
)->where('extra', '.*');

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderApiController::class, 'index']);
    Route::get('/orders/{orderId}', [OrderApiController::class, 'show']);
    Route::get('/orders/{orderId}/billing', [OrderApiController::class, 'billing']);
});



// mart all apis
Route::middleware('auth:sanctum')->group(function () {
Route::get('/mart-items/trending', [MartItemController::class, 'getTrendingItems']);
Route::get('/mart-items/featured', [MartItemController::class, 'getFeaturedItems']);
Route::get('/mart-items/on-sale', [MartItemController::class, 'getItemsOnSale']);
Route::get('/mart-items/search', [MartItemController::class, 'searchItems']);
Route::get('/mart-items/by-category', [MartItemController::class, 'getItemsByCategory']);
Route::get('/mart-items/by-category-only', [MartItemController::class, 'getItemsByCategoryOnly']);
Route::get('/mart-items/by-vendor', [MartItemController::class, 'getItemsByVendor']);
Route::get('/mart-items/by-section', [MartItemController::class, 'getItemsBySection']);
Route::get('/mart-items/all', [MartItemController::class, 'getMartItems']);
Route::get('/mart-items/by-brand', [MartItemController::class, 'getItemsByBrand']);
Route::get('/mart-items/sections', [MartItemController::class, 'getUniqueSections']);
    Route::get('/mart-items/getmartcategory', [MartItemController::class, 'getmartcategory']);
    Route::get('/mart-items/categoryhome', [MartItemController::class, 'getcategoryhome']);
    Route::get('/mart-items/sub_category', [MartItemController::class, 'getSubcategoriesByParent']);
    Route::get('/mart-items/sub_category_home', [MartItemController::class, 'getSubcategories_home']);
    Route::get('/mart-items/searchSubcategories', [MartItemController::class, 'searchSubcategories']);
    Route::get('/mart-items/getItemById', [MartItemController::class, 'getItemById']);
    Route::get('/mart-items/searchCategories', [MartItemController::class, 'searchCategories']);
    Route::get('/mart-items/getFeaturedCategories', [MartItemController::class, 'getFeaturedCategories']);
    Route::get('/mart-items/getSimilarProducts', [MartItemController::class, 'getSimilarProducts']);
    Route::get('/mart-items/getItemsBySectionName', [MartItemController::class, 'getItemsBySectionName']);
    Route::get('/mart-items/getMartVendors', [MartItemController::class, 'getMartVendors']);




});


Route::middleware('auth:sanctum')->group(function () {
Route::get('/vendor/attributes', [SettingsApiController::class, 'getVendorAttributes']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/vendor/{vendorId}/reviews', [Vendor_Reviews::class, 'getVendorReviews']);
    Route::get('/reviews/order', [Vendor_Reviews::class, 'getOrderReviewById']);
    Route::get('/review-attributes/{id}', [Vendor_Reviews::class, 'getReviewAttributeById']);

});



Route::middleware('auth:sanctum')->group(function () {
Route::prefix('firestore')->group(function () {
    Route::get('/settings/razorpay', [FirestoreBridgeController::class, 'getRazorpaySettings']);
    Route::get('/settings/cod', [FirestoreBridgeController::class, 'getCodSettings']);
    Route::post('/products', [FirestoreBridgeController::class, 'setProduct']);
    Route::get('/orders', [FirestoreBridgeController::class, 'getAllOrders']);
    Route::get('/email-templates/{type}', [FirestoreBridgeController::class, 'getEmailTemplates']);
    Route::get('/notifications/{type}', [FirestoreBridgeController::class, 'getNotificationContent']);
    Route::post('/chat/driver/inbox', [FirestoreBridgeController::class, 'addDriverInbox']);
    Route::post('/chat/driver/messages', [FirestoreBridgeController::class, 'addDriverChat']);
    Route::post('/chat/restaurant/inbox', [FirestoreBridgeController::class, 'addRestaurantInbox']);
    Route::post('/chat/restaurant/messages', [FirestoreBridgeController::class, 'addRestaurantChat']);
    Route::post('/chat/upload-image', [FirestoreBridgeController::class, 'uploadChatImageToStorage']);
    Route::post('/chat/upload-video', [FirestoreBridgeController::class, 'uploadChatVideoToStorage']);
    Route::get('/vendor-categories/{id}', [FirestoreBridgeController::class, 'getVendorCategoryByCategoryId']);
    Route::post('/ratings', [FirestoreBridgeController::class, 'setRatingModel']);
    Route::put('/vendors/{vendorId}', [FirestoreBridgeController::class, 'updateVendor']);
    Route::get('/advertisements/active', [FirestoreBridgeController::class, 'getAllAdvertisement']);
    Route::get('/promotions/active', [FirestoreBridgeController::class, 'fetchActivePromotions']);
    Route::get('/promotions/by-product', [FirestoreBridgeController::class, 'getActivePromotionForProduct']);
    Route::get('/search/products', [FirestoreBridgeController::class, 'getAllProductsInZone']);
    Route::get('/search/vendors', [FirestoreBridgeController::class, 'getAllVendors']);
});
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/products', [ProductController::class, 'getAllPublishedProducts']);
});
