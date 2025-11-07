<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestaurantController extends Controller
{
    /**
     * Get Nearest Restaurants (Stream/Real-time)
     * GET /api/restaurants/nearest
     *
     * Query Parameters:
     * - zone_id (required): Current zone ID
     * - latitude (required): User's latitude
     * - longitude (required): User's longitude
     * - radius (required): Search radius in km
     * - is_dining (optional): Filter for dine-in restaurants (default: false)
     * - user_id (optional): For subscription filtering
     */
    public function nearest(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:0',
            'is_dining' => 'nullable|boolean',
            'user_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $zoneId = $request->input('zone_id');
        $userLat = $request->input('latitude');
        $userLon = $request->input('longitude');
        $radius = $request->input('radius', 10); // Default 10km
        $isDining = $request->input('is_dining', false);
        $userId = $request->input('user_id');

        try {
            // Build query for restaurants/vendors
            $query = Vendor::select('vendors.*')
                ->selectRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                    [$userLat, $userLon, $userLat]
                )
                // 1. Zone Filtering
                ->where('zoneId', $zoneId)
                ->where(function($q) {
                    // Treat NULL and TRUE as published, only FALSE as not published
                    $q->where('publish', true)->orWhereNull('publish');
                })
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                // 2. Geographic Filtering - radius check
                ->havingRaw('distance <= ?', [$radius]);

            // Filter for dine-in if requested
            if ($isDining) {
                $query->where('enabledDiveInFuture', true);
            }

            // 4. Type Filtering - Exclude 'mart' vType (for food category)
            if (DB::getSchemaBuilder()->hasColumn('vendors', 'vType')) {
                $query->where(function($q) {
                    $q->where('vType', 'restaurant')
                      ->orWhere('vType', 'food')
                      ->orWhereNull('vType'); // Include NULL as restaurant by default
                })
                ->where('vType', '!=', 'mart'); // Explicitly exclude mart
            }

            // 6. Sorting - Sort by distance first, then by rating
            $query->orderBy('distance', 'asc')
                  ->orderByRaw('COALESCE(reviewsSum, 0) DESC'); // Secondary sort by rating

            // Get results
            $restaurants = $query->get();

            // Format response with subscription data
            $data = $restaurants->map(function ($restaurant) use ($userId) {
                return $this->formatRestaurantResponse($restaurant, $userId);
            });

            // 3. Subscription Filtering - Apply after fetching subscription data
            $filteredData = $data->filter(function($restaurant) {
                return $this->isSubscriptionValid($restaurant);
            })->values(); // Re-index array after filtering

            return response()->json([
                'success' => true,
                'data' => $filteredData
            ]);

        } catch (\Exception $e) {
            Log::error('Nearest Restaurants Error: ' . $e->getMessage(), [
                'zone_id' => $zoneId,
                'latitude' => $userLat,
                'longitude' => $userLon,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch nearest restaurants',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if subscription is valid (Business Logic #3)
     * 
     * Rules:
     * - Include if subscriptionTotalOrders = "-1" (unlimited)
     * - OR if subscription is valid (not expired) AND subscriptionTotalOrders > 0
     * - Exclude if subscription expired or orders exhausted
     * - Include if no subscription (free/commission model)
     */
    private function isSubscriptionValid($restaurant)
    {
        // If no subscription data, include restaurant (free/commission model)
        if (empty($restaurant['subscriptionPlan'])) {
            return true;
        }

        $totalOrders = $restaurant['subscriptionTotalOrders'] ?? '0';
        $expiryDate = $restaurant['subscriptionExpiryDate'] ?? null;

        // Unlimited orders (-1 means unlimited)
        if ($totalOrders === '-1' || (int)$totalOrders === -1) {
            return true;
        }

        // Check if subscription is not expired
        $isNotExpired = true;
        if ($expiryDate !== null) {
            try {
                $expiry = new \DateTime($expiryDate);
                $now = new \DateTime();
                $isNotExpired = $expiry >= $now;
            } catch (\Exception $e) {
                // If date parsing fails, assume not expired
                $isNotExpired = true;
            }
        }

        // Check if orders available and not expired
        $ordersAvailable = (int)$totalOrders > 0;

        return $isNotExpired && $ordersAvailable;
    }

    /**
     * Format restaurant data for API response
     */
    private function formatRestaurantResponse($restaurant, $userId = null)
    {
        // Get subscription data if exists
        $subscriptionPlan = null;
        $subscriptionTotalOrders = null;
        $subscriptionExpiryDate = null;

        if (DB::getSchemaBuilder()->hasTable('subscription_history')) {
            $subscription = DB::table('subscription_history')
                ->where('user_id', $restaurant->id) // user_id contains vendor IDs
                ->where(function($q) {
                    $q->where('expiry_date', '>=', now())
                      ->orWhereNull('expiry_date'); // NULL means unlimited
                })
                ->orderBy('expiry_date', 'desc')
                ->first();

            if ($subscription) {
                // Parse subscription_plan JSON if it exists
                $plan = null;
                if (!empty($subscription->subscription_plan)) {
                    $plan = json_decode($subscription->subscription_plan, true);
                }

                if ($plan) {
                    $subscriptionPlan = [
                        'id' => $plan['id'] ?? null,
                        'expiryDay' => $plan['expiryDay'] ?? null,
                        'expiryDate' => $subscription->expiry_date ?? null
                    ];
                    $subscriptionTotalOrders = $plan['orderLimit'] ?? null;
                    $subscriptionExpiryDate = $subscription->expiry_date ?? null;
                }
            }
        }

        // Calculate review average
        $reviewsAverage = 0;
        if ($restaurant->reviewsCount > 0 && isset($restaurant->reviewsSum)) {
            $reviewsAverage = round($restaurant->reviewsSum / $restaurant->reviewsCount, 1);
        }

        return [
            'id' => $restaurant->id,
            'title' => $restaurant->title ?? '',
            'zoneId' => $restaurant->zoneId ?? '',
            'latitude' => (float) $restaurant->latitude,
            'longitude' => (float) $restaurant->longitude,
            'distance' => round($restaurant->distance ?? 0, 2),
            'vType' => $restaurant->vType ?? 'restaurant',
            'isActive' => (bool) ($restaurant->publish ?? true), // Using publish field (NULL/TRUE = active)
            'isOpen' => (bool) ($restaurant->isOpen ?? false),
            'subscriptionPlan' => $subscriptionPlan,
            'subscriptionTotalOrders' => $subscriptionTotalOrders,
            'subscriptionExpiryDate' => $subscriptionExpiryDate,
            'reviewsCount' => (int) ($restaurant->reviewsCount ?? 0),
            'reviewsSum' => (float) ($restaurant->reviewsSum ?? 0),
            'reviewsAverage' => $reviewsAverage,
            'restaurantCost' => $restaurant->restaurantCost ?? $restaurant->DeliveryCharge ?? '0',
            'createdAt' => $restaurant->createdAt ?? $restaurant->created_at ?? now()->toISOString(),
            'photo' => $restaurant->photo ?? $restaurant->categoryPhoto ?? $restaurant->photos ?? '',
            'location' => $restaurant->location ?? '',
            'enabledDiveInFuture' => (bool) ($restaurant->enabledDiveInFuture ?? false),
            'description' => $restaurant->description ?? '',
            'phonenumber' => $restaurant->phonenumber ?? '',
            'adminCommission' => $restaurant->adminCommission ?? 0,
            'specialDiscountEnable' => (bool) ($restaurant->specialDiscountEnable ?? false),
        ];
    }

    /**
     * Get Restaurant by ID
     * GET /api/restaurants/{id}
     */
    public function show($id)
    {
        try {
            $restaurant = Vendor::find($id);

            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatRestaurantResponse($restaurant)
            ]);

        } catch (\Exception $e) {
            Log::error('Get Restaurant Error: ' . $e->getMessage(), ['id' => $id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch restaurant',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get Restaurants by Zone
     * GET /api/restaurants/by-zone/{zone_id}
     */
    public function byZone($zoneId)
    {
        try {
            $restaurants = Vendor::where('zoneId', $zoneId)
                ->where(function($q) {
                    // Treat NULL and TRUE as published, only FALSE as not published
                    $q->where('publish', true)->orWhereNull('publish');
                })
                ->get();

            $data = $restaurants->map(function ($restaurant) {
                return $this->formatRestaurantResponse($restaurant);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get Restaurants by Zone Error: ' . $e->getMessage(), ['zone_id' => $zoneId]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch restaurants',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search Restaurants
     * GET /api/restaurants/search
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'zone_id' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = Vendor::where(function($q) {
                    // Treat NULL and TRUE as published, only FALSE as not published
                    $q->where('publish', true)->orWhereNull('publish');
                })
                ->where(function($q) use ($request) {
                    $searchTerm = $request->input('query');
                    $q->where('title', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%")
                      ->orWhere('location', 'like', "%{$searchTerm}%");
                });

            // Filter by zone if provided
            if ($request->has('zone_id')) {
                $query->where('zoneId', $request->input('zone_id'));
            }

            // Add distance calculation if lat/lon provided
            if ($request->has('latitude') && $request->has('longitude')) {
                $lat = $request->input('latitude');
                $lon = $request->input('longitude');
                
                $query->selectRaw(
                    'vendors.*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                    [$lat, $lon, $lat]
                )->orderBy('distance', 'asc');
            } else {
                $query->orderBy('title', 'asc');
            }

            $restaurants = $query->get();

            $data = $restaurants->map(function ($restaurant) {
                return $this->formatRestaurantResponse($restaurant);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Search Restaurants Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to search restaurants',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

