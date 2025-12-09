<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class SearchController extends Controller
{
    /**
     * Constructor
     */
//    public function __construct()
//    {
//        // Only apply location check for web routes, not API routes
//        if (!request()->is('api/*') && !isset($_COOKIE['address_name'])) {
//            \Redirect::to('set-location')->send();
//        }
//    }

    public function index()
    {
        return view('search.search');
    }


    /****************************************
     * SEARCH CATEGORIES API – SQL VERSION
     ****************************************/
    public function searchCategories(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $request->validate([
                'q' => 'nullable|string|max:100',
                'page' => 'nullable|integer|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $searchTerm = $request->input('q', '');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $offset = ($page - 1) * $limit;

            $query = DB::table('mart_categories');

            if (!empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%$searchTerm%")
                        ->orWhere('description', 'LIKE', "%$searchTerm%");
                });
            }

            $total = $query->count();

            $data = $query->orderBy('category_order')
                ->offset($offset)
                ->limit($limit)
                ->get();

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'has_more' => ($offset + $limit) < $total
                ],
                'search_term' => $searchTerm,
                'response_time_ms' => $responseTime
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Category SQL search error: '.$e->getMessage());

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved with fallback',
                'data' => $this->getFallbackResponse($searchTerm, $limit, $offset)['data'],
                'fallback' => true
            ], 200);
        }
    }


    /****************************************
     * GET PUBLISHED CATEGORIES – SQL VERSION
     ****************************************/
    public function getPublishedCategories(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $limit = $request->input('limit', 50);

            $categories = DB::table('mart_categories')
                ->where('publish', 1)
                ->orderBy('category_order')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Published categories retrieved successfully',
                'data' => $categories,
                'count' => count($categories)
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Published category error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching published categories',
                'data' => []
            ], 500);
        }
    }


    /****************************************
     * SEARCH MART ITEMS – SQL VERSION
     ****************************************/
    public function searchMartItems(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $request->validate([
                'search' => 'nullable|string|max:100',
                'category' => 'nullable|string|max:100',
                'subcategory' => 'nullable|string|max:100',
                'vendor' => 'nullable|string|max:100',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'veg' => 'nullable|boolean',
                'isAvailable' => 'nullable|boolean',
                'isBestSeller' => 'nullable|boolean',
                'isFeature' => 'nullable|boolean',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            $page   = $request->get('page', 1);
            $limit  = $request->get('limit', 20);
            $offset = ($page - 1) * $limit;

            $filters = $request->only([
                'search','category','subcategory','vendor',
                'min_price','max_price','veg','isAvailable',
                'isBestSeller','isFeature'
            ]);

            $query = DB::table('mart_items');

            // --------------------------------------------------
            // SEARCH (FIXED → removed keywords column)
            // --------------------------------------------------
            if (!empty($filters['search'])) {
                $search = $filters['search'];

                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");

                    // only search keywords IF column exists
                    if (Schema::hasColumn('mart_items', 'keywords')) {
                        $q->orWhere('keywords', 'LIKE', "%{$search}%");
                    }
                });
            }

            // Category filters
            if (!empty($filters['category'])) {
                $query->where('categoryTitle', 'LIKE', "%{$filters['category']}%");
            }

            if (!empty($filters['subcategory'])) {
                $query->where('subcategoryTitle', 'LIKE', "%{$filters['subcategory']}%");
            }

            if (!empty($filters['vendor'])) {
                $query->where('vendorTitle', 'LIKE', "%{$filters['vendor']}%");
            }

            // Price filters
            if (isset($filters['min_price'])) {
                $query->where('price', '>=', $filters['min_price']);
            }

            if (isset($filters['max_price'])) {
                $query->where('price', '<=', $filters['max_price']);
            }

            // Boolean flags
            foreach (['veg','isAvailable','isBestSeller','isFeature'] as $flag) {
                if (isset($filters[$flag])) {
                    $query->where($flag, $filters[$flag]);
                }
            }

            // --------------------------------------------------
            // ORDERING (SAFE)
            // --------------------------------------------------
            if (!empty($filters['search'])) {
                $search = $filters['search'];

                $query->orderByRaw("
                CASE
                    WHEN name = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    WHEN name LIKE ? THEN 3
                    ELSE 4
                END
            ", [
                    $search,
                    "{$search}%",
                    "%{$search}%"
                ]);
            }

            // Secondary ordering
            $query->orderByDesc('isBestSeller')
                ->orderByDesc('isFeature')
                ->orderBy('name');

            // --------------------------------------------------
            // PAGINATION
            // --------------------------------------------------
            $total = $query->count();

            $data = $query->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Mart items retrieved successfully',
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'has_more' => ($offset + $limit) < $total
                ],
                'filters_applied' => $filters,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Mart search SQL error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'  => $e->getMessage(),
                'line'   => $e->getLine(),
                'file'   => $e->getFile()
            ], 500);
        }
    }


    /****************************************
     * FEATURED ITEMS – SQL VERSION
     ****************************************/
    public function getFeaturedMartItems(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'nullable|string|in:best_seller,trending,featured,new,spotlight',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $type = $request->get('type', 'featured');
            $limit = $request->get('limit', 20);

            $query = DB::table('mart_items')->where('isAvailable', 1);

            $types = [
                'best_seller' => 'isBestSeller',
                'trending' => 'isTrending',
                'featured' => 'isFeature',
                'new' => 'isNew',
                'spotlight' => 'isSpotlight'
            ];

            if (isset($types[$type])) {
                $query->where($types[$type], 1);
            }

            $data = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'message' => ucfirst($type).' items retrieved successfully',
                'data' => $data,
                'type' => $type,
                'count' => count($data)
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Featured SQL error: '.$e->getMessage());

            return response()->json([
                'success' => true,
                'message' => 'Fallback featured items',
                'data' => [],
                'fallback' => true
            ], 200);
        }
    }


    /****************************************
     * FALLBACK HELPERS
     ****************************************/
    private function getFallbackResponse($searchTerm, $limit, $offset)
    {
        $fallbackData = [
            ['id'=>'fallback_1','title'=>'Groceries'],
            ['id'=>'fallback_2','title'=>'Medicine'],
            ['id'=>'fallback_3','title'=>'Pet Care']
        ];

        return [
            'data' => array_slice($fallbackData, $offset, $limit),
            'pagination' => [
                'current_page' => 1,
                'total' => count($fallbackData),
                'has_more' => false
            ]
        ];
    }

    private function getFallbackMartItemsResponse(Request $request): array
    {
        return [[
            'id' => 'fallback_item_1',
            'name' => 'Fresh Orange Juice',
            'price' => 120,
            'disPrice' => 110
        ]];
    }


    /****************************************
     * HEALTH CHECK
     ****************************************/
    public function healthCheck(Request $request): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            return response()->json(['status'=>'healthy'],200);
        } catch (\Exception $e) {
            return response()->json(['status'=>'unhealthy'],200);
        }
    }
}
