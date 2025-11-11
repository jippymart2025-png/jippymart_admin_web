<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\vendor_products;
use App\Models\VendorCategory;
use App\Models\Coupon;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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


    public function getRestaurantCategory($categoryId)
    {
        $vendors = Vendor::query()
            // ✅ Match categoryID (string, not JSON)
            ->where('categoryID', 'LIKE', "%{$categoryId}%")

            // ✅ Filter only published vendors
            ->where('publish', 1)

            // ✅ Filter only vendors that are open
            ->where('isOpen', 1)

            // ✅ Optional: Filter only those that deliver
//            ->where('enabledDelivery', 1)

            ->get()
        ->map(function ($item) {
        // ✅ Helper closure to safely decode JSON
        $safeDecode = function ($value) {
            if (empty($value) || !is_string($value)) return $value;
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        };

        // ✅ Decode relevant fields
        $item->restaurantMenuPhotos = $safeDecode($item->restaurantMenuPhotos);
        $item->photos       = $safeDecode($item->photos);
        $item->workingHours = $safeDecode($item->workingHours);
        $item->filters      = $safeDecode($item->filters);
        $item->coordinates  = $safeDecode($item->coordinates);
        $item->lastAutoScheduleUpdate  = $safeDecode($item->lastAutoScheduleUpdate);



        // ✅ (optional) decode more fields if you have them
        $item->categoryID       = $safeDecode($item->categoryID);
        $item->categoryTitle    = $safeDecode($item->categoryTitle);
        $item->specialDiscount  = $safeDecode($item->specialDiscount);
        $item->adminCommission  = $safeDecode($item->adminCommission);
        $item->g                = $safeDecode($item->g);

        return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $vendors,
        ]);
    }

}
