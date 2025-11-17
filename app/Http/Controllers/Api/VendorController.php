<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\vendor_products;
use App\Models\VendorCategory;
use App\Models\Coupon;
use App\Models\Vendor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VendorController extends Controller
{
    /**
     * Get Products by Vendor ID
     */
//    public function getProductsByVendorId(Request $request, $vendorId)
//    {
//        try {
//            $foodType = $request->query('food_type', 'Delivery');
//            $limit = 400;
//
//            $query = vendor_products::query()
//                ->where('vendorID', $vendorId)
//                ->where('publish', true)
//                ->orderBy('createdAt', 'asc')
//                ->limit($limit);
//
//            if ($foodType === 'Delivery') {
//                $query->where('takeaway_option', false);
//            }
//
//            $products = $query->get();
//
//            return response()->json([
//                'success' => true,
//                'data' => $products,
//                'count' => $products->count(),
//                'food_type' => $foodType,
//            ]);
//        } catch (\Throwable $e) {
//            Log::error('getProductsByVendorId error: ' . $e->getMessage());
//            return response()->json([
//                'success' => false,
//                'message' => 'Failed to load products'
//            ], 500);
//        }
//    }

    /**
     * Get Vendor Category by ID
     */
    public function getVendorCategoryById($id)
    {
        $category = VendorCategory::find($id);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $category]);
    }

    /**
     * Get Product by ID
     */
    public function getProductById($id)
    {
        $product = vendor_products::find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * 0021a904-ff79-4e2f-93ab-b71bd98f32de
     * Get Offers by Vendor ID
     */
    public function getOffersByVendorId($vendorId)
    {
        $offers = Coupon::where('resturant_id', $vendorId)
            ->where('isEnabled', true)
            ->where('isPublic', true)
            ->where('expiresAt', '>=', Carbon::now())
            ->get();

        return response()->json(['success' => true, 'data' => $offers]);
    }


    public function getNearestRestaurantByCategory(Request $request, $categoryId)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0',
            'filter' => 'nullable|string|in:distance,rating',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userLat = $request->input('latitude');
        $userLon = $request->input('longitude');
        $radius = $request->input('radius', 10); // default radius = 10 km
        $filter = strtolower($request->input('filter', 'distance'));

        try {
            // ✅ Base query
            $query = Vendor::query()
                ->where('publish', 1)
                ->where('isOpen', 1)
                ->where(function ($q) use ($categoryId) {
                    $q->where('categoryID', 'LIKE', "%{$categoryId}%");
                });

            // ✅ Ensure coordinates exist
            $query->whereNotNull('latitude')
                  ->whereNotNull('longitude')
                  ->select('vendors.*')
                  ->selectRaw(
                      '(6371 * acos(cos(radians(?)) * cos(radians(latitude))
                      * cos(radians(longitude) - radians(?))
                      + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                      [$userLat, $userLon, $userLat]
                  )
                  ->having('distance', '<=', $radius);

            // ✅ Sorting
            if ($filter === 'rating') {
                $query->orderByRaw('CASE WHEN COALESCE(reviewsCount, 0) > 0 THEN COALESCE(reviewsSum, 0) / NULLIF(reviewsCount, 0) ELSE 0 END DESC')
                      ->orderByRaw('COALESCE(reviewsCount, 0) DESC');
            } else {
                $query->orderBy('distance', 'asc');
            }

            // ✅ Fetch
            $vendors = $query->get()->map(function ($item) {
                $safeDecode = function ($value) {
                    if (empty($value) || !is_string($value)) return $value;
                    $decoded = json_decode($value, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
                };

                // Decode all JSON fields safely
                foreach ([
                    'restaurantMenuPhotos', 'photos', 'workingHours', 'filters',
                    'coordinates', 'lastAutoScheduleUpdate', 'createdAt',
                    'categoryID', 'categoryTitle', 'specialDiscount',
                    'adminCommission', 'g'
                ] as $field) {
                    if (isset($item->$field)) {
                        $item->$field = $safeDecode($item->$field);
                    }
                }

                return $item;
            });

            return response()->json([
                'success' => true,
                'count' => $vendors->count(),
                'filter' => $filter,
                'data' => $vendors->values(),
            ]);
        } catch (\Exception $e) {
            Log::error('Nearest Category Restaurants Error: ' . $e->getMessage(), [
                'category_id' => $categoryId,
                'latitude' => $userLat,
                'longitude' => $userLon,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch nearest restaurants',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getMartVendorById($vendorId): \Illuminate\Http\JsonResponse
    {
        try {
            $vendor = Vendor::query()
                ->where('vType', 'LIKE', '%mart%')
                ->where(function ($query) use ($vendorId) {
                    foreach ($this->expandMartVendorIds($vendorId) as $candidate) {
                        $query->orWhere('id', $candidate);
                    }
                })
                ->first();

            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mart vendor not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->transformVendor($vendor),
            ]);
        } catch (\Throwable $e) {
            Log::error('getMartVendorById error: ' . $e->getMessage(), [
                'vendor_id' => $vendorId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mart vendor',
            ], 500);
        }
    }


    public function getDefaultMartVendor(): \Illuminate\Http\JsonResponse
    {
        try {
            $vendor = Vendor::query()
                ->where('vType', 'LIKE', '%mart%')
                ->where('isOpen', 1)
                ->where('publish', 1)
                ->orderByDesc('createdAt')
                ->first();

            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'No mart vendors available',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->transformVendor($vendor),
            ]);
        } catch (\Throwable $e) {
            Log::error('getDefaultMartVendor error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch default mart vendor',
            ], 500);
        }
    }

    public function getMartVendorsByZone($zoneId)
    {
        if (empty($zoneId)) {
            return response()->json([
                'success' => false,
                'message' => 'Zone ID is required',
            ], 400);
        }

        try {
            $vendors = Vendor::query()
                ->whereRaw('LOWER(vType) = ?', ['mart'])
                ->where('zoneId', $zoneId)
                ->orderByDesc('isOpen')
                ->orderBy('title')
                ->get()
                ->map(function ($vendor) {
                    return $this->transformVendor($vendor);
                });

            return response()->json([
                'success' => true,
                'count' => $vendors->count(),
                'zone_id' => $zoneId,
                'data' => $vendors->values(),
            ]);
        } catch (\Throwable $e) {
            Log::error('getMartVendorsByZone error: ' . $e->getMessage(), [
                'zone_id' => $zoneId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mart vendors by zone',
            ], 500);
        }
    }

    private function transformVendor(Vendor $vendor): array
    {
        $data = $vendor->toArray();
        foreach ($this->jsonFields() as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->safeJsonDecode($data[$field]);
            }
        }

        return $data;
    }

    private function jsonFields(): array
    {
        return [
            'restaurantMenuPhotos',
            'photos',
            'workingHours',
            'filters',
            'coordinates',
            'lastAutoScheduleUpdate',
            'createdAt',
            'categoryID',
            'categoryTitle',
            'specialDiscount',
            'adminCommission',
            'g',
            'location',
        ];
    }

    private function safeJsonDecode($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function expandMartVendorIds(string $vendorId): array
    {
        $baseId = $vendorId;
        $lowerVendorId = strtolower($vendorId);

        if (Str::startsWith($lowerVendorId, 'mart_')) {
            $baseId = substr($vendorId, strpos($vendorId, '_') + 1);
        }

        return array_values(array_unique(array_filter([
            $vendorId,
            $baseId,
            'mart_' . $baseId,
            'MART_' . $baseId,
        ])));
    }
}
