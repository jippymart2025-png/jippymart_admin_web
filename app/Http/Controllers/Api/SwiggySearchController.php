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
     * Unified search across restaurants, products, and categories
     */
    public function unifiedSearch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'zone_id' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->input('query');
            $zoneId = $request->input('zone_id');
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $limit = $request->input('limit', 20);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $limit;

            // Execute all searches in parallel for better performance
            $restaurantsPromise = $this->searchRestaurants($query, $zoneId, $latitude, $longitude, $limit, $offset);
            $productsPromise = $this->searchProducts($query, $zoneId, $limit, $offset);
            $categoriesPromise = $this->searchCategories($query, $limit, $offset);

            // Wait for all queries to complete
            $restaurants = $restaurantsPromise;
            $products = $productsPromise;
            $categories = $categoriesPromise;

            // Format responses
            $formattedRestaurants = $restaurants->map(function ($restaurant) {
                return $this->formatRestaurantResponse($restaurant);
            });

            $formattedProducts = $products->map(function ($product) {
                return $this->formatProductResponse($product);
            });

            $formattedCategories = $categories->map(function ($category) {
                return $this->formatCategoryResponse($category);
            });

            $totalResults = $formattedRestaurants->count() + $formattedProducts->count() + $formattedCategories->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'restaurants' => $formattedRestaurants,
                    'products' => $formattedProducts,
                    'categories' => $formattedCategories,
                    'total_results' => $totalResults,
                    'restaurants_count' => $formattedRestaurants->count(),
                    'products_count' => $formattedProducts->count(),
                    'categories_count' => $formattedCategories->count(),
                ],
                'meta' => [
                    'query' => $query,
                    'zone_id' => $zoneId,
                    'page' => $page,
                    'limit' => $limit,
                    'has_more' => $totalResults >= $limit
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Unified Search Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to perform search',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search restaurants with location filtering
     */
    private function searchRestaurants($query, $zoneId, $latitude, $longitude, $limit, $offset)
    {
        $restaurantQuery = Vendor::where(function($q) {
            // Treat NULL and TRUE as published, only FALSE as not published
            $q->where('publish', true)->orWhereNull('publish');
        })
        ->where(function($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('description', 'like', "%{$query}%")
              ->orWhere('location', 'like', "%{$query}%")
              ->orWhere('vType', 'like', "%{$query}%");
        });

        // Filter by zone if provided
        if ($zoneId) {
            $restaurantQuery->where('zoneId', $zoneId);
        }

        // Add distance calculation if lat/lon provided
        if ($latitude && $longitude) {
            $restaurantQuery->selectRaw(
                'vendors.*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$latitude, $longitude, $latitude]
            )->orderBy('distance', 'asc');
        } else {
            $restaurantQuery->orderBy('title', 'asc');
        }

        return $restaurantQuery->skip($offset)->take($limit)->get();
    }

    /**
     * Search products
     */
    private function searchProducts($query, $zoneId, $limit, $offset)
    {
        $productQuery = VendorProduct::where('publish', true)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('categoryID', 'like', "%{$query}%")
                  ->orWhere('price', 'like', "%{$query}%");
            });
        // If zone filtering is needed for products (via vendor relationship)
        if ($zoneId) {
            $productQuery->whereHas('vendor', function($q) use ($zoneId) {
                $q->where('zoneId', $zoneId)
                  ->where(function($q) {
                      $q->where('publish', true)->orWhereNull('publish');
                  });
            });
        }

        return $productQuery->orderBy('name', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    /**
     * Search categories
     */
    private function searchCategories($query, $limit, $offset)
    {
        return VendorCategory::where('publish', true)
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->orderBy('title', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    /**
     * Format restaurant response
     */
    private function formatRestaurantResponse($restaurant)
    {
        return [
            'id' => $restaurant->id,
            'title' => $restaurant->title ?? '',
            'description' => $restaurant->description ?? '',
            'location' => $restaurant->location ?? '',
            'latitude' => $restaurant->latitude ?? null,
            'longitude' => $restaurant->longitude ?? null,
            'zoneId' => $restaurant->zoneId ?? '',
            'photo' => $restaurant->photo ?? '',
            'cover_photo' => $restaurant->cover_photo ?? '',
            'phonenumber' => $restaurant->phonenumber ?? '',
            'email' => $restaurant->email ?? '',
            'address' => $restaurant->address ?? '',
            'publish' => (bool) ($restaurant->publish ?? true),
            'vType' => $restaurant->vType ?? '',
            'categoryTitle' => $restaurant->categoryTitle ?? [],
            'rating' => $restaurant->rating ?? 0,
            'total_rating' => $restaurant->total_rating ?? 0,
            'delivery_time' => $restaurant->delivery_time ?? '',
            'delivery_charge' => $restaurant->delivery_charge ?? 0,
            'minimum_order' => $restaurant->minimum_order ?? 0,
            'is_open' => (bool) ($restaurant->is_open ?? true),
            'distance' => $restaurant->distance ?? null,
            'created_at' => $restaurant->created_at ? $restaurant->created_at->toISOString() : null,
            'updated_at' => $restaurant->updated_at ? $restaurant->updated_at->toISOString() : null,
        ];
    }

    /**
     * Format product response
     */
    private function formatProductResponse($product)
    {
        return [
            'id' => $product->id,
            'name' => $product->name ?? '',
            'description' => $product->description ?? '',
            'price' => $product->price ?? '',
            'disPrice' => $product->disPrice ?? '',
            'categoryID' => $product->categoryID ?? '',
            'vendorID' => $product->vendorID ?? '',
            'photo' => $product->photo ?? '',
            'veg' => (bool) ($product->veg ?? false),
            'nonveg' => (bool) ($product->nonveg ?? false),
            'outofstock' => (bool) ($product->outofstock ?? false),
            'publish' => (bool) ($product->publish ?? true),
            'addOnsTitle' => $product->addOnsTitle ?? [],
            'addOnsPrice' => $product->addOnsPrice ?? [],
            'productSpecification' => $product->productSpecification ?? [],
            'rating' => $product->rating ?? 0,
            'total_rating' => $product->total_rating ?? 0,
            'created_at' => $product->created_at ? $product->created_at->toISOString() : null,
            'updated_at' => $product->updated_at ? $product->updated_at->toISOString() : null,
        ];
    }

    /**
     * Format category response
     */
    private function formatCategoryResponse($category)
    {
        return [
            'id' => $category->id,
            'title' => $category->title ?? '',
            'photo' => $category->photo ?? '',
            'show_in_homepage' => (bool) ($category->show_in_homepage ?? false),
            'publish' => (bool) ($category->publish ?? true),
            'description' => $category->description ?? '',
            'vType' => $category->vType ?? null,
            'created_at' => $category->created_at ? $category->created_at->toISOString() : null,
            'updated_at' => $category->updated_at ? $category->updated_at->toISOString() : null,
        ];
    }
}
