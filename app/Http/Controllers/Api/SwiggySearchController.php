<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\VendorCategory;

class SwiggySearchController extends Controller
{
    /**
     * Unified Swiggy-style Search
     */
    public function unifiedSearch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query'     => 'required|string|min:2',
            'zone_id'   => 'required|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'limit'     => 'nullable|integer|min:1|max:100',
            'page'      => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            // FIX: InputBag → string
            $query     = $request->input('query');
            $zoneId    = $request->input('zone_id');
            $latitude  = $request->input('latitude');
            $longitude = $request->input('longitude');

            $limit  = $request->input('limit', 20);
            $page   = $request->input('page', 1);
            $offset = ($page - 1) * $limit;

            // Run all searches
            $restaurants = $this->searchRestaurants($query, $zoneId, $latitude, $longitude, $limit, $offset);
            $products    = $this->searchProducts($query, $zoneId, $limit, $offset);
            $categories  = $this->searchCategories($query, $limit, $offset);

            // Format results
            $formattedRestaurants = $restaurants->map(fn ($r) => $this->formatRestaurantResponse($r));
            $formattedProducts    = $products->map(fn ($p) => $this->formatProductResponse($p));
            $formattedCategories  = $categories->map(fn ($c) => $this->formatCategoryResponse($c));

            $totalResults =
                $formattedRestaurants->count() +
                $formattedProducts->count() +
                $formattedCategories->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'restaurants'   => $formattedRestaurants,
                    'products'      => $formattedProducts,
                    'categories'    => $formattedCategories,
                    'total_results' => $totalResults,
                ],
                'meta' => [
                    'page'     => $page,
                    'limit'    => $limit,
                    'query'    => $query,
                    'zone_id'  => $zoneId,
                    'has_more' => $totalResults >= $limit
                ]
            ]);

        } catch (\Exception $e) {

            Log::error('Unified Search Error : ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to perform search',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Restaurant Search (Uses real columns only)
     */
    private function searchRestaurants($query, $zoneId, $latitude, $longitude, $limit, $offset)
    {
        $restaurantQuery = Vendor::where(function ($q) {
            $q->where('publish', 1)->orWhereNull('publish');
        })
            ->where('zoneId', $zoneId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('location', 'like', "%{$query}%")
                    ->orWhere('vType', 'like', "%{$query}%")
                    ->orWhere('cuisineTitle', 'like', "%{$query}%")
                    ->orWhere('categoryTitle', 'like', "%{$query}%")
                    ->orWhere('restaurant_slug', 'like', "%{$query}%")
                    ->orWhere('zone_slug', 'like', "%{$query}%");
            });

        // Distance sorting
        if ($latitude && $longitude) {
            $restaurantQuery->selectRaw(
                "vendors.*,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance",
                [$latitude, $longitude, $latitude]
            )
                ->orderBy('distance', 'asc');
        } else {
            $restaurantQuery->orderBy('title', 'asc');
        }

        return $restaurantQuery->skip($offset)->take($limit)->get();
    }

    /**
     * Product Search
     */
    private function searchProducts($query, $zoneId, $limit, $offset)
    {
        return VendorProduct::where('publish', 1)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('categoryID', 'like', "%{$query}%");
            })
            ->whereHas('vendor', function ($q) use ($zoneId) {
                $q->where('zoneId', $zoneId)
                    ->where(function ($q) {
                        $q->where('publish', 1)->orWhereNull('publish');
                    });
            })
            ->orderBy('name', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    /**
     * Category Search
     */
    private function searchCategories($query, $limit, $offset)
    {
        return VendorCategory::where('publish', 1)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('title', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    /**
     * Format Restaurant Response
     */
    private function formatRestaurantResponse($r)
    {
        return [
            'id' => $r->id,
            'title' => $r->title ?? '',
            'description' => $r->description ?? '',
            'location' => $r->location ?? '',
            'latitude' => $r->latitude ?? null,
            'longitude' => $r->longitude ?? null,
            'zoneId' => $r->zoneId ?? '',
            'photo' => $r->photo ?? '',
            'cover_photo' => $r->cover_photo ?? '',
            'phonenumber' => $r->phonenumber ?? '',
            'email' => $r->email ?? '',
            'address' => $r->address ?? '',
            'publish' => (bool) ($r->publish ?? true),
            'vType' => $r->vType ?? '',
            'categoryTitle' => $r->categoryTitle ?? [],
'workingHours' => is_string($r->workingHours)
    ? json_decode($r->workingHours, true)
    : ($r->workingHours ?? []),
            'rating' => $r->rating ?? 0,
            'total_rating' => $r->total_rating ?? 0,
            'delivery_time' => $r->delivery_time ?? '',
            'delivery_charge' => $r->delivery_charge ?? 0,
            'minimum_order' => $r->minimum_order ?? 0,
            'is_open' =>  $r->isOpen ,
            'distance' => $r->distance ?? null,
            'created_at' => $r->created_at ? $r->created_at->toISOString() : null,
            'updated_at' => $r->updated_at ? $r->updated_at->toISOString() : null,
        ];
    }

    /**
     * Format Product Response
     */
    private function formatProductResponse($p)
    {
        return [
            'id'         => $p->id,
            'name'       => $p->name,
            'description'=> $p->description,
            'price'      => $p->price,
            'disPrice'   => $p->disPrice,
            'photo'      => $p->photo,
            'categoryID' => $p->categoryID,
            'vendorID'   => $p->vendorID,
            'veg'        => (bool)$p->veg,
            'nonveg'     => (bool)$p->nonveg,
        ];
    }

    /**
     * Format Category Response
     */
    private function formatCategoryResponse($c)
    {
        return [
            'id'          => $c->id,
            'title'       => $c->title,
            'photo'       => $c->photo,
            'publish'     => (bool)$c->publish,
            'description' => $c->description,
            'vType'       => $c->vType,
        ];
    }
}
