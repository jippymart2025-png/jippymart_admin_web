<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorProduct;
use Illuminate\Http\Request;
use Carbon\Carbon;


class productcontroller extends Controller
{
    /**
     * Fetch all published and available products for a vendor
     */
    public function getProductsByVendorId($vendorId)
    {
        try {
            $products = VendorProduct::where('vendorID', $vendorId)
                ->where('publish', 1)
                ->where('isAvailable', 1)
                ->get()
                ->map(function ($item) {
                    // ✅ Helper closure to safely decode JSON
                    $safeDecode = function ($value) {
                        if (empty($value) || !is_string($value)) return $value;
                        $decoded = json_decode($value, true);
                        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
                    };

                    // ✅ Decode relevant fields
                    $item->photos       = $safeDecode($item->photos);
                    $item->createdAt    = $safeDecode($item->createdAt);

                    // ✅ (optional) decode more fields if you have them
//                    $item->categoryTitle    = $safeDecode($item->categoryTitle);
//                    $item->specialDiscount  = $safeDecode($item->specialDiscount);
//                    $item->adminCommission  = $safeDecode($item->adminCommission);
//                    $item->g                = $safeDecode($item->g);

                    return $item;
                });


            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => $products->isEmpty()
                    ? 'No available products found for this vendor'
                    : 'Products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching vendor products',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
